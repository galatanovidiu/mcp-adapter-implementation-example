<?php
/**
 * Custom Layered MCP Tool that extends McpTool to support custom callback handling
 *
 * @package McpAdapterExample
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Servers;

use WP\MCP\Domain\Tools\McpTool;
use WP\MCP\Core\Contracts\McpServerInterface;

class LayeredMcpTool extends McpTool {
    
    /**
     * Custom callback handler
     */
    private $callback_handler;
    
    public function __construct(
        string $ability,
        string $name,
        string $description,
        array $input_schema,
        callable $callback_handler,
        ?string $title = null,
        ?array $output_schema = null,
        array $annotations = array()
    ) {
        parent::__construct($ability, $name, $description, $input_schema, $title, $output_schema, $annotations);
        $this->callback_handler = $callback_handler;
    }
    
    /**
     * Execute the tool with custom callback handling
     */
    public function execute_with_callback(array $input): array {
        if (!is_callable($this->callback_handler)) {
            return array(
                'error' => array(
                    'code' => 'invalid_callback',
                    'message' => 'Tool callback is not callable'
                )
            );
        }
        
        return call_user_func($this->callback_handler, $input);
    }
}
