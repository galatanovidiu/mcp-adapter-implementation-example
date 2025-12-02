<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetUserMeta implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-user-meta',
			array(
				'label'               => 'Get User Meta',
				'description'         => 'Retrieve user metadata for a specific user.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'user_id' ),
					'properties' => array(
						'user_id'         => array(
							'type'        => 'integer',
							'description' => 'User ID.',
						),
						'meta_keys'       => array(
							'type'        => 'array',
							'description' => 'Specific meta keys to retrieve. If not provided, all meta will be returned.',
							'items'       => array( 'type' => 'string' ),
						),
						'include_private' => array(
							'type'        => 'boolean',
							'description' => 'Include meta keys starting with underscore.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'user_id', 'meta' ),
					'properties' => array(
						'user_id' => array( 'type' => 'integer' ),
						'meta'    => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'users',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
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
	 * Check permission for getting user meta.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$user_id         = (int) ( $input['user_id'] ?? 0 );
		$current_user_id = \get_current_user_id();

		// Users can view their own meta
		if ( $current_user_id === $user_id ) {
			return true;
		}

		// Otherwise require edit_users capability
		return \current_user_can( 'edit_users' );
	}

	/**
	 * Execute the get user meta operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$user_id = (int) $input['user_id'];

		// Check if user exists
		$user = \get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return array(
				'error' => array(
					'code'    => 'user_not_found',
					'message' => 'User not found.',
				),
			);
		}

		$include_private = ! empty( $input['include_private'] );
		$requested_keys  = ! empty( $input['meta_keys'] ) && \is_array( $input['meta_keys'] )
			? array_map( 'sanitize_key', $input['meta_keys'] )
			: array();

		// Get all user meta or specific keys
		if ( empty( $requested_keys ) ) {
			$meta = \get_user_meta( $user_id );
		} else {
			$meta = array();
			foreach ( $requested_keys as $key ) {
				$meta[ $key ] = \get_user_meta( $user_id, $key, false );
			}
		}

		// Process meta data
		$processed_meta = array();
		foreach ( $meta as $key => $values ) {
			// Skip private meta if not requested
			if ( ! $include_private && \str_starts_with( $key, '_' ) ) {
				continue;
			}

			// Handle single vs multiple values
			if ( \is_array( $values ) ) {
				if ( count( $values ) === 1 ) {
					$processed_meta[ $key ] = \maybe_unserialize( $values[0] );
				} else {
					$processed_meta[ $key ] = array_map( 'maybe_unserialize', $values );
				}
			} else {
				$processed_meta[ $key ] = \maybe_unserialize( $values );
			}
		}

		return array(
			'user_id' => $user_id,
			'meta'    => $processed_meta,
		);
	}
}
