<section class="premium-hero" aria-labelledby="hero-title">
    <div class="hero-light hero-light-one" aria-hidden="true"></div>
    <div class="hero-light hero-light-two" aria-hidden="true"></div>
    <div class="container premium-hero-grid">
        <div class="premium-hero-copy">
            <span class="premium-eyebrow">THE CAMPUS. THE CROWD. THE MOMENT.</span>
            <h1 id="hero-title">Be there.<br><em>Make it a memory.</em></h1>
            <p>Match nights, live performances, and a stadium full of campus spirit. Find your next event and reserve your place.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(url('/events')) ?>">Explore events</a>
                <a class="button button-secondary" href="<?= e(url('/login')) ?>">My admission passes</a>
            </div>
            <div class="hero-assurance"><span>Free student admission</span><span>Your seat, reserved</span></div>
        </div>
        <div class="stadium-scene" aria-hidden="true">
            <div class="stadium-scene-glow"></div>
            <img class="stadium-illustration" src="<?= e(url('/public/assets/images/stadium-night.svg')) ?>" alt="" width="720" height="600" fetchpriority="high">
            <div class="scene-caption"><span>CAMPUS STADIUM</span><strong>A place in the crowd.<br>A moment of your own.</strong></div>
        </div>
    </div>
    <div class="container hero-bottom"><span>Good company. Great atmosphere. Your campus.</span><button type="button" class="motion-toggle" hidden aria-pressed="false">Pause animation</button></div>
</section>
<div class="experience-strip"><div class="container"><span>Find your event</span><span>Choose your seat</span><span>Bring your QR pass</span><span>Enjoy the moment</span></div></div>
<section class="section premium-events" id="featured" data-reveal>
    <div class="container">
        <div class="section-heading">
            <div><span class="section-kicker">ON THE CALENDAR</span><h2>Your next campus moment.</h2><p>Free admission for registered university students.</p></div>
            <a class="text-link" href="<?= e(url('/events')) ?>">All events</a>
        </div>
        <?php if ($events): ?>
            <div class="card-grid">
                <?php foreach ($events as $event): ?>
                    <?php require __DIR__ . '/partials/event-card.php'; ?>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><h3>Events will appear here when announced</h3><p>Check back for the next stadium event.</p></div>
        <?php endif ?>
    </div>
</section>
<section class="container premium-how" aria-labelledby="how-title" data-reveal>
    <div class="premium-how-heading"><span class="section-kicker">LESS PLANNING. MORE LIVING.</span><h2 id="how-title">A great night starts<br> with a reserved seat.</h2><a class="button button-primary" href="<?= e(url('/register')) ?>">Join your campus</a></div>
    <div class="premium-how-steps">
        <article><span class="step-symbol" aria-hidden="true">&#9671;</span><div><h3>Find something worth showing up for.</h3><p>Browse upcoming matches and performances in one place.</p></div></article>
        <article><span class="step-symbol" aria-hidden="true">&#9638;</span><div><h3>Pick your place. Bring your people.</h3><p>Choose seats for yourself and registered classmates, then confirm your reservation.</p></div></article>
        <article><span class="step-symbol" aria-hidden="true">&#9635;</span><div><h3>Your pass. Your way in.</h3><p>Keep your personal QR pass ready and bring your university ID to the assigned gate.</p></div></article>
    </div>
</section>
