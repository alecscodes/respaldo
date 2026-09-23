# 🔄 Respaldo

> **Backup management application** built with Laravel & Vue  
> Manage multiple apps, track backups, and automate server backups with a secure CLI script.
---

## 🎥 Deploy Demo

<p align="center">
  <a href="https://streamable.com/wesm7b" target="_blank">
    <img
      src="https://cdn-cf-east.streamable.com/image/wesm7b.jpg?Expires=1763553201102&Key-Pair-Id=APKAIEYUVEN4EVB2OKEQ&Signature=N4Ayt0brk9YuL4Yt7O8kAwEwGcx9~1MF03V4KodunD7~9XfLOOo2xdPKeVf-4ow3b3R~OjnZ1rMf11coKuW6WrO-MiPqWqBPoTGJvOKxFPZBjrfAWRLf71TRT~2mbl~bKNo43XXQG5UiLqWTrbbQOpZ6~vMV~ooZ~uBLS1socL41iYqPytbxKFTyFh9hMsahSCBeM4ZTTyO125QzVoiooUIeAa0JR1Nhj6THlFjqzGJpqNONiNeVZKWOv9QVBIllkiscVrZYCWI6NxmSjSqGMHE7RYSuWv0ASr0fz1dqfIHBAe-DcODbcNqEJ0rUcnD9qDTwGjO6QiNfvmTdeGuk4A__"
      alt="Respaldo Deploy Demo"
      style="width:100%;max-width:900px;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.15);cursor:pointer;"
    >
  </a>
</p>

<p align="center">
  <em>Watch how to clone, configure, and deploy <b>Respaldo</b> in under 2 minutes.</em><br>
  <a href="https://streamable.com/wesm7b" target="_blank">▶️ Watch on Streamable</a>
</p>

---

## 📋 Table of Contents

- [Quick Start](#-quick-start)
  - [Docker](#-docker)
  - [Hosting](#-hosting)
  - [Restore on a new server](#️-restore-on-a-new-server)
- [Features](#-features)
- [Usage](#-usage)
- [Configuration](#️-configuration)
  - [Telegram Notifications](#-telegram-notifications)
  - [Registration Control](#-registration-control)
  - [IP Banning](#-ip-banning)
  - [Backup Volume Configuration](#-backup-volume-configuration)
  - [CLI Script Usage](#-cli-script-usage)
- [Artisan Commands](#-artisan-commands)
- [Tech Stack](#-tech-stack)
- [Development](#-development)
  - [Running Tests](#running-tests)
  - [Code Quality](#code-quality)
  - [Frontend Development](#frontend-development)
- [License](#-license)
- [Support](#-support)

---

## 🚀 Quick Start

### 🐳 Docker

**Install**

```bash
git clone https://github.com/alecscodes/respaldo.git && cd respaldo
cp .env.example .env
sed -i "s|^APP_KEY=$|APP_KEY=base64:$(openssl rand -base64 32)|" .env
docker compose build
docker compose run --rm app php artisan migrate --force
docker compose up -d --wait
docker compose exec app php artisan optimize
```

The app runs on port 80. To use another port, set `APP_PORT` in `.env`.

**Update**

```bash
git pull
docker compose build
docker compose run --rm app php artisan migrate --force
docker compose up -d --wait
docker compose exec app php artisan optimize
```

Your data (database and uploaded files) is kept in a Docker volume, so updates never delete it. Backup archives are saved on the server in `./backups` (change it with `BACKUP_VOLUME` in `.env`).

Update not showing up? Rebuild with `docker compose build --no-cache`.

### 🖥 Hosting

**Install**

1. Clone the project outside the public web folder (e.g. not inside `public_html` or `/var/www/html`) and set it up:

   ```bash
   git clone https://github.com/alecscodes/respaldo.git ~/respaldo && cd ~/respaldo
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && php artisan key:generate
   npm ci
   npm run build
   php artisan migrate --force
   php artisan optimize
   chmod -R 775 storage bootstrap/cache
   ```

2. Point your domain's document root to `~/respaldo/public` (cPanel: Domains; nginx: `root`; Apache: `DocumentRoot`).
3. Add this cron job (`crontab -e`, or Cron Jobs in cPanel):

   ```
   * * * * * cd ~/respaldo && php artisan schedule:run >> /dev/null 2>&1
   ```

**Update**

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan reload
```

Something broken after an update? Run `php artisan optimize:clear`, then `php artisan optimize`.

### ♻️ Restore on a new server

Every hour the database is copied to the backups folder as `database.sqlite`. If the server dies, connect the backup disk to a new server and:

1. Run the install steps up to `migrate`, but use the `APP_KEY` from the old server (keep it in your password manager; without it two-factor login stops working) and set `BACKUP_VOLUME` to the backup disk.
2. Copy the database back, then continue with `migrate`:

   ```bash
   # Docker
   docker compose run --rm app cp /app/backups/database.sqlite /app/storage/app/database.sqlite
   # Hosting (BACKUP_VOLUME=/mnt/backups)
   cp /mnt/backups/database.sqlite database/database.sqlite
   ```

## ✨ Features

- 🎯 **Multi-app management** with individual storage limits
- 📦 **Backup tracking** - view, download, and manage backups for each app
- ⏰ **Missed backup alerts** - receive notifications when expected backups are missing
- 🖥️ **CLI script** - download a secure, personalized CLI script for automated server backups
- 💾 **Storage management** - monitor usage with visual indicators and enforce limits
- 🚫 **Ignore file support** - CLI script respects `.respaldoignore` (takes precedence) or `.gitignore` files
- ✅ **Space validation** - double-check storage availability before backups
- 📢 **Telegram notifications** for instant alerts on backup failures, storage issues, and missed backups
- 🔐 **Two-factor authentication** for enhanced security
- 🌙 **Dark mode** for comfortable monitoring
- 📱 **Mobile-first responsive design** - manage backups from anywhere
- 🗑️ **Backup retention** - automatically delete old backups by age or count, runs automatically when space is insufficient

---

## 📖 Usage

Getting started with backups:

1. Navigate to **Apps → Create App**
2. Enter your app name and storage limit (GB)
3. (Optional) Set expected backup schedule to receive missed backup alerts - useful when running automated backups via cron
4. (Optional) Configure backup retention - set retention days and/or count to automatically clean up old backups
5. Download the CLI script from **Script → Download**
6. Run the script to create backups (interactively or via cron)

**Managing backups:**

- **View:** Click any app to see backup history
- **Download:** Click download button on any backup
- **Delete:** Remove old backups to free up space
- **Apply Retention:** Click "Apply Retention" button on app page to manually clean up old backups
- **Automatic cleanup:** Retention policies automatically run when space is insufficient before showing errors

---

## ⚙️ Configuration

### 📱 Telegram Notifications

Set up Telegram notifications to receive instant alerts:

1. Create a bot via [@BotFather](https://t.me/BotFather) on Telegram
2. Get your bot token and chat ID
3. Navigate to **Settings → Telegram** in the dashboard
4. Enter your bot credentials

> 📌 Only error notifications are sent for backup failures, storage issues, and disk space warnings.

### 👥 Registration Control

- Registration is **automatically enabled** for the first user
- Registration is **automatically disabled** after the first user is created
- Manual control available via **Settings → Registration**

### 🚫 IP Banning

Respaldo automatically bans IPs for suspicious activity:

**Automatic bans triggered by:**

- 2 failed login attempts
- Accessing non-existent routes (e.g., `/wp-admin`)
- Automatically detects and bans related IPs (client, forwarded, proxy, server)

**Unban commands:**

```bash
# Unban a specific IP
php artisan ip:unban 192.168.1.100

# Unban all IPs
php artisan ip:unban --all
```

### 💾 Backup Volume Configuration

The `BACKUP_VOLUME` environment variable controls where backup archives are stored:

- **Docker**: the host path is mounted at `/app/backups`; the SQLite database is separate, in the named volume `respaldo_storage`
- **Host (no Docker)**: backups use `BACKUP_VOLUME` from `.env` (defaults to `./backups`); database stays at `database/database.sqlite`

This allows you to keep backup data on external or network-attached storage while the application code stays in the project directory.

**Configure in `.env`:**

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_URL` | ✅ Yes | - | Full URL of your application |
| `APP_PORT` | ❌ No | `80` | Application port (Docker only) |
| `BACKUP_VOLUME` | ❌ No | `./backups` | Backup storage path on the host |

### 📢 CLI Script Usage

1. **Download the script** from the dashboard or directly from `/script/download`

   > ⚠️ **Important:** Ensure `APP_URL` is set correctly in `.env` before downloading—it's embedded in the script.

2. **Run the script:**

   **Interactive mode:**

   ```bash
   bash respaldo.sh
   # or
   chmod +x respaldo.sh && ./respaldo.sh
   ```

   **Interactive menu options:**
   - List apps
   - Create new app
   - Create backup (respects `.respaldoignore` or `.gitignore`)
   - Download backup

   **Quick backup mode** (for cron jobs):

   ```bash
   respaldo /path/to/your/project
   ```

3. **Make it global** (optional):

   **User-level:**

   ```bash
   mkdir -p ~/bin
   mv respaldo.sh ~/bin/respaldo
   chmod +x ~/bin/respaldo
   export PATH="$HOME/bin:$PATH"  # Add to ~/.bashrc or ~/.zshrc
   ```

   **System-wide:**

   ```bash
   sudo mv respaldo.sh /usr/local/bin/respaldo
   sudo chmod +x /usr/local/bin/respaldo
   ```

**Automated Backups with Cron:**

**Prerequisites:**

- Backup the directory interactively once to save app association
- Use full absolute paths in cron

**Example:**

```bash
crontab -e
# Add this line for daily backups at 2 AM
0 2 * * * /usr/local/bin/respaldo /path/to/your/project >> /var/log/respaldo.log 2>&1
```

**Ignore Files:**

The script uses `.respaldoignore` if it exists (ignoring `.gitignore`), otherwise falls back to `.gitignore` if present.

```bash
# .respaldoignore example
node_modules/
.env
*.log
logs/
temp/
```

Patterns work the same as `.gitignore`.

### 📋 Log Retention

Respaldo automatically cleans up old logs daily. Critical logs are kept for 365 days, while debug logs are kept for 7 days. Retention periods are configurable per log level via settings.

```bash
php artisan logs:cleanup              # Clean up old logs
php artisan logs:cleanup --dry-run    # Preview what would be deleted
```

---

## 🔧 Artisan Commands

Respaldo includes several helpful Artisan commands:

| Command | Description |
|---------|-------------|
| `php artisan ip:unban <ip>` | Unban a specific IP address |
| `php artisan ip:unban --all` | Unban all banned IP addresses |
| `php artisan backups:check-missed` | Check for missed backups and send alerts (runs automatically every hour) |
| `php artisan backups:apply-retention` | Manually apply backup retention policies for all apps |
| `php artisan backups:apply-retention --app=1` | Manually apply retention policy for a specific app |
| `php artisan logs:cleanup` | Clean up old logs based on configurable retention periods (runs automatically daily) |
| `php artisan logs:cleanup --dry-run` | Preview what logs would be deleted without actually deleting them |

---

## 🛠 Tech Stack

| Category | Technology |
|----------|-----------|
| **Backend** | Laravel 12 · PHP 8.4+ |
| **Frontend** | Vue 3 · Inertia v2 · Tailwind CSS v4 |
| **Database** | SQLite (MySQL/PostgreSQL supported) |
| **Deployment** | Docker · Hosting |
| **Testing** | Pest PHP v4 |
| **Code Quality** | Larastan (PHPStan) · Laravel Pint · ESLint · Prettier |

---

## 🧪 Development

For local development:

```bash
git clone https://github.com/alecscodes/respaldo.git
cd respaldo
composer install && npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite && php artisan migrate
npm run build && composer run dev
```

Visit `http://localhost:8000` to access the application.

---

### Running Tests

```bash
php artisan test          # Run all tests
```

### Code Quality

```bash
vendor/bin/pint           # Format code with Laravel Pint
composer run analyze      # Run static analysis (PHPStan)
npm run lint              # Lint and fix JavaScript/TypeScript/Vue code (ESLint)
npm run format            # Format frontend code (Prettier)
npm run format:check      # Check frontend code formatting (Prettier)
```

### Frontend Development

```bash
npm run dev              # Start Vite dev server with hot reload
npm run build            # Build for production
```

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](LICENSE).

---

## ⚠️ Disclaimer

Respaldo is provided **"as is"** without warranty of any kind. For critical services, always maintain multiple backup systems to ensure data safety.

---

## 💬 Support

Need help? Found a bug? Have a feature request?

- 🐛 [Report an issue](https://github.com/alecscodes/respaldo/issues)
- 💡 [Request a feature](https://github.com/alecscodes/respaldo/issues/new)

---

<div align="center">

**Made with ❤️ for reliable backup management**

</div>
