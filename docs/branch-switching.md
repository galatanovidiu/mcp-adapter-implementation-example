# Branch Switching System

This plugin includes a powerful branch switching system that allows you to easily test different versions and PRs of the MCP Adapter and Abilities API packages.

## Quick Start

```bash
# Run the script to see current status and available configurations
./switch-branches.sh

# Switch to a specific configuration
./switch-branches.sh pr-37-cursor-fix
```

## Files

- **`switch-branches.sh`** - The main script for switching between branch configurations
- **`branch-config.json.example`** - Template with example configurations (committed to repo)
- **`branch-config.json`** - Your personal configuration file (auto-created, not committed)

## How It Works

1. **First Run**: The script automatically creates `branch-config.json` with only the stable configuration
2. **Personal Configs**: Add your own configurations to `branch-config.json` without affecting the repository
3. **Clean Commits**: `branch-config.json` is gitignored, so personal configurations stay local

## Configuration Structure

Each configuration correlates package versions with their repository URLs:

```json
{
  "configurations": {
    "my-config": {
      "description": "Description of what this configuration does",
      "packages": {
        "wordpress/mcp-adapter": {
          "version": "dev-branch-name",
          "repository": "https://github.com/user/mcp-adapter"
        },
        "wordpress/abilities-api": {
          "version": "dev-trunk",
          "repository": "https://github.com/WordPress/abilities-api"
        }
      }
    }
  },
  "current": "stable"
}
```

## Adding New Configurations

### Option 1: Copy from Example
```bash
# Copy configurations from the example file
cp branch-config.json.example branch-config.json
# Edit as needed
```

### Option 2: Manual Addition
Edit `branch-config.json` and add your configuration:

```json
{
  "configurations": {
    "stable": { ... },
    "my-pr-test": {
      "description": "Testing PR #123 from contributor",
      "packages": {
        "wordpress/mcp-adapter": {
          "version": "dev-fix/some-issue",
          "repository": "https://github.com/contributor/mcp-adapter"
        },
        "wordpress/abilities-api": {
          "version": "dev-trunk",
          "repository": "https://github.com/WordPress/abilities-api"
        }
      }
    }
  },
  "current": "stable"
}
```

## Example Configurations

The `branch-config.json.example` file includes several pre-configured examples:

- **`stable`** - Official trunk versions
- **`pr-37-cursor-fix`** - PR #37 for Cursor IDE compatibility
- **`custom`** - Template for your own configurations

## Benefits

- ✅ **No Repository Pollution**: Personal configurations don't get committed
- ✅ **Easy PR Testing**: Quickly test contributor PRs and branches
- ✅ **Clean Repository Management**: Automatically manages VCS repositories
- ✅ **Template System**: Example configurations provide guidance
- ✅ **Automatic Setup**: Creates initial config on first run

## Commands

```bash
# Show current status and available configurations
./switch-branches.sh

# Switch to a specific configuration
./switch-branches.sh [config-name]

# Examples
./switch-branches.sh stable
./switch-branches.sh pr-37-cursor-fix
./switch-branches.sh custom
```

## Troubleshooting

### Missing jq
```bash
# macOS
brew install jq

# Ubuntu/Debian
sudo apt-get install jq
```

### Reset to Clean State
```bash
rm branch-config.json
./switch-branches.sh  # Will recreate with stable only
```

### View Available Configurations
The script always shows available configurations when run without arguments, making it easy to see what's available.
