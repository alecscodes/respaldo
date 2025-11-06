# Respaldo

A comprehensive backup management application built with Laravel & Vue.js. Manage multiple apps, track backups, and automate server backups with a secure CLI script—all from a clean, simple dashboard.

---

## 🚀 Quick Start

### Docker Deployment

```bash
git clone https://github.com/alecscodes/respaldo.git
cd respaldo
cp .env.example .env
docker-compose up -d
```

### Local Development

```bash
# Clone & install
git clone https://github.com/alecscodes/respaldo.git
cd respaldo
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Build & run
npm run build
composer run dev
```

Visit `http://localhost:8000` 🎉

---

## ✨ Features

- **Multi-App Management** – Create and manage multiple apps with individual storage limits
- **Backup Tracking** – View, download, and manage backups for each app
- **CLI Script** – Download a secure, personalized CLI script for automated server backups
- **Storage Management** – Monitor storage usage with visual indicators and enforce limits
- **Gitignore Support** – CLI script respects `.gitignore` and `.respaldoignore` files
- **Space Validation** – Double-check storage availability before backups
- **Telegram Notifications** – Get instant alerts for backup failures, storage issues, and disk space warnings
- **Two-Factor Auth** – Secure your account with 2FA
- **Dark Mode** – Beautiful UI with light/dark themes
- **Mobile-First** – Fully responsive design
- **Auto-Updates** – Update the application directly from the web interface

---

## 🛠 Tech Stack

**Backend**: Laravel 12 · PHP 8.4+  
**Frontend**: Vue 3 · Inertia.js v2 · Tailwind CSS v4  
**Database**: SQLite (MySQL/PostgreSQL supported)  
**Testing**: Pest PHP v4  
**Deploy**: Docker & Docker Compose

---

## ⚙️ Configuration

### 👥 Registration Control

- **Fresh install**: Registration is auto-enabled for first user
- **After setup**: Auto-disabled for security
- **Manual control**: Enable/disable in **Settings → Registration**

### 📢 CLI Script Usage

1. **Download the script** from the admin panel (Script is personalized per user)

2. **Make it executable** (browsers don't preserve executable permissions):

```bash
chmod +x respaldo.sh
```

**Note:** After downloading, the script needs to be made executable. This is a security feature of browsers - they don't automatically make downloaded files executable.

3. **Run the script**:

```bash
./respaldo
```

4. **First time setup**:
   - If not logged in, the script will open your browser for authentication
   - After logging in, run the script again

5. **Create or select app**:
   - The script will show you a list of available apps
   - You can create a new app or select an existing one

6. **Create backup**:
   - The script will automatically:
     - Read `.gitignore` or `.respaldoignore` from the current directory
     - Create a compressed tar.gz archive
     - Check available space
     - Upload the backup to the server

### 📁 Ignore Files

The CLI script supports two ignore files to exclude files and folders from backups:

- `.respaldoignore` – Custom ignore file for backups (takes precedence over .gitignore)
- `.gitignore` – Standard Git ignore patterns (used if .respaldoignore doesn't exist)

#### Creating a `.respaldoignore` File

Create a `.respaldoignore` file in the directory you want to backup. It works exactly like `.gitignore`:

```bash
# Ignore node_modules
node_modules/

# Ignore .env files
.env
.env.local

# Ignore logs
*.log
logs/

# Ignore specific files
config.json
secrets/

# Ignore everything in temp directory
temp/
```

**Patterns work the same as `.gitignore`:**

- `folder/` – Ignores the entire folder
- `*.ext` – Ignores all files with that extension
- `file.txt` – Ignores a specific file
- `# comment` – Comments start with #

The script will automatically use `.respaldoignore` if it exists, otherwise it will use `.gitignore`.

### 🔄 Application Updates

The application includes an automatic update system that allows you to update directly from the web interface.

**How it works:**

- Navigate to **Settings → Updates** in the app
- Check for available updates
- Click **Update** to pull the latest changes from the Git repository
- The system automatically:
  - Fetches latest changes from remote
  - Updates dependencies (Composer/NPM) if needed
  - Rebuilds frontend assets if needed
  - Clears caches

**Requirements:**

- Application must be in a Git repository
- Remote repository must be configured
- Git must be available in the container

### 📢 Telegram Notifications

The application can send Telegram notifications for important backup events and errors. This is especially useful when running automated backups via cron jobs.

**Setup:**

1. Create a Telegram bot using [@BotFather](https://t.me/BotFather) on Telegram
2. Get your bot token from BotFather
3. Send a message to your bot
4. Visit `https://api.telegram.org/bot<your_bot_token>/getUpdates` to find your chat ID
5. Navigate to **Settings → Telegram** in the app
6. Enter your bot token and chat ID
7. Save the settings

**Notifications sent for:**

- **Backup Failures** – When a backup fails to create or store
- **Insufficient Storage** – When an app doesn't have enough storage space for a backup
- **Disk Space Warnings** – When server disk space exceeds 90% usage
- **Storage Issues** – When the server doesn't have enough disk space for a backup

**Note:** Notifications are only sent when errors occur. Successful backups don't trigger notifications to avoid spam. This ensures you're only alerted when action is needed.

### 🔒 IP Banning System

The application includes an automatic IP banning system to protect against attackers and malicious requests.

**Automatic Banning:**

- **Failed Login Attempts**: IPs are permanently banned after **2 failed login attempts**
- **Non-existent Routes**: IPs accessing non-existent routes (e.g., `/wordpress`, `/wp-admin`) are permanently banned
- **Multi-IP Detection**: Detects and bans all related IPs including:
  - Client IP
  - Forwarded IPs (X-Forwarded-For)
  - Proxy/VPN IPs (CF-Connecting-IP, X-Real-Ip, etc.)
  - Server IPs

**Unbanning IPs:**

Unban a specific IP:

```bash
php artisan ip:unban 192.168.1.100
```

Unban all IPs:

```bash
php artisan ip:unban --all
```

**Note**: Banned IPs are stored in the database and cached for performance. Unbanning clears both the database and cache entries.

---

## 📖 Usage

### Adding an App

1. Navigate to **Apps → Create App**
2. Enter app name and storage size limit (in GB)
3. Hit **Create** ✅

### Managing Backups

- **View Backups**: Click any app to see its backup history
- **Download Backups**: Click download button on any backup
- **Delete Backups**: Remove old backups to free up space

### Using the CLI Script

The CLI script provides an interactive interface for managing backups:

1. **List apps** – See all your apps
2. **Create new app** – Create a new app from the command line
3. **Create backup** – Backup the current directory
4. **Download backup** – Download a previous backup

---

## 🧪 Development

```bash
# Run tests
php artisan test

# Code formatting
vendor/bin/pint

# Static analysis (Larastan)
composer run analyze

# Frontend dev mode
npm run dev
```

---

## 📄 License

MIT License - feel free to use, modify, and distribute.

---

## ⚠️ Disclaimer

This software is provided **"as is"** without warranty. Use at your own risk. Not responsible for data loss, backup failures, or any damages. Always maintain multiple backup systems for critical services.

---

## 💬 Support

Questions or issues? Check the [issue tracker](https://github.com/alecscodes/respaldo/issues) or open a new issue.

**Happy backing up!** 🚀
