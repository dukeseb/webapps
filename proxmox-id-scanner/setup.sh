#!/usr/bin/env bash
#
# One-time setup for Proxmox ID Scanner.
#
# Creates the /etc/proxmox-ids config directory (kept outside the web root
# on purpose, so nothing secret is ever servable over HTTP), generates your
# initial login, and sets permissions for your web server user.
#
# Run as root: sudo ./setup.sh

set -euo pipefail

CONFIG_DIR="/etc/proxmox-ids"
WEB_USER="${WEB_USER:-www-data}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Please run this as root (e.g. sudo ./setup.sh)." >&2
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "PHP CLI not found. Install PHP (8.1+) with the curl, json, and sodium extensions first." >&2
    exit 1
fi

LOADED_EXTENSIONS="$(php -m)"
for ext in curl json sodium; do
    if ! grep -qi "^${ext}\$" <<< "$LOADED_EXTENSIONS"; then
        echo "Warning: PHP extension '${ext}' does not appear to be loaded. The app needs it." >&2
    fi
done

if ! id "$WEB_USER" >/dev/null 2>&1; then
    echo "User '$WEB_USER' does not exist. Set WEB_USER=<your-webserver-user> and re-run, e.g.:" >&2
    echo "  WEB_USER=nginx sudo -E ./setup.sh" >&2
    exit 1
fi

echo "== Proxmox ID Scanner setup =="
echo "Web server user: $WEB_USER"
echo

mkdir -p "$CONFIG_DIR"
chown "root:$WEB_USER" "$CONFIG_DIR"
chmod 770 "$CONFIG_DIR"

if [ -f "$CONFIG_DIR/auth.php" ]; then
    echo "Found existing $CONFIG_DIR/auth.php -- leaving your login as-is."
else
    read -rp "Choose an app username [admin]: " APP_USER
    APP_USER="${APP_USER:-admin}"

    read -rsp "Choose an app password (leave blank to generate one): " APP_PASS
    echo
    if [ -z "$APP_PASS" ]; then
        APP_PASS="$(php -r 'echo bin2hex(random_bytes(9));')"
        echo "Generated password: $APP_PASS"
        echo "Save this now -- it will not be shown again. You can change it later from the app's Change Password page."
    fi

    PASS_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$APP_PASS")"

    cat > "$CONFIG_DIR/auth.php" <<PHP
<?php

/**
 * Login credentials for the Proxmox ID Scanner web app itself.
 * Managed via the "Change password" page.
 */

return [
    'username' => '$(printf '%s' "$APP_USER" | sed "s/'/\\\\'/g")',
    'password_hash' => '$(printf '%s' "$PASS_HASH" | sed "s/'/\\\\'/g")',
];
PHP

    chown "root:$WEB_USER" "$CONFIG_DIR/auth.php"
    chmod 660 "$CONFIG_DIR/auth.php"
    echo "Wrote $CONFIG_DIR/auth.php"
fi

if [ -f "$CONFIG_DIR/config.php" ]; then
    echo "Found existing $CONFIG_DIR/config.php -- leaving your server list as-is."
else
    cat > "$CONFIG_DIR/config.php" <<'PHP'
<?php

/**
 * Managed by the Proxmox ID Scanner's "Manage Servers" page.
 * Passwords are encrypted at rest -- edit via the web UI rather
 * than hand-editing this file.
 */

return [];
PHP

    chown "root:$WEB_USER" "$CONFIG_DIR/config.php"
    chmod 660 "$CONFIG_DIR/config.php"
    echo "Wrote $CONFIG_DIR/config.php (empty -- add servers from the app's Manage Servers page)"
fi

echo
echo "Done. Next steps:"
echo "  1. Point your web server's document root at this folder."
echo "  2. Make sure PHP files are executed (mod_php, php-fpm, etc)."
echo "  3. Restart/reload your web server."
echo "  4. Visit the site, log in, and add your Proxmox servers under 'Manage Servers'."
echo
echo "See apache/proxmox-ids.conf.example for a sample Apache vhost."
