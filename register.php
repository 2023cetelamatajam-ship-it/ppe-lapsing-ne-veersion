<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

$databaseError = db_error();
$pdo = $databaseError === null ? db() : null;
$userCount = $pdo instanceof PDO ? count_users($pdo) : 0;
$bootstrapSetup = $userCount === 0;
$existingUsers = $pdo instanceof PDO ? fetch_users($pdo) : [];

if (!$bootstrapSetup) {
    require_admin();
}

$createErrors = [];
$passwordErrors = [];
$createForm = [
    'full_name' => trim((string) request_value('full_name', '')),
    'email' => trim((string) request_value('email', '')),
    'role' => (string) request_value('role', 'Accounting Staff'),
];
$selectedPasswordUserId = (int) request_value(
    'password_user_id',
    $existingUsers[0]['user_id'] ?? (current_user()['user_id'] ?? 0)
);
$passwordForm = [
    'password_user_id' => $selectedPasswordUserId,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    $formAction = (string) request_value('form_action', 'create_user');

    if ($formAction === 'change_password' && !$bootstrapSetup) {
        $selectedPasswordUserId = (int) request_value('password_user_id', 0);
        $passwordForm['password_user_id'] = $selectedPasswordUserId;
        $newPassword = (string) request_value('new_password', '');
        $confirmNewPassword = (string) request_value('confirm_new_password', '');
        $targetUser = fetch_user_by_id($pdo, $selectedPasswordUserId);

        if (!$targetUser) {
            $passwordErrors[] = 'Please choose a valid user account.';
        }

        if (strlen($newPassword) < 6) {
            $passwordErrors[] = 'New password must be at least 6 characters long.';
        }

        if ($newPassword !== $confirmNewPassword) {
            $passwordErrors[] = 'New password confirmation does not match.';
        }

        if ($passwordErrors === [] && $targetUser) {
            update_user_password($pdo, $selectedPasswordUserId, $newPassword);
            set_flash('success', 'Password updated successfully for ' . $targetUser['full_name'] . '.');
            redirect('auth/register.php?password_user_id=' . $selectedPasswordUserId . '#password-panel');
        }
    } else {
        $password = (string) request_value('password', '');
        $confirmPassword = (string) request_value('confirm_password', '');
        $role = $bootstrapSetup ? 'Admin' : $createForm['role'];

        if ($createForm['full_name'] === '') {
            $createErrors[] = 'Full name is required.';
        }

        if ($createForm['email'] === '' || !filter_var($createForm['email'], FILTER_VALIDATE_EMAIL)) {
            $createErrors[] = 'A valid email address is required.';
        }

        if (strlen($password) < 6) {
            $createErrors[] = 'Password must be at least 6 characters long.';
        }

        if ($password !== $confirmPassword) {
            $createErrors[] = 'Password confirmation does not match.';
        }

        if (!in_array($role, ['Admin', 'Accounting Staff', 'Auditor'], true)) {
            $createErrors[] = 'Please choose a valid role.';
        }

        if ($createErrors === []) {
            $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $check->execute(['email' => $createForm['email']]);

            if ((int) $check->fetchColumn() > 0) {
                $createErrors[] = 'That email address is already being used.';
            }
        }

        if ($createErrors === []) {
            $insert = $pdo->prepare(
                'INSERT INTO users (full_name, email, password, role) VALUES (:full_name, :email, :password, :role)'
            );
            $insert->execute([
                'full_name' => $createForm['full_name'],
                'email' => $createForm['email'],
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
            ]);

            if ($bootstrapSetup) {
                $user = [
                    'user_id' => (int) $pdo->lastInsertId(),
                    'full_name' => $createForm['full_name'],
                    'email' => $createForm['email'],
                    'role' => 'Admin',
                ];
                login_user($user);
                set_flash('success', 'The first admin account is ready.');
                redirect('modules/dashboard.php');
            }

            set_flash('success', 'User account created successfully.');
            redirect('auth/register.php');
        }
    }
}

$existingUsers = $pdo instanceof PDO ? fetch_users($pdo) : [];
$selectedPasswordUserId = $bootstrapSetup
    ? 0
    : ($selectedPasswordUserId > 0 ? $selectedPasswordUserId : (int) ($existingUsers[0]['user_id'] ?? 0));
$selectedPasswordUser = (!$bootstrapSetup && $selectedPasswordUserId > 0)
    ? fetch_user_by_id($pdo, $selectedPasswordUserId)
    : null;

$pageTitle = 'User Management';
$pageHeading = $bootstrapSetup ? 'Create the first admin account' : 'User Management';
$pageDescription = $bootstrapSetup
    ? 'Set up the first administrator so the PPE system can be used.'
    : '';

require_once APP_ROOT . '/includes/header.php';
?>
<?php if ($databaseError !== null): ?>
    <div class="alert alert-danger">Database connection failed: <?= e($databaseError) ?></div>
<?php else: ?>
    <?php if ($createErrors !== []): ?>
        <div class="alert alert-danger">
            <?= e(implode(' ', $createErrors)) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <section class="shell-card h-100">
                <div class="mb-4">
                    <p class="eyebrow mb-2"><?= $bootstrapSetup ? 'Initial setup' : 'Create account' ?></p>
                    <h2 class="section-title mb-2"><?= $bootstrapSetup ? 'First administrator' : 'New user profile' ?></h2>
                    <p class="section-subtitle">
                        <?= $bootstrapSetup ? 'This first account will automatically become an Admin.' : '' ?>
                    </p>
                </div>

                <form method="post" class="stack-gap">
                    <input type="hidden" name="form_action" value="create_user">
                    <div>
                        <label class="form-label" for="full_name">Full name</label>
                        <input class="form-control" id="full_name" name="full_name" value="<?= e($createForm['full_name']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="email">Email address</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?= e($createForm['email']) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="password">Password</label>
                        <input class="form-control" id="password" name="password" type="password" minlength="6" required>
                    </div>
                    <div>
                        <label class="form-label" for="confirm_password">Confirm password</label>
                        <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="6" required>
                    </div>
                    <div>
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role" <?= $bootstrapSetup ? 'disabled' : '' ?>>
                            <option value="Admin" <?= selected_if($createForm['role'], 'Admin') ?>>Admin</option>
                            <option value="Accounting Staff" <?= selected_if($createForm['role'], 'Accounting Staff') ?>>Accounting Staff</option>
                            <option value="Auditor" <?= selected_if($createForm['role'], 'Auditor') ?>>Auditor</option>
                        </select>
                        <?php if ($bootstrapSetup): ?>
                            <input type="hidden" name="role" value="Admin">
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-person-plus me-2"></i><?= $bootstrapSetup ? 'Create Admin Account' : 'Create User' ?>
                    </button>
                </form>
            </section>
        </div>

        <?php if (!$bootstrapSetup): ?>
            <div class="col-lg-7">
                <div class="stack-gap h-100">
                <section class="shell-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <p class="eyebrow mb-2">Current access list</p>
                            <h2 class="section-title mb-0">Existing users</h2>
                        </div>
                        <span class="badge text-bg-dark"><?= e((string) $userCount) ?> <?= e(pluralize($userCount, 'user')) ?></span>
                    </div>
                    <div class="table-wrap">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existingUsers as $user): ?>
                                    <tr>
                                        <td><?= e($user['full_name']) ?></td>
                                        <td><?= e($user['email']) ?></td>
                                        <td><span class="badge <?= e(role_badge_class((string) $user['role'])) ?>"><?= e($user['role']) ?></span></td>
                                        <td><?= e(format_date((string) $user['created_at'])) ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-light" href="<?= e(base_url('auth/register.php?password_user_id=' . $user['user_id'] . '#password-panel')) ?>">
                                                Change password
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="shell-card" id="password-panel">
                    <div class="mb-4">
                        <p class="eyebrow mb-2">Account security</p>
                        <h2 class="section-title mb-2">Change password</h2>
                        <p class="section-subtitle"></p>
                    </div>

                    <?php if ($passwordErrors !== []): ?>
                        <div class="alert alert-danger">
                            <?= e(implode(' ', $passwordErrors)) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="stack-gap">
                        <input type="hidden" name="form_action" value="change_password">
                        <div>
                            <label class="form-label" for="password_user_id">User account</label>
                            <select class="form-select" id="password_user_id" name="password_user_id" required>
                                <?php foreach ($existingUsers as $user): ?>
                                    <option value="<?= e((string) $user['user_id']) ?>" <?= selected_if($selectedPasswordUserId, $user['user_id']) ?>>
                                        <?= e($user['full_name'] . ' - ' . $user['email']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($selectedPasswordUser): ?>
                                <div class="form-help">
                                    Updating password for <?= e($selectedPasswordUser['full_name']) ?>.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="form-label" for="new_password">New password</label>
                            <input class="form-control" id="new_password" name="new_password" type="password" minlength="6" required>
                        </div>
                        <div>
                            <label class="form-label" for="confirm_new_password">Confirm new password</label>
                            <input class="form-control" id="confirm_new_password" name="confirm_new_password" type="password" minlength="6" required>
                        </div>
                        <div class="form-help">Passwords must be at least 6 characters long.</div>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-shield-lock me-2"></i>Update Password
                        </button>
                    </form>
                </section>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require_once APP_ROOT . '/includes/footer.php'; ?>
