<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListUsers implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-users',
			array(
				'label'               => 'List Users',
				'description'         => 'List WordPress users with filtering, searching, and pagination options.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'role' => array(
							'type'        => 'string',
							'description' => 'Filter users by role (administrator, editor, author, contributor, subscriber, or custom role).',
						),
						'search' => array(
							'type'        => 'string',
							'description' => 'Search term to match against user login, email, display name, or meta values.',
						),
						'include' => array(
							'type'        => 'array',
							'description' => 'Array of user IDs to include.',
							'items'       => array( 'type' => 'integer' ),
						),
						'exclude' => array(
							'type'        => 'array',
							'description' => 'Array of user IDs to exclude.',
							'items'       => array( 'type' => 'integer' ),
						),
						'orderby' => array(
							'type'        => 'string',
							'description' => 'Field to order results by.',
							'enum'        => array( 'ID', 'login', 'nicename', 'email', 'url', 'registered', 'display_name', 'post_count', 'include', 'meta_value', 'meta_value_num' ),
							'default'     => 'registered',
						),
						'order' => array(
							'type'        => 'string',
							'description' => 'Sort order.',
							'enum'        => array( 'ASC', 'DESC' ),
							'default'     => 'DESC',
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of users to return.',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 500,
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of users to skip (for pagination).',
							'default'     => 0,
							'minimum'     => 0,
						),
						'meta_key' => array(
							'type'        => 'string',
							'description' => 'Meta key to query (used with meta_value).',
						),
						'meta_value' => array(
							'type'        => 'string',
							'description' => 'Meta value to match.',
						),
						'meta_compare' => array(
							'type'        => 'string',
							'description' => 'Comparison operator for meta query.',
							'enum'        => array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ),
							'default'     => '=',
						),
						'include_meta' => array(
							'type'        => 'boolean',
							'description' => 'Include user meta in results.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'users', 'total' ),
					'properties' => array(
						'users' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'id', 'login', 'email', 'display_name', 'roles' ),
								'properties' => array(
									'id'           => array( 'type' => 'integer' ),
									'login'        => array( 'type' => 'string' ),
									'email'        => array( 'type' => 'string' ),
									'display_name' => array( 'type' => 'string' ),
									'first_name'   => array( 'type' => 'string' ),
									'last_name'    => array( 'type' => 'string' ),
									'nickname'     => array( 'type' => 'string' ),
									'description'  => array( 'type' => 'string' ),
									'url'          => array( 'type' => 'string' ),
									'registered'   => array( 'type' => 'string' ),
									'roles'        => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'capabilities' => array( 'type' => 'object' ),
									'meta'         => array( 'type' => 'object' ),
								),
							),
						),
						'total' => array(
							'type'        => 'integer',
							'description' => 'Total number of users matching the query',
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'users', 'management' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.9,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for listing users.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'list_users' );
	}

	/**
	 * Execute the list users operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		// Build WP_User_Query arguments
		$args = array(
			'number' => isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 50,
			'offset' => isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0,
			'orderby' => isset( $input['orderby'] ) ? \sanitize_key( (string) $input['orderby'] ) : 'registered',
			'order' => isset( $input['order'] ) ? \sanitize_key( (string) $input['order'] ) : 'DESC',
			'count_total' => true, // We need total count for pagination
		);

		// Add role filter
		if ( ! empty( $input['role'] ) ) {
			$args['role'] = \sanitize_key( (string) $input['role'] );
		}

		// Add search filter
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = '*' . \sanitize_text_field( (string) $input['search'] ) . '*';
		}

		// Add include/exclude filters
		if ( ! empty( $input['include'] ) && \is_array( $input['include'] ) ) {
			$args['include'] = array_map( 'intval', $input['include'] );
		}

		if ( ! empty( $input['exclude'] ) && \is_array( $input['exclude'] ) ) {
			$args['exclude'] = array_map( 'intval', $input['exclude'] );
		}

		// Add meta query
		if ( ! empty( $input['meta_key'] ) ) {
			$args['meta_key'] = \sanitize_key( (string) $input['meta_key'] );
			
			if ( ! empty( $input['meta_value'] ) ) {
				$args['meta_value'] = \sanitize_text_field( (string) $input['meta_value'] );
				$args['meta_compare'] = isset( $input['meta_compare'] ) ? \sanitize_key( (string) $input['meta_compare'] ) : '=';
			}
		}

		// Execute query
		$user_query = new \WP_User_Query( $args );
		$users = $user_query->get_results();
		$total = $user_query->get_total();

		$include_meta = ! empty( $input['include_meta'] );
		$processed_users = array();

		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$user_data = array(
				'id'           => $user->ID,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'nickname'     => $user->nickname,
				'description'  => $user->description,
				'url'          => $user->user_url,
				'registered'   => $user->user_registered,
				'roles'        => array_values( $user->roles ),
				'capabilities' => $user->allcaps,
			);

			// Include meta if requested
			if ( $include_meta ) {
				$user_data['meta'] = \get_user_meta( $user->ID );
			}

			$processed_users[] = $user_data;
		}

		return array(
			'users' => $processed_users,
			'total' => (int) $total,
		);
	}
}
