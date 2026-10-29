<?php $filters = $filters ?? []; ?>
<section class="page-heading container">
    <span class="section-kicker">ADMINISTRATION · REPORTING</span>
    <h1>Stadium <em>reports.</em></h1>
    <p>Review reservations and attendance, then export a private report.</p>
</section>
<section class="container admin-section php-content">
    <?php require __DIR__ . '/partials/admin-nav.php'; ?>
    <article class="admin-panel">
        <form class="php-form-grid" method="get" action="<?= e(url('/admin/reports')) ?>">
            <div class="form-field">
                <label for="report-event">Event</label>
                <select class="form-control" id="report-event" name="event_id">
                    <option value="">All stadium events</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= e($event['id']) ?>" <?= (int)($filters['event_id'] ?? 0) === (int)$event['id'] ? 'selected' : '' ?>><?= e($event['title']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label for="report-status">Booking status</label>
                <select class="form-control" id="report-status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach (['reserved', 'cancelled'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-field">
                <label for="report-from">From date</label>
                <input class="form-control" id="report-from" name="from" type="date" value="<?= e($filters['from'] ?? '') ?>">
            </div>
            <div class="form-field">
                <label for="report-to">To date</label>
                <input class="form-control" id="report-to" name="to" type="date" value="<?= e($filters['to'] ?? '') ?>">
            </div>
            <button class="button button-primary" type="submit">Apply filters</button>
        </form>
        <div class="quick-actions">
            <a class="button button-secondary" href="<?= e(url('/admin/reports/export') . '?' . http_build_query(array_merge($filters, ['format' => 'csv']))) ?>">Download CSV</a><a class="button button-secondary" href="<?= e(url('/admin/reports/export') . '?' . http_build_query(array_merge($filters, ['format' => 'pdf']))) ?>">Download PDF</a>
        </div>
    </article>
    <article class="admin-panel">
        <h2><?= count($rows) ?> booking records</h2>
        <div class="php-table-wrap">
            <table class="php-table">
                <thead>
                    <tr>
                        <th>Event and seat</th>
                        <th>Attendee</th>
                        <th>Booking</th>
                        <th>Admission</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?= e($row['event_title'] ?? $row['title'] ?? '') ?></strong><small><?= e($row['seat_label'] ?? (($row['section'] ?? '') . ' · ' . ($row['row_label'] ?? '') . ($row['number'] ?? ''))) ?></small></td>
                            <td><?= e($row['attendee_name'] ?? $row['full_name'] ?? '') ?><small><?= e($row['attendee_student_id'] ?? $row['student_id'] ?? '') ?></small></td>
                            <td><?= e($row['status'] ?? $row['booking_status'] ?? '') ?><small><?= e($row['created_at'] ?? '') ?></small></td>
                            <td><?= e(str_replace('_', ' ', $row['ticket_status'] ?? 'Unassigned')) ?><small><?= e($row['checked_in_at'] ?? '') ?></small></td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="4">No bookings match these filters.</td>
                        </tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </article>
</section>
