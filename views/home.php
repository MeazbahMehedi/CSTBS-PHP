<section class="hero campus-hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">CAMPUS STADIUM</span>
            <h1>Campus events.<br><em>Your seat, reserved.</em></h1>
            <p>Find upcoming matches and performances, choose your seats, and keep your admission pass ready for the gate.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(url('/events')) ?>">Browse events</a>
                <a class="button button-secondary" href="<?= e(url('/login')) ?>">View my tickets</a>
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <img src="<?= e(url('/public/assets/images/stadium.svg')) ?>" alt="">
        </div>
    </div>
</section>
<section class="section" id="featured">
    <div class="container">
        <div class="section-heading">
            <div>
                <h2>Upcoming events</h2>
                <p>Free admission for registered university students.</p>
            </div>
            <a class="text-link" href="<?= e(url('/events')) ?>">All events</a>
        </div>
        <?php if ($events): ?>
            <div class="card-grid">
                <?php foreach ($events as $event): ?>
                    <?php require __DIR__ . '/partials/event-card.php'; ?>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>Events will appear here when announced</h3>
                <p>Check back for the next stadium event.</p>
            </div>
        <?php endif ?>
    </div>
</section>
<section class="container booking-introduction">
    <div>
        <h2>From reservation to entry</h2>
        <p>Choose an event, select your seats, and confirm registered attendees. Your personal QR pass is available in My tickets.</p>
    </div>
    <a class="button button-secondary" href="<?= e(url('/register')) ?>">Create student account</a>
</section>
