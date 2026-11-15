<section class="page-heading container">
    <span class="section-kicker">STADIUM ADMISSION CHECK</span>
    <h1>Ticket <em>verification.</em></h1>
</section>
<section class="container php-content">
    <article class="admin-panel php-narrow">
        <div class="php-verdict php-verdict-<?= e($verdict['status']) ?>" role="status">
            <h2><?= e($verdict['title']) ?></h2>
            <p><?= e($verdict['message']) ?></p>
        </div>
        <?php if ($ticket): ?>
            <h2><?= e($ticket['event_title'] ?? $ticket['title'] ?? '') ?></h2>
            <dl class="php-details">
                <dt>Attendee</dt>
                <dd><?= e($ticket['attendee_name']) ?></dd>
                <dt>Student ID</dt>
                <dd><?= e($ticket['attendee_student_id'] ?? 'Unassigned') ?></dd>
                <dt>Seat</dt>
                <dd><?= e($ticket['section'] . ' · ' . $ticket['row_label'] . $ticket['number']) ?></dd>
                <dt>Assigned entrance</dt>
                <dd><?= e($ticket['gate_name'] ?? 'Awaiting assignment') ?></dd>
                <dt>Check-in status</dt>
                <dd><?= e(str_replace('_', ' ', $ticket['status'])) ?></dd>
            </dl>
            <?php if ($ticket['status'] === 'checked_in'): ?>
                <section class="entry-recorded" aria-label="Recorded admission">
                    <h3>Admission recorded</h3>
                    <dl class="php-details">
                        <dt>Entry time</dt>
                        <dd><time datetime="<?= e($ticket['checked_in_at'] . 'Z') ?>"><?= e(admission_time($ticket['checked_in_at'])) ?></time></dd>
                        <dt>Entry gate</dt>
                        <dd><?= e($ticket['checked_in_gate_name'] ?? 'Unavailable') ?></dd>
                        <dt>Admitted by</dt>
                        <dd><?= e($ticket['checked_in_by_name'] ?? 'Unavailable') ?></dd>
                    </dl>
                </section>
            <?php endif ?>
            <?php if ($ticket['status'] === 'active' && !$ticket['deleted_at']): ?>
            <form class="admin-stack-form" method="get" action="<?= e(url('/entry/verify/' . rawurlencode($token))) ?>">
                <label for="verification-gate">Current physical gate</label>
                <select class="form-control" id="verification-gate" name="gate_id" required>
                    <option value="">Choose entrance</option>
                    <?php foreach ($gates as $entrance): ?>
                        <option value="<?= e($entrance['id']) ?>" <?= $gate && (int)$gate['id'] === (int)$entrance['id'] ? 'selected' : '' ?>><?= e($entrance['name']) ?></option>
                    <?php endforeach ?>
                </select>
                <button class="button button-secondary" type="submit">Check selected gate</button>
            </form>
            <?php endif ?>
            <?php if ($verdict['status'] === 'valid' && $gate): ?>
                <form class="admin-stack-form" method="post" action="<?= e(url('/entry/check-in/' . rawurlencode($token))) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="gate_id" value="<?= e($gate['id']) ?>">
                    <p>Match the attendee's name and Student ID with their university identity card.</p>
                    <button class="button button-primary" type="submit">Confirm entry</button>
                </form>
            <?php endif ?>
        <?php endif ?>
        <div class="confirm-actions">
            <a class="button button-secondary" href="<?= e(url('/entry/scan' . ($gate ? '?gate_id=' . $gate['id'] : ''))) ?>">Scan another ticket</a>
        </div>
    </article>
</section>
