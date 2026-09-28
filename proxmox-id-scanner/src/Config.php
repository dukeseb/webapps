<?php

require_once __DIR__ . '/Crypto.php';

/**
 * Reads/writes the list of Proxmox servers at /etc/proxmox-ids/config.php.
 * Passwords are stored encrypted (see Crypto) and only decrypted in memory
 * right before an API call.
 */
final class Config
{
    private const SERVERS_FILE = '/etc/proxmox-ids/config.php';

    /** @return array<int, array<string, mixed>> raw entries, password still encrypted */
    private static function loadRaw(): array
    {
        if (!is_file(self::SERVERS_FILE)) {
            return [];
        }
        $servers = require self::SERVERS_FILE;
        return is_array($servers) ? $servers : [];
    }

    /**
     * Servers ready to use against the Proxmox API (password decrypted).
     * @return array<int, array<string, mixed>>
     */
    public static function loadServersForUse(): array
    {
        $servers = self::loadRaw();
        foreach ($servers as &$s) {
            $s['password'] = isset($s['password_enc']) ? Crypto::decrypt($s['password_enc']) : '';
        }
        unset($s);
        return $servers;
    }

    /**
     * Servers for display/editing -- password is never exposed, only
     * whether one is set.
     * @return array<int, array<string, mixed>>
     */
    public static function loadServersForEdit(): array
    {
        $servers = self::loadRaw();
        foreach ($servers as &$s) {
            $s['has_password'] = !empty($s['password_enc']);
            unset($s['password_enc']);
        }
        unset($s);
        return $servers;
    }

    public static function addServer(string $name, string $host, int $port, string $username, string $password, bool $verifySsl): void
    {
        $servers = self::loadRaw();
        $servers[] = [
            'name'         => $name,
            'host'         => $host,
            'port'         => $port,
            'username'     => $username,
            'password_enc' => Crypto::encrypt($password),
            'verify_ssl'   => $verifySsl,
        ];
        self::writeRaw($servers);
    }

    /**
     * @param string|null $password Pass null/'' to keep the existing password unchanged.
     */
    public static function updateServer(int $index, string $name, string $host, int $port, string $username, ?string $password, bool $verifySsl): void
    {
        $servers = self::loadRaw();
        if (!isset($servers[$index])) {
            throw new RuntimeException('Server not found');
        }

        $passwordEnc = ($password !== null && $password !== '')
            ? Crypto::encrypt($password)
            : ($servers[$index]['password_enc'] ?? Crypto::encrypt(''));

        $servers[$index] = [
            'name'         => $name,
            'host'         => $host,
            'port'         => $port,
            'username'     => $username,
            'password_enc' => $passwordEnc,
            'verify_ssl'   => $verifySsl,
        ];
        self::writeRaw($servers);
    }

    public static function deleteServer(int $index): void
    {
        $servers = self::loadRaw();
        if (!isset($servers[$index])) {
            throw new RuntimeException('Server not found');
        }
        unset($servers[$index]);
        self::writeRaw(array_values($servers));
    }

    /** @param array<int, array<string, mixed>> $servers Entries already containing password_enc. */
    private static function writeRaw(array $servers): void
    {
        $export = var_export($servers, true);
        $php = "<?php\n\n"
            . "/**\n"
            . " * Managed by the Proxmox ID Scanner's \"Manage Servers\" page.\n"
            . " * Passwords are encrypted at rest -- edit via the web UI rather\n"
            . " * than hand-editing this file.\n"
            . " */\n\n"
            . "return {$export};\n";

        $tmp = self::SERVERS_FILE . '.tmp';
        if (file_put_contents($tmp, $php, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write server config (check /etc/proxmox-ids permissions)');
        }
        chmod($tmp, 0660);
        rename($tmp, self::SERVERS_FILE);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(self::SERVERS_FILE, true);
        }
    }
}
