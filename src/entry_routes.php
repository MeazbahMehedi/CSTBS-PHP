<?php
declare(strict_types=1);

function visible_ticket(int $id): array
{
    $user = require_user();
    $ticket = ticket_row($id);
    if ($user['role'] !== 'admin' && (int) $ticket['owner_id'] !== (int) $user['id'] && (int) $ticket['attendee_user_id'] !== (int) $user['id']) {
        throw new HttpError(404, 'Ticket not found.');
    }
    return $ticket;
}
function qr_svg(string $token): string
{
    $options = new \chillerlan\QRCode\QROptions(['outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG, 'outputBase64' => false]);
    return (new \chillerlan\QRCode\QRCode($options))->render($token);
}
function pdf_download(string $html, string $filename): never
{
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isPhpEnabled', false);
    $pdf = new \Dompdf\Dompdf($options);
    $pdf->loadHtml('<meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:11px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:6px}</style>' . $html);
    $pdf->setPaper('A4');
    $pdf->render();
    $pdf->stream($filename, ['Attachment' => true]);
    exit;
}
route('GET', '/tickets/(\d+)', function ($id) {
    $ticket = visible_ticket((int) $id);
    render('ticket', ['title' => 'Admission pass', 'ticket' => $ticket, 'token' => ticket_token($ticket)]);
});
route('GET', '/tickets/(\d+)/qr.svg', function ($id) {
    $ticket = visible_ticket((int) $id);
    if ($ticket['status'] !== 'active' || $ticket['booking_status'] !== 'reserved') { throw new HttpError(404, 'Active pass not found.'); }
    header('Content-Type: image/svg+xml');
    echo qr_svg(ticket_token($ticket));
});
route('GET', '/tickets/(\d+)/pdf', function ($id) {
    $ticket = visible_ticket((int) $id);
    $html = '<h1>CSTBS admission pass</h1><h2>' . e($ticket['event_title']) . '</h2><p>' . e($ticket['starts_at'] . ' · ' . $ticket['venue']) . '</p><p>' . e($ticket['attendee_name'] . ' · ' . $ticket['attendee_student_id']) . '</p><p>' . e($ticket['section'] . ' · ' . $ticket['row_label'] . $ticket['number'] . ' · ' . $ticket['gate_name']) . '</p><p>Status ' . e($ticket['status']) . '</p>';
    if ($ticket['status'] === 'active' && $ticket['booking_status'] === 'reserved') {
        $html .= '<img width="220" height="220" src="data:image/svg+xml;base64,' . base64_encode(qr_svg(ticket_token($ticket))) . '">';
    }
    pdf_download($html, 'CSTBS-ticket-' . (int) $id . '.pdf');
});
route('GET', '/entry/check-ins', function () {
    require_user(['staff', 'admin']);
    $gateId = (int) ($_GET['gate_id'] ?? 0);
    $where = " WHERE t.status='checked_in'";
    $params = [];
    if ($gateId) { $where .= ' AND t.checked_in_gate_id=?'; $params[] = $gateId; }
    render('check-ins', ['title' => 'Gate attendance',
        'tickets' => all(TICKET_SELECT . $where . ' ORDER BY t.checked_in_at DESC LIMIT 1000', $params),
        'gates' => all('SELECT * FROM gates ORDER BY event_id,name')]);
});
route('GET', '/entry/scan', function () {
    require_user(['staff', 'admin']);
    $code = $_GET['code'] ?? '';
    if (!is_string($code)) { throw new HttpError(400, 'Invalid ticket code.'); }
    if ($code !== '') {
        $code = trim($code);
        if (!resolve_ticket($code)) { throw new HttpError(400, 'Unknown or altered ticket code.'); }
        redirect('/entry/verify/' . $code . '?gate_id=' . (int) ($_GET['gate_id'] ?? 0));
    }
    render('scan', ['title' => 'QR scanner', 'gates' => all('SELECT g.*,e.title AS event_title,e.checkin_enabled FROM gates g JOIN events e ON e.id=g.event_id ORDER BY e.starts_at,g.name'),
        'selected_gate' => (int) ($_GET['gate_id'] ?? 0)]);
});
route('GET', '/entry/verify/([A-Za-z0-9_.-]+)', function ($token) {
    require_user(['staff', 'admin']);
    $ticket = resolve_ticket($token);
    $gate = one('SELECT * FROM gates WHERE id=?', [(int) ($_GET['gate_id'] ?? 0)]);
    render('verify', ['title' => 'Verify admission', 'ticket' => $ticket, 'gate' => $gate, 'token' => $token,
        'verdict' => admission_verdict($ticket, $gate), 'gates' => $ticket ? all('SELECT * FROM gates WHERE event_id=? ORDER BY name', [$ticket['event_id']]) : []]);
});
route('POST', '/entry/check-in/([A-Za-z0-9_.-]+)', function ($token) {
    $staff = require_user(['staff', 'admin']);
    rate_limit('check-in', 120, 60);
    $ticket = resolve_ticket($token);
    if (!$ticket) { throw new HttpError(404, 'Unknown ticket.'); }
    $gateId = (int) ($_POST['gate_id'] ?? 0);
    check_in((int) $ticket['id'], $gateId, $staff);
    redirect('/entry/verify/' . $token . '?gate_id=' . $gateId);
});
route('POST', '/entry/scan-photo', function () {
    require_user(['staff', 'admin']);
    rate_limit('scan-photo', 20, 60);
    $image = $_FILES['qr_photo'] ?? null;
    if (!$image || $image['error'] !== UPLOAD_ERR_OK || $image['size'] > 3 * 1024 * 1024 || !is_uploaded_file($image['tmp_name'])) {
        throw new HttpError(400, 'Upload a QR photograph smaller than 3 MB.');
    }
    $dimensions = @getimagesize($image['tmp_name']);
    if (!$dimensions || $dimensions[0] * $dimensions[1] > 12000000 || !in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new HttpError(400, 'Use a JPEG, PNG, or WebP image of at most 12 megapixels.');
    }
    try { $result = (new \chillerlan\QRCode\QRCode())->readFromFile($image['tmp_name']); $token = (string) $result; }
    catch (Throwable) { throw new HttpError(400, 'Could not read this QR photograph. Try a sharper image.'); }
    if (!resolve_ticket($token)) { throw new HttpError(400, 'The image does not contain an official CSTBS ticket.'); }
    redirect('/entry/verify/' . $token . '?gate_id=' . (int) ($_POST['gate_id'] ?? 0));
});
function check_in(int $ticketId, int $gateId, array $staff): void
{
    if (!in_array($staff['role'], ['staff', 'admin'], true)) { throw new HttpError(403, 'Staff access required.'); }
    $ticket = ticket_row($ticketId);
    transaction(function () use ($ticket, $ticketId, $gateId, $staff) {
        event_row((int) $ticket['event_id'], true);
        $fresh = ticket_row($ticketId);
        $gate = one('SELECT * FROM gates WHERE id=?', [$gateId]);
        $verdict = admission_verdict($fresh, $gate);
        if ($verdict['status'] !== 'valid') { throw new HttpError(409, $verdict['title']); }
        query("UPDATE tickets SET status='checked_in',checked_in_at=UTC_TIMESTAMP(),checked_in_by_id=?,checked_in_gate_id=? WHERE id=? AND status='active'", [$staff['id'], $gateId, $ticketId]);
    });
}
