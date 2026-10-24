<?php
declare(strict_types=1);

route('GET', '/', function () {
    render('home', ['title' => 'Home', 'events' => all('SELECT * FROM events WHERE starts_at>? ORDER BY starts_at LIMIT 3', [date('Y-m-d H:i:s')])]);
});
route('GET', '/events', function () { render('events', ['title' => 'Events', 'events' => all('SELECT * FROM events ORDER BY starts_at')]); });
route('GET', '/events/(\d+)', function ($id) { render('event', ['title' => 'Event details', 'event' => event_row((int) $id)]); });
route('GET', '/events/(\d+)/seats', function ($id) {
    $user = require_user(['student']);
    if (scalar("SELECT id FROM tickets WHERE event_id=? AND attendee_user_id=? AND status IN ('active','checked_in')", [$id, $user['id']])) {
        flash('Your admission pass is already on your dashboard.'); redirect('/dashboard');
    }
    $event = event_row((int) $id);
    render('seats', ['title' => 'Choose seats', 'event' => $event,
        'booking_enabled' => $event['booking_enabled'] && $event['starts_at'] > date('Y-m-d H:i:s'),
        'seats' => all("SELECT s.*,g.name AS gate_name,b.id AS booking_id FROM seats s JOIN gates g ON g.id=s.gate_id
            LEFT JOIN bookings b ON b.seat_id=s.id AND b.status='reserved' WHERE s.event_id=? ORDER BY s.section,s.row_label,s.number", [$id])]);
});
route('GET', '/api/events/(\d+)/seats', function ($id) {
    rate_limit('availability', 120, 60);
    $event = event_row((int) $id);
    $occupied = array_map('intval', array_column(all("SELECT b.seat_id FROM bookings b JOIN seats s ON s.id=b.seat_id WHERE s.event_id=? AND b.status='reserved'", [$id]), 'seat_id'));
    $blocked = array_map('intval', array_column(all('SELECT id FROM seats WHERE event_id=? AND is_blocked=1', [$id]), 'id'));
    $total = (int) scalar('SELECT COUNT(*) FROM seats WHERE event_id=?', [$id]);
    json_response(['event_id' => (int) $id, 'total' => $total, 'available' => $total - count(array_unique([...$occupied, ...$blocked])),
        'reserved_seat_ids' => $occupied, 'blocked_seat_ids' => $blocked, 'booking_open' => (bool) $event['booking_enabled'] && $event['starts_at'] > date('Y-m-d H:i:s')]);
});
route('POST', '/events/(\d+)/booking/review', function ($id) {
    $user = require_user(['student']);
    if (!$user['student_id']) { throw new HttpError(400, 'Add a Student ID to your account first.'); }
    unset($_SESSION['pending_booking']);
    $event = event_row((int) $id);
    require_open_booking($event);
    if (scalar("SELECT id FROM tickets WHERE event_id=? AND attendee_user_id=? AND status IN ('active','checked_in')", [$id, $user['id']])) {
        flash('Only one live ticket per student per event is allowed.'); redirect('/dashboard');
    }
    $ids = parse_seat_ids($_POST['seat_ids'] ?? null);
    $seats = selected_seats((int) $id, $ids);
    $_SESSION['pending_booking'] = ['event_id' => (int) $id, 'seat_ids' => $ids, 'expires' => time() + 900];
    render('review', ['title' => 'Review reservation', 'event' => $event, 'seats' => $seats]);
});
route('POST', '/events/(\d+)/booking/confirm', function ($id) {
    $user = require_user(['student']);
    rate_limit('confirm', 12, 60);
    $pending = $_SESSION['pending_booking'] ?? null;
    unset($_SESSION['pending_booking']);
    if (!$pending || $pending['event_id'] !== (int) $id || $pending['expires'] < time()) { flash('Select seats and review them before confirming.'); redirect('/events/' . $id . '/seats'); }
    $attendees = $_POST['attendee_student_id'] ?? [];
    $names = $_POST['attendee_name'] ?? [];
    if (!is_array($attendees) || !is_array($names)) { throw new HttpError(400, 'Invalid attendee list.'); }
    confirm_booking((int) $id, $pending['seat_ids'], $user, array_values($attendees), array_values($names));
    flash('Booking confirmed. Your admission passes are ready.', 'success');
    redirect('/bookings');
});

const BOOKING_SELECT = "SELECT b.*,e.id AS event_id,e.title AS event_title,e.starts_at,s.section,s.row_label,s.number,
    CONCAT(s.section,' · ',s.row_label,s.number) AS seat_label,t.id AS ticket_id,t.status AS ticket_status,
    t.attendee_name,t.attendee_student_id,u.full_name AS owner_name FROM bookings b JOIN seats s ON s.id=b.seat_id
    JOIN events e ON e.id=s.event_id JOIN users u ON u.id=b.user_id LEFT JOIN tickets t ON t.booking_id=b.id ";
route('GET', '/bookings', function () {
    $user = require_user();
    render('bookings', ['title' => 'My bookings', 'bookings' => all(BOOKING_SELECT . ' WHERE b.user_id=? ORDER BY b.id DESC', [$user['id']])]);
});
route('POST', '/bookings/(\d+)/cancel', function ($id) {
    cancel_booking((int) $id, require_user(['student'])); flash('Reservation cancelled.', 'success'); redirect('/bookings');
});
