<?php
/**
 * Execute Pipeline Ability
 *
 * Executes a declarative pipeline.
 *
 * @package OvidiuGalatan\McpAdapterExample\Abilities\Pipeline
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Pipeline;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;
use OvidiuGalatan\McpAdapterExample\Pipeline\PipelineExecutor;
use OvidiuGalatan\McpAdapterExample\Pipeline\PipelineValidator;
use OvidiuGalatan\McpAdapterExample\Pipeline\DataTokenizer;

/**
 * Class ExecutePipeline
 *
 * Executes declarative pipelines with advanced control flow.
 */
final class ExecutePipeline implements RegistersAbility {
	/**
	 * Register the ability
	 *
	 * @return void
	 */
	public static function register(): void {
		wp_register_ability(
			'mcp-adapter/execute-pipeline',
			[
				'label' => 'Execute Pipeline',
				'description' => 'Execute a declarative pipeline for multi-step WordPress operations. Use when: 1) Processing 10+ items in a loop (posts, users, products), 2) Chaining 3+ operations with data flowing between them, 3) Applying conditional logic or error handling, 4) Transforming data (filter, map, aggregate) without sending it through the model. Pipeline format: {"steps":[...]} where each step has "type" (ability|transform|loop|conditional|parallel|try_catch|sub_pipeline) and optional "output" for storing results. Steps reference previous outputs using $variable syntax (e.g. "$posts", "$post.ID"). Available transforms: filter, map, pluck, unique, sort, count, sum, average, join. Example - process all posts: {"steps":[{"type":"ability","ability":"core/list-posts","input":{"posts_per_page":50},"output":"posts"},{"type":"loop","input":"$posts","itemVar":"post","steps":[{"type":"ability","ability":"core/update-post-meta","input":{"post_id":"$post.ID","meta_key":"processed","meta_value":true}}]}]}',
				'category' => 'system',
				'input_schema' => [
					'type' => 'object',
					'properties' => [
						'pipeline' => [
							'type' => 'object',
							'description' => 'Pipeline definition with steps to execute',
							'properties' => [
								'steps' => [
									'type' => 'array',
									'description' => 'Array of pipeline steps',
									'items' => [
										'type' => 'object',
										'properties' => [
											'type' => [
												'type' => 'string',
												'enum' => [ 'ability', 'transform', 'conditional', 'loop', 'parallel', 'try_catch', 'sub_pipeline' ],
												'description' => 'Step type',
											],
											'output' => [
												'type' => 'string',
												'description' => 'Variable name to store step result (without $ prefix)',
											],
										],
										'required' => [ 'type' ],
									],
								],
							],
							'required' => [ 'steps' ],
						],
						'context' => [
							'type' => 'object',
							'description' => 'Initial context variables',
							'additionalProperties' => true,
						],
						'tokenize_sensitive' => [
							'type' => 'boolean',
							'description' => 'Whether to tokenize sensitive data (default: true)',
							'default' => true,
						],
						'validate_only' => [
							'type' => 'boolean',
							'description' => 'Only validate pipeline without executing (dry-run)',
							'default' => false,
						],
						'limits' => [
							'type' => 'object',
							'description' => 'Resource limits for pipeline execution',
							'properties' => [
								'max_steps' => [
									'type' => 'integer',
									'description' => 'Maximum number of steps (default: 1000)',
									'default' => 1000,
								],
								'max_depth' => [
									'type' => 'integer',
									'description' => 'Maximum nesting depth (default: 10)',
									'default' => 10,
								],
								'timeout' => [
									'type' => 'integer',
									'description' => 'Timeout in seconds (default: 300)',
									'default' => 300,
								],
							],
						],
					],
					'required' => [ 'pipeline' ],
				],
				'output_schema' => [
					'type' => 'object',
					'properties' => [
						'success' => [
							'type' => 'boolean',
							'description' => 'Whether pipeline executed successfully',
						],
						'result' => [
							'description' => 'Result from last step',
						],
						'context' => [
							'type' => 'object',
							'description' => 'Final context variables',
						],
						'stats' => [
							'type' => 'object',
							'description' => 'Execution statistics',
							'properties' => [
								'steps_executed' => [ 'type' => 'integer' ],
								'duration' => [ 'type' => 'number' ],
								'memory_peak' => [ 'type' => 'integer' ],
								'steps_by_type' => [ 'type' => 'object' ],
							],
						],
						'validation_errors' => [
							'type' => 'array',
							'items' => [ 'type' => 'string' ],
							'description' => 'Validation errors (if validate_only=true)',
						],
					],
				],
				'permission_callback' => [ self::class, 'check_permission' ],
				'execute_callback' => [ self::class, 'execute' ],
				'meta' => [
					'mcp' => [
						'public' => true,
						'type' => 'tool',
					],
					'annotations' => [
						'audience' => [ 'assistant' ],
						'priority' => 0.9,
						'readOnlyHint' => false,
						'destructiveHint' => false,
						'idempotentHint' => false,
					],
				],
			]
		);
	}

	/**
	 * Check if user has permission to execute pipelines
	 *
	 * @param array $input Input parameters
	 * @return bool True if permitted
	 */
	public static function check_permission( array $input ): bool {
		// Only administrators can execute pipelines
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute the pipeline
	 *
	 * @param array $input Input parameters
	 * @return array Execution result
	 */
	public static function execute( array $input ): array {
		$pipeline = $input['pipeline'] ?? [];
		$context = $input['context'] ?? [];
		$tokenize = $input['tokenize_sensitive'] ?? true;
		$validate_only = $input['validate_only'] ?? false;
		$limits = $input['limits'] ?? [];

		// Validate pipeline
		$validator = new PipelineValidator();
		$is_valid = $validator->validate( $pipeline );

		if ( ! $is_valid ) {
			return [
				'success' => false,
				'validation_errors' => $validator->get_errors(),
				'message' => 'Pipeline validation failed',
			];
		}

		// If validate-only mode, return validation result
		if ( $validate_only ) {
			return [
				'success' => true,
				'validation_errors' => [],
				'message' => 'Pipeline is valid',
			];
		}

		// Tokenize sensitive data in initial context
		$tokenizer = null;
		if ( $tokenize ) {
			$tokenizer = new DataTokenizer();
			$context = $tokenizer->tokenize( $context );
		}

		// Execute pipeline
		try {
			$executor = new PipelineExecutor( $limits );
			$result = $executor->execute( $pipeline, $context );

			// Detokenize result if needed
			if ( $tokenizer ) {
				$result['result'] = $tokenizer->detokenize( $result['result'] );
				$result['context'] = $tokenizer->detokenize( $result['context'] );

				// Add tokenization stats
				$result['stats']['tokens_used'] = $tokenizer->get_token_count();
			}

			return $result;

		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'error' => [
					'message' => $e->getMessage(),
					'type' => get_class( $e ),
					'code' => $e->getCode(),
				],
				'stats' => [
					'steps_executed' => $executor->get_stats()['steps_executed'] ?? 0,
					'duration' => $executor->get_stats()['duration'] ?? 0,
				],
			];
		}
	}
}
