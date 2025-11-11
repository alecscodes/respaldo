<?php

namespace App\Services;

use App\Models\User;

class ScriptGeneratorService
{
    public function generateScript(User $user, string $baseUrl): string
    {
        $token = $user->createToken('respaldo-cli')->plainTextToken;

        $script = <<<'BASH'
#!/bin/bash

# Respaldo Backup CLI Script
# Generated automatically - do not modify manually
#
# To ignore files from backups, create a .respaldoignore file (or use .gitignore)
# Example .respaldoignore:
#   node_modules/
#   .env
#   *.log

# Auto-make script executable if needed and re-run
if [ ! -x "$0" ]; then
    echo "Making script executable..."
    # Get absolute path of script
    script_path="$0"
    if [ "${script_path#/}" = "$script_path" ]; then
        # Relative path, make it absolute
        script_path="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
    fi
    chmod +x "$script_path" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "Script is now executable! Running..."
        # Re-execute with absolute path
        exec "$script_path" "$@"
    else
        echo "Could not make script executable automatically."
        echo "Please run: chmod +x respaldo.sh"
        exit 1
    fi
fi

BASE_URL="{{BASE_URL}}"
TOKEN="{{TOKEN}}"
CONFIG_DIR="$HOME/.respaldo"
CONFIG_FILE="$CONFIG_DIR/config"
APPS_MAP_FILE="$CONFIG_DIR/apps_map"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Initialize config directory
mkdir -p "$CONFIG_DIR"

# Load existing config if available
if [ -f "$CONFIG_FILE" ]; then
    # Source config file safely
    set -a
    source "$CONFIG_FILE" 2>/dev/null
    set +a
fi

# Load apps mapping (directory -> app_id)
if [ ! -f "$APPS_MAP_FILE" ]; then
    touch "$APPS_MAP_FILE"
fi

# Save token if not exists or if TOKEN is empty
if [ ! -f "$CONFIG_FILE" ] || [ -z "$TOKEN" ]; then
    {
        echo "TOKEN=\"$TOKEN\""
        echo "BASE_URL=\"$BASE_URL\""
    } > "$CONFIG_FILE"
    echo -e "${GREEN}Configuration saved to $CONFIG_FILE${NC}"
    # Reload config
    set -a
    source "$CONFIG_FILE"
    set +a
fi

# Check if token exists
if [ -z "$TOKEN" ]; then
    echo -e "${RED}Error: Authentication token not found.${NC}"
    echo "Please download a new script from the admin panel."
    exit 1
fi

# Remove hardcoded credentials from script after first successful use (security)
remove_hardcoded_credentials() {
    # Only remove if config file exists and has valid values
    if [ ! -f "$CONFIG_FILE" ]; then
        return 0
    fi

    # Check if config file has valid TOKEN and BASE_URL
    config_token=$(grep "^TOKEN=" "$CONFIG_FILE" 2>/dev/null | cut -d'"' -f2)
    config_url=$(grep "^BASE_URL=" "$CONFIG_FILE" 2>/dev/null | cut -d'"' -f2)

    if [ -z "$config_token" ] || [ -z "$config_url" ]; then
        return 0
    fi

    # Get absolute path of script
    script_path="$0"
    if [ "${script_path#/}" = "$script_path" ]; then
        # Relative path, make it absolute
        script_path="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
    fi

    # Check if script still has hardcoded credentials (lines 46-47)
    # Look for BASE_URL and TOKEN assignment lines that are not comments
    has_hardcoded=false
    if grep -q "^BASE_URL=" "$script_path" 2>/dev/null; then
        has_hardcoded=true
    fi
    if grep -q "^TOKEN=" "$script_path" 2>/dev/null; then
        has_hardcoded=true
    fi

    if [ "$has_hardcoded" = "false" ]; then
        # Already removed
        return 0
    fi

    # Create temporary file without hardcoded credentials
    temp_script=$(mktemp)

    # Remove lines starting with BASE_URL= or TOKEN= (but keep comments and other references)
    # Use awk to filter out the assignment lines
    awk '
        /^BASE_URL=/ { next }
        /^TOKEN=/ { next }
        { print }
    ' "$script_path" > "$temp_script" 2>/dev/null

    if [ $? -eq 0 ] && [ -s "$temp_script" ]; then
        # Preserve script permissions
        script_perms=$(stat -f "%OLp" "$script_path" 2>/dev/null || stat -c "%a" "$script_path" 2>/dev/null || echo "755")
        chmod "$script_perms" "$temp_script" 2>/dev/null || chmod +x "$temp_script"
        mv "$temp_script" "$script_path"
        echo -e "${GREEN}Security: Hardcoded credentials removed from script.${NC}"
    else
        rm -f "$temp_script"
    fi
}

# Check authentication
check_auth() {
    response=$(curl -s -w "\n%{http_code}" -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/apps")
    http_code=$(echo "$response" | tail -n1)

    # Extract body (everything except last line which is http_code)
    # Compatible with both macOS and Linux
    body=$(echo "$response" | sed '$d')

    if [ "$http_code" != "200" ]; then
        echo -e "${RED}Authentication failed (HTTP $http_code)${NC}"
        case "$http_code" in
            401|403)
                echo -e "${YELLOW}Your API token is invalid or expired.${NC}"
                echo "Please download a fresh script from the admin panel:"
                echo "1. Log in to $BASE_URL"
                echo "2. Go to an app page"
                echo "3. Click 'Download CLI Script'"
                echo "4. Replace this script with the new one"
                ;;
            500)
                echo -e "${YELLOW}Server error occurred.${NC}"
                echo "The server encountered an error. Please try again later."
                echo "Response: $body"
                ;;
            000)
                echo -e "${YELLOW}Unable to connect to server.${NC}"
                echo "Please check:"
                echo "- Your internet connection"
                echo "- That the server is running at $BASE_URL"
                echo "- That the BASE_URL is correct in $CONFIG_FILE"
                ;;
            *)
                echo -e "${YELLOW}Unexpected error occurred.${NC}"
                echo "Response: $body"
                ;;
        esac
        exit 1
    fi

    # After successful authentication, remove hardcoded credentials for security
    remove_hardcoded_credentials
}

# List apps
list_apps() {
    response=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/apps")
    apps=$(echo "$response" | grep -o '"id":[0-9]*,"name":"[^"]*"' | sed 's/"id":\([0-9]*\),"name":"\([^"]*\)"/\1) \2/')
    if [ -z "$apps" ]; then
        echo "No apps found"
    else
        echo "$apps"
    fi
}

# Create app
create_app() {
    read -p "App name: " app_name
    read -p "Storage size (GB): " storage_gb

    response=$(curl -s -X POST -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -d "{\"name\":\"$app_name\",\"storage_size\":$storage_gb}" \
        "$BASE_URL/api/apps")

    echo "$response"
}

# Get or save app association for a directory
get_app_for_directory() {
    dir_path="$1"
    abs_dir=$(cd "$dir_path" && pwd)
    grep "^$abs_dir|" "$APPS_MAP_FILE" 2>/dev/null | cut -d'|' -f2
}

save_app_for_directory() {
    dir_path="$1"
    app_id="$2"
    abs_dir=$(cd "$dir_path" && pwd)
    # Remove old entry if exists
    grep -v "^$abs_dir|" "$APPS_MAP_FILE" > "${APPS_MAP_FILE}.tmp" 2>/dev/null || true
    mv "${APPS_MAP_FILE}.tmp" "$APPS_MAP_FILE" 2>/dev/null || touch "$APPS_MAP_FILE"
    # Add new entry
    echo "$abs_dir|$app_id" >> "$APPS_MAP_FILE"
}

# Create backup
create_backup() {
    app_id=$1
    backup_dir="${2:-.}"

    if [ -z "$app_id" ]; then
        echo -e "${RED}Error: App ID required${NC}"
        return 1
    fi

    # Validate backup directory
    if [ ! -d "$backup_dir" ]; then
        echo -e "${RED}Error: Directory '$backup_dir' does not exist${NC}"
        return 1
    fi

    # Get absolute path
    abs_backup_dir=$(cd "$backup_dir" && pwd)

    # Save app association for this directory
    save_app_for_directory "$abs_backup_dir" "$app_id"

    # Change to backup directory
    cd "$abs_backup_dir" || {
        echo -e "${RED}Error: Cannot access directory '$backup_dir'${NC}"
        return 1
    }

    # Check for .gitignore or .respaldoignore
    ignore_file=""
    if [ -f ".respaldoignore" ]; then
        ignore_file=".respaldoignore"
    elif [ -f ".gitignore" ]; then
        ignore_file=".gitignore"
    fi

    # Create temporary exclude file for tar
    exclude_file=$(mktemp)

    if [ -n "$ignore_file" ]; then
        # Convert ignore patterns to tar exclude format
        while IFS= read -r line; do
            # Skip comments and empty lines
            [[ "$line" =~ ^#.*$ ]] && continue
            [[ -z "$line" ]] && continue
            echo "$line" >> "$exclude_file"
        done < "$ignore_file"
    fi

    # Create backup file - macOS compatible
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS mktemp doesn't support --suffix
        backup_file=$(mktemp)
        backup_file="${backup_file}.tar.gz"
    else
        backup_file=$(mktemp --suffix=.tar.gz)
    fi

    echo -e "${YELLOW}Creating backup...${NC}"

    if [ -n "$ignore_file" ]; then
        tar -czf "$backup_file" --exclude-from="$exclude_file" . 2>&1
    else
        tar -czf "$backup_file" . 2>&1
    fi

    tar_exit=$?
    if [ $tar_exit -ne 0 ]; then
        echo -e "${RED}Error creating tar archive${NC}"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    # Get file size - macOS compatible
    if [[ "$OSTYPE" == "darwin"* ]]; then
        file_size=$(stat -f%z "$backup_file" 2>/dev/null)
    else
        file_size=$(stat -c%s "$backup_file" 2>/dev/null)
    fi

    if [ -z "$file_size" ] || [ "$file_size" = "0" ]; then
        echo -e "${RED}Error: Backup file is empty${NC}"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    # Check available space via API
    echo -e "${YELLOW}Checking available space...${NC}"
    space_check=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/apps/$app_id/space-check?size=$file_size")

    if echo "$space_check" | grep -q '"available":false'; then
        echo -e "${RED}Error: Not enough space available${NC}"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    # Upload backup
    echo -e "${YELLOW}Uploading backup (size: $(echo "scale=2; $file_size/1024/1024" | bc)MB)...${NC}"

    response=$(curl -s -w "\n%{http_code}" -X POST \
        -H "Authorization: Bearer $TOKEN" \
        -F "file=@$backup_file;type=application/gzip" \
        "$BASE_URL/api/apps/$app_id/backups")

    http_code=$(echo "$response" | tail -n1)

    # Extract body (compatible with macOS)
    body=$(echo "$response" | sed '$d')

    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
        echo -e "${GREEN}Backup created successfully!${NC}"
        echo "$body"
    else
        echo -e "${RED}Error uploading backup: HTTP $http_code${NC}"
        if [ "$http_code" = "000" ]; then
            echo -e "${YELLOW}Connection failed. Please check:${NC}"
            echo "- Server is running at $BASE_URL"
            echo "- Your internet connection"
            echo "- Firewall settings"
        else
            echo "Response: $body"
        fi
    fi

    # Cleanup
    rm -f "$backup_file" "$exclude_file"
}

# List backups for an app (sorted by most recent first)
list_backups() {
    app_id=$1

    if [ -z "$app_id" ]; then
        echo -e "${RED}Error: App ID required${NC}"
        return 1
    fi

    response=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/apps/$app_id/backups")

    # Check if response has backups
    if ! echo "$response" | grep -q '"id"'; then
        echo "No backups found"
        return 0
    fi

    # Parse and format each backup (backups are already sorted by most recent first from API)
    echo "$response" | sed 's/},{/}\n{/g' | sed 's/\[{/{/g' | sed 's/}\]/}/g' | while IFS= read -r backup_json; do
        if [ -z "$backup_json" ] || [ "$backup_json" = "{" ] || [ "$backup_json" = "}" ]; then
            continue
        fi

        # Extract fields using grep and sed
        backup_id=$(echo "$backup_json" | grep -o '"id":[0-9]*' | sed 's/"id":\([0-9]*\)/\1/')
        filename=$(echo "$backup_json" | grep -o '"filename":"[^"]*"' | sed 's/"filename":"\([^"]*\)"/\1/')
        created_at=$(echo "$backup_json" | grep -o '"created_at":"[^"]*"' | sed 's/"created_at":"\([^"]*\)"/\1/')

        if [ -z "$backup_id" ] || [ -z "$filename" ]; then
            continue
        fi

        # Format date (created_at is in ISO 8601 format like "2025-11-03T16:42:12.000000Z")
        if [ -n "$created_at" ]; then
            # Extract date and time part (remove microseconds and timezone)
            date_part=$(echo "$created_at" | sed 's/\([0-9]\{4\}-[0-9]\{2\}-[0-9]\{2\}\)T\([0-9]\{2\}:[0-9]\{2\}:[0-9]\{2\}\)[^"]*/\1 \2/')

            if [[ "$OSTYPE" == "darwin"* ]]; then
                # macOS date formatting
                formatted_date=$(date -j -f "%Y-%m-%d %H:%M:%S" "$date_part" "+%Y-%m-%d %H:%M" 2>/dev/null || echo "$date_part")
            else
                # Linux date formatting
                formatted_date=$(date -d "$date_part" "+%Y-%m-%d %H:%M" 2>/dev/null || echo "$date_part")
            fi
        else
            formatted_date="N/A"
        fi

        echo "$backup_id) $filename ($formatted_date)"
    done
}

# Download backup
download_backup() {
    app_id=$1
    backup_id=$2

    if [ -z "$app_id" ] || [ -z "$backup_id" ]; then
        echo -e "${RED}Error: App ID and Backup ID required${NC}"
        return 1
    fi

    # Get backup filename from API
    backup_info=$(curl -s -H "Authorization: Bearer $TOKEN" "$BASE_URL/api/apps/$app_id/backups")
    filename=$(echo "$backup_info" | sed 's/},{/}\n{/g' | grep "\"id\":$backup_id" | grep -o '"filename":"[^"]*"' | sed 's/"filename":"\([^"]*\)"/\1/' | head -1)

    if [ -z "$filename" ]; then
        echo -e "${RED}Error: Backup not found${NC}"
        return 1
    fi

    echo -e "${YELLOW}Downloading backup: $filename${NC}"

    # Download the backup file
    curl -s -H "Authorization: Bearer $TOKEN" \
        -o "$filename" \
        "$BASE_URL/api/backups/$backup_id/download"

    if [ $? -eq 0 ] && [ -f "$filename" ]; then
        echo -e "${GREEN}Backup downloaded successfully to: $filename${NC}"
    else
        echo -e "${RED}Error downloading backup${NC}"
        return 1
    fi
}

# Main script
main() {
    check_auth

    # Check if a directory was passed as argument (quick backup mode)
    if [ -n "$1" ]; then
        backup_dir="$1"

        if [ ! -d "$backup_dir" ]; then
            echo -e "${RED}Error: Directory '$backup_dir' does not exist${NC}"
            exit 1
        fi

        abs_backup_dir=$(cd "$backup_dir" && pwd)

        # Check if we have a saved app for this directory
        saved_app_id=$(get_app_for_directory "$abs_backup_dir")

        if [ -n "$saved_app_id" ]; then
            echo -e "${GREEN}Using saved app (ID: $saved_app_id) for directory: $abs_backup_dir${NC}"
            create_backup "$saved_app_id" "$backup_dir"
            if [ $? -eq 0 ]; then
                echo ""
                echo -e "${GREEN}Backup complete!${NC}"
                exit 0
            else
                exit 1
            fi
        else
            # No saved app, show apps and ask
            echo -e "${YELLOW}Backing up directory: $abs_backup_dir${NC}"
            echo ""
            echo -e "${YELLOW}Your apps:${NC}"
            list_apps
            echo ""
            read -p "Enter app ID (will be saved for this directory): " app_id

            if [ -z "$app_id" ]; then
                echo -e "${RED}Error: App ID required${NC}"
                exit 1
            fi

            create_backup "$app_id" "$backup_dir"
            if [ $? -eq 0 ]; then
                echo ""
                echo -e "${GREEN}Backup complete! App association saved for this directory.${NC}"
                exit 0
            else
                exit 1
            fi
        fi
    fi

    # Interactive mode
    while true; do
        echo ""
        echo -e "${GREEN}Respaldo Backup CLI${NC}"
        echo ""
        echo "1) List apps"
        echo "2) Create new app"
        echo "3) Create backup"
        echo "4) Download backup"
        echo "5) Exit"
        echo ""
        read -p "Choose an option (1-5): " choice

        case $choice in
            1)
                echo -e "${YELLOW}Your apps:${NC}"
                list_apps
                ;;
            2)
                create_app
                ;;
            3)
                current_dir=$(pwd)
                saved_app_id=$(get_app_for_directory "$current_dir")

                if [ -n "$saved_app_id" ]; then
                    echo -e "${GREEN}Found saved app (ID: $saved_app_id) for current directory${NC}"
                    echo ""
                    read -p "Use saved app? (y/n): " use_saved
                    if [[ "$use_saved" =~ ^[Yy]$ ]]; then
                        app_id=$saved_app_id
                    else
                        echo -e "${YELLOW}Your apps:${NC}"
                        list_apps
                        echo ""
                        read -p "Enter app ID: " app_id
                    fi
                else
                    echo -e "${YELLOW}Your apps:${NC}"
                    list_apps
                    echo ""
                    read -p "Enter app ID (will be saved for this directory): " app_id
                fi

                if [ -z "$app_id" ]; then
                    echo -e "${RED}Error: App ID required${NC}"
                    continue
                fi

                create_backup "$app_id" "."
                echo ""
                echo -e "${GREEN}Backup complete. Exiting...${NC}"
                exit 0
                ;;
            4)
                echo -e "${YELLOW}Your apps:${NC}"
                list_apps
                echo ""
                read -p "Enter app ID: " app_id
                echo -e "${YELLOW}Backups for this app (most recent first):${NC}"
                list_backups "$app_id"
                echo ""
                read -p "Enter backup ID: " backup_id
                download_backup "$app_id" "$backup_id"
                echo ""
                echo -e "${GREEN}Download complete. Exiting...${NC}"
                exit 0
                ;;
            5)
                echo -e "${GREEN}Goodbye!${NC}"
                exit 0
                ;;
            *)
                echo -e "${RED}Invalid option. Please choose 1-5.${NC}"
                ;;
        esac
    done
}

# Run main function with all arguments
main "$@"

BASH;

        return str_replace(
            ['{{BASE_URL}}', '{{TOKEN}}'],
            [$baseUrl, $token],
            $script
        );
    }
}
