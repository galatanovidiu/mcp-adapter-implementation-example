<?php
/**
 * List Pipeline Examples Resource
 *
 * Exposes example pipeline definitions as an MCP resource.
 *
 * @package OvidiuGalatan\McpAdapterExample\Abilities\Pipeline
 */

declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Pipeline;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

/**
 * Class ListPipelineExamples
 *
 * MCP Resource that provides example pipeline definitions.
 */
final class ListPipelineExamples implements RegistersAbility {
	/**
	 * Register the resource
	 *
	 * @return void
	 */
	public static function register(): void {
		wp_register_ability(
			'pipeline/examples',
			[
				'label' => 'Pipeline Examples',
				'description' => 'Example pipeline definitions demonstrating various patterns: content processing, user management, error handling, WooCommerce inventory, and data aggregation. Use these as templates for building your own pipelines.',
				'category' => 'system',
				'execute_callback' => [ self::class, 'execute' ],
				'permission_callback' => [ self::class, 'check_permission' ],
				'meta' => [
					'mcp' => [
						'public' => true,
						'type' => 'resource',
						'uri' => 'pipeline://examples',
						'mime_type' => 'application/json',
					],
					'annotations' => [
						'audience' => [ 'assistant' ],
						'priority' => 0.9,
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
		return true; // Examples are public
	}

	/**
	 * Execute the resource
	 *
	 * @param array $input Input parameters
	 * @return array Example pipelines
	 */
	public static function execute( array $input ): array {
		return [
			'examples' => [
				[
					'name' => 'content-processing',
					'description' => 'Batch process content - analyze published posts and update metadata',
					'use_case' => 'Content analytics and bulk metadata updates',
					'pipeline' => [
						'steps' => [
							[
								'type' => 'ability',
								'ability' => 'core/list-posts',
								'input' => [
									'post_status' => 'publish',
									'posts_per_page' => 50,
								],
								'output' => 'posts',
								'description' => 'Fetch all published posts',
							],
							[
								'type' => 'transform',
								'operation' => 'filter',
								'input' => '$posts',
								'params' => [
									'condition' => [
										'field' => 'post_meta.ai_analyzed',
										'operator' => 'empty',
									],
								],
								'output' => 'unanalyzed_posts',
								'description' => 'Filter posts that haven\'t been analyzed',
							],
							[
								'type' => 'loop',
								'input' => '$unanalyzed_posts',
								'itemVar' => 'post',
								'steps' => [
									[
										'type' => 'ability',
										'ability' => 'core/update-post-meta',
										'input' => [
											'post_id' => '$post.ID',
											'meta_key' => 'ai_analyzed',
											'meta_value' => true,
										],
										'description' => 'Mark post as analyzed',
									],
								],
								'output' => 'processed_posts',
							],
						],
					],
				],
				[
					'name' => 'error-handling',
					'description' => 'Demonstrate error recovery with try/catch blocks',
					'use_case' => 'Robust workflows that handle failures gracefully',
					'pipeline' => [
						'steps' => [
							[
								'type' => 'try_catch',
								'try' => [
									[
										'type' => 'ability',
										'ability' => 'core/get-post',
										'input' => [ 'post_id' => 999999 ],
										'output' => 'post',
									],
								],
								'catch' => [
									[
										'type' => 'ability',
										'ability' => 'core/create-post',
										'input' => [
											'post_title' => 'Fallback Post',
											'post_content' => 'Created as fallback',
											'post_status' => 'draft',
										],
										'output' => 'fallback_post',
									],
								],
								'output' => 'result',
							],
						],
					],
				],
				[
					'name' => 'data-aggregation',
					'description' => 'Extract and aggregate data using transformations',
					'use_case' => 'Reporting and analytics without sending raw data through model',
					'pipeline' => [
						'steps' => [
							[
								'type' => 'ability',
								'ability' => 'core/list-posts',
								'input' => [ 'posts_per_page' => 100 ],
								'output' => 'posts',
							],
							[
								'type' => 'parallel',
								'steps' => [
									[
										'type' => 'transform',
										'operation' => 'count',
										'input' => '$posts',
										'output' => 'total_posts',
									],
									[
										'type' => 'transform',
										'operation' => 'pluck',
										'input' => '$posts',
										'params' => [ 'field' => 'post_author' ],
										'output' => 'author_ids',
									],
								],
							],
							[
								'type' => 'transform',
								'operation' => 'unique',
								'input' => '$author_ids',
								'output' => 'unique_authors',
							],
						],
					],
				],
				[
					'name' => 'conditional-workflow',
					'description' => 'Use conditional logic to handle different scenarios',
					'use_case' => 'Branch logic based on data conditions',
					'pipeline' => [
						'steps' => [
							[
								'type' => 'ability',
								'ability' => 'core/list-posts',
								'input' => [ 'posts_per_page' => 10 ],
								'output' => 'posts',
							],
							[
								'type' => 'loop',
								'input' => '$posts',
								'itemVar' => 'post',
								'steps' => [
									[
										'type' => 'conditional',
										'condition' => [
											'field' => '$post.post_status',
											'operator' => 'equals',
											'value' => 'draft',
										],
										'then' => [
											[
												'type' => 'ability',
												'ability' => 'core/update-post',
												'input' => [
													'post_id' => '$post.ID',
													'post_status' => 'pending',
												],
											],
										],
										'else' => [
											[
												'type' => 'ability',
												'ability' => 'core/update-post-meta',
												'input' => [
													'post_id' => '$post.ID',
													'meta_key' => 'reviewed',
													'meta_value' => true,
												],
											],
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}
}
