<section class="page-heading container animated-enter">
    <span class="section-kicker">CAMPUS EXPERIENCES</span>
    <h1>Make time for <em>matchday.</em></h1>
    <p>Discover stadium events and reserve your free university admission.</p>
</section>
<section class="container section php-content">
    <?php if ($events): ?>
        <div class="card-grid">
            <?php foreach ($events as $event) require __DIR__ . '/partials/event-card.php'; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            No stadium events have been announced yet.
        </div>
    <?php endif ?>
</section>
