<?php

declare(strict_types=1);

require __DIR__ . '/src/Auth.php';

Auth::startSession();
if (Auth::isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired, please try again.';
    } else {
        $username = (string)($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (Auth::attempt($username, $password)) {
            header('Location: /index.php');
            exit;
        }

        sleep(1); // slow down brute-force guessing
        $error = 'Invalid username or password.';
    }
}

$csrf = Auth::csrfToken();

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
<title>Log in &middot; Proxmox ID Scanner</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<main class="auth-box">
    <h1>Proxmox ID Scanner</h1>
    <?php if ($error): ?>
        <p class="banner banner-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form method="post" action="login.php">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <label>Username
            <input type="text" name="username" autocomplete="username" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Log in</button>
    </form>
</main>
</body>
</html>
