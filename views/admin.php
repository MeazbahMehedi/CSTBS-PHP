<section class="page-heading container admin-page-title animated-enter">
    <span class="section-kicker">AUTHORIZED ADMINISTRATION · STADIUM OPERATIONS</span>
    <div class="admin-heading">
        <div>
            <h1>Stadium <em>control center.</em></h1>
            <p>Open entrances, monitor attendance, and manage event operations from one screen.</p>
        </div>
        <a class="button button-primary" href="<?= e(url('/entry/scan')) ?>">Open QR scanner</a>
    </div>
</section>
<section class="container admin-section php-content">
    <?php require __DIR__ . '/partials/admin-nav.php'; ?>
    <div class="admin-metrics ops-metrics">
        <a class="metric-card" href="<?= e(url('/admin/users')) ?>"><span>Registered students</span><strong><?= e($metrics['students'] ?? 0) ?></strong><small>Manage university accounts</small></a><a class="metric-card" href="<?= e(url('/admin/events')) ?>"><span>Stadium events</span><strong><?= e($metrics['events'] ?? 0) ?></strong><small>Manage events and sections</small></a><a class="metric-card" href="<?= e(url('/admin/bookings')) ?>"><span>Active admission passes</span><strong><?= e($metrics['active_tickets'] ?? 0) ?></strong><small>View bookings and tickets</small></a><a class="metric-card" href="<?= e(url('/admin/check-ins')) ?>"><span>Students checked in</span><strong><?= e($metrics['inside'] ?? 0) ?></strong><small>Check attendance status</small></a>
    </div>
    <div class="admin-panel ops-control-panel">
        <div class="admin-panel-head">
            <div>
                <span class="section-kicker">LIVE CONTROL</span>
                <h2>Event entry and gate controls</h2>
                <p>Open entry to open the event's entrances together. Closing entry stops new check-ins.</p>
            </div>
            <a class="button button-secondary button-small" href="<?= e(url('/admin/events/new')) ?>">Add event</a>
        </div>
        <?php if (!$events): ?>
            <p class="empty-state">Create the first stadium event to begin managing reservations and entrances.</p>
        <?php endif ?>
        <div class="ops-event-list">
            <?php foreach ($events as $event): ?>
                <article class="ops-event-card">
                    <div class="ops-event-info">
                        <div class="ops-event-top">
                            <h3><?= e($event['title']) ?></h3>
                            <span class="ops-state <?= $event['checkin_enabled'] ? 'state-open' : 'state-closed' ?>"><?= $event['checkin_enabled'] ? 'ENTRY OPEN' : 'ENTRY CLOSED' ?></span>
                        </div>
                        <p><?= e(date('d M Y · g:i A', strtotime($event['starts_at']))) ?> · <?= e($event['venue']) ?></p>
                        <div class="ops-inline-stats">
                            <span><strong><?= e($event['inside'] ?? 0) ?></strong> entered</span><span><strong><?= e($event['reserved'] ?? 0) ?></strong> reserved</span><span><strong><?= e($event['available'] ?? 0) ?></strong> available</span><span><strong><?= e($event['gates_open'] ?? 0) ?>/<?= e($event['gates_total'] ?? 0) ?></strong> gates open</span>
                        </div>
                    </div>
                    <div class="ops-actions">
                        <form method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/entry/' . ($event['checkin_enabled'] ? 'close' : 'open'))) ?>" data-confirm="Change entry status at all gates for this event?">
                            <?= csrf_field() ?><button class="button <?= $event['checkin_enabled'] ? 'button-danger' : 'button-primary' ?> button-small" type="submit"><?= $event['checkin_enabled'] ? 'Close entry' : 'Open entry' ?></button>
                        </form>
                        <a class="button button-secondary button-small" href="<?= e(url('/admin/events/' . $event['id'] . '/gates')) ?>">Manage gates</a><a class="button button-secondary button-small" href="<?= e(url('/admin/events/' . $event['id'] . '/seats')) ?>">Manage seats</a>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    </div>
    <div class="quick-actions">
        <a class="button button-secondary" href="<?= e(url('/admin/bookings')) ?>">Manage tickets</a><a class="button button-secondary" href="<?= e(url('/admin/users')) ?>">Students &amp; staff</a><a class="button button-primary" href="<?= e(url('/admin/reports')) ?>">Reports &amp; exports</a>
    </div>
</section>
