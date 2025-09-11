<?php
/**
 * Example usage of the Layered MCP Server
 *
 * This file demonstrates how to instantiate and use the LayerdMcpServer
 * following the "Layered Tool Pattern" from Block Engineering.
 *
 * @package McpAdapterExample
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Servers;

use WP\MCP\Transport\Http\RestTransport;

/**
 * Example class showing how to use the LayerdMcpServer
 */
class LayeredServerExample {
    
    /**
     * Initialize and register the layered server
     */
    public static function init(): void {
        // Hook into WordPress initialization
        add_action('init', array(self::class, 'register_layered_server'));
    }
    
    /**
     * Register the layered MCP server
     */
    public static function register_layered_server(): void {
        // Create the layered server instance
        $layered_server = new LayerdMcpServer();
        
        // Initialize with REST transport
        $layered_server->initialize_transport([
            RestTransport::class
        ]);
        
        // The server is now ready to handle MCP requests at:
        // /wp-json/mcp-layered/abilities/
        //
        // Available tools:
        // 1. get_ability_categories - Discovery layer
        // 2. get_ability_info - Planning layer  
        // 3. execute_ability - Execution layer
    }
    
    /**
     * Example of how an AI agent would use the layered server
     */
    public static function example_usage_flow(): array {
        $layered_server = new LayerdMcpServer();
        
        // Step 1: Discovery - Find what abilities are available
        $discovery_tool = $layered_server->get_tool('get_ability_categories');
        if ($discovery_tool instanceof LayeredMcpTool) {
            $categories = $discovery_tool->execute_with_callback(array(
                'search' => 'posts' // Optional search filter
            ));
            
            // AI now knows what categories of abilities exist
            // Example result: 
            // {
            //   "categories": [
            //     {
            //       "namespace": "wpmcp-example",
            //       "abilities": [
            //         {"name": "wpmcp-example/list-posts", "label": "List Posts", ...},
            //         {"name": "wpmcp-example/create-post", "label": "Create Post", ...}
            //       ],
            //       "count": 2
            //     }
            //   ],
            //   "total_abilities": 15
            // }
        }
        
        // Step 2: Planning - Get detailed info about a specific ability
        $planning_tool = $layered_server->get_tool('get_ability_info');
        if ($planning_tool instanceof LayeredMcpTool) {
            $ability_info = $planning_tool->execute_with_callback(array(
                'ability_name' => 'wpmcp-example/list-posts'
            ));
            
            // AI now understands the input schema, output schema, and requirements
            // Example result:
            // {
            //   "name": "wpmcp-example/list-posts",
            //   "label": "List Posts",
            //   "description": "List and search WordPress posts...",
            //   "input_schema": {...},
            //   "output_schema": {...},
            //   "permission_required": true,
            //   "meta": {...}
            // }
        }
        
        // Step 3: Execution - Actually run the ability
        $execution_tool = $layered_server->get_tool('execute_ability');
        if ($execution_tool instanceof LayeredMcpTool) {
            $result = $execution_tool->execute_with_callback(array(
                'ability_name' => 'wpmcp-example/list-posts',
                'parameters' => array(
                    'post_type' => array('post'),
                    'limit' => 5,
                    'post_status' => array('publish')
                )
            ));
            
            // AI gets the actual data from WordPress
            // Example result:
            // {
            //   "result": {
            //     "posts": [...],
            //     "total": 5,
            //     "found_posts": 25
            //   },
            //   "ability_name": "wpmcp-example/list-posts",
            //   "executed_at": "2025-01-10T12:00:00+00:00"
            // }
        }
        
        return array(
            'discovery' => $categories ?? array(),
            'planning' => $ability_info ?? array(),
            'execution' => $result ?? array()
        );
    }
}

// Initialize the example (uncomment to activate)
// LayeredServerExample::init();
