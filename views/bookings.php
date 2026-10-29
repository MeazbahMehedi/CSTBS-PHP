<section class="page-heading container">
    <span class="section-kicker">YOUR RESERVATIONS</span>
    <h1>My <em>bookings.</em></h1>
    <p>Review your seats, tickets reserved for classmates, and cancellation history.</p>
</section>
<section class="container php-content">
    <div class="admin-panel">
        <div class="php-table-wrap">
            <table class="php-table">
                <thead>
                    <tr>
                        <th>Event and seat</th>
                        <th>Attendee</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><strong><?= e($booking['event_title'] ?? $booking['title'] ?? '') ?></strong><small><?= e($booking['seat_label'] ?? (($booking['section'] ?? '') . ' · ' . ($booking['row_label'] ?? '') . ($booking['number'] ?? ''))) ?></small></td>
                            <td><?= e($booking['attendee_name'] ?? 'Awaiting assignment') ?><small><?= e($booking['attendee_student_id'] ?? '') ?></small></td>
                            <td><span class="history-state <?= $booking['status'] === 'reserved' ? 'is-active' : 'is-cancelled' ?>"><?= e(ucfirst($booking['status'])) ?></span></td>
                            <td>
                            <div class="php-inline">
                                <?php if (!empty($booking['ticket_id'])): ?>
                                    <a class="button button-secondary button-small" href="<?= e(url('/tickets/' . $booking['ticket_id'])) ?>">View QR ticket</a>
                                <?php endif ?>
                                <?php if ($booking['status'] === 'reserved' && ($booking['ticket_status'] ?? '') !== 'checked_in'): ?>
                                    <form method="post" action="<?= e(url('/bookings/' . $booking['id'] . '/cancel')) ?>" data-confirm="Cancel this seat reservation?">
                                        <?= csrf_field() ?><button class="button button-danger button-small" type="submit">Cancel</button>
                                    </form>
                                <?php endif ?>
                            </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$bookings): ?>
                        <tr>
                            <td colspan="4">Your reservations will appear here. <a href="<?= e(url('/events')) ?>">Explore events</a>.</td>
                        </tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
