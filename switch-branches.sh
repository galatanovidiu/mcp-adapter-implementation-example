#!/bin/bash

# Branch Switcher for MCP Adapter Development
# Usage: ./switch-branches.sh [configuration-name]
# Example: ./switch-branches.sh experimental

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/branch-config.json"
CONFIG_EXAMPLE_FILE="$SCRIPT_DIR/branch-config.json.example"
COMPOSER_FILE="$SCRIPT_DIR/composer.json"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to create initial config file from example
create_initial_config() {
    if [[ ! -f "$CONFIG_EXAMPLE_FILE" ]]; then
        print_error "Example configuration file not found: $CONFIG_EXAMPLE_FILE"
        print_error "Please ensure branch-config.json.example exists in the same directory as this script"
        exit 1
    fi
    
    print_info "Creating initial branch-config.json with stable configuration only..."
    
    # Extract only the stable configuration from the example
    jq '{
        configurations: {
            stable: .configurations.stable
        },
        current: "stable"
    }' "$CONFIG_EXAMPLE_FILE" > "$CONFIG_FILE"
    
    print_success "Created branch-config.json with stable configuration"
    print_info "You can add more configurations by editing branch-config.json or copying from branch-config.json.example"
}

# Check if required files exist
if [[ ! -f "$CONFIG_FILE" ]]; then
    if [[ -f "$CONFIG_EXAMPLE_FILE" ]]; then
        create_initial_config
    else
        print_error "Configuration file not found: $CONFIG_FILE"
        print_error "Example configuration file not found: $CONFIG_EXAMPLE_FILE"
        exit 1
    fi
fi

if [[ ! -f "$COMPOSER_FILE" ]]; then
    print_error "Composer file not found: $COMPOSER_FILE"
    exit 1
fi

# Check if jq is available for JSON parsing
if ! command -v jq &> /dev/null; then
    print_error "jq is required but not installed. Please install jq first."
    print_info "Install with: brew install jq (macOS) or apt-get install jq (Ubuntu)"
    exit 1
fi

# Function to list available configurations
list_configurations() {
    print_info "Available branch configurations:"
    echo
    jq -r '.configurations | to_entries[] | "  \(.key): \(.value.description)"' "$CONFIG_FILE"
    echo
    current=$(jq -r '.current' "$CONFIG_FILE")
    print_info "Current configuration: $current"
}

# Function to get current configuration
get_current_config() {
    jq -r '.current' "$CONFIG_FILE"
}

# Function to update composer.json with new dependencies
update_composer() {
    local config_name="$1"
    
    print_info "Switching to configuration: $config_name"
    
    # Get the packages for the specified configuration
    local packages
    packages=$(jq -r ".configurations[\"$config_name\"].packages" "$CONFIG_FILE")
    
    if [[ "$packages" == "null" ]]; then
        print_error "Configuration '$config_name' not found"
        list_configurations
        exit 1
    fi
    
    print_info "Updating composer.json..."
    
    # Create a temporary file for the updated composer.json
    local temp_composer=$(mktemp)
    
    # Build dependencies and repositories from the packages configuration
    local dependencies='{}'
    local repositories='[]'
    
    # Extract unique repositories and build dependencies from config
    for package in $(echo "$packages" | jq -r 'keys[]'); do
        local version=$(echo "$packages" | jq -r ".[\"$package\"].version")
        local repo_url=$(echo "$packages" | jq -r ".[\"$package\"].repository")
        
        # Check if the package configuration specifies a type (default to "vcs")
        local repo_type=$(echo "$packages" | jq -r ".[\"$package\"].type // \"vcs\"")
        
        # Add to dependencies
        dependencies=$(echo "$dependencies" | jq --arg pkg "$package" --arg ver "$version" '. + {($pkg): $ver}')
        
        # Add unique repository with the specified type
        local repo_obj=$(jq -n --arg type "$repo_type" --arg url "$repo_url" '{type: $type, url: $url}')
        if ! echo "$repositories" | jq --argjson repo "$repo_obj" 'map(.url) | contains([$repo.url])' | grep -q true; then
            repositories=$(echo "$repositories" | jq --argjson repo "$repo_obj" '. + [$repo]')
        fi
    done
    
    # Update composer.json with new dependencies and repositories
    jq --argjson deps "$dependencies" --argjson repos "$repositories" '
        .require = (.require + $deps) |
        .repositories = $repos
    ' "$COMPOSER_FILE" > "$temp_composer"
    
    # Replace the original composer.json
    mv "$temp_composer" "$COMPOSER_FILE"
    
    # Update the current configuration in the config file
    local temp_config=$(mktemp)
    jq --arg current "$config_name" '.current = $current' "$CONFIG_FILE" > "$temp_config"
    mv "$temp_config" "$CONFIG_FILE"
    
    print_success "Updated composer.json with $config_name configuration"
}

# Function to check if wp-env is running
is_wp_env_running() {
    if command -v wp-env &> /dev/null; then
        # Check if wp-env is running for this project
        cd "$SCRIPT_DIR" && wp-env run cli "echo 'test'" &> /dev/null
        return $?
    fi
    return 1
}

# Function to run composer update
run_composer_update() {
    print_info "Running composer update..."
    
    local composer_cmd
    
    # Check if we should run composer inside wp-env container
    if is_wp_env_running; then
        print_info "Detected wp-env is running, using composer inside container..."
        
        # Check if we need to update repository paths for container
        local temp_composer=$(mktemp)
        
        # Update repository paths for container environment
        # No need to change paths since we're mapping them correctly in .wp-env.json
        cp "$COMPOSER_FILE" "$temp_composer"
        
        # Replace composer.json temporarily for container
        cp "$COMPOSER_FILE" "$COMPOSER_FILE.backup"
        mv "$temp_composer" "$COMPOSER_FILE"
        
        composer_cmd="cd $SCRIPT_DIR && wp-env run cli composer update --working-dir=/var/www/html/wp-content/plugins/mcp-adapter-implementation-example --no-interaction --optimize-autoloader"
        
        # Run composer and capture result
        local result=0
        if eval "$composer_cmd"; then
            print_success "Composer update completed successfully"
        else
            print_error "Composer update failed"
            result=1
        fi
        
        # Restore original composer.json
        mv "$COMPOSER_FILE.backup" "$COMPOSER_FILE"
        
        if [[ $result -ne 0 ]]; then
            exit 1
        fi
    else
        print_info "Running composer on host system..."
        composer_cmd="composer update --no-interaction --optimize-autoloader"
        
        if eval "$composer_cmd"; then
            print_success "Composer update completed successfully"
        else
            print_error "Composer update failed"
            exit 1
        fi
    fi
}

# Function to show current status
show_status() {
    local current_config
    current_config=$(get_current_config)
    
    print_info "Current Configuration: $current_config"
    
    local description
    description=$(jq -r ".configurations[\"$current_config\"].description" "$CONFIG_FILE")
    print_info "Description: $description"
    
    echo
    print_info "Current packages in composer.json:"
    jq -r '.require | to_entries[] | select(.key | startswith("wordpress/")) | "  \(.key): \(.value)"' "$COMPOSER_FILE"
    
    echo
    print_info "Current repositories:"
    jq -r '.repositories[] | "  \(.url)"' "$COMPOSER_FILE"
}

# Main script logic
main() {
    echo "🔄 MCP Adapter Branch Switcher"
    echo "=============================="
    echo
    
    # If no arguments provided, show current status and available options
    if [[ $# -eq 0 ]]; then
        show_status
        echo
        list_configurations
        echo
        print_info "Usage: $0 [configuration-name]"
        print_info "Example: $0 experimental"
        exit 0
    fi
    
    local config_name="$1"
    local current_config
    current_config=$(get_current_config)
    
    # Check if we're already on the requested configuration
    if [[ "$config_name" == "$current_config" ]]; then
        print_warning "Already on configuration: $config_name"
        show_status
        exit 0
    fi
    
    # Validate configuration exists
    if ! jq -e ".configurations[\"$config_name\"]" "$CONFIG_FILE" > /dev/null; then
        print_error "Configuration '$config_name' not found"
        list_configurations
        exit 1
    fi
    
    # Show what we're switching from and to
    echo "Switching from: $current_config"
    echo "Switching to: $config_name"
    echo
    
    # Update composer.json
    update_composer "$config_name"
    
    # Run composer update
    run_composer_update
    
    echo
    print_success "Successfully switched to $config_name configuration!"
    
    # Show final status
    echo
    show_status
}

# Run the main function with all arguments
main "$@"
