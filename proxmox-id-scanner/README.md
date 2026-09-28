# Proxmox ID Scanner

See which VM and LXC IDs are already in use across **all** your Proxmox
servers and clusters — standalone hosts and clusters alike — in one
dashboard, so you're not left guessing (or colliding) the next time you spin
something up.

## Features

- Aggregates VMs/containers from every configured Proxmox host or cluster
  into one sorted table (ID, type, name, status, node, source server)
- Works with standalone hosts *and* clusters (add one node per cluster — its
  API already reports the whole cluster)
- Flags VMIDs that exist on more than one independent server/cluster, since
  they're not automatically unique across separate hosts
- Suggests the next free ID
- Add/edit/remove servers entirely from the web UI — no config files to
  hand-edit
- Login-gated, with Proxmox credentials encrypted at rest

## Requirements

- PHP 8.1+ with the `curl`, `json`, and `sodium` extensions
- A web server that executes PHP (Apache + mod_php is what's tested; plain
  PHP-FPM behind nginx should work the same way, since there's no
  Apache-specific behavior in the app itself)
- Network access from this server to each Proxmox host's API port (8006 by
  default)
- A dedicated low-privilege Proxmox account is strongly recommended — see
  [Adding servers](#adding-servers) below

## Installation

This app lives in the `proxmox-id-scanner` folder of the [webapps](https://github.com/dukeseb/webapps)
repo. Either clone the whole repo and point your web server's document root
at the subfolder, or sparse-checkout just this app:

```bash
git clone https://github.com/dukeseb/webapps.git
cd webapps/proxmox-id-scanner
sudo ./setup.sh
```

`setup.sh` creates `/etc/proxmox-ids` (kept outside the web root on purpose —
see [Security](#security)), and walks you through choosing your login
username/password. If your web server doesn't run as `www-data`, set
`WEB_USER` first:

```bash
WEB_USER=nginx sudo -E ./setup.sh
```

Then point your web server's document root at this folder and reload it. A
sample Apache vhost is in [`apache/proxmox-ids.conf.example`](apache/proxmox-ids.conf.example).

Visit the site, log in with the credentials `setup.sh` gave you, and go to
**Manage Servers** to add your first Proxmox host or cluster.

## Adding servers

Rather than a root account, create a read-only account on each Proxmox host
or cluster:

```bash
pveum user add idsreader@pve
pveum passwd idsreader@pve
pveum aclmod / -user idsreader@pve -role PVEAuditor
```

Then add it via **Manage Servers** in the app: name, host/IP, port (8006 by
default), the username above, and its password. For a cluster, add just one
member node — its `cluster/resources` API call already returns every node's
VMs/containers.

Proxmox typically uses a self-signed certificate; leave "Verify SSL" unchecked
unless you've installed a real certificate on that host.

## Security

- The app's own login password is stored as a one-way bcrypt hash.
- Proxmox server passwords are encrypted at rest (libsodium `secretbox`) with
  a key generated on first use, since — unlike the app's login password —
  they have to be reversible so the app can present them to each Proxmox
  API on every page load.
- All of this lives in `/etc/proxmox-ids`, outside the web root, so none of
  it is ever servable over HTTP even if the web server were misconfigured.
- There's no built-in HTTPS. If you're exposing this beyond your own LAN,
  put it behind a reverse proxy with TLS (or Apache's `mod_ssl` /
  Let's Encrypt) rather than serving the login page over plain HTTP.

## Changing your login

Use the **Change Password** page once logged in — no need to touch any
files on disk.

## How it works

Each configured server is just a name, host, port, username, and encrypted
password. On each page load, the app logs into every configured Proxmox API
(`/access/ticket`) and calls `/cluster/resources?type=vm`, which returns every
VM/CT that host knows about — whether it's standalone or part of a cluster.
Results are merged, sorted by VMID, and cross-checked for collisions between
independent servers.

## License

MIT — see [LICENSE](LICENSE).
