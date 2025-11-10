# 🔄 Respaldo

A comprehensive backup management application built with Laravel & Vue.js. Manage multiple apps, track backups, and automate server backups with a secure CLI script—all from a clean, simple dashboard.

---

## 🚀 Quick Start

### Production Deployment

For production deployments, use the included `deploy.sh` script:

```bash
# Clone the repository
git clone https://github.com/alecscodes/respaldo.git
cd respaldo

# Make it executable (only if you have permission issues)
# chmod +x deploy.sh

# Run deployment (handles both fresh installs and updates)
./deploy.sh
```

**Usage:**

- **Fresh Install**: Run `./deploy.sh` on a new installation
- **Update**: Run `./deploy.sh` to pull latest changes and redeploy

The script is idempotent—safe to run multiple times. It only prompts for missing environment variables and skips steps that are already complete.

### Docker Deployment

```bash
git clone https://github.com/alecscodes/respaldo.git
cd respaldo
cp .env.example .env
```

**Configure required environment variables in `.env`:**

- `APP_URL` (required) - The full URL of your application (e.g., `http://localhost:8000` or `https://respaldo.example.com`)
- `APP_PORT` (optional) - Port to expose the application on (default: `8000`)
- `BACKUP_VOLUME` (optional) - Path to store backups (default: `./backups`). Can be an external location like `/mnt/external-drive/backups`

Then start the containers:

```bash
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

1. **Download the script** from the dashboard (navigate to `/script/download` or use the download button in the dashboard)

   **Important:** The script is personalized per website. It uses the `APP_URL` from your `.env` file to know which website to send backups to. Make sure your `APP_URL` is set correctly in your `.env` file before downloading the script, as this URL is embedded in the script and used for all API communications.

2. **Run the script**:

   The script will automatically make itself executable on first run. You can run it using:

```bash
bash respaldo.sh
```

   Alternatively, if you prefer to make it executable manually:

```bash
chmod +x respaldo.sh
./respaldo.sh
```

**Script Features:**

The CLI script provides an interactive menu with the following options:

1. **List apps** - View all your apps with their IDs and names
2. **Create a new app** – Create an app directly from the command line (you’ll be prompted for the app name and storage size in GB).
3. **Create backup** - Backup the current directory (or a specified directory) to an app
   - Automatically reads `.gitignore` or `.respaldoignore` files
   - Creates a compressed tar.gz archive
   - Checks available storage space
   - Uploads the backup to the server
   - Saves app association for the directory (so future backups are faster)
4. **Download backup** - Download a previous backup from any app
   - Lists all apps
   - Shows all backups for the selected app (most recent first)
   - Downloads the selected backup to the current directory

**Quick backup mode:**

You can also run the script in non-interactive mode by passing a directory path as an argument:

```bash
respaldo /path/to/your/project
```

This will automatically use the saved app association for that directory and create a backup without showing the menu. Perfect for cron jobs!

3. **Make it a global command** (optional):

   To use `respaldo` as a command from anywhere on your system:

   Add it to your local bin directory (recommended for user-level installation):

```bash
# Create ~/bin if it doesn't exist
mkdir -p ~/bin

# Move the script
mv respaldo.sh ~/bin/respaldo

# Make it executable
chmod +x ~/bin/respaldo

# Add ~/bin to your PATH (add this to ~/.bashrc or ~/.zshrc)
export PATH="$HOME/bin:$PATH"

# Reload your shell configuration
source ~/.bashrc  # or source ~/.zshrc

# Now you can run it from anywhere:
respaldo
```

   Or move the script to a system-wide directory in your PATH:

```bash
# Move the script to a directory in your PATH
sudo mv respaldo.sh /usr/local/bin/respaldo

# Make it executable (if not already)
sudo chmod +x /usr/local/bin/respaldo

# Now you can run it from anywhere:
respaldo
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

### ⏰ Automated Backups with Cron

You can automate backups using cron jobs. The script supports non-interactive mode when a directory path is passed as an argument.

**Prerequisites:**

- The directory must have been backed up at least once interactively (so the app association is saved)
- The script must be accessible from the cron environment (use full paths)

**Example cron jobs:**

```bash
# Edit your crontab
crontab -e

# Perform a daily backup at 2 AM using the specified respaldo script and project path
0 2 * * * /path/to/respaldo.sh /path/to/your/project

# Perform a daily backup at 2 AM with logging (only if the respaldo binary has been moved to /usr/local/bin)
0 2 * * * /usr/local/bin/respaldo /path/to/your/project >> /var/log/respaldo.log 2>&1
```

**Important Notes:**

- Replace `/path/to/your/project` with the actual path to your project directory
- Replace `/path/to/respaldo.sh` or `/usr/local/bin/respaldo` with the actual path to your script
- **First-time setup:** Run the script interactively from the project directory at least once to establish the app association. After that, cron can run it non-interactively.
- Use full absolute paths in cron jobs (cron doesn't use your shell's PATH)
- Make sure the script has executable permissions
- Consider setting up Telegram notifications (see below) to get alerts if automated backups fail
- For logging, redirect output to a log file as shown in the last example

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

- When updates are available, a notification appears on the **Dashboard**
- The notification shows how many commits you're behind
- Click **Update Now** to pull the latest changes from the Git repository
- The system automatically:
  - Fetches latest changes from remote
  - Updates dependencies (Composer/NPM) if needed
  - Rebuilds frontend assets if needed
  - Clears caches

**Updating via Artisan:**

You can also use the Artisan command to perform updates:

```bash
php artisan git:update
```

This command will pull the latest changes and run all deployment steps (composer install, npm install, build, migrations, etc.).

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

1. **List apps** – See all your apps with their IDs and names
2. **Create new app** – Create a new app from the command line (requires app name and storage size in GB)
3. **Create backup** – Backup the current directory (or a specified directory) to an app
   - Automatically respects `.gitignore` or `.respaldoignore` files
   - Creates compressed tar.gz archives
   - Checks storage availability before uploading
4. **Download backup** – Download a previous backup from any app
   - Browse backups by app
   - View backup history (most recent first)
   - Download to current directory

**Quick backup mode:** Pass a directory path as an argument for non-interactive backups (perfect for cron jobs):

```bash
respaldo /path/to/your/project
```

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
