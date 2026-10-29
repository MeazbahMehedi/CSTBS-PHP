<section class="page-heading container animated-enter">
    <span class="section-kicker">STAFF ACCESS · ENTRY CONTROL</span>
    <h1>Scan a <em>stadium ticket.</em></h1>
    <p>Select your physical entrance, scan the student's QR, and verify their university ID before confirming admission.</p>
</section>
<section class="container gate-grid php-content">
    <article class="admin-panel glass-panel">
        <h2>Select your entrance</h2>
        <label for="gate-selector">Gate where you are working</label>
        <select class="form-control" id="gate-selector" required>
            <option value="">Choose event and gate</option>
            <?php foreach ($gates as $gate): ?>
                <option value="<?= e($gate['id']) ?>" <?= (int)($_GET['gate_id'] ?? 0) === (int)$gate['id'] ? 'selected' : '' ?>><?= e(($gate['event_title'] ?? $gate['title'] ?? '') . ' · ' . $gate['name']) ?> (<?= (!empty($gate['is_open']) && !empty($gate['checkin_enabled'])) ? 'OPEN' : 'CLOSED' ?>)</option>
            <?php endforeach ?>
        </select>
        <p class="muted">The selected gate must match the entrance printed on the student's ticket.</p>
        <h2>Live camera scanner</h2>
        <video class="scanner-video" id="gate-video" data-scan-url="<?= e(url('/entry/scan')) ?>" playsinline muted aria-label="Camera preview for ticket scanning"></video>
        <p id="scanner-status" role="status" aria-live="polite">Choose a gate, then start the camera. Live scanning requires HTTPS or localhost and a compatible browser.</p>
        <div class="confirm-actions">
            <button class="button button-primary" type="button" id="start-scanner">Start camera</button><button class="button button-secondary" type="button" id="stop-scanner">Stop camera</button>
        </div>
    </article>
    <article class="admin-panel glass-panel">
        <h2>Read a QR photograph</h2>
        <p>Take a clear photograph of the ticket QR or choose a saved image.</p>
        <form class="admin-stack-form" id="qr-photo-form" method="post" action="<?= e(url('/entry/scan-photo')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="gate_id" class="scanner-gate-id" value="<?= e($_GET['gate_id'] ?? '') ?>">
            <label for="qr-photo">QR image, up to 3 MB</label>
            <input class="form-control" id="qr-photo" name="qr_photo" type="file" accept="image/png,image/jpeg,image/webp" capture="environment" required>
            <button class="button button-primary button-full" type="submit">Read QR photograph</button>
        </form>
        <hr class="soft-divider">
        <h2>Manual ticket code</h2>
        <form class="admin-stack-form" id="manual-code-form" method="get" action="<?= e(url('/entry/scan')) ?>">
            <input type="hidden" name="gate_id" class="scanner-gate-id" value="<?= e($_GET['gate_id'] ?? '') ?>">
            <label for="scanned-code">Signed code from the student's digital pass</label>
            <textarea class="form-control" id="scanned-code" name="code" maxlength="1000" rows="4" required></textarea>
            <button class="button button-secondary button-full" type="submit">Verify code</button>
        </form>
    </article>
</section>
<script src="<?= e(url('/public/assets/js/scanner.js')) ?>" defer></script>
