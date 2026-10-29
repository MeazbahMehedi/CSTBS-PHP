<article class="event-card">
    <a class="event-card-link" href="<?= e(url('/events/' . $event['id'])) ?>">
    <div class="event-card-art art-<?= e(strtolower($event['category'])) ?>">
        <span class="category-pill"><?= e($event['category']) ?></span><span class="art-caption" aria-hidden="true">CAMPUS
        <br>
        LIVE</span>
    </div>
    <div class="event-card-body">
        <div class="event-date">
            <strong><?= e(date('d', strtotime($event['starts_at']))) ?></strong><span><?= e(date('M Y', strtotime($event['starts_at']))) ?></span>
        </div>
        <div class="event-card-info">
            <h3><?= e($event['title']) ?></h3>
            <p><?= e($event['venue']) ?> · <?= e(date('g:i A', strtotime($event['starts_at']))) ?></p>
            <div class="card-bottom">
                <span>Free admission</span><span class="text-link">View event</span>
            </div>
        </div>
    </div>
    </a>
</article>
