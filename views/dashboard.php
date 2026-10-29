<?php $user = current_user(); ?>
<section class="page-heading container animated-enter">
    <span class="section-kicker">CAMPUS STADIUM · STUDENT PORTAL</span>
    <h1>Welcome, <em><?= e(explode(' ', $user['full_name'])[0]) ?>.</em></h1>
    <p>Your university matchdays, reservations, and QR admission passes in one place.</p>
</section>
<section class="container admin-section php-content">
    <div class="dashboard-split-cards">
        <a class="student-action-card" href="<?= e(url('/events')) ?>">
        <h2>Browse events</h2>
        <p>Find upcoming stadium activities and reserve your seat.</p>
        <span class="text-link">Explore events</span></a><a class="student-action-card" href="<?= e(url('/bookings')) ?>">
        <h2>My bookings</h2>
        <p>Review reservations, classmate tickets, and cancellations.</p>
        <span class="text-link">Booking history</span></a><a class="student-action-card" href="<?= e(url('/account')) ?>">
        <h2>Student profile</h2>
        <p><?= e($user['student_id'] ?? 'Add your Student ID to book') ?></p>
        <span class="text-link">Manage your account</span></a>
    </div>
    <div class="admin-panel dashboard-tickets">
        <div class="admin-panel-head">
            <div>
                <h2>My admission passes</h2>
                <p>Personal tickets, including passes reserved by a classmate.</p>
            </div>
        </div>
        <?php if (!$tickets): ?>
            <p class="empty-state">Your next stadium experience starts with an event.</p>
        <?php endif ?>
        <?php foreach ($tickets as $ticket): ?>
            <div class="student-ticket-row">
                <div>
                    <h3><?= e($ticket['event_title'] ?? $ticket['title'] ?? '') ?></h3>
                    <p class="muted"><?= e(date('d M Y · g:i A', strtotime($ticket['starts_at']))) ?> · <?= e($ticket['venue']) ?></p>
                    <small><?= e($ticket['section'] . ' · ' . $ticket['row_label'] . $ticket['number']) ?> · <?= e($ticket['gate_name'] ?? 'Gate assignment pending') ?></small>
                </div>
                <div class="ticket-actions">
                    <span class="history-state is-active"><?= e(str_replace('_', ' ', $ticket['status'])) ?></span><a class="button button-secondary button-small" href="<?= e(url('/tickets/' . $ticket['id'])) ?>">View ticket</a>
                </div>
            </div>
        <?php endforeach ?>
    </div>
    <?php if (!empty($events)): ?>
        <div class="admin-panel">
            <h2>Upcoming stadium events</h2>
            <div class="card-grid">
                <?php foreach ($events as $event) require __DIR__ . '/partials/event-card.php'; ?>
            </div>
        </div>
    <?php endif ?>
</section>
