<?php

/**
 * Login/session handling for the app's own login (not Proxmox credentials).
 */
final class Auth
{
    private const AUTH_FILE = '/etc/proxmox-ids/auth.php';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']),
            ]);
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        self::startSession();
        return !empty($_SESSION['authenticated']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    public static function username(): string
    {
        self::startSession();
        return (string)($_SESSION['username'] ?? '');
    }

    public static function load(): array
    {
        if (!is_file(self::AUTH_FILE)) {
            throw new RuntimeException('Missing auth config at ' . self::AUTH_FILE);
        }
        return require self::AUTH_FILE;
    }

    public static function attempt(string $username, string $password): bool
    {
        $auth = self::load();
        $ok = hash_equals((string)$auth['username'], $username)
            && password_verify($password, (string)$auth['password_hash']);

        if ($ok) {
            self::startSession();
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $auth['username'];
        }

        return $ok;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    public static function changeCredentials(string $newUsername, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $php = "<?php\n\n"
            . "/**\n"
            . " * Login credentials for the Proxmox ID Scanner web app itself.\n"
            . " * Managed via the \"Change password\" page.\n"
            . " */\n\n"
            . "return [\n"
            . "    'username' => " . var_export($newUsername, true) . ",\n"
            . "    'password_hash' => " . var_export($hash, true) . ",\n"
            . "];\n";

        $tmp = self::AUTH_FILE . '.tmp';
        if (file_put_contents($tmp, $php, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write new credentials (check /etc/proxmox-ids permissions)');
        }
        chmod($tmp, 0660);
        rename($tmp, self::AUTH_FILE);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(self::AUTH_FILE, true);
        }

        self::startSession();
        $_SESSION['username'] = $newUsername;
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        self::startSession();
        return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
    }
}
