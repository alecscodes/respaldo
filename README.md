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
  - [Deploy](#-deploy)
- [Features](#-features)
- [Usage](#-usage)
- [Configuration](#️-configuration)
  - [Telegram Notifications](#-telegram-notifications)
  - [Registration Control](#-registration-control)
  - [IP Banning](#-ip-banning)
  - [Backup Volume Configuration](#-backup-volume-configuration)
  - [CLI Script Usage](#-cli-script-usage)
  - [Automatic Updates](#-automatic-updates)
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

### 📦 Deploy

```bash
git clone https://github.com/alecscodes/respaldo.git
cd respaldo
./deploy.sh
```

The `deploy.sh` script will:

- Set up `.env` and prompt for `APP_URL`
- Ask you to choose between **Docker** or **Standard** deployment (first time only)
- Remember your choice for future deployments (automatically updates if already installed)

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
- 🔄 **Automatic updates** - updates run automatically every minute via scheduler
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

The `BACKUP_VOLUME` environment variable controls where backups and the database are stored:

- **When `BACKUP_VOLUME` is set:** Backups and SQLite database are stored in the specified directory
- **When `BACKUP_VOLUME` is not set:** Backups go to `./backups`, database to `database/database.sqlite`

This allows you to keep all application data (backups + database) in a single location, which is especially useful when using external storage or network-attached storage.

**Configure in `.env`:**

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_URL` | ✅ Yes | - | Full URL of your application |
| `APP_PORT` | ❌ No | `8000` | Application port (Docker only) |
| `BACKUP_VOLUME` | ❌ No | `./backups` | Backup storage path (when set, SQLite database is also stored here) |

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

### 🔄 Automatic Updates

Respaldo automatically checks for and applies updates every minute via the Laravel scheduler:

- **Autonomous updates**: The application checks for new commits from the Git repository every minute
- **Smart skipping**: Updates are skipped if no new commits are available
- **Docker support**: Commands run correctly in Docker environments via shell execution
- **Update process**: Automatically pulls changes, installs dependencies, builds assets, runs migrations, and optimizes cache

You can also manually trigger an update:

```bash
php artisan git:update
```

> 📋 Requires Git repository with configured remote.

---

## 🔧 Artisan Commands

Respaldo includes several helpful Artisan commands:

| Command | Description |
|---------|-------------|
| `php artisan git:update` | Manually trigger application update from Git repository (runs automatically every minute) |
| `php artisan ip:unban <ip>` | Unban a specific IP address |
| `php artisan ip:unban --all` | Unban all banned IP addresses |
| `php artisan backups:check-missed` | Check for missed backups and send alerts (runs automatically every hour) |
| `php artisan backups:apply-retention` | Manually apply backup retention policies for all apps |
| `php artisan backups:apply-retention --app=1` | Manually apply retention policy for a specific app |

---

## 🛠 Tech Stack

| Category | Technology |
|----------|-----------|
| **Backend** | Laravel 12 · PHP 8.4+ |
| **Frontend** | Vue 3 · Inertia v2 · Tailwind CSS v4 |
| **Database** | SQLite (MySQL/PostgreSQL supported) |
| **Deployment** | Docker · Standard Hosting |
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
