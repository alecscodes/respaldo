<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ScriptGeneratorService
{
    /**
     * Get the current script version hash.
     * This is a hash of the script template (without user-specific tokens).
     */
    public function getScriptVersion(): string
    {
        $scriptTemplate = $this->getScriptTemplate();

        return hash('sha256', $scriptTemplate);
    }

    /**
     * Get the script template without user-specific replacements.
     */
    private function getScriptTemplate(): string
    {
        return <<<'BASH'
#!/bin/sh

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
VERSION_FILE="$CONFIG_DIR/script_version"
USER_AGENT="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36"

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

# Auto-update script before running
# Accepts script arguments to preserve them after update
auto_update_script() {
    # Skip auto-update if explicitly disabled
    if [ "$RESPALDO_NO_AUTO_UPDATE" = "1" ]; then
        return 0
    fi

    # Get absolute path of script
    script_path="$0"
    if [ "${script_path#/}" = "$script_path" ]; then
        # Relative path, make it absolute
        script_path="$(cd "$(dirname "$0")" && pwd)/$(basename "$0")"
    fi

    # Store script arguments for re-execution after update
    # "$@" here refers to function arguments passed from main()
    script_args=("$@")

    # Check current version
    current_version=""
    if [ -f "$VERSION_FILE" ]; then
        current_version=$(cat "$VERSION_FILE" 2>/dev/null | head -1)
    fi

    # Get latest version from server
    version_response=$(curl -s -w "\n%{http_code}" \
        -H "Authorization: Bearer $TOKEN" \
        -H "User-Agent: $USER_AGENT" \
        "$BASE_URL/api/script/version" 2>/dev/null)

    version_http_code=$(echo "$version_response" | tail -n1)
    version_body=$(echo "$version_response" | sed '$d')

    # If version check fails, continue with current script (don't block execution)
    if [ "$version_http_code" != "200" ]; then
        return 0
    fi

    # Extract version from JSON response
    latest_version=$(echo "$version_body" | grep -o '"version":"[^"]*"' | sed 's/"version":"\([^"]*\)"/\1/' | head -1)

    if [ -z "$latest_version" ]; then
        return 0
    fi

    # Compare versions
    if [ "$current_version" = "$latest_version" ]; then
        # Already up to date
        return 0
    fi

    # Update available - download latest script
    echo -e "${YELLOW}New script version available. Updating...${NC}"

    # Download latest script to temporary file
    temp_script=$(mktemp)
    download_response=$(curl -s -w "\n%{http_code}" \
        -H "Authorization: Bearer $TOKEN" \
        -H "User-Agent: $USER_AGENT" \
        -o "$temp_script" \
        "$BASE_URL/api/script/download" 2>/dev/null)

    download_http_code=$(echo "$download_response" | tail -n1)

    if [ "$download_http_code" != "200" ] || [ ! -s "$temp_script" ]; then
        echo -e "${YELLOW}Warning: Could not download update. Continuing with current version.${NC}"
        rm -f "$temp_script"
        return 0
    fi

    # Preserve script permissions
    script_perms=$(stat -f "%OLp" "$script_path" 2>/dev/null || stat -c "%a" "$script_path" 2>/dev/null || echo "755")
    chmod "$script_perms" "$temp_script" 2>/dev/null || chmod +x "$temp_script"

    # Atomically replace script
    if mv "$temp_script" "$script_path" 2>/dev/null; then
        # Save new version
        echo "$latest_version" > "$VERSION_FILE" 2>/dev/null
        echo -e "${GREEN}Script updated successfully!${NC}"
        echo -e "${YELLOW}Re-running with updated script...${NC}"
        echo ""
        # Re-execute updated script with same arguments (preserve original operation)
        # This ensures cron jobs continue with backup instead of showing menu
        exec "$script_path" "${script_args[@]}"
    else
        echo -e "${YELLOW}Warning: Could not replace script. Continuing with current version.${NC}"
        rm -f "$temp_script"
        return 0
    fi
}

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
    response=$(curl -s -w "\n%{http_code}" -H "Authorization: Bearer $TOKEN" -H "User-Agent: $USER_AGENT" "$BASE_URL/api/apps")
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
    response=$(curl -s -H "Authorization: Bearer $TOKEN" -H "User-Agent: $USER_AGENT" "$BASE_URL/api/apps")
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
        -H "User-Agent: $USER_AGENT" \
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
            case "$line" in
                \#*) continue ;;
                "") continue ;;
            esac
            echo "$line" >> "$exclude_file"
        done < "$ignore_file"
    fi

    # Create backup file - macOS compatible
    case "$(uname -s)" in
        Darwin*)
            # macOS mktemp doesn't support --suffix
            backup_file=$(mktemp)
            backup_file="${backup_file}.tar.gz"
            ;;
        *)
            backup_file=$(mktemp --suffix=.tar.gz)
            ;;
    esac

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
    case "$(uname -s)" in
        Darwin*)
            file_size=$(stat -f%z "$backup_file" 2>/dev/null)
            ;;
        *)
            file_size=$(stat -c%s "$backup_file" 2>/dev/null)
            ;;
    esac

    if [ -z "$file_size" ] || [ "$file_size" = "0" ]; then
        echo -e "${RED}Error: Backup file is empty${NC}"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    # Check available space
    echo -e "${YELLOW}Checking available space...${NC}"
    if curl -s -H "Authorization: Bearer $TOKEN" -H "User-Agent: $USER_AGENT" \
        "$BASE_URL/api/apps/$app_id/space-check?size=$file_size" | grep -q '"available":false'; then
        echo -e "${RED}Error: Not enough space available${NC}"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    # Upload using chunked upload with adaptive chunk size
    size_mb=$(awk "BEGIN {printf \"%.2f\", $file_size/1024/1024}")
    echo -e "${YELLOW}Uploading backup (size: ${size_mb}MB)...${NC}"

    # Use larger chunks for larger files to reduce overhead
    # < 100MB: 10MB chunks, < 1GB: 25MB chunks, < 10GB: 50MB chunks, >= 10GB: 100MB chunks
    if [ $file_size -lt $((100 * 1024 * 1024)) ]; then
        chunk_size=$((10 * 1024 * 1024))
    elif [ $file_size -lt $((1024 * 1024 * 1024)) ]; then
        chunk_size=$((25 * 1024 * 1024))
    elif [ $file_size -lt $((10 * 1024 * 1024 * 1024)) ]; then
        chunk_size=$((50 * 1024 * 1024))
    else
        chunk_size=$((100 * 1024 * 1024))
    fi
    filename=$(basename "$backup_file")

    # Initialize upload
    init_body=$(curl -s -X POST \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -H "User-Agent: $USER_AGENT" \
        -d "{\"filename\":\"$filename\",\"total_size\":$file_size,\"chunk_size\":$chunk_size}" \
        -w "\n%{http_code}" \
        "$BASE_URL/api/apps/$app_id/backups/chunked/init")

    init_http_code=$(echo "$init_body" | tail -n1)
    init_body=$(echo "$init_body" | sed '$d')

    if [ "$init_http_code" != "201" ]; then
        echo -e "${RED}Error initializing upload: HTTP $init_http_code${NC}"
        echo "$init_body"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    upload_id=$(echo "$init_body" | grep -o '"upload_id":"[^"]*"' | sed 's/"upload_id":"\([^"]*\)"/\1/')
    total_chunks=$(echo "$init_body" | grep -o '"total_chunks":[0-9]*' | sed 's/"total_chunks":\([0-9]*\)/\1/')

    if [ -z "$upload_id" ] || [ -z "$total_chunks" ]; then
        echo -e "${RED}Error: Failed to parse upload response${NC}"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    # Upload chunks in parallel for better performance (reduced to prevent database locks)
    max_parallel=2
    if [ $total_chunks -lt 10 ]; then
        max_parallel=1
    elif [ $total_chunks -ge 50 ]; then
        max_parallel=3
    fi

    # Function to upload a single chunk
    upload_single_chunk() {
        local idx=$1
        local start=$((idx * chunk_size))
        local length=$((start + chunk_size > file_size ? file_size - start : chunk_size))
        local chunk_temp=$(mktemp)
        local max_retries=3
        local retry_count=0

        # Extract chunk using optimized dd
        if [ $length -gt $((1024 * 1024)) ]; then
            block_size=$((1024 * 1024))
            skip_blocks=$((start / block_size))
            blocks=$((length / block_size))
            remainder=$((length % block_size))
            
            if [ $blocks -gt 0 ]; then
                dd if="$backup_file" of="$chunk_temp" bs=$block_size skip=$skip_blocks count=$blocks 2>/dev/null || {
                    rm -f "$chunk_temp"
                    return 1
                }
            fi
            
            if [ $remainder -gt 0 ]; then
                dd if="$backup_file" of="$chunk_temp" bs=1 skip=$((start + blocks * block_size)) count=$remainder seek=$((blocks * block_size)) 2>/dev/null || {
                    rm -f "$chunk_temp"
                    return 1
                }
            fi
        else
            dd if="$backup_file" of="$chunk_temp" bs=1 skip=$start count=$length 2>/dev/null || {
                rm -f "$chunk_temp"
                return 1
            }
        fi

        # Upload chunk with retry logic
        while [ $retry_count -le $max_retries ]; do
            chunk_http_code=$(curl -s -X POST \
                -H "Authorization: Bearer $TOKEN" \
                -H "User-Agent: $USER_AGENT" \
                -F "upload_id=$upload_id" \
                -F "chunk_index=$idx" \
                -F "chunk=@$chunk_temp" \
                -w "%{http_code}" \
                -o /dev/null \
                --connect-timeout 30 \
                --max-time 300 \
                "$BASE_URL/api/apps/$app_id/backups/chunked/upload" 2>/dev/null)

            if [ "$chunk_http_code" = "200" ]; then
                rm -f "$chunk_temp"
                return 0
            fi
            
            retry_count=$((retry_count + 1))
            if [ $retry_count -le $max_retries ]; then
                sleep $((retry_count * 2))
            fi
        done

        rm -f "$chunk_temp"
        return 1
    }

    # Upload chunks in parallel
    chunk_index=0
    uploaded_count=0
    failed_chunks=0
    last_progress=0
    pids=()

    while [ $chunk_index -lt $total_chunks ] || [ ${#pids[@]} -gt 0 ]; do
        # Start new uploads up to max_parallel
        while [ ${#pids[@]} -lt $max_parallel ] && [ $chunk_index -lt $total_chunks ]; do
            upload_single_chunk $chunk_index &
            pids+=($!)
            chunk_index=$((chunk_index + 1))
        done

        # Check for completed uploads
        new_pids=()
        for pid in "${pids[@]}"; do
            if kill -0 "$pid" 2>/dev/null; then
                new_pids+=($pid)
            else
                wait "$pid"
                if [ $? -eq 0 ]; then
                    uploaded_count=$((uploaded_count + 1))
                else
                    failed_chunks=$((failed_chunks + 1))
                fi
            fi
        done
        pids=("${new_pids[@]}")

        # Update progress
        if [ $total_chunks -gt 0 ]; then
            progress=$((uploaded_count * 100 / total_chunks))
            if [ $((progress - last_progress)) -ge 5 ] || [ $uploaded_count -eq $total_chunks ]; then
                echo -ne "\r${YELLOW}Progress: ${progress}% (${uploaded_count}/${total_chunks} chunks uploaded)${NC}"
                last_progress=$progress
            fi
        fi

        sleep 0.1
    done

    echo ""

    if [ $failed_chunks -gt 0 ]; then
        echo -e "${RED}Error: Failed to upload $failed_chunks chunk(s)${NC}"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    # Finalize
    finalize_body=$(curl -s -X POST \
        -H "Authorization: Bearer $TOKEN" \
        -H "Content-Type: application/json" \
        -H "User-Agent: $USER_AGENT" \
        -d "{\"upload_id\":\"$upload_id\"}" \
        -w "\n%{http_code}" \
        "$BASE_URL/api/apps/$app_id/backups/chunked/finalize")

    finalize_http_code=$(echo "$finalize_body" | tail -n1)
    finalize_body=$(echo "$finalize_body" | sed '$d')

    if [ "$finalize_http_code" = "201" ]; then
        echo -e "${GREEN}Backup created successfully!${NC}"
    else
        echo -e "${RED}Error finalizing upload: HTTP $finalize_http_code${NC}"
        [ "$finalize_http_code" = "000" ] && echo -e "${YELLOW}Connection failed. Check server and network.${NC}" || echo "$finalize_body"
        rm -f "$backup_file" "$exclude_file"
        return 1
    fi

    rm -f "$backup_file" "$exclude_file"
}

# List backups for an app (sorted by most recent first)
list_backups() {
    app_id=$1

    if [ -z "$app_id" ]; then
        echo -e "${RED}Error: App ID required${NC}"
        return 1
    fi

    response=$(curl -s -H "Authorization: Bearer $TOKEN" -H "User-Agent: $USER_AGENT" "$BASE_URL/api/apps/$app_id/backups")

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

            case "$(uname -s)" in
                Darwin*)
                    # macOS date formatting
                    formatted_date=$(date -j -f "%Y-%m-%d %H:%M:%S" "$date_part" "+%Y-%m-%d %H:%M" 2>/dev/null || echo "$date_part")
                    ;;
                *)
                    # Linux date formatting
                    formatted_date=$(date -d "$date_part" "+%Y-%m-%d %H:%M" 2>/dev/null || echo "$date_part")
                    ;;
            esac
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
        -H "User-Agent: $USER_AGENT" \
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
    # Auto-update check (before authentication to ensure we have latest features)
    # Pass script arguments so they're preserved when script is re-executed after update
    # This ensures cron jobs continue with backup instead of showing menu
    auto_update_script "$@"

    check_auth

    # Check if a directory was passed as argument (quick backup mode)
    # This handles both initial calls and re-execution after update
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
                    case "$use_saved" in
                        [Yy]*) app_id=$saved_app_id ;;
                        *)
                            echo -e "${YELLOW}Your apps:${NC}"
                            list_apps
                            echo ""
                            read -p "Enter app ID: " app_id
                            ;;
                    esac
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
    }

    public function generateScript(User $user, string $baseUrl): string
    {
        $token = $user->createToken('respaldo-cli')->plainTextToken;
        $script = $this->getScriptTemplate();

        return str_replace(
            ['{{BASE_URL}}', '{{TOKEN}}'],
            [$baseUrl, $token],
            $script
        );
    }
}
