<?php
declare(strict_types=1);

const TICKET_SELECT = 'SELECT t.*,e.title AS event_title,e.starts_at,e.venue,e.checkin_enabled,
    s.section,s.row_label,s.number,s.gate_id,g.name AS gate_name,g.is_open,
    b.status AS booking_status,b.user_id AS owner_id,u.email AS attendee_email
    FROM tickets t JOIN bookings b ON b.id=t.booking_id JOIN seats s ON s.id=b.seat_id
    JOIN events e ON e.id=t.event_id JOIN gates g ON g.id=s.gate_id
    LEFT JOIN users u ON u.id=t.attendee_user_id ';

function ticket_row(int $id): array
{
    $ticket = one(TICKET_SELECT . ' WHERE t.id=?', [$id]);
    if (!$ticket) { throw new HttpError(404, 'Ticket not found.'); }
    return $ticket;
}
function ticket_token(array $ticket): string
{
    $payload = rtrim(strtr(base64_encode($ticket['id'] . ':' . $ticket['nonce']), '+/', '-_'), '=');
    return $payload . '.' . hash_hmac('sha256', $payload, (string) config('secret'));
}
function resolve_ticket(string $token): ?array
{
    if (!preg_match('/^([A-Za-z0-9_-]{10,200})\.([a-f0-9]{64})$/', $token, $parts)) { return null; }
    if (!hash_equals(hash_hmac('sha256', $parts[1], (string) config('secret')), $parts[2])) { return null; }
    $decoded = base64_decode(strtr($parts[1], '-_', '+/'), true);
    if ($decoded === false || !preg_match('/^(\d+):([a-f0-9]{48})$/', $decoded, $identity)) { return null; }
    return one(TICKET_SELECT . ' WHERE t.id=? AND t.nonce=?', [(int) $identity[1], $identity[2]]);
}
function admission_verdict(?array $ticket, ?array $gate): array
{
    $status = 'waiting';
    if (!$ticket) { return ['status' => 'invalid', 'title' => 'INVALID TICKET', 'message' => 'The QR is unknown or altered.']; }
    if ($ticket['status'] === 'checked_in') { return ['status' => 'invalid', 'title' => 'ALREADY CHECKED IN', 'message' => 'This pass has already been used.']; }
    if ($ticket['booking_status'] !== 'reserved' || $ticket['status'] === 'cancelled') {
        return ['status' => 'invalid', 'title' => 'CANCELLED TICKET', 'message' => 'This reservation was cancelled.'];
    }
    if ($ticket['status'] !== 'active' || !$ticket['attendee_user_id']) {
        return ['status' => 'invalid', 'title' => 'TICKET NOT ASSIGNED', 'message' => 'An administrator must verify the attendee.'];
    }
    if (!$ticket['checkin_enabled']) { $title = 'VALID TICKET — ENTRY NOT OPEN'; $message = 'An administrator must open stadium entry.'; }
    elseif (!$gate) { $title = 'VALID TICKET — SELECT GATE'; $message = 'Select your physical entrance.'; }
    elseif ((int) $gate['event_id'] !== (int) $ticket['event_id'] || (int) $gate['id'] !== (int) $ticket['gate_id']) {
        $title = 'VALID TICKET — WRONG GATE'; $message = 'Use ' . $ticket['gate_name'] . '.';
    }
    elseif (!$gate['is_open']) { $title = 'VALID TICKET — GATE CLOSED'; $message = 'An administrator must open this gate.'; }
    else { $status = 'valid'; $title = 'VALID TICKET — ADMIT STUDENT'; $message = 'Match the university ID, then confirm entry.'; }
    return compact('status', 'title', 'message');
}
function parse_seat_ids(mixed $raw): array
{
    if (!is_string($raw) || strlen($raw) > 120 || !preg_match('/^\d+(,\d+){0,5}$/', $raw)) {
        throw new HttpError(400, 'Select between one and six seats.');
    }
    $ids = array_map('intval', explode(',', $raw));
    if (count(array_unique($ids)) !== count($ids) || min($ids) < 1) { throw new HttpError(400, 'Invalid seat selection.'); }
    return $ids;
}
function selected_seats(int $eventId, array $ids): array
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $found = all('SELECT s.*,g.name AS gate_name FROM seats s JOIN gates g ON g.id=s.gate_id
        WHERE s.event_id=? AND s.id IN (' . $placeholders . ') ORDER BY s.id', [$eventId, ...$ids]);
    if (count($found) !== count($ids)) { throw new HttpError(400, 'Seats must belong to this event.'); }
    $indexed = array_column($found, null, 'id');
    foreach ($found as $seat) {
        if ($seat['is_blocked'] || scalar("SELECT id FROM bookings WHERE seat_id=? AND status='reserved'", [$seat['id']])) {
            throw new HttpError(409, 'A selected seat is no longer available. Choose again.');
        }
    }
    return array_map(fn($id) => $indexed[$id], $ids);
}
function require_open_booking(array $event): void
{
    if (!$event['booking_enabled'] || $event['starts_at'] <= date('Y-m-d H:i:s')) {
        throw new HttpError(409, 'Reservations are closed for this event.');
    }
}
function queue_mail(string $email, string $subject, string $body): void
{
    query('INSERT INTO mail_outbox(recipient,subject,body) VALUES(?,?,?)', [$email, $subject, $body]);
}
function confirm_booking(int $eventId, array $ids, array $booker, array $attendeeIds, array $names): array
{
    return transaction(function () use ($eventId, $ids, $booker, $attendeeIds, $names) {
        $event = event_row($eventId, true);
        require_open_booking($event);
        $seats = selected_seats($eventId, $ids);
        if (!$attendeeIds && count($ids) === 1) { $attendeeIds = [$booker['student_id']]; $names = [$booker['full_name']]; }
        if (count($attendeeIds) !== count($ids) || count($names) !== count($ids)) { throw new HttpError(400, 'Provide a registered attendee for every seat.'); }
        $seen = [];
        $ticketIds = [];
        foreach ($seats as $index => $seat) {
            if (!is_string($attendeeIds[$index]) || !is_string($names[$index])) { throw new HttpError(400, 'Invalid attendee details.'); }
            $sid = student_id($attendeeIds[$index]);
            $attendee = one("SELECT * FROM users WHERE student_id=? AND role='student'", [$sid]);
            $normalize = fn($name) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
            if (!$attendee || $normalize($attendee['full_name']) !== $normalize($names[$index])) { throw new HttpError(400, 'The attendee name and Student ID must match a registered student.'); }
            if ($index === 0 && (int) $attendee['id'] !== (int) $booker['id']) { throw new HttpError(400, 'The first seat must belong to you.'); }
            if (in_array($sid, $seen, true) || scalar("SELECT id FROM tickets WHERE event_id=? AND attendee_user_id=? AND status IN ('active','checked_in')", [$eventId, $attendee['id']])) {
                throw new HttpError(409, 'Only one live ticket per student per event is allowed.');
            }
            $seen[] = $sid;
            query('INSERT INTO bookings(user_id,seat_id) VALUES(?,?)', [$booker['id'], $seat['id']]);
            $bookingId = (int) db()->lastInsertId();
            query('INSERT INTO tickets(booking_id,event_id,attendee_user_id,attendee_name,attendee_student_id,nonce) VALUES(?,?,?,?,?,?)',
                [$bookingId, $eventId, $attendee['id'], $attendee['full_name'], $sid, bin2hex(random_bytes(24))]);
            $ticketIds[] = (int) db()->lastInsertId();
            queue_mail($attendee['email'], 'CSTBS reservation confirmed', 'Your reservation for ' . $event['title'] . ' is confirmed. Sign in to view your admission pass.');
        }
        return $ticketIds;
    });
}
function cancel_booking(int $id, array $actor): void
{
    transaction(function () use ($id, $actor) {
        $booking = one('SELECT b.*,s.event_id FROM bookings b JOIN seats s ON s.id=b.seat_id WHERE b.id=?', [$id]);
        if (!$booking || ($actor['role'] !== 'admin' && (int) $booking['user_id'] !== (int) $actor['id'])) { throw new HttpError(404, 'Booking not found.'); }
        event_row((int) $booking['event_id'], true);
        $ticket = one('SELECT * FROM tickets WHERE booking_id=? FOR UPDATE', [$id]);
        if ($ticket && $ticket['status'] === 'checked_in') { throw new HttpError(409, 'A checked-in ticket cannot be cancelled.'); }
        if (scalar("SELECT status FROM bookings WHERE id=? FOR UPDATE", [$id]) === 'cancelled') { return; }
        query("UPDATE bookings SET status='cancelled' WHERE id=?", [$id]);
        query("UPDATE tickets SET status='cancelled' WHERE booking_id=?", [$id]);
        $owner = one('SELECT email FROM users WHERE id=?', [$booking['user_id']]);
        queue_mail($owner['email'], 'CSTBS reservation cancelled', 'Reservation ' . $id . ' has been cancelled.');
    });
}
