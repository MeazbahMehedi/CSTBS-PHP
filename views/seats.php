<?php
$occupied_count = count(array_filter($seats, fn($seat) => !empty($seat['booking_id'])));
$blocked_count = count(array_filter($seats, fn($seat) => !empty($seat['is_blocked']) && empty($seat['booking_id'])));
$sections = [];
foreach ($seats as $seat) $sections[$seat['section']][$seat['row_label']][] = $seat;
?>
<section class="page-heading container seat-heading">
    <a class="back-link" href="<?= e(url('/events/' . $event['id'])) ?>">Back to event details</a><span class="section-kicker">RESERVATION</span>
    <h1>Choose your seats.</h1>
    <p><?= e($event['title']) ?> · <?= e(date('d M Y · g:i A', strtotime($event['starts_at']))) ?> · <?= e($event['venue']) ?></p>
</section>
<nav class="container" aria-label="Reservation progress">
    <ol class="booking-progress">
        <li aria-current="step"><span>Select seats</span></li>
        <li ><span>Attendees &amp; review</span></li>
        <li><span>Confirmation</span></li>
    </ol>
</nav>
<section class="container seat-layout php-content">
    <div class="seat-card">
        <div class="seat-card-top">
            <h2>Stadium seating</h2>
            <p id="availability-summary" role="status" aria-live="polite"><?= count($seats) - $occupied_count - $blocked_count ?> of <?= count($seats) ?> seats available</p>
        </div>
        <div class="seat-legend">
            <span><i class="seat-key available"></i> Available</span><span><i class="seat-key selected"></i> Selected</span><span><i class="seat-key taken"></i> Reserved</span><span><i class="seat-key blocked"></i> Blocked</span>
        </div>
        <div class="stage">
            <span>FIELD / STAGE</span>
        </div>
        <div class="seat-scroll" role="region" aria-label="Stadium seating grid" tabindex="0">
            <div id="seat-grid">
                <?php foreach ($sections as $section => $rows): ?>
                    <h3 class="seat-section-heading"><?= e($section) ?></h3>
                    <?php foreach ($rows as $row => $row_seats): ?>
                        <div class="seat-row">
                            <span class="seat-row-label"><?= e($row) ?></span>
                            <div class="seat-grid" style="--seat-cols: <?= count($row_seats) ?>">
                                <?php foreach ($row_seats as $seat): $taken = !empty($seat['booking_id']); $blocked = !empty($seat['is_blocked']); $label = $seat['section'] . ' · ' . $seat['row_label'] . $seat['number']; ?>
                                    <button type="button" class="seat-button<?= $taken ? ' is-taken' : ($blocked ? ' is-blocked' : '') ?>" data-seat-id="<?= e($seat['id']) ?>" data-label="<?= e($label) ?>" aria-label="<?= e($label . ($taken ? ', reserved' : ($blocked ? ', blocked' : ', available'))) ?>" aria-pressed="false" <?= ($taken || $blocked || !$booking_enabled) ? 'disabled' : '' ?>><?= e($seat['row_label'] . $seat['number']) ?></button>
                                <?php endforeach ?>
                            </div>
                        </div>
                <?php endforeach; endforeach ?>
            </div>
        </div>
        <p class="seat-hint">Choose up to six seats. Availability refreshes every five seconds and is checked on confirmation.</p>
    </div>
    <aside class="seat-checkout" aria-label="Selected seats">
        <span class="section-kicker">YOUR SELECTION</span>
        <h2>Booking summary</h2>
        <div class="checkout-line">
            <span>University admission</span><strong>Free</strong>
        </div>
        <div class="checkout-line">
            <span>Seats selected</span><strong id="seat-count">0</strong>
        </div>
        <div class="seat-selection-label" id="selected-labels">
            No seats selected yet
        </div>
        <div class="checkout-total">
            <span>Amount due</span><strong id="total-price">¥0.00</strong>
        </div>
        <?php if ($booking_enabled): ?>
            <form id="seat-booking-form" method="post" action="<?= e(url('/events/' . $event['id'] . '/booking/review')) ?>" data-availability-url="<?= e(url('/api/events/' . $event['id'] . '/seats')) ?>" data-max-seats="6">
                <?= csrf_field() ?>
                <input type="hidden" name="seat_ids" id="seat-ids" value="">
                <button class="button button-primary checkout-button" id="review-button" type="submit" disabled>Review reservation</button>
            </form>
        <?php else: ?>
            <p class="flash flash-error">Reservations are closed for this event.</p>
        <?php endif ?>
        <p class="seat-checkout-note" id="seat-status" role="status" aria-live="polite">University reservations are free.</p>
    </aside>
</section>
