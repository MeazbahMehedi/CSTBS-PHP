<section class="page-heading container">
    <span class="section-kicker">ADMINISTRATION</span>
    <h1>Campus <em>accounts.</em></h1>
    <p>Manage access for students, staff, and administrators.</p>
</section>
<section class="container admin-section php-content">
    <?php require __DIR__ . '/partials/admin-nav.php'; ?>

    <div class="admin-panel">
        <div class="php-table-wrap">
            <table class="php-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Student ID</th>
                        <th>Role</th>
                        <th>Administrator activation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?= e($user['full_name']) ?></strong><small><?= e($user['email']) ?></small></td>
                            <td><?= e($user['student_id'] ?? '—') ?></td>
                            <td>
                            <form class="php-inline" method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/role')) ?>">
                                <?= csrf_field() ?>
                                <label class="sr-only" for="role-<?= e($user['id']) ?>">Role for <?= e($user['full_name']) ?></label>
                                <select class="form-control" id="role-<?= e($user['id']) ?>" name="role">
                                    <?php foreach (['student', 'staff', 'admin'] as $role): ?>
                                        <option value="<?= e($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <button class="button button-secondary button-small" type="submit">Save role</button>
                            </form>
                            </td>
                            <td>
                            <?php if ($user['role'] === 'admin' && empty($user['password_ready'])): ?>
                                <form method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/key')) ?>">
                                    <?= csrf_field() ?><button class="button button-secondary button-small" type="submit">Issue activation key</button>
                                </form>
                            <?php elseif ($user['role'] === 'admin'): ?>
                                <span class="history-state is-active">Activated</span>
                            <?php else: ?>
                                <span class="muted">Student or staff login</span>
                            <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
