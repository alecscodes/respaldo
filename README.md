# 🔄 Respaldo

> **Backup management application** built with Laravel & Vue.js  
> Manage multiple apps, track backups, and automate server backups with a secure CLI script.

---

## 📋 Table of Contents

- [Quick Start](#-quick-start)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Configuration](#️-configuration)
- [Usage](#-usage)
- [Development](#-development)
- [Artisan Commands](#-artisan-commands)
- [License](#-license)
- [Support](#-support)

---

## 🚀 Quick Start

### 🚀 Deploy

```bash
git clone https://github.com/alecscodes/respaldo.git
cd respaldo
./deploy.sh
```

The `deploy.sh` script will:

- Set up `.env` and prompt for `APP_URL`
- Ask you to choose between **Docker** or **Standard** deployment (first time only)
- Remember your choice for future deployments
- Handle all setup automatically

**Docker:** Uses `docker compose up -d --build --remove-orphans` (scheduler runs in container)  
**Standard:** Manual setup with automatic configuration

**Configure in `.env`:**

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_URL` | ✅ Yes | - | Full URL of your application |
| `APP_PORT` | ❌ No | `8000` | Application port |
| `BACKUP_VOLUME` | ❌ No | `./backups` | Backup storage path |

> 💡 The script handles both fresh installs and updates. Safe to run multiple times.

### 💻 Local Development

For local development without Docker:

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

## ✨ Features

- 🎯 **Multi-app management** with individual storage limits
- 📦 **Backup tracking** - view, download, and manage backups for each app
- 🖥️ **CLI script** - download a secure, personalized CLI script for automated server backups
- 💾 **Storage management** - monitor usage with visual indicators and enforce limits
- 🚫 **Gitignore support** - CLI script respects `.gitignore` and `.respaldoignore` files
- ✅ **Space validation** - double-check storage availability before backups
- 📢 **Telegram notifications** for instant alerts on backup failures and storage issues
- 🔐 **Two-factor authentication** for enhanced security
- 🌙 **Dark mode** for comfortable monitoring
- 📱 **Mobile-first responsive design** - manage backups from anywhere
- 🔄 **Auto-updates** - update directly from the web interface

---

## 🛠 Tech Stack

| Category | Technologies |
|----------|-------------|
| **Backend** | Laravel 12 · PHP 8.4+ |
| **Frontend** | Vue 3 · Inertia.js v2 · Tailwind CSS v4 |
| **Database** | SQLite (MySQL/PostgreSQL supported) |
| **Testing** | Pest PHP v4 |
| **Deploy** | Docker & Docker Compose |

---

## ⚙️ Configuration

### 👥 Registration Control

- Registration is **automatically enabled** for the first user
- Registration is **automatically disabled** after the first user is created
- Manual control available via **Settings → Registration**

### 📢 CLI Script Usage

1. **Download the script** from the dashboard at `/script/download`

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
   - Create backup (respects `.gitignore`/`.respaldoignore`)
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

### ⏰ Automated Backups with Cron

**Prerequisites:**

- Backup the directory interactively once to save app association
- Use full absolute paths in cron

**Example:**

```bash
crontab -e
# Add this line for daily backups at 2 AM
0 2 * * * /usr/local/bin/respaldo /path/to/your/project >> /var/log/respaldo.log 2>&1
```

### 📁 Ignore Files

The script supports `.respaldoignore` (takes precedence) or `.gitignore`:

```bash
# .respaldoignore example
node_modules/
.env
*.log
logs/
temp/
```

Patterns work the same as `.gitignore`.

### 📢 Telegram Notifications

Set up Telegram notifications to receive instant alerts:

1. Create a bot via [@BotFather](https://t.me/BotFather) on Telegram
2. Get your bot token and chat ID
3. Navigate to **Settings → Telegram** in the dashboard
4. Enter your bot credentials

> 📌 Only error notifications are sent (no success spam).

### 🔒 IP Banning

Respaldo automatically bans IPs for suspicious activity:

**Automatic bans triggered by:**

- 2 failed login attempts
- Accessing non-existent routes (e.g., `/wp-admin`)
- Detects and bans related IPs (client, forwarded, proxy, server)

**Unban commands:**

```bash
# Unban a specific IP
php artisan ip:unban 192.168.1.100

# Unban all IPs
php artisan ip:unban --all
```

### 🔄 Updates

Update Respaldo directly from the dashboard notification or via command line:

```bash
php artisan git:update
```

> 📋 Requires Git repository with configured remote.

---

## 📖 Usage

Getting started with backups is simple:

1. Navigate to **Apps → Create App**
2. Enter your app name and storage limit (GB)
3. Download the CLI script from **Script → Download**
4. Use the script to create backups interactively or via cron

**Managing backups:**

- **View:** Click any app to see backup history
- **Download:** Click download button on any backup
- **Delete:** Remove old backups to free up space

---

## 🧪 Development

### Running Tests

```bash
php artisan test          # Run all tests
```

### Code Quality

```bash
vendor/bin/pint           # Format code with Laravel Pint
composer run analyze      # Run static analysis (PHPStan)
```

### Frontend Development

```bash
npm run dev              # Start Vite dev server with hot reload
npm run build            # Build for production
```

---

## 🔧 Artisan Commands

Respaldo includes several helpful Artisan commands:

| Command | Description |
|---------|-------------|
| `php artisan git:update` | Update the application from Git repository |
| `php artisan ip:unban <ip>` | Unban a specific IP address |
| `php artisan ip:unban --all` | Unban all banned IP addresses |

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
