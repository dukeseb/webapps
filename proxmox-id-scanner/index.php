<?php

declare(strict_types=1);

require __DIR__ . '/src/Auth.php';
require __DIR__ . '/src/Config.php';
require __DIR__ . '/src/ProxmoxClient.php';

Auth::requireLogin();

$servers = Config::loadServersForUse();

$entries = [];      // flat list of every VM/CT found
$serverErrors = []; // name => error message

foreach ($servers as $server) {
    $name = $server['name'] ?? $server['host'];
    try {
        $client = new ProxmoxClient(
            $server['host'],
            $server['port'] ?? 8006,
            $server['username'],
            $server['password'],
            $server['verify_ssl'] ?? false
        );
        $client->login();
        $resources = $client->getVmResources();

        foreach ($resources as $res) {
            $entries[] = [
                'vmid'   => (int)($res['vmid'] ?? 0),
                'name'   => $res['name'] ?? '',
                'type'   => $res['type'] ?? '?',    // qemu | lxc
                'status' => $res['status'] ?? '?',
                'node'   => $res['node'] ?? '?',
                'server' => $name,
            ];
        }
    } catch (Throwable $e) {
        $serverErrors[$name] = $e->getMessage();
    }
}

usort($entries, fn($a, $b) => $a['vmid'] <=> $b['vmid']);

// Flag IDs that appear under more than one distinct server name (independent
// clusters/hosts don't share a VMID namespace, so collisions are possible).
$idsByServer = [];
foreach ($entries as $e) {
    $idsByServer[$e['vmid']][$e['server']] = true;
}
$collisions = array_keys(array_filter($idsByServer, fn($s) => count($s) > 1));

// Suggest the next free ID (Proxmox's own default allocator starts at 100).
$usedIds = array_column($entries, 'vmid');
$nextFree = 100;
while (in_array($nextFree, $usedIds, true)) {
    $nextFree++;
}

$totalVms = count(array_filter($entries, fn($e) => $e['type'] === 'qemu'));
$totalCts = count(array_filter($entries, fn($e) => $e['type'] === 'lxc'));
$activePage = 'index';

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
<title>Proxmox ID Scanner</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php require __DIR__ . '/src/nav.php'; ?>

<header>
    <h1>Proxmox VM / LXC ID Scanner</h1>
    <p class="subtitle">Live view of every VMID in use across all configured Proxmox servers.</p>
</header>

<main>
    <?php if (!$servers): ?>
    <section class="banner banner-info">
        No servers configured yet. Go to <a href="servers.php">Manage Servers</a> to add one.
    </section>
    <?php endif; ?>

    <section class="summary">
        <div class="card"><span class="card-value"><?= h(count($entries)) ?></span><span class="card-label">Total IDs in use</span></div>
        <div class="card"><span class="card-value"><?= h($totalVms) ?></span><span class="card-label">QEMU VMs</span></div>
        <div class="card"><span class="card-value"><?= h($totalCts) ?></span><span class="card-label">LXC Containers</span></div>
        <div class="card"><span class="card-value"><?= h($nextFree) ?></span><span class="card-label">Next free ID</span></div>
    </section>

    <?php if ($serverErrors): ?>
    <section class="errors">
        <h2>Connection problems</h2>
        <ul>
            <?php foreach ($serverErrors as $name => $msg): ?>
                <li><strong><?= h($name) ?>:</strong> <?= h($msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if ($collisions): ?>
    <section class="errors warning">
        <h2>ID collisions across servers</h2>
        <p>These VMIDs exist on more than one independent server/cluster. That's fine as long as they stay on separate hosts, but it means the ID isn't globally unique &mdash; worth knowing before migrating anything.</p>
        <ul>
            <?php foreach ($collisions as $id): ?>
                <li><?= h($id) ?> &mdash; used on: <?= h(implode(', ', array_keys($idsByServer[$id]))) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section>
        <table>
            <thead>
                <tr>
                    <th>VMID</th>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Node</th>
                    <th>Server</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr class="<?= in_array($e['vmid'], $collisions, true) ? 'collision' : '' ?>">
                    <td><?= h($e['vmid']) ?></td>
                    <td><span class="badge badge-<?= h($e['type']) ?>"><?= h($e['type']) ?></span></td>
                    <td><?= h($e['name']) ?></td>
                    <td><span class="status status-<?= h($e['status']) ?>"><?= h($e['status']) ?></span></td>
                    <td><?= h($e['node']) ?></td>
                    <td><?= h($e['server']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$entries): ?>
                <tr><td colspan="6">No VMs or containers found (or every server failed to connect).</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</main>

<footer>
    <p>Fetched live from the Proxmox API on every page load. <a href="">Refresh</a></p>
</footer>
</body>
</html>
