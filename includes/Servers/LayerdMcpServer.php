<?php
/**
 * Layered MCP Server implementation following the "Layered Tool Pattern"
 * as described in Block Engineering's article about building MCP tools like ogres with layers.
 *
 * This server provides three layers:
 * 1. Discovery Layer: get_ability_categories - helps AI discover what's available
 * 2. Planning Layer: get_ability_info - provides detailed information for planning calls
 * 3. Execution Layer: execute_ability - actually performs the requested actions
 *
 * @package McpAdapterExample
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Servers;

use WP\MCP\Core\Contracts\McpServerInterface;
use WP\MCP\Domain\Prompts\McpPrompt;
use WP\MCP\Domain\Resources\McpResource;
use WP\MCP\Domain\Tools\McpTool;
use OvidiuGalatan\McpAdapterExample\Servers\LayeredMcpTool;
use WP\MCP\Infrastructure\ErrorHandling\NullMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\Infrastructure\McpTransportContext;
use WP\MCP\Transport\Infrastructure\McpRequestRouter;
use WP\MCP\Handlers\Initialize\InitializeHandler;
use WP\MCP\Handlers\Tools\ToolsHandler;
use OvidiuGalatan\McpAdapterExample\Servers\LayeredToolsHandler;
use WP\MCP\Handlers\Resources\ResourcesHandler;
use WP\MCP\Handlers\Prompts\PromptsHandler;
use WP\MCP\Handlers\System\SystemHandler;
use WP\MCP\Transport\Contracts\McpTransportInterface;

class LayerdMcpServer implements McpServerInterface {

    /**
     * Server configuration
     */
    private string $server_id = 'layered-abilities-server';
    private string $server_route_namespace = 'mcp-layered';
    private string $server_route = 'abilities';
    private string $server_name = 'Layered WordPress Abilities Server';
    private string $server_description = 'A layered MCP server that provides structured access to WordPress abilities through discovery, planning, and execution layers.';
    private string $server_version = '1.0.0';

    /**
     * Layered tools
     */
    private array $tools = array();
    private array $resources = array();
    private array $prompts = array();

    /**
     * Error and observability handlers
     */
    private $error_handler;
    private string $observability_handler = NullMcpObservabilityHandler::class;
    private bool $mcp_validation_enabled = true;
    private $transport_permission_callback;

    public function __construct(
        string $server_id,
        string $server_route_namespace,
        string $server_route,
        string $server_name,
        string $server_description,
        string $server_version,
        array $mcp_transports,
        string $error_handler,
        string $observability_handler,
        array $tools,
        array $resources,
        array $prompts,
        $transport_permission_callback
    ) {
        // Set server configuration
        $this->server_id = $server_id;
        $this->server_route_namespace = $server_route_namespace;
        $this->server_route = $server_route;
        $this->server_name = $server_name;
        $this->server_description = $server_description;
        $this->server_version = $server_version;
        
        // Initialize error handler - handle empty string case
        if (!empty($error_handler) && class_exists($error_handler)) {
            $this->error_handler = new $error_handler();
        } else {
            $this->error_handler = new NullMcpErrorHandler();
        }
        
        // Set observability handler - handle empty string case
        $this->observability_handler = !empty($observability_handler) ? $observability_handler : NullMcpObservabilityHandler::class;
        
        // Set transport permission callback - handle null case
        $this->transport_permission_callback = is_callable($transport_permission_callback) ? $transport_permission_callback : null;
        $this->mcp_validation_enabled = apply_filters('mcp_validation_enabled', true);
        
        // Initialize the layered tools (ignoring the passed tools/resources/prompts arrays)
        $this->initialize_layered_tools();
        
        // Initialize transport
        $this->initialize_transport($mcp_transports);
    }

    /**
     * Get category statistics from all registered abilities
     */
    private function get_category_statistics(): array {
        $all_abilities = wp_get_abilities();
        $namespaces = array();
        $categories = array();
        
        foreach ($all_abilities as $name => $ability) {
            // Extract namespace
            $name_str = (string) $name;
            $parts = explode('/', $name_str, 2);
            $namespace = (string) ($parts[0] ?? 'unknown');
            
            if (!isset($namespaces[$namespace])) {
                $namespaces[$namespace] = 0;
            }
            $namespaces[$namespace]++;
            
            // Extract categories from meta
            $meta = $ability->get_meta();
            if (isset($meta['categories']) && is_array($meta['categories'])) {
                foreach ($meta['categories'] as $category) {
                    if (!isset($categories[$category])) {
                        $categories[$category] = 0;
                    }
                    $categories[$category]++;
                }
            }
        }
        
        return array(
            'total_abilities' => count($all_abilities),
            'namespaces' => $namespaces,
            'categories' => $categories
        );
    }

    /**
     * Build dynamic description for get_ability_categories tool
     */
    private function build_dynamic_description(): string {
        $stats = $this->get_category_statistics();
        
        $description = "Get information about available WordPress ability categories. This MCP server provides {$stats['total_abilities']} abilities";
        
        if (!empty($stats['namespaces'])) {
            $namespace_list = array();
            foreach ($stats['namespaces'] as $namespace => $count) {
                $namespace_list[] = "$namespace ($count abilities)";
            }
            $description .= " across these namespaces: " . implode(', ', $namespace_list);
        }
        
        if (!empty($stats['categories'])) {
            $description .= "\n\n**Categories:**";
            $category_descriptions = array(
                'content' => 'Posts, pages, taxonomies, menus',
                'users' => 'User management and profiles',
                'media' => 'Files, attachments, images',
                'appearance' => 'Themes and customization',
                'system' => 'Database, updates, debugging',
                'engagement' => 'Comments and interactions',
                'management' => 'Administrative operations',
                'monitoring' => 'System monitoring and health',
                'uploads' => 'File upload operations',
                'themes' => 'Theme management',
                'comments' => 'Comment operations',
                'menus' => 'Navigation menu management'
            );
            
            foreach ($stats['categories'] as $category => $count) {
                $desc = isset($category_descriptions[$category]) ? $category_descriptions[$category] : ucfirst($category) . ' operations';
                $description .= "\n- $category: $desc ($count abilities)";
            }
        }
        
        $description .= "\n\nCall this first to discover what types of abilities are available.";
        
        return $description;
    }

    /**
     * Initialize the three layered tools following the pattern from Block Engineering
     */
    private function initialize_layered_tools(): void {
        // Layer 1: Discovery Tool
        $discovery_tool = new LayeredMcpTool(
            'get_ability_categories', // ability name
            'get_ability_categories', // tool name
            $this->build_dynamic_description(),
            array(
                'type' => 'object',
                'properties' => array(
                    'search' => array(
                        'type' => 'string',
                        'description' => 'Optional search term to filter categories'
                    )
                )
            ),
            array($this, 'handle_get_ability_categories')
        );
        $discovery_tool->set_mcp_server($this);
        $this->tools['get_ability_categories'] = $discovery_tool;

        // Layer 2: Planning Tool  
        $planning_tool = new LayeredMcpTool(
            'get_ability_info', // ability name
            'get_ability_info', // tool name
            'Get detailed information about a specific WordPress ability. You must call this before calling execute_ability to understand the required parameters and expected output.',
            array(
                'type' => 'object',
                'required' => array('ability_name'),
                'properties' => array(
                    'ability_name' => array(
                        'type' => 'string',
                        'description' => 'The full name of the ability (e.g., "wpmcp-example/list-posts")'
                    )
                )
            ),
            array($this, 'handle_get_ability_info')
        );
        $planning_tool->set_mcp_server($this);
        $this->tools['get_ability_info'] = $planning_tool;

        // Layer 3: Execution Tool
        $execution_tool = new LayeredMcpTool(
            'execute_ability', // ability name
            'execute_ability', // tool name
            'Execute a WordPress ability. Be sure to get ability info before calling this. This tool performs the actual operation.',
            array(
                'type' => 'object',
                'required' => array('ability_name'),
                'properties' => array(
                    'ability_name' => array(
                        'type' => 'string',
                        'description' => 'The full name of the ability to execute'
                    ),
                    'parameters' => array(
                        'type' => 'object',
                        'description' => 'The parameters to pass to the ability',
                        'additionalProperties' => true
                    )
                )
            ),
            array($this, 'handle_execute_ability')
        );
        $execution_tool->set_mcp_server($this);
        $this->tools['execute_ability'] = $execution_tool;
    }

    /**
     * Layer 1: Discovery Handler - Get ability categories
     */
    public function handle_get_ability_categories(array $input): array {
        $search = isset($input['search']) ? sanitize_text_field($input['search']) : '';
        
        $all_abilities = wp_get_abilities();
        $namespaces = array();
        $ability_categories = array();
        
        foreach ($all_abilities as $name => $ability) {
            // Extract namespace from ability name
            $name_str = (string) $name;
            $parts = explode('/', $name_str, 2);
            $namespace = (string) ($parts[0] ?? 'unknown');
            
            // Get categories from meta
            $meta = $ability->get_meta();
            $categories = isset($meta['categories']) && is_array($meta['categories']) ? $meta['categories'] : array();
            
            // Apply search filter
            if ($search) {
                $matches = stripos($namespace, $search) !== false || 
                          stripos($ability->get_label(), $search) !== false ||
                          stripos($ability->get_description(), $search) !== false;
                
                // Also search in categories
                foreach ($categories as $category) {
                    if (stripos($category, $search) !== false) {
                        $matches = true;
                        break;
                    }
                }
                
                if (!$matches) {
                    continue;
                }
            }
            
            // Group by namespace
            if (!isset($namespaces[$namespace])) {
                $namespaces[$namespace] = array(
                    'namespace' => $namespace,
                    'abilities' => array(),
                    'count' => 0
                );
            }
            
            $ability_data = array(
                'name' => $name_str,
                'label' => $ability->get_label(),
                'description' => $ability->get_description(),
                'categories' => $categories
            );
            
            $namespaces[$namespace]['abilities'][] = $ability_data;
            $namespaces[$namespace]['count']++;
            
            // Track categories
            foreach ($categories as $category) {
                if (!isset($ability_categories[$category])) {
                    $ability_categories[$category] = array(
                        'category' => $category,
                        'abilities' => array(),
                        'count' => 0
                    );
                }
                
                $ability_categories[$category]['abilities'][] = array(
                    'name' => $name_str,
                    'label' => $ability->get_label()
                );
                $ability_categories[$category]['count']++;
            }
        }
        
        return array(
            'categories' => array_values($namespaces),
            'ability_categories' => array_values($ability_categories),
            'total_abilities' => count($all_abilities),
            'statistics' => $this->get_category_statistics()
        );
    }

    /**
     * Layer 2: Planning Handler - Get detailed ability information
     */
    public function handle_get_ability_info(array $input): array {
        $ability_name = sanitize_text_field($input['ability_name']);
        $ability = wp_get_ability($ability_name);
        
        if (!$ability) {
            return array(
                'error' => array(
                    'code' => 'ability_not_found',
                    'message' => "Ability '{$ability_name}' not found"
                )
            );
        }
        
        return array(
            'name' => $ability->get_name(),
            'label' => $ability->get_label(),
            'description' => $ability->get_description(),
            'input_schema' => $ability->get_input_schema(),
            'output_schema' => $ability->get_output_schema(),
            'permission_required' => true, // We can't directly check if permission callback exists from outside
            'meta' => $ability->get_meta()
        );
    }

    /**
     * Layer 3: Execution Handler - Execute the ability
     */
    public function handle_execute_ability(array $input): array {
        $ability_name = sanitize_text_field($input['ability_name']);
        $parameters = isset($input['parameters']) && is_array($input['parameters']) ? $input['parameters'] : array();
        
        $ability = wp_get_ability($ability_name);
        
        if (!$ability) {
            return array(
                'error' => array(
                    'code' => 'ability_not_found',
                    'message' => "Ability '{$ability_name}' not found"
                )
            );
        }
        
        // Execute the ability
        $result = $ability->execute($parameters);
        
        if (is_wp_error($result)) {
            return array(
                'error' => array(
                    'code' => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                    'data' => $result->get_error_data()
                )
            );
        }
        
        return array(
            'result' => $result,
            'ability_name' => $ability_name,
            'executed_at' => current_time('c')
        );
    }

    // McpServerInterface implementation
    
    public function get_server_id(): string {
        return $this->server_id;
    }

    public function get_server_route_namespace(): string {
        return $this->server_route_namespace;
    }

    public function get_server_route(): string {
        return $this->server_route;
    }

    public function get_server_name(): string {
        return $this->server_name;
    }

    public function get_server_description(): string {
        return $this->server_description;
    }

    public function get_server_version(): string {
        return $this->server_version;
    }

    public function get_tools(): array {
        return $this->tools;
    }

    public function get_tool(string $tool_name): ?McpTool {
        return $this->tools[$tool_name] ?? null;
    }

    public function get_resources(): array {
        return $this->resources;
    }

    public function get_resource(string $resource_uri): ?McpResource {
        return $this->resources[$resource_uri] ?? null;
    }

    public function get_prompts(): array {
        return $this->prompts;
    }

    public function get_prompt(string $prompt_name): ?McpPrompt {
        return $this->prompts[$prompt_name] ?? null;
    }

    public function get_transport_permission_callback(): ?callable {
        return $this->transport_permission_callback;
    }

    public function is_mcp_validation_enabled(): bool {
        return $this->mcp_validation_enabled;
    }

    public function register_tools(array $abilities): void {
        // This layered server doesn't use the standard ability registration
        // It has its own fixed set of layered tools
    }

    public function register_resources(array $abilities): void {
        // Not implemented for this layered server
    }

    public function register_prompts(array $prompts): void {
        // Not implemented for this layered server
    }

    public function remove_tool(string $tool_name): bool {
        if (isset($this->tools[$tool_name])) {
            unset($this->tools[$tool_name]);
            return true;
        }
        return false;
    }

    public function remove_resource(string $resource_uri): bool {
        if (isset($this->resources[$resource_uri])) {
            unset($this->resources[$resource_uri]);
            return true;
        }
        return false;
    }

    public function remove_prompt(string $prompt_name): bool {
        if (isset($this->prompts[$prompt_name])) {
            unset($this->prompts[$prompt_name]);
            return true;
        }
        return false;
    }

    public function initialize_transport(array $mcp_transports): void {
        foreach ($mcp_transports as $mcp_transport) {
            // Check for interface implementation
            if (!in_array(McpTransportInterface::class, class_implements($mcp_transport) ?: array(), true)) {
                throw new \Exception(
                    esc_html__('MCP transport class must implement the McpTransportInterface.', 'mcp-adapter')
                );
            }

            // Interface-based instantiation with dependency injection
            $context = $this->create_transport_context();
            new $mcp_transport($context);
        }
    }

    /**
     * Create transport context with all required dependencies.
     */
    private function create_transport_context(): McpTransportContext {
        // Create handlers
        $initialize_handler = new InitializeHandler($this);
        $tools_handler = new LayeredToolsHandler($this);
        $resources_handler = new ResourcesHandler($this);
        $prompts_handler = new PromptsHandler($this);
        $system_handler = new SystemHandler($this);

        // Create context for the router first (without router to avoid circular dependency)
        $router_context = new McpTransportContext(
            array(
                'mcp_server' => $this,
                'initialize_handler' => $initialize_handler,
                'tools_handler' => $tools_handler,
                'resources_handler' => $resources_handler,
                'prompts_handler' => $prompts_handler,
                'system_handler' => $system_handler,
                'observability_handler' => $this->observability_handler,
                'request_router' => null,
                'transport_permission_callback' => $this->transport_permission_callback,
            )
        );

        // Create the router
        $request_router = new McpRequestRouter($router_context);

        // Create the final context with the router
        return new McpTransportContext(
            array(
                'mcp_server' => $this,
                'initialize_handler' => $initialize_handler,
                'tools_handler' => $tools_handler,
                'resources_handler' => $resources_handler,
                'prompts_handler' => $prompts_handler,
                'system_handler' => $system_handler,
                'observability_handler' => $this->observability_handler,
                'request_router' => $request_router,
                'transport_permission_callback' => $this->transport_permission_callback,
            )
        );
    }
}