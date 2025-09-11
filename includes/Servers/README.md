# Layered MCP Server Implementation

This directory contains a complete implementation of the "Layered Tool Pattern" for MCP servers, as described in [Block Engineering's article about building MCP tools like ogres with layers](https://engineering.block.xyz/blog/build-mcp-tools-like-ogres-with-layers).

## Overview

Instead of exposing all WordPress abilities as individual MCP tools (which can overwhelm AI agents), this layered approach provides just 3 tools that guide the AI through a structured discovery and execution process:

### Layer 1: Discovery (`get_ability_categories`)
- **Purpose**: Help AI discover what types of abilities are available
- **Input**: Optional search filter
- **Output**: Categorized list of abilities by namespace
- **Usage**: "What can I do with this WordPress site?"

### Layer 2: Planning (`get_ability_info`)
- **Purpose**: Provide detailed information about a specific ability
- **Input**: Ability name (e.g., "wpmcp-example/list-posts")
- **Output**: Complete schema information, permissions, and metadata
- **Usage**: "How do I call this specific ability?"

### Layer 3: Execution (`execute_ability`)
- **Purpose**: Actually execute the WordPress ability
- **Input**: Ability name and parameters
- **Output**: The actual result from the WordPress ability
- **Usage**: "Run this ability with these parameters"

## Files

- **`LayerdMcpServer.php`** - Main server implementation
- **`LayeredMcpTool.php`** - Custom tool class that supports callback execution
- **`LayeredToolsHandler.php`** - Custom tools handler for the layered approach
- **`LayeredServerExample.php`** - Example usage and initialization
- **`README.md`** - This documentation

## Benefits of the Layered Approach

1. **Reduced Cognitive Load**: AI agents see only 3 tools instead of dozens
2. **Guided Discovery**: AI learns what's available before attempting to use it
3. **Better Error Handling**: Each layer can provide specific guidance
4. **Scalability**: Works with any number of underlying WordPress abilities
5. **Debugging**: Easy to trace the AI's decision-making process

## Usage Example

```php
// 1. AI discovers available abilities
$categories = get_ability_categories(['search' => 'posts']);

// 2. AI gets detailed info about a specific ability
$info = get_ability_info(['ability_name' => 'wpmcp-example/list-posts']);

// 3. AI executes the ability with proper parameters
$result = execute_ability([
    'ability_name' => 'wpmcp-example/list-posts',
    'parameters' => [
        'post_type' => ['post'],
        'limit' => 10
    ]
]);
```

## Integration

To use this layered server in your WordPress plugin:

```php
use OvidiuGalatan\McpAdapterExample\Servers\LayerdMcpServer;
use WP\MCP\Transport\McpRestTransport;

// Create and initialize the server
$server = new LayerdMcpServer();
$server->initialize_transport([McpRestTransport::class]);
```

The server will be available at: `/wp-json/mcp-layered/abilities/`

## Comparison with Traditional Approach

**Traditional MCP Server:**
- Exposes 15+ individual tools
- AI must guess which tools to use
- High chance of incorrect tool selection
- Difficult to debug AI reasoning

**Layered MCP Server:**
- Exposes exactly 3 tools
- AI follows guided discovery process
- Clear progression: discover → plan → execute
- Easy to trace AI decision-making

This implementation demonstrates how thoughtful API design can significantly improve AI agent performance and reliability.
