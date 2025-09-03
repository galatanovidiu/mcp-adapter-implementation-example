# MCP Adapter Implementation Example

A WordPress plugin demonstrating MCP Adapter integration and implementation patterns.

[Abilities API](https://github.com/WordPress/abilities-api)
[MCP Adapter](https://github.com/WordPress/mcp-adapter)

## Purpose

This plugin serves as an implementation example to demonstrate:
- WordPress Abilities API functionality
- MCP Adapter integration
- REST transport layer for MCP communication
- Best practices for MCP server implementation in WordPress

## Features

The plugin implements a comprehensive set of MCP abilities that demonstrate WordPress content management through the Model Context Protocol:

### Posts Management (CRUD Operations)
- **Create Post** (`wpmcp-example/create-post`) - Create posts for any public post type with HTML content and WordPress block support
- **Get Post** (`wpmcp-example/get-post`) - Retrieve individual posts with full content and metadata
- **List Posts** (`wpmcp-example/list-posts`) - Query and filter posts across different post types
- **Update Post** (`wpmcp-example/update-post`) - Modify existing posts including content, metadata, and status
- **Delete Post** (`wpmcp-example/delete-post`) - Remove posts from the system

### Post Metadata Management
- **List Post Meta Keys** (`wpmcp-example/list-post-meta-keys`) - Discover available metadata fields
- **Get Post Meta** (`wpmcp-example/get-post-meta`) - Retrieve specific metadata values
- **Update Post Meta** (`wpmcp-example/update-post-meta`) - Modify post metadata
- **Delete Post Meta** (`wpmcp-example/delete-post-meta`) - Remove metadata entries

### Gutenberg Blocks Integration
- **List Block Types** (`wpmcp-example/list-block-types`) - Discover available Gutenberg blocks with descriptions, categories, and attribute schemas for proper block comment generation

### Taxonomy & Terms Management
- **List Taxonomies** (`wpmcp-example/list-taxonomies`) - Explore available taxonomies and their configurations
- **Get Terms** (`wpmcp-example/get-terms`) - Retrieve terms from specific taxonomies
- **Create Term** (`wpmcp-example/create-term`) - Add new terms to taxonomies
- **Update Term** (`wpmcp-example/update-term`) - Modify existing terms
- **Delete Term** (`wpmcp-example/delete-term`) - Remove terms from taxonomies

### Post-Term Relationships
- **Attach Post Terms** (`wpmcp-example/attach-post-terms`) - Associate posts with taxonomy terms
- **Detach Post Terms** (`wpmcp-example/detach-post-terms`) - Remove term associations from posts

**Technical Stack:**
- PHP 8.1+
- WordPress Abilities API
- MCP Adapter with REST transport
- Jetpack Autoloader

**Code Quality:**
- WordPress Coding Standards (PHPCS)
- PHPStan Level 8 static analysis
- Automated code formatting

## Dependencies

**Abilities API**: Currently hardcoded in this implementation. As soon as it becomes available as a Composer package, it will be loaded as a standard dependency.

## Installation

### Prerequisites

- WordPress 6.0+ (recommended)
- PHP 8.1 or higher
- Composer installed on your system
- WordPress Application Passwords enabled (for MCP client authentication)

### Steps

1. **Clone or download** this plugin to your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone [repository-url] mcp-adapter-implementation-example
   # OR extract the plugin files to wp-content/plugins/mcp-adapter-implementation-example/
   ```

2. **Install dependencies** by running Composer in the plugin directory:
   ```bash
   cd wp-content/plugins/mcp-adapter-implementation-example/
   composer install
   ```

3. **Activate the plugin** through the WordPress admin dashboard:
   - Go to Plugins → Installed Plugins
   - Find "MCP Adapter Implementation Example"
   - Click "Activate"

4. **Verify installation**:
   - The plugin will automatically register MCP abilities
   - An MCP server endpoint will be created at: `/wp-json/mcp-adapter-example/mcp`
   - Check WordPress admin for any error messages

### Post-Installation

- Configure your MCP client using the server configuration provided in the next section
- Generate Application Passwords for secure API access
- Test the connection using an MCP-compatible tool or client

## Usage

This is an educational/example plugin demonstrating MCP Adapter implementation patterns. Once activated, it exposes WordPress content management capabilities through the Model Context Protocol for AI agent interaction.

The plugin creates an MCP server at the `mcp-adapter-example` endpoint with tools prefixed as `wpmcp-example/` to demonstrate proper tool naming conventions.

## Development

### Code Quality Commands

```bash
# Check coding standards
composer lint:php

# Fix coding standards automatically
composer lint:php:fix

# Run static analysis
composer lint:php:stan

# Format code (alias for lint:php:fix)
composer format
```

### Documentation

- [Coding Standards](docs/coding-standards.md) - Detailed setup and usage guide

## MCP Server Configuration 

```json
{
  "mcpServers": {
    "wordpress-mcp.test-mcp-tools": {
      "command": "npx", // or full path to npx
      "args": [
        "-y",
        "@automattic/mcp-wordpress-remote@latest"      ],
      "env": {
        "WP_API_URL": "http://your-wordpress-site.com/wp-json/mcp-adapter-example/mcp",
        "WP_API_USERNAME": "your wordpress username",
        "WP_API_PASSWORD": "your wordpress application password", // https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/
        "LOG_FILE": "full path to lofs file"
      }
    }
  }
}
```