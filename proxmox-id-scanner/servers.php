<?php

declare(strict_types=1);

require __DIR__ . '/src/Auth.php';
require __DIR__ . '/src/Config.php';

Auth::requireLogin();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired, please try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        try {
            if ($action === 'add') {
                $name = trim((string)($_POST['name'] ?? ''));
                $host = trim((string)($_POST['host'] ?? ''));
                $port = (int)($_POST['port'] ?? 8006);
                $username = trim((string)($_POST['username'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                $verifySsl = !empty($_POST['verify_ssl']);

                if ($name === '' || $host === '' || $username === '' || $password === '') {
                    $error = 'Name, host, username, and password are all required for a new server.';
                } else {
                    Config::addServer($name, $host, $port, $username, $password, $verifySsl);
                    $success = "Added \"{$name}\".";
                }
            } elseif ($action === 'edit') {
                $index = (int)($_POST['index'] ?? -1);
                $name = trim((string)($_POST['name'] ?? ''));
                $host = trim((string)($_POST['host'] ?? ''));
                $port = (int)($_POST['port'] ?? 8006);
                $username = trim((string)($_POST['username'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                $verifySsl = !empty($_POST['verify_ssl']);

                if ($name === '' || $host === '' || $username === '') {
                    $error = 'Name, host, and username are required.';
                } else {
                    Config::updateServer($index, $name, $host, $port, $username, $password !== '' ? $password : null, $verifySsl);
                    $success = "Updated \"{$name}\".";
                }
            } elseif ($action === 'delete') {
                $index = (int)($_POST['index'] ?? -1);
                Config::deleteServer($index);
                $success = 'Server removed.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$servers = Config::loadServersForEdit();
$editIndex = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$editing = ($editIndex !== null && isset($servers[$editIndex])) ? $servers[$editIndex] : null;

$csrf = Auth::csrfToken();
$activePage = 'servers';

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
<title>Manage Servers &middot; Proxmox ID Scanner</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require __DIR__ . '/src/nav.php'; ?>

<main>
    <h1>Manage Servers</h1>
    <p class="subtitle">Add each Proxmox host or cluster you want scanned. If several nodes belong to the same cluster, add just one of them &mdash; its API already reports the whole cluster's VMs/CTs. Passwords are encrypted at rest and never shown again once saved.</p>

    <?php if ($error): ?>
        <p class="banner banner-error"><?= h($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="banner banner-success"><?= h($success) ?></p>
    <?php endif; ?>

    <section>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Host</th>
                    <th>Port</th>
                    <th>Username</th>
                    <th>Verify SSL</th>
                    <th>Password</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servers as $i => $s): ?>
                <tr>
                    <td><?= h($s['name']) ?></td>
                    <td><?= h($s['host']) ?></td>
                    <td><?= h($s['port']) ?></td>
                    <td><?= h($s['username']) ?></td>
                    <td><?= $s['verify_ssl'] ? 'Yes' : 'No' ?></td>
                    <td><?= $s['has_password'] ? '&bull;&bull;&bull;&bull;&bull;&bull;' : '<em>not set</em>' ?></td>
                    <td class="actions">
                        <a href="servers.php?edit=<?= h($i) ?>">Edit</a>
                        <form method="post" action="servers.php" onsubmit="return confirm('Remove this server?');">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="index" value="<?= h($i) ?>">
                            <button type="submit" class="link-button">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$servers): ?>
                <tr><td colspan="7">No servers configured yet &mdash; add one below.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2><?= $editing ? 'Edit server' : 'Add a server' ?></h2>
        <form method="post" action="servers.php" class="panel-form">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
            <?php if ($editing): ?>
                <input type="hidden" name="index" value="<?= h($editIndex) ?>">
            <?php endif; ?>

            <label>Name
                <input type="text" name="name" placeholder="e.g. Cluster A or Server B" value="<?= h($editing['name'] ?? '') ?>" required>
            </label>
            <label>Host / IP
                <input type="text" name="host" placeholder="10.0.0.10" value="<?= h($editing['host'] ?? '') ?>" required>
            </label>
            <label>Port
                <input type="number" name="port" value="<?= h($editing['port'] ?? 8006) ?>" required>
            </label>
            <label>Username
                <input type="text" name="username" placeholder="idsreader@pve" value="<?= h($editing['username'] ?? '') ?>" required>
            </label>
            <label>Password<?= $editing ? ' (leave blank to keep current)' : '' ?>
                <input type="password" name="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
            </label>
            <label class="checkbox">
                <input type="checkbox" name="verify_ssl" value="1" <?= !empty($editing['verify_ssl']) ? 'checked' : '' ?>>
                Verify SSL certificate (leave unchecked for Proxmox's default self-signed cert)
            </label>

            <div class="form-actions">
                <button type="submit"><?= $editing ? 'Save changes' : 'Add server' ?></button>
                <?php if ($editing): ?>
                    <a href="servers.php" class="button-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</main>
</body>
</html>
