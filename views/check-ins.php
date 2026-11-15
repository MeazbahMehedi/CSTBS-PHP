<section class="page-heading container">
    <span class="section-kicker">ADMINISTRATION · ATTENDANCE</span>
    <div class="admin-heading">
        <div>
            <h1>Gate <em>&amp; check-ins.</em></h1>
            <p>Monitor student admission and entrance activity.</p>
        </div>
        <a class="button button-primary" href="<?= e(url('/entry/scan')) ?>">Open QR scanner</a>
    </div>
</section>
<section class="container admin-section php-content">
    <?php if (current_user()['role'] === 'admin'): ?>
        <?php require __DIR__ . '/partials/admin-nav.php'; ?>
    <?php endif ?>
    <div class="admin-panel">
        <div class="php-table-wrap">
            <table class="php-table">
                <thead>
                    <tr>
                        <th>Attendee</th>
                        <th>Event and seat</th>
                        <th>Gate</th>
                        <th>Admission status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><strong><?= e($ticket['attendee_name']) ?></strong><small><?= e($ticket['attendee_student_id'] ?? 'Awaiting assignment') ?></small></td>
                            <td><?= e($ticket['event_title'] ?? $ticket['title'] ?? '') ?><small><?= e($ticket['section'] . ' · ' . $ticket['row_label'] . $ticket['number']) ?></small></td>
                            <td><?= e($ticket['checked_in_gate_name'] ?? $ticket['gate_name'] ?? 'Awaiting assignment') ?></td>
                            <td><span class="history-state is-active">Entry recorded</span><small><?= e(admission_time($ticket['checked_in_at'])) ?></small><small>By <?= e($ticket['checked_in_by_name'] ?? 'Unavailable') ?></small></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$tickets): ?>
                        <tr>
                            <td colspan="4">Student admission records will appear here.</td>
                        </tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
