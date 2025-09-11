<?php
/**
 * Custom Tools Handler for Layered MCP Server
 *
 * @package McpAdapterExample
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Servers;

use WP\MCP\Handlers\Tools\ToolsHandler;
use WP\MCP\Core\Contracts\McpServerInterface;

class LayeredToolsHandler extends ToolsHandler {
    
    public function __construct(McpServerInterface $mcp_server) {
        parent::__construct($mcp_server);
    }
    
    /**
     * Override the call_tool method to handle LayeredMcpTool instances
     */
    public function call_tool(array $message, int $request_id = 0): array {
        // Handle both direct params and nested params structure.
        $request_params = $message['params'] ?? $message;
        
        if (!isset($request_params['name'])) {
            return array(
                'error' => array(
                    'code' => -32602,
                    'message' => 'Tool name is required'
                )
            );
        }
        
        $tool_name = $request_params['name'];
        $arguments = $request_params['arguments'] ?? array();
        
        // Access the server through reflection since mcp property is private
        $reflection = new \ReflectionClass(parent::class);
        $mcp_property = $reflection->getProperty('mcp');
        $mcp_property->setAccessible(true);
        $mcp_server = $mcp_property->getValue($this);
        
        $tool = $mcp_server->get_tool($tool_name);
        
        if (!$tool) {
            return array(
                'error' => array(
                    'code' => -32601,
                    'message' => "Tool '{$tool_name}' not found"
                )
            );
        }
        
        // If it's our custom LayeredMcpTool, use the callback execution
        if ($tool instanceof LayeredMcpTool) {
            try {
                $result = $tool->execute_with_callback($arguments);
                
                return array(
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => json_encode($result, JSON_PRETTY_PRINT)
                        )
                    )
                );
            } catch (\Throwable $e) {
                return array(
                    'error' => array(
                        'code' => -32603,
                        'message' => 'Tool execution failed: ' . $e->getMessage()
                    )
                );
            }
        }
        
        // Fall back to parent implementation for regular tools
        return parent::call_tool($message, $request_id);
    }
}
