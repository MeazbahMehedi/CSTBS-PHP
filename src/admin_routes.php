<?php
declare(strict_types=1);

function admin_events(bool $deleted = false): array
{
    return all("SELECT e.*,
        (SELECT COUNT(*) FROM bookings b JOIN seats s ON s.id=b.seat_id WHERE s.event_id=e.id AND b.status='reserved') AS reserved,
        (SELECT COUNT(*) FROM bookings b JOIN seats s ON s.id=b.seat_id LEFT JOIN tickets t ON t.booking_id=b.id WHERE s.event_id=e.id AND b.status='reserved' AND (t.id IS NULL OR t.status!='checked_in')) AS cancel_count,
        (SELECT COUNT(*) FROM seats s WHERE s.event_id=e.id AND s.is_blocked=0 AND NOT EXISTS(SELECT 1 FROM bookings b WHERE b.seat_id=s.id AND b.status='reserved')) AS available,
        (SELECT COUNT(*) FROM tickets t WHERE t.event_id=e.id AND t.status='checked_in') AS inside,
        (SELECT COUNT(*) FROM gates g WHERE g.event_id=e.id AND g.is_open=1) AS gates_open,
        (SELECT COUNT(*) FROM gates g WHERE g.event_id=e.id) AS gates_total FROM events e WHERE e.deleted_at " . ($deleted ? 'IS NOT NULL' : 'IS NULL') . ' ORDER BY e.starts_at');
}
function add_section(int $eventId, int $gateId, string $name, int $rows, int $columns): void
{
    if ($rows < 1 || $rows > 20 || $columns < 1 || $columns > 30) { throw new HttpError(400, 'Use 1–20 rows and 1–30 seats per row.'); }
    if (!one('SELECT id FROM gates WHERE id=? AND event_id=?', [$gateId, $eventId])) { throw new HttpError(400, 'Gate must belong to this event.'); }
    if (scalar('SELECT id FROM seats WHERE event_id=? AND section=? LIMIT 1', [$eventId, $name])) { throw new HttpError(409, 'Section already exists.'); }
    for ($row = 0; $row < $rows; $row++) {
        for ($number = 1; $number <= $columns; $number++) {
            query('INSERT INTO seats(event_id,gate_id,section,row_label,number) VALUES(?,?,?,?,?)', [$eventId, $gateId, $name, chr(65 + $row), $number]);
        }
    }
}
function save_event(?int $id): int
{
    $values = [text_input('title', 3, 150), text_input('category', 2, 40), text_input('description', 10, 10000), text_input('venue', 2, 120)];
    $raw = text_input('starts_at', 16, 19);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw);
    if (!$date || $date->format('Y-m-d\TH:i') !== $raw) { throw new HttpError(400, 'Provide a valid event date and time.'); }
    $values[] = $date->format('Y-m-d H:i:s');
    return transaction(function () use ($id, $values) {
        if ($id) {
            event_row($id, true);
            query('UPDATE events SET title=?,category=?,description=?,venue=?,starts_at=?,price=0 WHERE id=?', [...$values, $id]);
            query('UPDATE events SET booking_enabled=? WHERE id=?', [(int) isset($_POST['booking_enabled']), $id]);
            return $id;
        }
        query('INSERT INTO events(title,category,description,venue,starts_at) VALUES(?,?,?,?,?)', $values);
        $eventId = (int) db()->lastInsertId();
        query("INSERT INTO gates(event_id,name) VALUES(?,'Gate 1')", [$eventId]);
        add_section($eventId, (int) db()->lastInsertId(), 'Main', 6, 10);
        return $eventId;
    });
}
if (!str_starts_with($GLOBALS['request_path'], '/admin')) { return; }
require_user(['admin']);
route('GET', '/admin', function () {
    render('admin', ['title' => 'Stadium operations', 'events' => admin_events(), 'metrics' => [
        'students' => scalar("SELECT COUNT(*) FROM users WHERE role='student'"), 'events' => scalar('SELECT COUNT(*) FROM events WHERE deleted_at IS NULL'),
        'active_tickets' => scalar("SELECT COUNT(*) FROM tickets t JOIN events e ON e.id=t.event_id WHERE e.deleted_at IS NULL AND t.status IN ('active','checked_in')"),
        'inside' => scalar("SELECT COUNT(*) FROM tickets WHERE status='checked_in'")]]);
});
route('GET', '/admin/events', function () {
    $deleted = ($_GET['status'] ?? '') === 'deleted';
    render('admin-events', ['title' => 'Manage events', 'events' => admin_events($deleted), 'showDeleted' => $deleted]);
});
route('GET', '/admin/events/new', function () { render('admin-event', ['title' => 'Create event', 'event' => null]); });
route('POST', '/admin/events/new', function () { $id = save_event(null); flash('Event created with 60 seats.', 'success'); redirect('/events/' . $id); });
route('GET', '/admin/events/(\d+)/edit', function ($id) { render('admin-event', ['title' => 'Edit event', 'event' => event_row((int) $id)]); });
route('POST', '/admin/events/(\d+)/edit', function ($id) { save_event((int) $id); flash('Event updated.', 'success'); redirect('/admin/events'); });
route('POST', '/admin/events/(\d+)/delete', function ($id) {
    transaction(function () use ($id) {
        $event = event_row((int) $id, true, true);
        if ($event['deleted_at']) { return; }
        $cancelled = all("SELECT b.id,u.email FROM bookings b JOIN seats s ON s.id=b.seat_id
            JOIN users u ON u.id=b.user_id LEFT JOIN tickets t ON t.booking_id=b.id
            WHERE s.event_id=? AND b.status='reserved' AND (t.id IS NULL OR t.status!='checked_in')", [$id]);
        query("UPDATE bookings b JOIN seats s ON s.id=b.seat_id LEFT JOIN tickets t ON t.booking_id=b.id
            SET b.status='cancelled' WHERE s.event_id=? AND b.status='reserved' AND (t.id IS NULL OR t.status!='checked_in')", [$id]);
        query("UPDATE tickets SET status='cancelled' WHERE event_id=? AND status IN ('active','unassigned')", [$id]);
        query('UPDATE gates SET is_open=0 WHERE event_id=?', [$id]);
        query('UPDATE events SET deleted_at=UTC_TIMESTAMP(),booking_enabled=0,checkin_enabled=0 WHERE id=?', [$id]);
        foreach ($cancelled as $booking) {
            queue_mail($booking['email'], 'CSTBS event removed', $event['title'] . ' has been removed. Reservation ' . $booking['id'] . ' is cancelled.');
        }
    });
    flash('Event deleted. Unused passes are cancelled and attendance history is retained.', 'success'); redirect('/admin/events');
});
route('GET', '/admin/events/(\d+)/seats', function ($id) {
    render('admin-seats', ['title' => 'Manage seats', 'event' => event_row((int) $id), 'gates' => all('SELECT * FROM gates WHERE event_id=? ORDER BY name', [$id]),
        'seats' => all("SELECT s.*,b.id AS booking_id FROM seats s LEFT JOIN bookings b ON b.seat_id=s.id AND b.status='reserved' WHERE s.event_id=? ORDER BY s.section,s.row_label,s.number", [$id])]);
});
route('POST', '/admin/events/(\d+)/sections/create', function ($id) {
    transaction(function () use ($id) {
        event_row((int) $id, true);
        add_section((int) $id, (int) ($_POST['gate_id'] ?? 0), text_input('section', 1, 30), (int) ($_POST['rows'] ?? 0), (int) ($_POST['seat_count'] ?? 0));
    });
    redirect('/admin/events/' . $id . '/seats');
});
route('POST', '/admin/events/(\d+)/sections/([^/]+)/rename', function ($id, $section) {
    transaction(function () use ($id, $section) {
        event_row((int) $id, true);
        $name = text_input('new_name', 1, 30);
        if ($name !== $section && scalar('SELECT id FROM seats WHERE event_id=? AND section=? LIMIT 1', [$id, $name])) { throw new HttpError(409, 'Section already exists.'); }
        query('UPDATE seats SET section=? WHERE event_id=? AND section=?', [$name, $id, $section]);
    });
    redirect('/admin/events/' . $id . '/seats');
});
route('POST', '/admin/events/(\d+)/sections/([^/]+)/delete', function ($id, $section) {
    transaction(function () use ($id, $section) {
        event_row((int) $id, true);
        if (scalar('SELECT b.id FROM bookings b JOIN seats s ON s.id=b.seat_id WHERE s.event_id=? AND s.section=? LIMIT 1', [$id, $section])) { throw new HttpError(409, 'Booking history prevents section deletion.'); }
        query('DELETE FROM seats WHERE event_id=? AND section=?', [$id, $section]);
    });
    redirect('/admin/events/' . $id . '/seats');
});
route('POST', '/admin/seats/(\d+)/toggle-block', function ($id) {
    $seat = one('SELECT * FROM seats WHERE id=?', [$id]);
    if (!$seat) { throw new HttpError(404, 'Seat not found.'); }
    transaction(function () use ($id, $seat) {
        event_row((int) $seat['event_id'], true);
        if (scalar("SELECT id FROM bookings WHERE seat_id=? AND status='reserved'", [$id])) { throw new HttpError(409, 'Cancel the active reservation before blocking.'); }
        query('UPDATE seats SET is_blocked=NOT is_blocked WHERE id=?', [$id]);
    });
    redirect('/admin/events/' . $seat['event_id'] . '/seats');
});
route('GET', '/admin/events/(\d+)/gates', function ($id) {
    render('gates', ['title' => 'Manage gates', 'event' => event_row((int) $id), 'gates' => all('SELECT g.*,(SELECT COUNT(*) FROM seats s WHERE s.gate_id=g.id) AS seat_count FROM gates g WHERE event_id=? ORDER BY name', [$id]), 'seats' => all('SELECT * FROM seats WHERE event_id=? ORDER BY section,row_label,number', [$id])]);
});
route('POST', '/admin/events/(\d+)/gates', function ($id) {
    event_row((int) $id);
    query('INSERT INTO gates(event_id,name) VALUES(?,?)', [$id, text_input('name', 1, 40)]);
    redirect('/admin/events/' . $id . '/gates');
});
route('POST', '/admin/events/(\d+)/gates/(\d+)/rename', function ($id, $gateId) {
    query('UPDATE gates SET name=? WHERE id=? AND event_id=?', [text_input('name', 1, 40), $gateId, $id]);
    redirect('/admin/events/' . $id . '/gates');
});
route('POST', '/admin/events/(\d+)/entry/(open|close)', function ($id, $action) {
    transaction(function () use ($id, $action) {
        event_row((int) $id, true);
        $open = (int) ($action === 'open');
        query('UPDATE events SET checkin_enabled=? WHERE id=?', [$open, $id]);
        query('UPDATE gates SET is_open=? WHERE event_id=?', [$open, $id]);
    });
    redirect('/admin');
});
route('POST', '/admin/events/(\d+)/toggle/(booking|checkin)', function ($id, $field) {
    transaction(function () use ($id, $field) {
        $event = event_row((int) $id, true);
        if ($field === 'booking') { query('UPDATE events SET booking_enabled=NOT booking_enabled WHERE id=?', [$id]); }
        else {
            $open = (int) !$event['checkin_enabled'];
            query('UPDATE events SET checkin_enabled=? WHERE id=?', [$open, $id]);
            query('UPDATE gates SET is_open=? WHERE event_id=?', [$open, $id]);
        }
    });
    redirect('/admin/events');
});
route('POST', '/admin/gates/(\d+)/toggle', function ($id) {
    transaction(function () use ($id) {
        $gate = one('SELECT * FROM gates WHERE id=?', [$id]);
        if (!$gate) { throw new HttpError(404, 'Gate not found.'); }
        event_row((int) $gate['event_id'], true);
        query('UPDATE gates SET is_open=NOT is_open WHERE id=?', [$id]);
        $open = (int) (scalar('SELECT COUNT(*) FROM gates WHERE event_id=? AND is_open=1', [$gate['event_id']]) > 0);
        query('UPDATE events SET checkin_enabled=? WHERE id=?', [$open, $gate['event_id']]);
    });
    redirect('/admin');
});
route('POST', '/admin/events/(\d+)/sections/([^/]+)/gate', function ($id, $section) {
    transaction(function () use ($id, $section) {
        event_row((int) $id, true);
        $gateId = (int) ($_POST['gate_id'] ?? 0);
        if (!one('SELECT id FROM gates WHERE id=? AND event_id=?', [$gateId, $id])) { throw new HttpError(400, 'Gate must belong to this event.'); }
        if (scalar("SELECT t.id FROM tickets t JOIN bookings b ON b.id=t.booking_id JOIN seats s ON s.id=b.seat_id WHERE s.event_id=? AND s.section=? AND t.status='checked_in' LIMIT 1", [$id, $section])) { throw new HttpError(409, 'A section containing admitted attendees cannot move gates.'); }
        query('UPDATE seats SET gate_id=? WHERE event_id=? AND section=?', [$gateId, $id, $section]);
    });
    redirect('/admin/events/' . $id . '/gates');
});
route('GET', '/admin/users', function () { render('users', ['title' => 'Campus accounts', 'users' => all('SELECT * FROM users ORDER BY id')]); });
route('POST', '/admin/users/(\d+)/role', function ($id) {
    $actor = require_user(['admin']);
    $role = text_input('role', 5, 7);
    if (!in_array($role, ['student', 'staff', 'admin'], true)) { throw new HttpError(400, 'Invalid role.'); }
    transaction(function () use ($id, $role, $actor) {
        $admins = all("SELECT id FROM users WHERE role='admin' ORDER BY id FOR UPDATE");
        $user = one('SELECT * FROM users WHERE id=? FOR UPDATE', [$id]);
        if (!$user) { throw new HttpError(404, 'Account not found.'); }
        if ($user['role'] === $role) { return; }
        if ((int) $id === (int) $actor['id'] || ($user['role'] === 'admin' && count($admins) <= 1)) { throw new HttpError(409, 'Keep your own role and at least one administrator.'); }
        if ($role === 'student' && !$user['student_id']) { throw new HttpError(400, 'A student needs a Student ID.'); }
        query('UPDATE users SET role=?,auth_version=auth_version+1 WHERE id=?', [$role, $id]);
        if ($role === 'admin') { query('UPDATE users SET password_ready=0,password_hash=? WHERE id=?', [hash_password(bin2hex(random_bytes(32))), $id]); }
    });
    redirect('/admin/users');
});
route('POST', '/admin/users/(\d+)/key', function ($id) {
    $key = issue_admin_key((int) $id);
    render('admin-key', ['title' => 'Activation key', 'key' => $key, 'user' => one('SELECT * FROM users WHERE id=?', [$id])]);
});
route('GET', '/admin/bookings', function () { render('admin-bookings', ['title' => 'Bookings and tickets', 'bookings' => all(BOOKING_SELECT . ' ORDER BY b.id DESC')]); });
route('POST', '/admin/bookings/(\d+)/cancel', function ($id) { cancel_booking((int) $id, require_user(['admin'])); redirect('/admin/bookings'); });
route('POST', '/admin/bookings/(\d+)/assign', function ($id) {
    transaction(function () use ($id) {
        $ticket = one('SELECT * FROM tickets WHERE booking_id=?', [$id]);
        if (!$ticket) { throw new HttpError(404, 'Ticket not found.'); }
        event_row((int) $ticket['event_id'], true);
        $ticket = one('SELECT * FROM tickets WHERE booking_id=? FOR UPDATE', [$id]);
        if ($ticket['status'] !== 'unassigned' || scalar('SELECT status FROM bookings WHERE id=?', [$id]) !== 'reserved') { throw new HttpError(409, 'Only unassigned reserved tickets can be assigned.'); }
        $user = one("SELECT * FROM users WHERE student_id=? AND role='student'", [student_id(text_input('attendee_student_id', 6, 20))]);
        if (!$user || mb_strtolower($user['full_name']) !== mb_strtolower(text_input('attendee_name', 1, 120))) { throw new HttpError(400, 'Attendee details must match a registered student.'); }
        query("UPDATE tickets SET attendee_user_id=?,attendee_name=?,attendee_student_id=?,status='active' WHERE id=?", [$user['id'], $user['full_name'], $user['student_id'], $ticket['id']]);
    });
    redirect('/admin/bookings');
});
route('GET', '/admin/check-ins', function () { render('check-ins', ['title' => 'Gate attendance', 'tickets' => all(TICKET_SELECT . " WHERE t.status='checked_in' ORDER BY t.checked_in_at DESC"), 'gates' => all('SELECT * FROM gates ORDER BY event_id,name')]); });
require __DIR__ . '/reports.php';
