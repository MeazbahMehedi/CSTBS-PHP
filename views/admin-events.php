<section class="page-heading container">
    <span class="section-kicker">ADMINISTRATION</span>
    <div class="admin-heading">
        <div>
            <h1>Events <em>&amp; seats.</em></h1>
            <p>Manage your stadium calendar, inventory, and entrances.</p>
        </div>
        <a class="button button-primary" href="<?= e(url('/admin/events/new')) ?>">Create event</a>
    </div>
</section>
<section class="container admin-section php-content">
    <?php require __DIR__ . '/partials/admin-nav.php'; ?>
    <div class="admin-panel">
        <div class="php-table-wrap">
            <table class="php-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date and venue</th>
                        <th>Reservations</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td><strong><?= e($event['title']) ?></strong><small><?= e($event['category']) ?></small></td>
                            <td><?= e(date('d M Y · g:i A', strtotime($event['starts_at']))) ?><small><?= e($event['venue']) ?></small></td>
                            <td><?= $event['booking_enabled'] ? 'Open' : 'Closed' ?><small><?= $event['checkin_enabled'] ? 'Entry open' : 'Entry closed' ?></small></td>
                            <td>
                            <div class="php-inline">
                                <a class="button button-secondary button-small" href="<?= e(url('/admin/events/' . $event['id'] . '/edit')) ?>">Edit</a><a class="button button-secondary button-small" href="<?= e(url('/admin/events/' . $event['id'] . '/seats')) ?>">Seats</a><a class="button button-secondary button-small" href="<?= e(url('/admin/events/' . $event['id'] . '/gates')) ?>">Gates</a>
                                <form method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/delete')) ?>" data-confirm="Delete this event and its unbooked seating inventory?">
                                    <?= csrf_field() ?><button class="button button-danger button-small" type="submit">Delete</button>
                                </form>
                            </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?php if (!$events): ?>
                        <tr>
                            <td colspan="4">Your stadium events will appear here.</td>
                        </tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
