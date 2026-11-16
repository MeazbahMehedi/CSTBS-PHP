<?php
$user = current_user();
$upcomingTickets = array_values(array_filter($tickets, fn($ticket) =>
    $ticket['status'] === 'active'
    && empty($ticket['deleted_at'])
    && strtotime($ticket['starts_at']) >= time()
));
usort($upcomingTickets, fn($first, $second) => strcmp($first['starts_at'], $second['starts_at']));
$nextTicket = $upcomingTickets[0] ?? null;
?>
<section class="page-heading container student-dashboard-heading">
    <div>
        <span class="section-kicker">MY TICKETS</span>
        <h1>Welcome, <?= e(explode(' ', $user['full_name'])[0]) ?>.</h1>
        <p>Keep your admission passes ready for your next campus event.</p>
    </div>
    <a class="button button-primary" href="<?= e(url('/events')) ?>">Browse events</a>
</section>
<section class="container php-content student-workspace">
    <?php if ($nextTicket): ?>
        <article class="next-ticket-card">
            <div class="next-ticket-details">
                <span class="section-kicker">YOUR NEXT EVENT</span>
                <h2><?= e($nextTicket['event_title'] ?? $nextTicket['title'] ?? '') ?></h2>
                <p><?= e(date('l, d M Y · g:i A', strtotime($nextTicket['starts_at']))) ?></p>
                <p><?= e($nextTicket['venue']) ?></p>
                <dl class="next-ticket-location">
                    <div><dt>Seat</dt><dd><?= e($nextTicket['section'] . ' · ' . $nextTicket['row_label'] . $nextTicket['number']) ?></dd></div>
                    <div><dt>Entrance</dt><dd><?= e($nextTicket['gate_name'] ?? 'Awaiting assignment') ?></dd></div>
                </dl>
                <a class="button button-primary" href="<?= e(url('/tickets/' . $nextTicket['id'])) ?>">Open admission pass</a>
            </div>
            <a class="next-ticket-qr" href="<?= e(url('/tickets/' . $nextTicket['id'])) ?>" aria-label="Open admission pass">
                <img src="<?= e(url('/tickets/' . $nextTicket['id'] . '/qr.svg')) ?>" width="180" height="180" alt="Your next event QR admission pass">
                <span>Present at the assigned gate</span>
            </a>
        </article>
    <?php endif ?>
    <section class="admin-panel dashboard-tickets" aria-labelledby="admission-passes-heading">
        <div class="admin-panel-head">
            <div>
                <h2 id="admission-passes-heading">My admission passes</h2>
                <p>Includes passes reserved for you by a classmate.</p>
            </div>
            <a class="text-link" href="<?= e(url('/bookings')) ?>">Booking history</a>
        </div>
        <?php if (!$tickets): ?>
            <div class="empty-state">
                <h3>No upcoming admission passes</h3>
                <p>Reserve a seat for an event to receive your personal QR pass.</p>
                <a class="button button-secondary" href="<?= e(url('/events')) ?>">Find an event</a>
            </div>
        <?php endif ?>
        <?php foreach ($tickets as $ticket): ?>
            <article class="student-ticket-row">
                <div>
                    <h3><?= e($ticket['event_title'] ?? $ticket['title'] ?? '') ?></h3>
                    <p><?= e(date('d M Y · g:i A', strtotime($ticket['starts_at']))) ?> · <?= e($ticket['venue']) ?></p>
                    <small><?= e($ticket['section'] . ' · ' . $ticket['row_label'] . $ticket['number']) ?> · <?= e($ticket['gate_name'] ?? 'Gate assignment pending') ?></small>
                    <?php if ($ticket['status'] === 'checked_in' && !empty($ticket['checked_in_at'])): ?>
                        <small>Used <?= e(admission_time($ticket['checked_in_at'])) ?></small>
                    <?php endif ?>
                </div>
                <div class="ticket-actions">
                    <span class="history-state <?= $ticket['status'] === 'active' ? 'is-active' : 'is-used' ?>"><?= $ticket['status'] === 'checked_in' ? 'Used' : 'Ready for entry' ?></span>
                    <a class="button button-secondary button-small" href="<?= e(url('/tickets/' . $ticket['id'])) ?>">View pass</a>
                </div>
            </article>
        <?php endforeach ?>
    </section>
    <?php if (!empty($events)): ?>
        <section class="dashboard-event-section" aria-labelledby="upcoming-events-heading">
            <div class="section-heading">
                <h2 id="upcoming-events-heading">Upcoming events</h2>
                <a class="text-link" href="<?= e(url('/events')) ?>">All events</a>
            </div>
            <div class="card-grid">
                <?php foreach ($events as $event): ?>
                    <?php require __DIR__ . '/partials/event-card.php'; ?>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>
</section>
