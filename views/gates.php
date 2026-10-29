<?php $section_groups = []; foreach (($seats ?? []) as $seat) $section_groups[$seat['section']][] = $seat; $section_groups = $section_groups ?: ($sections ?? []); ?>
<section class="page-heading container">
    <span class="section-kicker">ADMINISTRATION · ENTRANCES</span>
    <h1>Manage <em>gates.</em></h1>
    <p><?= e($event['title']) ?> · Assign sections and control admission.</p>
</section>
<section class="container admin-section php-content">
    <?php require __DIR__ . '/partials/admin-nav.php'; ?>
    <div class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <h2>Event admission</h2>
                <p><?= $event['checkin_enabled'] ? 'Admission is open' : 'Admission is closed' ?></p>
            </div>
            <span class="ops-state <?= $event['checkin_enabled'] ? 'state-open' : 'state-closed' ?>"><?= $event['checkin_enabled'] ? 'OPEN' : 'CLOSED' ?></span>
        </div>
        <div class="quick-actions">
            <?php foreach (['open' => 'Open all gates', 'close' => 'Close all gates'] as $action => $label): ?>
                <form method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/entry/' . $action)) ?>" data-confirm="Change admission at all gates for this event?">
                    <?= csrf_field() ?><button class="button <?= $action === 'open' ? 'button-primary' : 'button-danger' ?>" type="submit"><?= e($label) ?></button>
                </form>
            <?php endforeach ?>
        </div>
    </div>
    <div class="admin-panel">
        <h2>Entrance status</h2>
        <?php foreach ($gates as $gate): ?>
            <div class="ops-gate-row">
                <div>
                    <strong><?= e($gate['name']) ?></strong>
                    <p class="muted"><?= e($gate['seat_count'] ?? '') ?> seats assigned</p>
                </div>
                <span class="ops-state <?= ($gate['is_open'] && $event['checkin_enabled']) ? 'state-open' : 'state-closed' ?>"><?= ($gate['is_open'] && $event['checkin_enabled']) ? 'OPEN' : 'CLOSED' ?></span>
                <form method="post" action="<?= e(url('/admin/gates/' . $gate['id'] . '/toggle')) ?>">
                    <?= csrf_field() ?><button class="button button-secondary button-small" type="submit"><?= $gate['is_open'] ? 'Close gate' : 'Open gate' ?></button>
                </form>
                <form class="ops-rename-form" method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/gates/' . $gate['id'] . '/rename')) ?>">
                    <?= csrf_field() ?>
                    <label for="gate-name-<?= e($gate['id']) ?>">Gate name</label>
                    <input class="form-control" id="gate-name-<?= e($gate['id']) ?>" name="name" maxlength="40" value="<?= e($gate['name']) ?>" required>
                    <button class="button button-secondary button-small" type="submit">Rename</button>
                </form>
            </div>
        <?php endforeach ?>
        <form class="admin-stack-form" method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/gates')) ?>">
            <?= csrf_field() ?>
            <label for="new-gate-name">Add an entrance</label>
            <input class="form-control" id="new-gate-name" name="name" minlength="2" maxlength="40" placeholder="Gate 2" required>
            <button class="button button-primary" type="submit">Add gate</button>
        </form>
    </div>
    <div class="admin-panel">
        <h2>Section gate assignments</h2>
        <?php foreach ($section_groups as $section => $section_seats): ?>
            <form class="php-inline php-section-assignment" method="post" action="<?= e(url('/admin/events/' . $event['id'] . '/sections/' . rawurlencode((string) $section) . '/gate')) ?>">
                <?= csrf_field() ?>
                <label for="section-gate-<?= e(rawurlencode((string) $section)) ?>"><?= e($section) ?></label>
                <select class="form-control" id="section-gate-<?= e(rawurlencode((string) $section)) ?>" name="gate_id">
                    <?php foreach ($gates as $gate): ?>
                        <option value="<?= e($gate['id']) ?>" <?= isset($section_seats[0]['gate_id']) && (int)$section_seats[0]['gate_id'] === (int)$gate['id'] ? 'selected' : '' ?>><?= e($gate['name']) ?></option>
                    <?php endforeach ?>
                </select>
                <button class="button button-secondary button-small" type="submit">Assign gate</button>
            </form>
        <?php endforeach ?>
    </div>
</section>
