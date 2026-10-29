<section class="page-heading container animated-enter">
    <a class="back-link" href="<?= e(url('/events')) ?>">All events</a><span class="section-kicker"><?= e($event['category']) ?></span>
    <h1><?= e($event['title']) ?></h1>
    <p><?= e(date('l, d F Y · g:i A', strtotime($event['starts_at']))) ?> · <?= e($event['venue']) ?></p>
</section>
<section class="container php-content">
    <div class="dashboard-split-cards">
        <article class="admin-panel">
            <h2>Your campus. Your seat.</h2>
            <p class="php-description"><?= nl2br(e($event['description'])) ?></p>
            <p class="muted">Up to six seats per reservation. Every attendee needs their own registered student account.</p>
        </article>
        <aside class="admin-panel">
            <span class="section-kicker">UNIVERSITY ADMISSION</span>
            <h2>Free</h2>
            <p>Choose your assigned seat and receive your personal QR pass.</p>
            <?php if (!empty($event['booking_enabled']) && strtotime($event['starts_at']) > time()): ?>
                <a class="button button-primary" href="<?= e(url('/events/' . $event['id'] . '/seats')) ?>">Choose seats</a>
            <?php else: ?>
                <p class="flash flash-info">Reservations are closed for this event.</p>
            <?php endif ?>
        </aside>
    </div>
</section>
