<?php
declare(strict_types=1);

function report_rows(): array
{
    $where = [];
    $params = [];
    $eventId = filter_var($_GET['event_id'] ?? null, FILTER_VALIDATE_INT);
    if ($eventId) { $where[] = 'e.id=?'; $params[] = $eventId; }
    $status = $_GET['status'] ?? '';
    if ($status !== '') {
        if (!in_array($status, ['reserved', 'cancelled', 'active', 'checked_in', 'unassigned'], true)) { throw new HttpError(400, 'Invalid status filter.'); }
        $where[] = in_array($status, ['reserved', 'cancelled'], true) ? 'b.status=?' : 't.status=?';
        $params[] = $status;
    }
    foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
        $raw = $_GET[$key] ?? '';
        if ($raw === '') { continue; }
        if (!is_string($raw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) || date('Y-m-d', strtotime($raw)) !== $raw) { throw new HttpError(400, 'Invalid date filter.'); }
        $where[] = 'DATE(e.starts_at)' . $operator . '?';
        $params[] = $raw;
    }
    $rows = all(BOOKING_SELECT . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY b.id DESC LIMIT 10001', $params);
    if (count($rows) > 10000) { throw new HttpError(413, 'Narrow the filters to export at most 10,000 records.'); }
    return $rows;
}
function csv_cell(mixed $value): string
{
    $value = (string) $value;
    return preg_match('/^[\s]*[=+\-@\t\r]/u', $value) ? "'" . $value : $value;
}
route('GET', '/admin/reports', function () {
    render('reports', ['title' => 'Reports and exports', 'events' => all('SELECT * FROM events ORDER BY starts_at'), 'rows' => report_rows(), 'filters' => $_GET]);
});
route('GET', '/admin/reports/export', function () {
    $rows = report_rows();
    $headers = ['Booking', 'Event', 'Date', 'Seat', 'Booker', 'Attendee', 'Student ID', 'Booking status', 'Ticket status'];
    $data = array_map(fn($row) => [$row['id'], $row['event_title'], $row['starts_at'], $row['seat_label'], $row['owner_name'], $row['attendee_name'], $row['attendee_student_id'], $row['status'], $row['ticket_status']], $rows);
    if (($_GET['format'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="CSTBS-bookings.csv"');
        $output = fopen('php://output', 'wb');
        fputcsv($output, $headers, ',', '"', '');
        foreach ($data as $row) { fputcsv($output, array_map('csv_cell', $row), ',', '"', ''); }
        fclose($output);
        return;
    }
    if (($_GET['format'] ?? '') !== 'pdf') { throw new HttpError(400, 'Choose CSV or PDF.'); }
    $html = '<h1>CSTBS booking report</h1><table><tr>';
    foreach ($headers as $label) { $html .= '<th>' . e($label) . '</th>'; }
    $html .= '</tr>';
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) { $html .= '<td>' . e($cell) . '</td>'; }
        $html .= '</tr>';
    }
    pdf_download($html . '</table>', 'CSTBS-bookings.pdf');
});
