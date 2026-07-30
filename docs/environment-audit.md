# Environment Audit — GPS Catering Tracker

Read-only VPS readiness audit for a new Laravel 13 based GPS catering delivery
tracking project. No system packages, services, firewall, DNS, database or web
server configuration were modified during this audit.

## 1. Audit Timestamp

- Audit performed: 2026-07-30 02:44–02:46 UTC
- System clock: synchronized (NTP active)
- Timezone: Etc/UTC (UTC, +0000)

## 2. VPS Operating System

- Distribution: Ubuntu 24.04.4 LTS (Noble Numbat)
- Version ID: 24.04
- Kernel: Linux 6.17.0-1017-aws
- Architecture: x86-64 (x86_64)
- Virtualization: amazon (Amazon EC2)
- Hardware model: m7i-flex.large
- Hostname: ip-172-31-38-122
- systemd present: yes (PID 1 = systemd; systemctl available)

## 3. CPU, Memory and Disk

- CPU cores (nproc): 2
- Total memory: 7.6 GiB
- Available memory: ~4.4 GiB (used ~3.2 GiB at audit time)
- Swap: 0 B (no swap configured)
- Root filesystem (/): 29 GB total, 16 GB used, 13 GB available (54% used)
- /boot: 881 MB (23% used)
- /boot/efi: 105 MB (6% used)
- Primary disk: nvme0n1, 30 GB total

Observation: No swap is configured. For a single-delivery prototype this is
acceptable, but adding swap is advisable before running heavy Composer/npm
builds concurrently with MySQL.

## 4. Current User and Permissions

- Current user: ubuntu (uid=1000, gid=1000)
- Groups: ubuntu, adm, cdrom, sudo, dip, lxd, docker
- Root access: NOT assumed. User is in the `sudo` group (sudo available on
  demand) and in the `docker` group.
- Home directory: /home/ubuntu (owned by ubuntu)

## 5. Web Server

- Nginx: NOT installed (no `nginx` binary; service not-found/inactive)
- Apache (apache2): NOT installed (service inactive, no binary)
- httpd: not detected
- Caddy: inactive / not detected
- lighttpd: inactive / not detected

Result: No web server is currently installed or serving. Ports 80 and 443 are
NOT listening. The existing Laravel project on this host is presumably served
via `php artisan serve` (or not currently exposed), not through Nginx/Apache.

## 6. PHP and Extensions

- PHP CLI: installed — PHP 8.3.32 (cli, NTS), Zend Engine v4.3.32, OPcache 8.3.32
- PHP CLI binary: /usr/bin/php
- PHP-FPM: /usr/sbin/php-fpm8.3 (php8.3-fpm service: active, enabled)
- Installed PHP versions (/etc/php): 8.3 only
- Active CLI php.ini: /etc/php/8.3/cli/php.ini
- Active CLI conf.d: /etc/php/8.3/cli/conf.d
- FPM config tree: /etc/php/8.3/fpm (version 8.3)

Required Laravel extensions — presence check (from `php -m`):

| Extension   | Status  |
|-------------|---------|
| BCMath      | Present |
| Ctype       | Present |
| cURL        | Present |
| DOM/XML     | Present (dom, xml, SimpleXML, xmlreader, xmlwriter, xsl) |
| Fileinfo    | Present |
| Filter      | Present |
| Hash        | Present |
| Mbstring    | Present |
| OpenSSL     | Present |
| PCRE        | Present |
| PDO         | Present |
| PDO MySQL   | Present (pdo_mysql) |
| Session     | Present |
| Tokenizer   | Present |
| XML         | Present |
| JSON        | Present (built-in; json_encode confirmed) |
| Intl        | Present |
| Zip         | Present |

All Laravel-required extensions are present on PHP 8.3. Extra useful modules
present: gd, sodium, mysqli, sqlite3, pcntl, posix, opcache, exif, ffi.

IMPORTANT: The approved stack targets PHP 8.5. The installed CLI/FPM is PHP
8.3.32. See section 17 (Compatibility concerns).

## 7. Composer

- Installed: yes
- Version: Composer 2.10.2 (2026-07-01)
- Executable path: /usr/local/bin/composer
- Runs without root: yes (invoked as user `ubuntu`)
- No global update was run (per constraints).

## 8. Node.js Tooling

- Node.js: installed — v20.20.2 (/usr/bin/node)
- npm: installed — 10.8.2 (/usr/bin/npm)
- pnpm: NOT installed
- Yarn: NOT installed

Node 20 LTS is compatible with Vite and modern Laravel frontend tooling.

## 9. MySQL or MariaDB

- MySQL client: installed — mysql Ver 8.0.46-0ubuntu0.24.04.3
- MySQL server (mysqld): installed — Ver 8.0.46-0ubuntu0.24.04.3
- MariaDB: NOT installed
- Service status: mysql = active; mariadb = inactive
- Listening interface/port: 127.0.0.1:3306 (localhost only) and 127.0.0.1:33060
  (MySQL X protocol). Not exposed on public interfaces.
- Current-user safe access: The `ubuntu` OS user CANNOT list databases without
  credentials — `SELECT VERSION()` and `SHOW DATABASES` returned
  `ERROR 1045 (28000): Access denied ... (using password: NO)`. This is a
  secure default. No credentials were supplied and no grants were inspected.

IMPORTANT: Installed server is MySQL 8.0.46, NOT the approved MySQL 8.4 LTS.
See section 17.

## 10. Git

- Git: installed — git version 2.43.0
- Current working directory (/home/ubuntu) is NOT inside a Git repository.
- Target project directory is new and not yet a repo (not initialized per task).
- Global user.name: set; global user.email: set (left unchanged).
- Note: A separate Git repository exists at /home/ubuntu/GPS-server with remote
  `origin` = github.com/haikalputra-dev/GPS-Server.git (branch: main). Any
  embedded credentials in remotes were redacted; none were present in the URL.

## 11. Current Listening Ports

Redacted process names shown as REDACTED where applicable. All listeners are
bound to localhost/loopback except SSH.

- 0.0.0.0:22 and [::]:22 — SSH (publicly reachable)
- 127.0.0.1:3306 — MySQL
- 127.0.0.1:33060 — MySQL X protocol
- 127.0.0.1:8191, 127.0.0.1:40000, 127.0.0.1:40080 — local services (loopback)
- 127.0.0.1:46691, 127.0.0.1:37169, 127.0.0.1:52315, 127.0.0.1:44077 — local
  tooling/agent processes (loopback only)
- 127.0.0.54:53 and 127.0.0.53 — systemd-resolved DNS stub
- UDP 323 — chrony (NTP)
- Ports 80 and 443: NOT listening

## 12. Domain and DNS Observations

- Public IPv4 (EC2 metadata): metadata endpoint did not return a value during
  the audit (IMDS lookup returned no address / unavailable). The instance is an
  Amazon EC2 host (private IP 172.31.38.122 on enp39s0).
- No domain is configured in any web server (no web server installed).
- Application will need to be reached by public IP until a domain is set up.
- No DNS changes were made. No firewall/security-group changes were made.
- Note: AWS security group rules are external to the OS and were not inspected
  or modified; opening HTTP/HTTPS publicly will require a security-group change
  outside this host.

## 13. Process Managers and Scheduled Jobs

- Cron: installed (crontab present) and service active.
- Supervisor: NOT installed / inactive.
- Redis: NOT installed / inactive (no redis-server binary).
- systemd: present and managing services.
- Docker: installed — Docker 29.6.2, service active; user is in `docker` group.
- No application queue services from another project were detected running as
  systemd units for this project. No services were modified.

## 14. Existing Application Conflict Check

- The target directory /home/ubuntu/gps-catering-tracker did NOT exist prior to
  this audit and was safely created empty (only docs/environment-audit.md added).
- A SEPARATE existing Laravel application was found at /home/ubuntu/GPS-server:
  - composer.json: name `laravel/laravel`, requires `laravel/framework ^13.8`,
    `php ^8.3`, `laravel/tinker ^3.0`; dev deps include phpunit ^12.
  - Contains full Laravel skeleton (app/, config/, database/, public/, routes/,
    vendor/, .env, artisan) and its own Git repo + docs/.
  - This app was NOT modified, read into (beyond composer.json + directory
    listing), or overwritten. Its .env was NOT opened.
- No conflict: the new project lives in a distinct directory. There is no file
  overwrite risk.

## 15. Laravel 13 Readiness

Overall classification: PARTIALLY READY

The server can run a Laravel 13 application today (an existing Laravel 13.x app
already resides on the host). However, the approved provisional stack specifies
PHP 8.5 and MySQL 8.4 LTS, while the host provides PHP 8.3.32 and MySQL 8.0.46.
Laravel 13 itself runs fine on PHP 8.3, so the gap is stack-target alignment
plus a missing production web server, not a hard blocker.

Per-component compatibility (only claims that were verified):

| Requirement            | Status                                              |
|------------------------|-----------------------------------------------------|
| PHP 8.5                | NOT met — PHP 8.3.32 installed (8.5 not present)    |
| Laravel 13             | Compatible — framework runs on PHP 8.3; existing 13.x app present |
| MySQL 8.4 LTS          | NOT met — MySQL 8.0.46 installed (8.4 not present)  |
| Composer               | Compatible — 2.10.2, runs as non-root               |
| Node.js / Vite         | Compatible — Node 20.20.2 + npm 10.8.2              |
| Nginx or Apache deploy | NOT met — no web server installed (artisan serve only) |
| HTTP access by IP      | NOT verified — ports 80/443 not listening; SG rules external and not inspected |

## 16. Missing Prerequisites

Blocking / required before production-style deployment:

1. PHP 8.5 — not installed (only 8.3.32). Required only if the 8.5 target is
   firm; Laravel 13 otherwise runs on 8.3.
2. MySQL 8.4 LTS — not installed (only 8.0.46). Required only if 8.4 target is
   firm.
3. Web server (Nginx or Apache) — none installed. Needed for a real deployment
   (dev can use `php artisan serve`).
4. Database + database user for this project — not created (out of scope here).
5. A `.env` and app key — not created (Laravel not initialized in this packet).

Not blocking but noted:

- No swap configured.
- Redis not installed (only needed if queue/cache driver = redis; database or
  file drivers work without it).
- Supervisor not installed (needed later for queue workers in production).
- No domain configured; IP-based access requires an AWS security-group rule for
  ports 80/443 (external to this host).

## 17. Compatibility Concerns

1. PHP version mismatch: target 8.5 vs installed 8.3.32. Laravel 13 supports
   PHP 8.3, so the app will run, but if 8.5-specific features are required, PHP
   8.5 must be installed (e.g., via ppa:ondrej/php). This is a package
   installation and was intentionally NOT performed.
2. MySQL version mismatch: target 8.4 LTS vs installed 8.0.46. Schemas and
   Laravel migrations are broadly compatible, but upgrading to 8.4 LTS is a
   package/service change and was NOT performed.
3. No production web server: deployment behind Nginx + PHP-FPM (recommended) is
   not yet possible without installing Nginx.
4. Shared host with an existing Laravel app (GPS-server): resource contention on
   a 2-core / 7.6 GiB instance with no swap is possible if both apps run queues
   and Vite builds simultaneously.
5. MySQL access requires credentials (secure). Provisioning a dedicated DB user
   for this project is a future step needing appropriate privileges.

## 18. Recommended Installation Plan

Proposed order (all require explicit approval; NONE executed in this packet):

1. Decide whether PHP 8.5 and MySQL 8.4 targets are firm. If Laravel 13 on the
   existing PHP 8.3 / MySQL 8.0 is acceptable for the prototype, no version
   upgrades are needed to start.
2. If PHP 8.5 required: add ppa:ondrej/php, install php8.5 + matching FPM and
   the extensions listed in section 6.
3. If MySQL 8.4 required: plan an in-place upgrade or a fresh 8.4 install with a
   data migration strategy (back up first).
4. Install and configure Nginx with a server block pointing to
   gps-catering-tracker/public, wired to PHP-FPM.
5. Create a dedicated MySQL database and least-privilege user for this project.
6. Initialize Laravel 13 in gps-catering-tracker (composer create-project or
   fresh skeleton), configure .env, run key:generate and migrations.
7. Add frontend deps (Vite build), Leaflet 1.9.4, OSM tiles.
8. Optionally install Redis + Supervisor for queues/telemetry ingestion.
9. Add swap (e.g., 2 GiB) to protect against OOM during builds.
10. Open ports 80/443 in the AWS security group; later add a domain + TLS.

## 19. Commands Executed

All commands were read-only (inspection) except the final safe `mkdir`.

- pwd; whoami; id; uname -a
- cat /etc/os-release; hostnamectl; timedatectl; date
- nproc; free -h; df -h; lsblk; ps -p 1 -o comm=; which systemctl
- which nginx / apache2 / httpd / caddy / lighttpd; nginx -v; apache2 -v
- systemctl is-active/is-enabled nginx apache2 caddy lighttpd
- which php; php -v; php --ini; php -m; php -r json check
- which php-fpm; ls /usr/sbin/php-fpm*; ls /etc/php/
- systemctl is-active/is-enabled php8.3-fpm
- which composer; composer --version
- which node/npm/pnpm/yarn; node --version; npm --version
- which git; git --version; git status; git remote -v; git config user checks
- which mysql/mysqld/mariadb; mysql --version; mysqld --version
- systemctl is-active mysql mariadb
- mysql --protocol=socket -e "SELECT VERSION();" (access denied — expected)
- mysql --protocol=socket -e "SHOW DATABASES;" (access denied — expected)
- ss -lntup (process names redacted); ss -lnt grep 80/443
- which crontab; systemctl is-active cron
- which supervisord; systemctl is-active supervisor
- which redis-server; systemctl is-active redis-server redis
- which docker; docker --version; systemctl is-active docker
- curl IMDS public-ipv4 (no value returned)
- ls -ld /var/www /home/ubuntu/apps /home/ubuntu/projects; ls -la /home/ubuntu
- ls -la /home/ubuntu/GPS-server; git -C GPS-server remote -v/branch
- read /home/ubuntu/GPS-server/composer.json
- mkdir -p /home/ubuntu/gps-catering-tracker/docs (only write action)

## 20. Redacted Relevant Outputs

- OS: Ubuntu 24.04.4 LTS, kernel 6.17.0-1017-aws, x86_64, EC2 m7i-flex.large.
- CPU/mem/disk: 2 cores, 7.6 GiB RAM (no swap), 29 GB root (13 GB free).
- PHP: 8.3.32 CLI + php8.3-fpm (active). All required extensions present.
- Composer: 2.10.2 at /usr/local/bin/composer.
- Node 20.20.2 / npm 10.8.2.
- MySQL server + client 8.0.46, active, bound to 127.0.0.1:3306 (+33060).
  DB listing denied for OS user without credentials (secure default).
- Git 2.43.0; existing repo at GPS-server -> github.com/haikalputra-dev/GPS-Server.git.
- Listening ports: SSH 0.0.0.0:22; all others loopback; 80/443 not listening.
- ss process names redacted; no secrets, passwords, keys, or .env values printed.
- Git remote URLs checked for embedded credentials — none present; redaction
  pattern applied regardless.

## 21. Files Created

- /home/ubuntu/gps-catering-tracker/ (new empty project directory)
- /home/ubuntu/gps-catering-tracker/docs/ (directory)
- /home/ubuntu/gps-catering-tracker/docs/environment-audit.md (this report)

No Laravel, Composer, npm, or Git initialization was performed.

## 22. Statement of Non-Modification

No system configuration was changed during this audit. No packages were
installed, upgraded, or removed. No services were started, stopped, enabled, or
reconfigured. Firewall, DNS, SSH, web server, PHP-FPM, MySQL, and security-group
settings were left untouched. No database or database user was created. No
existing application (including /home/ubuntu/GPS-server) was modified or
overwritten. The only write action was creating the new empty project directory
and this report file.
