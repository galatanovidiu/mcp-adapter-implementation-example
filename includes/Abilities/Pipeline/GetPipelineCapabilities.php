<?php
/**
 * Get Pipeline Capabilities Ability
 *
 * Lists all available pipeline operations, transforms, and abilities.
 *
 * @package OvidiuGalatan\McpAdapterExample\Abilities\Pipeline
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Pipeline;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;
use OvidiuGalatan\McpAdapterExample\Pipeline\Transformations\TransformationRegistry;

/**
 * Class GetPipelineCapabilities
 *
 * Provides information about what's possible in pipelines.
 */
final class GetPipelineCapabilities implements RegistersAbility {
	/**
	 * Register the ability
	 *
	 * @return void
	 */
	public static function register(): void {
		wp_register_ability(
			'pipeline/get-capabilities',
			[
				'label' => 'Get Pipeline Capabilities',
				'description' => 'Get a complete list of what\'s possible in pipelines: available step types, transform operations, comparison operators, and callable WordPress abilities. Use this to discover what operations you can use when building pipelines.',
				'category' => 'system',
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'include' => [
							'type' => 'array',
							'description' => 'Which sections to include (default: all)',
							'items' => [
								'type' => 'string',
								'enum' => [ 'step_types', 'transforms', 'operators', 'abilities', 'examples' ],
							],
						],
					],
				],
				'output_schema' => [
					'type' => 'object',
					'properties' => [
						'step_types' => [
							'type' => 'array',
							'description' => 'Available step types for pipelines',
						],
						'transforms' => [
							'type' => 'object',
							'description' => 'Available transformation operations grouped by category',
							'properties' => [
								'array_operations' => [
									'type' => 'object',
									'properties' => [
										'operations' => [ 'type' => 'array' ],
										'description' => [ 'type' => 'string' ],
									],
								],
								'aggregations' => [
									'type' => 'object',
									'properties' => [
										'operations' => [ 'type' => 'array' ],
										'description' => [ 'type' => 'string' ],
									],
								],
								'string_operations' => [
									'type' => 'object',
									'properties' => [
										'operations' => [ 'type' => 'array' ],
										'description' => [ 'type' => 'string' ],
									],
								],
								'total' => [ 'type' => 'integer' ],
							],
						],
						'operators' => [
							'type' => 'object',
							'description' => 'Available comparison operators for conditionals',
						],
						'abilities' => [
							'type' => 'object',
							'description' => 'Summary of available WordPress abilities',
						],
						'examples' => [
							'type' => 'array',
							'description' => 'Quick example patterns',
						],
					],
				],
				'execute_callback' => [ self::class, 'execute' ],
				'permission_callback' => [ self::class, 'check_permission' ],
				'meta' => [
					'mcp' => [
						'public' => true,
						'type' => 'tool',
					],
					'annotations' => [
						'audience' => [ 'assistant' ],
						'priority' => 0.9,
						'readOnlyHint' => true,
					],
				],
			]
		);
	}

	/**
	 * Check permission
	 *
	 * @param array $input Input parameters
	 * @return bool
	 */
	public static function check_permission( array $input ): bool {
		return true; // Public information
	}

	/**
	 * Execute the ability
	 *
	 * @param array $input Input parameters
	 * @return array Capabilities information
	 */
	public static function execute( array $input ): array {
		$include = $input['include'] ?? [ 'step_types', 'transforms', 'operators', 'abilities', 'examples' ];

		$result = [];

		// Step types
		if ( in_array( 'step_types', $include, true ) ) {
			$result['step_types'] = [
				[
					'type' => 'ability',
					'description' => 'Execute a registered WordPress ability',
					'required' => [ 'ability' ],
					'optional' => [ 'input', 'output' ],
					'example' => '{"type":"ability","ability":"core/list-posts","input":{"posts_per_page":10},"output":"posts"}',
				],
				[
					'type' => 'transform',
					'description' => 'Apply data transformation (filter, map, count, etc.)',
					'required' => [ 'operation', 'input' ],
					'optional' => [ 'params', 'output' ],
					'example' => '{"type":"transform","operation":"count","input":"$posts","output":"total"}',
				],
				[
					'type' => 'loop',
					'description' => 'Iterate over an array executing steps for each item',
					'required' => [ 'input', 'steps' ],
					'optional' => [ 'itemVar', 'indexVar', 'output' ],
					'example' => '{"type":"loop","input":"$posts","itemVar":"post","steps":[...]}',
				],
				[
					'type' => 'conditional',
					'description' => 'Execute different steps based on a condition',
					'required' => [ 'condition' ],
					'optional' => [ 'then', 'else', 'output' ],
					'example' => '{"type":"conditional","condition":{"field":"$post.status","operator":"equals","value":"draft"},"then":[...]}',
				],
				[
					'type' => 'parallel',
					'description' => 'Execute multiple independent steps concurrently',
					'required' => [ 'steps' ],
					'optional' => [ 'output' ],
					'example' => '{"type":"parallel","steps":[...]}',
				],
				[
					'type' => 'try_catch',
					'description' => 'Execute steps with error handling',
					'required' => [ 'try' ],
					'optional' => [ 'catch', 'finally', 'output' ],
					'example' => '{"type":"try_catch","try":[...],"catch":[...],"finally":[...]}',
				],
				[
					'type' => 'sub_pipeline',
					'description' => 'Execute a nested pipeline',
					'required' => [ 'pipeline' ],
					'optional' => [ 'inputs', 'output' ],
					'example' => '{"type":"sub_pipeline","pipeline":{"steps":[...]},"inputs":{"var":"$value"}}',
				],
			];
		}

		// Transform operations
		if ( in_array( 'transforms', $include, true ) ) {
			TransformationRegistry::init();
			$all_transforms = TransformationRegistry::get_all();

			$result['transforms'] = [
				'array_operations' => [
					'operations' => array_values( array_intersect( $all_transforms, [ 'filter', 'map', 'pluck', 'unique', 'sort', 'reverse', 'slice', 'chunk', 'flatten', 'merge' ] ) ),
					'description' => 'Operations that work with arrays',
				],
				'aggregations' => [
					'operations' => array_values( array_intersect( $all_transforms, [ 'count', 'sum', 'average', 'min', 'max' ] ) ),
					'description' => 'Operations that aggregate data into a single value',
				],
				'string_operations' => [
					'operations' => array_values( array_intersect( $all_transforms, [ 'join', 'split', 'trim', 'uppercase', 'lowercase' ] ) ),
					'description' => 'Operations that work with strings',
				],
				'total' => count( $all_transforms ),
			];
		}

		// Comparison operators
		if ( in_array( 'operators', $include, true ) ) {
			$result['operators'] = [
				'equality' => [ 'equals', '==', '===', 'not_equals', '!=', '!==' ],
				'numeric' => [ 'greater_than', '>', 'less_than', '<', 'greater_than_or_equal', '>=', 'less_than_or_equal', '<=' ],
				'string' => [ 'contains', 'starts_with', 'ends_with' ],
				'membership' => [ 'in', 'not_in' ],
				'state' => [ 'empty', 'not_empty', 'null', 'not_null' ],
				'logical' => [ 'and', 'or' ],
			];
		}

		// Available abilities
		if ( in_array( 'abilities', $include, true ) ) {
			$all_abilities = function_exists( 'wp_get_all_abilities' ) ? wp_get_all_abilities() : [];

			$by_namespace = [];
			foreach ( array_keys( $all_abilities ) as $name ) {
				$parts = explode( '/', $name, 2 );
				$namespace = $parts[0] ?? 'unknown';
				if ( ! isset( $by_namespace[ $namespace ] ) ) {
					$by_namespace[ $namespace ] = 0;
				}
				$by_namespace[ $namespace ]++;
			}

			$result['abilities'] = [
				'total' => count( $all_abilities ),
				'by_namespace' => $by_namespace,
				'note' => 'Use ability name in format "namespace/ability-name". Example: "core/list-posts"',
			];
		}

		// Quick examples
		if ( in_array( 'examples', $include, true ) ) {
			$result['examples'] = [
				[
					'name' => 'Simple list and count',
					'pipeline' => [
						'steps' => [
							[ 'type' => 'ability', 'ability' => 'core/list-posts', 'input' => [ 'posts_per_page' => 10 ], 'output' => 'posts' ],
							[ 'type' => 'transform', 'operation' => 'count', 'input' => '$posts', 'output' => 'total' ],
						],
					],
				],
				[
					'name' => 'Loop through posts',
					'pipeline' => [
						'steps' => [
							[ 'type' => 'ability', 'ability' => 'core/list-posts', 'output' => 'posts' ],
							[
								'type' => 'loop',
								'input' => '$posts',
								'itemVar' => 'post',
								'steps' => [
									[ 'type' => 'ability', 'ability' => 'core/update-post-meta', 'input' => [ 'post_id' => '$post.ID', 'meta_key' => 'processed', 'meta_value' => true ] ],
								],
							],
						],
					],
				],
				[
					'name' => 'Filter and transform',
					'pipeline' => [
						'steps' => [
							[ 'type' => 'ability', 'ability' => 'core/list-posts', 'output' => 'posts' ],
							[ 'type' => 'transform', 'operation' => 'filter', 'input' => '$posts', 'params' => [ 'condition' => [ 'field' => 'post_status', 'operator' => 'equals', 'value' => 'publish' ] ], 'output' => 'published' ],
							[ 'type' => 'transform', 'operation' => 'pluck', 'input' => '$published', 'params' => [ 'field' => 'post_title' ], 'output' => 'titles' ],
						],
					],
				],
			];
		}

		return $result;
	}
}