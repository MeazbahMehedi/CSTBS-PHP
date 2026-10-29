<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> YOUR CAMPUS. YOUR MOMENTS.</span>
            <h1>Where campus <em>comes alive.</em></h1>
            <p>From match-day energy to unforgettable nights, discover what is happening at your campus stadium.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(url('/events')) ?>">Explore events</a><a class="button button-ghost" href="#featured">See what's coming</a>
            </div>
            <div class="hero-footnote">
                Browse events, choose your seats and reserve your spot.
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <img src="<?= e(url('/public/assets/images/stadium.svg')) ?>" alt="">
            <div class="hero-art-note">
                <span class="note-icon">★</span><span>More than a game
                <br>
                <strong>It's our community.</strong></span>
            </div>
        </div>
    </div>
</section>
<section class="section" id="featured">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="section-kicker">WHAT'S NEXT</span>
                <h2>Upcoming highlights</h2>
                <p>Find your next reason to cheer, sing, and show up.</p>
            </div>
            <a class="text-link" href="<?= e(url('/events')) ?>">View all events</a>
        </div>
        <?php if ($events): ?>
            <div class="card-grid">
                <?php foreach ($events as $event) require __DIR__ . '/partials/event-card.php'; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">Upcoming events will appear here when they are announced.</p>
        <?php endif ?>
    </div>
</section>
<section class="section section-muted">
    <div class="container info-banner">
        <div class="info-icon" aria-hidden="true">
            ✦
        </div>
        <div>
            <h2>Built for campus life.</h2>
            <p>Discover events, reserve free university seats, and bring your personal QR pass on matchday.</p>
        </div>
        <a class="button button-secondary" href="<?= e(url('/events')) ?>">Browse events</a>
    </div>
</section>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="section-kicker">HOW IT WORKS</span>
                <h2>Your next campus experience</h2>
            </div>
        </div>
        <div class="workflow-grid">
            <div class="workflow-step">
                <strong>Choose an event</strong>
                <p>Find campus games, athletics, and live performances.</p>
            </div>
            <div class="workflow-step">
                <strong>Pick your seats</strong>
                <p>Review current availability and reserve for registered classmates.</p>
            </div>
            <div class="workflow-step">
                <strong>Bring your pass</strong>
                <p>Find your signed QR ticket on your student dashboard.</p>
            </div>
        </div>
    </div>
</section>
