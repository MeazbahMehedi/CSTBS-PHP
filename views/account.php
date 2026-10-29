<?php $user = current_user(); ?>
<section class="page-heading container">
    <span class="section-kicker">YOUR CAMPUS ACCOUNT</span>
    <h1>Account <em>details.</em></h1>
    <p>Manage your university identity for event reservations.</p>
</section>
<section class="container php-content">
    <article class="admin-panel php-narrow">
        <dl class="php-details">
            <dt>Full name</dt>
            <dd><?= e($user['full_name']) ?></dd>
            <dt>Email address</dt>
            <dd><?= e($user['email']) ?></dd>
            <dt>Role</dt>
            <dd><?= e(ucfirst($user['role'])) ?></dd>
            <dt>Student ID</dt>
            <dd><?= e($user['student_id'] ?? 'Not provided') ?></dd>
        </dl>
        <?php if ($user['role'] === 'student'): ?>
            <form method="post" action="<?= e(url('/account/student-id')) ?>" class="admin-stack-form">
                <?= csrf_field() ?>
                <label for="student_id">University Student ID</label>
                <input class="form-control" id="student_id" name="student_id" minlength="6" maxlength="20" pattern="[A-Za-z0-9]{6,20}" value="<?= e($user['student_id'] ?? '') ?>" required>
                <button class="button button-primary" type="submit">Update Student ID</button>
            </form>
        <?php endif ?>
    </article>
</section>
