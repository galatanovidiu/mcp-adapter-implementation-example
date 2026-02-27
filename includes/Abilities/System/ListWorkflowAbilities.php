<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListWorkflowAbilities implements RegistersAbility {

	private const DEFAULT_LIMIT = 20;
	private const MAX_LIMIT = 50;

	public static function register(): void {
		\wp_register_ability(
			'core/list-workflow-abilities',
			array(
				'label'               => 'List Workflow Abilities',
				'description'         => 'List allowed workflow abilities with compact metadata for discovery.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'query'    => array(
							'type'        => 'string',
							'description' => 'Search term to filter abilities by name or description.',
						),
						'category' => array(
							'type'        => 'string',
							'description' => 'Filter abilities by category key.',
						),
						'limit'    => array(
							'type'        => 'integer',
							'description' => 'Maximum number of abilities to return. Default 20, max 50.',
							'default'     => self::DEFAULT_LIMIT,
							'minimum'     => 1,
							'maximum'     => self::MAX_LIMIT,
						),
						'cursor'   => array(
							'type'        => 'integer',
							'description' => 'Pagination offset for the next page of results.',
							'default'     => 0,
							'minimum'     => 0,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'abilities', 'total' ),
					'properties' => array(
						'abilities'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'       => array( 'type' => 'string' ),
									'name'     => array( 'type' => 'string' ),
									'label'    => array( 'type' => 'string' ),
									'summary'  => array( 'type' => 'string' ),
									'category' => array( 'type' => 'string' ),
									'tags'     => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'version'  => array( 'type' => 'string' ),
									'policy'   => array(
										'type'       => 'object',
										'properties' => array(
											'readonly'              => array( 'type' => 'boolean' ),
											'destructive'           => array( 'type' => 'boolean' ),
											'idempotent'            => array( 'type' => 'boolean' ),
											'requires_confirmation' => array( 'type' => 'boolean' ),
										),
									),
									'input'    => array(
										'type'       => 'object',
										'properties' => array(
											'required'       => array(
												'type'  => 'array',
												'items' => array( 'type' => 'string' ),
											),
											'optional_count' => array( 'type' => 'integer' ),
										),
									),
								),
							),
						),
						'total'       => array( 'type' => 'integer' ),
						'next_cursor' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'system',
				'meta'                => array(
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	public static function check_permission( ?array $input ): bool {
		$input = $input ?? [];
		return \current_user_can( 'manage_options' );
	}

	public static function execute( ?array $input ) {
		$input = $input ?? [];
		$query    = isset( $input['query'] ) ? \sanitize_text_field( (string) $input['query'] ) : '';
		$category = isset( $input['category'] ) ? \sanitize_key( (string) $input['category'] ) : '';
		$limit    = isset( $input['limit'] ) ? (int) $input['limit'] : self::DEFAULT_LIMIT;
		$cursor   = isset( $input['cursor'] ) ? (int) $input['cursor'] : 0;

		$limit  = max( 1, min( self::MAX_LIMIT, $limit ) );
		$cursor = max( 0, $cursor );

		$abilities = WorkflowAbilityCatalog::list_summaries( $query, $category );
		$total     = count( $abilities );
		$paged     = array_slice( $abilities, $cursor, $limit );
		$next      = $cursor + $limit < $total ? (string) ( $cursor + $limit ) : '';

		return array(
			'abilities'   => array_values( $paged ),
			'total'       => $total,
			'next_cursor' => $next,
		);
	}
}
