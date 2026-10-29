<?php $section_groups = []; foreach ($seats as $seat) $section_groups[$seat['section']][] = $seat; ?>
<section class="page-heading container">
    <a class="back-link" href="<?= e(url('/admin/events')) ?>">All stadium events</a><span class="section-kicker">SEATING INVENTORY</span>
    <h1>Manage <em>seats.</em></h1>
    <p><?= e($event['title']) ?> · <?= e($event['venue']) ?></p>
</section>
<section class="container admin-section php-content">
    <?php require __DIR__ . '/partials/admin-nav.php'; ?>
    <div class="admin-panel">
        <h2>Add a seating section</h2>
        <form class="php-form-grid" method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/sections/create')) ?>">
            <?= csrf_field() ?>
            <div class="form-field">
                <label for="section">Section name</label>
                <input class="form-control" id="section" name="section" maxlength="30" required>
            </div>
            <div class="form-field">
                <label for="rows">Rows</label>
                <input class="form-control" id="rows" name="rows" type="number" min="1" max="20" value="3" required>
            </div>
            <div class="form-field">
                <label for="seat_count">Seats per row</label>
                <input class="form-control" id="seat_count" name="seat_count" type="number" min="1" max="30" value="10" required>
            </div>
            <div class="form-field">
                <label for="gate_id">Assigned gate</label>
                <select class="form-control" id="gate_id" name="gate_id" required>
                    <?php foreach ($gates as $gate): ?>
                        <option value="<?= e($gate['id']) ?>"><?= e($gate['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <button class="button button-primary" type="submit">Create section</button>
        </form>
    </div>
    <?php foreach ($section_groups as $section => $inventory): ?>
        <article class="admin-panel">
            <div class="admin-panel-head">
                <div>
                    <h2><?= e($section) ?></h2>
                    <p><?= count($inventory) ?> seats · Reserved seats require cancellation before blocking.</p>
                </div>
                <div class="php-inline">
                    <form class="php-inline" method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/sections/' . rawurlencode($section) . '/rename')) ?>">
                        <?= csrf_field() ?>
                        <label class="sr-only" for="rename-<?= e($inventory[0]['id']) ?>">Section name</label>
                        <input class="form-control" id="rename-<?= e($inventory[0]['id']) ?>" name="new_name" maxlength="30" value="<?= e($section) ?>" required>
                        <button class="button button-secondary button-small" type="submit">Rename</button>
                    </form>
                    <form method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/sections/' . rawurlencode($section) . '/delete')) ?>" data-confirm="Delete this unbooked seating section?">
                        <?= csrf_field() ?><button class="button button-danger button-small" type="submit">Delete section</button>
                    </form>
                </div>
            </div>
            <div class="php-inventory">
                <?php foreach ($inventory as $seat): ?>
                    <form method="post" action="<?= e(url('/admin/seats/' . $seat['id'] . '/toggle-block')) ?>">
                        <?= csrf_field() ?><button class="seat-button<?= !empty($seat['booking_id']) ? ' is-taken' : (!empty($seat['is_blocked']) ? ' is-blocked' : '') ?>" type="submit" aria-label="<?= e($section . ' ' . $seat['row_label'] . $seat['number'] . (!empty($seat['is_blocked']) ? ', unblock seat' : ', block seat')) ?>" <?= !empty($seat['booking_id']) ? 'disabled' : '' ?>><?= e($seat['row_label'] . $seat['number']) ?></button>
                    </form>
                <?php endforeach ?>
            </div>
        </article>
    <?php endforeach ?>
</section>
