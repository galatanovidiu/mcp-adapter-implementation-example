# MCP Adapter Implementation Example

This WordPress plugin demonstrates how to implement and test Model Context Protocol (MCP) servers using the MCP Adapter framework. It provides a concrete implementation example for LLMs to understand how to build, configure, and test MCP-compliant WordPress plugins.

## Overview

The MCP Adapter Implementation Example is a WordPress plugin that:

- Implements MCP Protocol 2025-06-18 specification
- Provides WordPress-specific abilities through MCP
- Demonstrates proper MCP server architecture
- Includes comprehensive testing framework using FastMCP
- Shows best practices for MCP integration with WordPress

## Key Components

### 1. MCP Server Implementation
- **Protocol Compliance**: Full MCP 2025-06-18 support
- **WordPress Integration**: Native WordPress functionality exposed via MCP
- **Tool Definitions**: WordPress operations as MCP tools
- **Resource Management**: WordPress content as MCP resources
- **Error Handling**: Proper MCP error responses

### 2. Available Tools (Abilities)

#### Core WordPress Tools
- `discover_abilities` - List all available MCP tools
- `get_ability_info` - Get detailed information about specific tools
- `execute_ability` - Execute WordPress operations

#### WordPress Content Management
- `core-list-posts` - Retrieve WordPress posts with filtering
- `core-create-post` - Create new WordPress posts
- `core-get-post` - Retrieve specific WordPress post by ID
- `core-update-post` - Update existing WordPress posts
- `core-delete-post` - Delete WordPress posts

#### WordPress User Management
- `core-list-users` - List WordPress users
- `core-get-user` - Get specific user information
- `core-create-user` - Create new WordPress users

#### WordPress System Tools
- `core-list-plugins` - List installed WordPress plugins
- `core-list-themes` - List available WordPress themes
- `core-get-site-info` - Get WordPress site information

### 3. Testing Framework

#### FastMCP Testing Suite
Located in `/FastMcp/` directory:

```
FastMcp/
├── mcpServers.json          # Server configurations
├── run-all-tests.sh         # Comprehensive test runner
├── tests/
│   ├── test_basic_connectivity.py
│   ├── test_error_handling.py
│   ├── test_integration.py
│   └── config/
│       └── servers.py       # Server configuration loader
└── logs/                    # Test execution logs
```

#### Test Categories
1. **Basic Connectivity** - Server availability and protocol compliance
2. **Tools Testing** - Tool discovery and execution validation
3. **Resources Testing** - Resource management and access
4. **Error Handling** - Error scenarios and recovery
5. **WordPress Integration** - WordPress-specific functionality
6. **Protocol Compliance** - MCP 2025-06-18 specification adherence

## Usage for LLMs

### 1. Running Tests

#### Quick Start
```bash
cd /path/to/mcp-adapter/FastMcp
./run-all-tests.sh --mcp-example-only
```

#### Comprehensive Testing
```bash
# Test all servers and suites
./run-all-tests.sh

# Test specific server
./run-all-tests.sh --servers mcp-example-http

# Test specific test suites
./run-all-tests.sh --suites basic,wordpress-integration

# Generate HTML report
./run-all-tests.sh --output html

# Verbose mode with parallel execution
./run-all-tests.sh --verbose --parallel
```

#### Test Categories
```bash
# Test only WordPress integration features
./run-all-tests.sh --suites wordpress-integration

# Test error handling and recovery
./run-all-tests.sh --suites error-handling

# Test protocol compliance
./run-all-tests.sh --suites protocol-compliance
```

### 2. Configuration

#### Server Configuration (mcpServers.json)
```json
{
  "protocolVersion": "2025-06-18",
  "mcpServers": {
    "mcp-example-http": {
      "name": "MCP Implementation Example HTTP Server",
      "transport": "http",
      "url": "http://mcp-adapter.test/wp-json/mcp-example/mcp",
      "auth": {
        "type": "basic",
        "username": "your_username",
        "password": "your_app_password"
      },
      "capabilities": {
        "tools": true,
        "resources": true,
        "prompts": true,
        "logging": true
      }
    }
  }
}
```

#### Environment Variables
```bash
# Override default URLs
export MCP_TEST_URL="http://your-site.test/wp-json/mcp-example/mcp"

# Set credentials
export WP_API_USERNAME="your_username"
export WP_API_PASSWORD="your_app_password"
```

### 3. Testing Specific Features

#### WordPress Post Management
```bash
# Test post CRUD operations
./run-all-tests.sh --suites wordpress-integration -k "post"

# Test with verbose output
./run-all-tests.sh --verbose --suites wordpress-integration
```

#### Error Scenarios
```bash
# Test error handling
./run-all-tests.sh --suites error-handling

# Test with retries
./run-all-tests.sh --retries 5 --suites error-handling
```

#### Protocol Compliance
```bash
# Validate MCP 2025-06-18 compliance
./run-all-tests.sh --suites protocol-compliance

# Test capabilities negotiation
./run-all-tests.sh --suites basic -k "capabilities"
```

### 4. Understanding Test Results

#### Console Output
- ✓ Green: Tests passed
- ✗ Red: Tests failed
- Yellow: Warnings or skipped tests
- Blue: Informational messages

#### HTML Reports
Generated in `FastMcp/reports/` with:
- Server-by-server results
- Test suite breakdown
- Execution times
- Detailed logs
- Success/failure statistics

#### Log Files
Located in `FastMcp/logs/[server]/`:
- Individual test execution logs
- Error details and stack traces
- Performance metrics
- Protocol messages

### 5. Common Test Scenarios

#### Validating New MCP Tool
```bash
# Test specific tool functionality
./run-all-tests.sh --suites tools -k "your_new_tool"
```

#### Performance Testing
```bash
# Test with extended timeout
./run-all-tests.sh --timeout 600 --suites all
```

#### Debugging Issues
```bash
# Verbose mode with single server
./run-all-tests.sh --verbose --servers mcp-example-http --suites basic
```

## Expected Test Results

### Successful Test Run
```
================================================================
                   FASTMCP TEST SUMMARY
================================================================
Protocol Version: 2025-06-18
Timestamp: 2025-01-XX XX:XX:XX

SERVERS:
  Total:  6
  Passed: 6
  Failed: 0

TESTS:
  Total:  48
  Passed: 48
  Failed: 0

SUCCESS RATE: 100%
================================================================
```

### Typical Test Coverage
- **Basic Connectivity**: Server responsiveness, protocol handshake
- **Tool Discovery**: Available tools match expectations
- **Tool Execution**: All tools execute without errors
- **Error Handling**: Proper error responses for invalid inputs
- **WordPress Integration**: CRUD operations work correctly
- **Protocol Compliance**: Messages follow MCP 2025-06-18 spec

## Troubleshooting

### Common Issues

1. **Connection Failures**
   - Check WordPress site is accessible
   - Verify authentication credentials
   - Ensure MCP endpoint is active

2. **Test Failures**
   - Check WordPress user permissions
   - Verify plugin is activated
   - Review server logs for errors

3. **Protocol Errors**
   - Ensure server supports MCP 2025-06-18
   - Check capability negotiations
   - Validate message formats

### Debug Commands
```bash
# Test connectivity only
./run-all-tests.sh --dry-run --verbose

# Single test with full debug
./run-all-tests.sh --verbose --servers mcp-example-http --suites basic

# Check server configuration
python3 -c "
import sys
sys.path.append('FastMcp/tests')
from config.servers import *
print(f'Protocol: {PROTOCOL_VERSION}')
for name, config in SERVER_CONFIGS.items():
    print(f'{name}: {config.transport_type} - {config.protocol_version}')
"
```

## Integration Examples

### Custom Tool Development
```python
# Example: Adding a custom WordPress tool
def register_custom_tool():
    @mcp.tool()
    async def custom_wordpress_operation(param: str) -> dict:
        """Custom WordPress operation."""
        # Implementation here
        return {"result": f"Processed {param}"}
```

### Test Case Creation
```python
# Example: Testing custom functionality
async def test_custom_tool(mcp_client, server_name):
    async with mcp_client(server_name) as client:
        test_client = MCPTestClient(client)

        result = await test_client.call_tool("custom_wordpress_operation", {
            "param": "test_value"
        })

        assert result["result"] == "Processed test_value"
```

## Best Practices

1. **Always run tests before deploying** changes
2. **Use specific test suites** for targeted validation
3. **Check protocol compliance** for MCP compatibility
4. **Monitor test performance** and optimize slow operations
5. **Review test logs** for detailed debugging information
6. **Validate error handling** scenarios thoroughly
7. **Test with different WordPress configurations**

## Contributing

When contributing to this plugin:

1. **Run full test suite**: `./run-all-tests.sh`
2. **Validate protocol compliance**: `./run-all-tests.sh --suites protocol-compliance`
3. **Test WordPress integration**: `./run-all-tests.sh --suites wordpress-integration`
4. **Check error scenarios**: `./run-all-tests.sh --suites error-handling`
5. **Generate reports**: `./run-all-tests.sh --output html`

This testing framework ensures that all MCP implementations remain compatible with the 2025-06-18 protocol specification while providing robust WordPress functionality through the MCP interface.