<?php
/**
 * @var string $activePage
 * Expects an h() helper (htmlspecialchars wrapper) to already be defined
 * by whichever page includes this partial.
 */
?>
<nav class="topbar">
    <div class="topbar-brand">Proxmox ID Scanner</div>
    <div class="topbar-links">
        <a href="index.php" class="<?= ($activePage ?? '') === 'index' ? 'active' : '' ?>">Dashboard</a>
        <a href="servers.php" class="<?= ($activePage ?? '') === 'servers' ? 'active' : '' ?>">Manage Servers</a>
        <a href="change_password.php" class="<?= ($activePage ?? '') === 'password' ? 'active' : '' ?>">Change Password</a>
        <span class="topbar-user">Logged in as <strong><?= h(Auth::username()) ?></strong></span>
        <a href="logout.php" class="logout">Log out</a>
    </div>
</nav>
