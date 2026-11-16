<?php $user = current_user(); ?>
<section class="page-heading container animated-enter">
    <span class="section-kicker">RESERVATION</span>
    <h1>Review attendees and seats.</h1>
    <p>Check the selected seats and enter each attendee’s registered name and Student ID. The first seat is yours.</p>
</section>
<nav class="container" aria-label="Reservation progress">
    <ol class="booking-progress">
        <li class="is-complete"><span>Select seats</span></li>
        <li aria-current="step"><span>Attendees &amp; review</span></li>
        <li><span>Confirmation</span></li>
    </ol>
</nav>
<section class="container confirm-section php-content">
    <article class="confirm-panel glass-panel">
        <h2><?= e($event['title']) ?></h2>
        <p class="confirm-details"><?= e(date('d M Y · g:i A', strtotime($event['starts_at']))) ?> · <?= e($event['venue']) ?></p>
        <form method="post" action="<?= e(url('/events/' . $event['id'] . '/booking/confirm')) ?>">
            <?= csrf_field() ?>
            <?php foreach ($seats as $index => $seat): ?>
                <fieldset class="attendee-card">
                    <legend><strong>Ticket <?= $index + 1 ?> · <?= e($seat['section'] . ' · ' . $seat['row_label'] . $seat['number']) ?></strong>
                    <?php if ($index === 0): ?>
                        <span class="tag-free">Your seat</span>
                    <?php endif ?>
                    </legend>
                    <div class="attendee-fields">
                        <div class="form-field">
                            <label for="attendee-name-<?= $index ?>">Registered student full name</label>
                            <input class="form-control" id="attendee-name-<?= $index ?>" name="attendee_name[]" maxlength="120" value="<?= e($index === 0 ? $user['full_name'] : '') ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="attendee-id-<?= $index ?>">Student ID</label>
                            <input class="form-control" id="attendee-id-<?= $index ?>" name="attendee_student_id[]" minlength="6" maxlength="20" pattern="[A-Za-z0-9]{6,20}" value="<?= e($index === 0 ? $user['student_id'] : '') ?>" required>
                        </div>
                    </div>
                    <?php if ($index > 0): ?>
                        <p class="muted">Reserve only for a registered classmate who has agreed to attend.</p>
                    <?php endif ?>
                </fieldset>
            <?php endforeach ?>
            <div class="checkout-total">
                <span>Total payable</span><strong>¥0.00 · Free</strong>
            </div>
            <div class="confirm-actions">
                <button class="button button-primary" type="submit">Confirm <?= count($seats) ?> free ticket<?= count($seats) === 1 ? '' : 's' ?></button><a class="button button-secondary" href="<?= e(url('/events/' . $event['id'] . '/seats')) ?>">Choose different seats</a>
            </div>
        </form>
        <p class="seat-hint">Seats are held only after confirmation. Every seat and attendee is checked again before the reservation is saved.</p>
    </article>
</section>
