<section class="page-heading container">
    <span class="section-kicker">YOUR UNIVERSITY ADMISSION</span>
    <h1>Your <em>stadium pass.</em></h1>
    <p>Bring this pass and your university ID to the assigned gate.</p>
</section>
<section class="container ticket-display-wrap php-content">
    <article class="digital-ticket">
        <div class="ticket-event-head">
            <strong>CSTBS · Campus Stadium</strong><span class="history-state <?= in_array($ticket['status'], ['active', 'checked_in'], true) ? 'is-active' : 'is-cancelled' ?>"><?= e(ucfirst(str_replace('_', ' ', $ticket['status']))) ?></span>
        </div>
        <h2><?= e($ticket['event_title'] ?? $ticket['title'] ?? '') ?></h2>
        <p><?= e(date('l, d M Y · g:i A', strtotime($ticket['starts_at']))) ?> · <?= e($ticket['venue']) ?></p>
        <div class="digital-ticket-grid">
            <div>
                <small>ATTENDEE</small><strong><?= e($ticket['attendee_name']) ?></strong>
            </div>
            <div>
                <small>STUDENT ID</small><strong><?= e($ticket['attendee_student_id'] ?? 'Awaiting assignment') ?></strong>
            </div>
            <div>
                <small>SEAT</small><strong><?= e($ticket['section'] . ' · ' . $ticket['row_label'] . $ticket['number']) ?></strong>
            </div>
            <div>
                <small>ENTRANCE</small><strong><?= e($ticket['gate_name'] ?? 'Gate assignment pending') ?></strong>
            </div>
        </div>
        <?php if ($ticket['status'] === 'active' && ($ticket['booking_status'] ?? 'reserved') === 'reserved'): ?>
            <div class="php-qr">
                <img src="<?= e(url('/tickets/' . $ticket['id'] . '/qr.svg')) ?>" width="260" height="260" alt="Signed admission QR for this student ticket">
                <p>Present this QR at your assigned entrance.</p>
            </div>
            <details class="php-code">
                <summary>Show signed ticket code</summary>
                <p class="muted">Keep this admission credential private.</p>
                <textarea class="form-control" rows="4" readonly aria-label="Signed ticket code"><?= e($token) ?></textarea>
            </details>
        <?php else: ?>
            <p class="flash flash-info">This pass is <?= e(str_replace('_', ' ', $ticket['status'])) ?>.</p>
        <?php endif ?>
        <div class="ticket-actions">
            <button class="button button-secondary" type="button" data-print-ticket>Print ticket</button><a class="button button-secondary" href="<?= e(url('/tickets/' . $ticket['id'] . '/pdf')) ?>">Download PDF</a><a class="button button-secondary" href="<?= e(url('/dashboard')) ?>">My admission passes</a>
        </div>
    </article>
</section>
