<?php

/**
 * Symmetric encryption for Proxmox server passwords at rest.
 *
 * These credentials must be sent back to each Proxmox API on every page
 * load, so (unlike the app's own login password) they can't be one-way
 * hashed -- they need to be reversible. Instead they're encrypted with a
 * key that lives only on this server, outside the web root, alongside the
 * config file.
 */
final class Crypto
{
    private const KEY_FILE = '/etc/proxmox-ids/secret.key';

    private static function key(): string
    {
        if (!is_file(self::KEY_FILE)) {
            $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $old = umask(0177);
            file_put_contents(self::KEY_FILE, base64_encode($key), LOCK_EX);
            umask($old);
            chmod(self::KEY_FILE, 0640);
        }

        $raw = base64_decode(trim((string)file_get_contents(self::KEY_FILE)));
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('Secret key file is corrupt: ' . self::KEY_FILE);
        }

        return $raw;
    }

    public static function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, self::key());

        return base64_encode($nonce . $cipher);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded);
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, self::key());
        if ($plain === false) {
            throw new RuntimeException('Failed to decrypt stored credential (corrupt data or wrong key)');
        }

        return $plain;
    }
}
