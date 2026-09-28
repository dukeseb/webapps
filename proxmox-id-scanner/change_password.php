<?php

declare(strict_types=1);

require __DIR__ . '/src/Auth.php';

Auth::requireLogin();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired, please try again.';
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $newUsername = trim((string)($_POST['new_username'] ?? ''));
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $auth = Auth::load();

        if (!password_verify($current, (string)$auth['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newUsername === '') {
            $error = 'Username cannot be empty.';
        } elseif (strlen($newPassword) < 4) {
            $error = 'New password must be at least 4 characters.';
        } elseif ($newPassword !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } else {
            try {
                Auth::changeCredentials($newUsername, $newPassword);
                $success = 'Credentials updated. Use the new username/password next time you log in.';
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$csrf = Auth::csrfToken();
$currentAuth = Auth::load();
$activePage = 'password';

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change Password &middot; Proxmox ID Scanner</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require __DIR__ . '/src/nav.php'; ?>

<main>
    <h1>Change Password</h1>
    <p class="subtitle">Update the username/password used to log into this app.</p>

    <?php if ($error): ?>
        <p class="banner banner-error"><?= h($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="banner banner-success"><?= h($success) ?></p>
    <?php endif; ?>

    <form method="post" action="change_password.php" class="panel-form">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <label>Current password
            <input type="password" name="current_password" autocomplete="current-password" required>
        </label>
        <label>New username
            <input type="text" name="new_username" value="<?= h($currentAuth['username']) ?>" autocomplete="username" required>
        </label>
        <label>New password
            <input type="password" name="new_password" autocomplete="new-password" required minlength="4">
        </label>
        <label>Confirm new password
            <input type="password" name="confirm_password" autocomplete="new-password" required minlength="4">
        </label>
        <button type="submit">Update credentials</button>
    </form>
</main>
</body>
</html>
