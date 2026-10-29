<section class="page-heading container">
    <span class="section-kicker">PRIVATE ADMINISTRATOR ACTIVATION</span>
    <h1>Activation <em>key issued.</em></h1>
</section>
<section class="container php-content">
    <article class="admin-panel php-narrow">
        <h2><?= e($user['full_name']) ?></h2>
        <p><?= e($user['email']) ?></p>
        <p>Share this key privately with this administrator. It can be used once and expires after 20 minutes.</p>
        <code class="php-secret"><?= e($key) ?></code>
        <p>Use the key at <a href="<?= e(url('/admin/activate')) ?>">administrator activation</a>.</p>
        <a class="button button-secondary" href="<?= e(url('/admin/users')) ?>">Return to accounts</a>
    </article>
</section>
