<?php $editing = !empty($event); $values = $values ?? ($event ?? []); ?>
<section class="page-heading container">
    <a class="back-link" href="<?= e(url('/admin/events')) ?>">All stadium events</a><span class="section-kicker">ADMINISTRATION</span>
    <h1><?= $editing ? 'Edit your' : 'Create a' ?><em>stadium event.</em></h1>
</section>
<section class="container admin-section php-content">
    <?php require __DIR__ . '/partials/admin-nav.php'; ?>
    <article class="admin-panel php-narrow">
        <?php foreach (($errors ?? []) as $error): ?>
            <p class="field-error" role="alert"><?= e($error) ?></p>
        <?php endforeach ?>
        <form method="post" action="<?= e(url($editing ? '/admin/events/' . $event['id'] . '/edit' : '/admin/events/new')) ?>" class="admin-stack-form">
            <?= csrf_field() ?>
            <label for="title">Event title</label>
            <input class="form-control" id="title" name="title" maxlength="150" value="<?= e($values['title'] ?? '') ?>" required>
            <label for="category">Category</label>
            <input class="form-control" id="category" name="category" maxlength="40" value="<?= e($values['category'] ?? 'Sports') ?>" required>
            <label for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="5" maxlength="5000" required><?= e($values['description'] ?? '') ?></textarea>
            <label for="venue">Venue</label>
            <input class="form-control" id="venue" name="venue" maxlength="120" value="<?= e($values['venue'] ?? 'Campus Stadium') ?>" required>
            <label for="starts_at">Event date and time</label>
            <input class="form-control" id="starts_at" name="starts_at" type="datetime-local" value="<?= e(!empty($values['starts_at']) ? date('Y-m-d\TH:i', strtotime($values['starts_at'])) : '') ?>" required>
            <?php if ($editing): ?>
                <label class="php-check"><input type="checkbox" name="booking_enabled" value="1" <?= !empty($values['booking_enabled']) ? 'checked' : '' ?>> Reservations open</label>
            <?php endif ?>
            <p class="muted">University admission is free. New events receive 60 seats and a closed first entrance.</p>
            <button class="button button-primary" type="submit"><?= $editing ? 'Save event' : 'Create event' ?></button>
        </form>
    </article>
</section>
