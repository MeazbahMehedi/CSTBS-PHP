<section class="page-heading container">
    <span class="section-kicker">ADMINISTRATION</span>
    <h1>Bookings <em>&amp; tickets.</em></h1>
    <p>Preserve booking history, resolve attendee assignments, and manage cancellations.</p>
</section>
<section class="container admin-section php-content">
    <?php require __DIR__ . '/partials/admin-nav.php'; ?>
    <div class="admin-panel">
        <div class="php-table-wrap">
            <table class="php-table">
                <thead>
                    <tr>
                        <th>Event and seat</th>
                        <th>Booked by</th>
                        <th>Attendee</th>
                        <th>Status and actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><strong><?= e($booking['event_title'] ?? $booking['title'] ?? '') ?></strong><small><?= e($booking['seat_label'] ?? (($booking['section'] ?? '') . ' · ' . ($booking['row_label'] ?? '') . ($booking['number'] ?? ''))) ?></small></td>
                            <td><?= e($booking['owner_name'] ?? $booking['full_name'] ?? '') ?><small><?= e($booking['owner_email'] ?? $booking['email'] ?? '') ?></small></td>
                            <td><?= e($booking['attendee_name'] ?? 'Awaiting assignment') ?><small><?= e($booking['attendee_student_id'] ?? '') ?></small>
                            <?php if (($booking['ticket_status'] ?? '') === 'unassigned' && $booking['status'] === 'reserved'): ?>
                                <details>
                                    <summary>Assign registered attendee</summary>
                                    <form class="admin-stack-form" method="post" action="<?= e(url('/admin/bookings/' . $booking['id'] . '/assign')) ?>">
                                        <?= csrf_field() ?>
                                        <label for="attendee-name-<?= e($booking['id']) ?>">Registered full name</label>
                                        <input class="form-control" id="attendee-name-<?= e($booking['id']) ?>" name="attendee_name" maxlength="120" required>
                                        <label for="attendee-id-<?= e($booking['id']) ?>">Student ID</label>
                                        <input class="form-control" id="attendee-id-<?= e($booking['id']) ?>" name="attendee_student_id" pattern="[A-Za-z0-9]{6,20}" required><button class="button button-secondary button-small" type="submit">Assign attendee</button>
                                    </form>
                                </details>
                            <?php endif ?>
                            </td>
                            <td><span class="history-state <?= $booking['status'] === 'reserved' ? 'is-active' : 'is-cancelled' ?>"><?= e(str_replace('_', ' ', $booking['ticket_status'] ?? $booking['status'])) ?></span>
                            <div class="php-inline">
                                <?php if (!empty($booking['ticket_id'])): ?>
                                    <a class="button button-secondary button-small" href="<?= e(url('/tickets/' . $booking['ticket_id'])) ?>">Ticket</a>
                                <?php endif ?>
                                <?php if ($booking['status'] === 'reserved' && ($booking['ticket_status'] ?? '') !== 'checked_in'): ?>
                                    <form method="post" action="<?= e(url('/admin/bookings/' . $booking['id'] . '/cancel')) ?>" data-confirm="Cancel this reservation and invalidate its admission pass?">
                                        <?= csrf_field() ?><button class="button button-danger button-small" type="submit">Cancel</button>
                                    </form>
                                <?php endif ?>
                            </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$bookings): ?>
                        <tr>
                            <td colspan="4">Bookings will appear here when students reserve seats.</td>
                        </tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
