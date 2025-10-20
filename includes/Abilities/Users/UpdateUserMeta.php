<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdateUserMeta implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/update-user-meta',
			array(
				'label'               => 'Update User Meta',
				'description'         => 'Update user metadata for a specific user.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'user_id', 'meta' ),
					'properties' => array(
						'user_id'       => array(
							'type'        => 'integer',
							'description' => 'User ID.',
						),
						'meta'          => array(
							'type'                 => 'object',
							'description'          => 'Meta key-value pairs to update.',
							'additionalProperties' => true,
						),
						'allow_private' => array(
							'type'        => 'boolean',
							'description' => 'Allow updating meta keys starting with underscore.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'user_id', 'updated_keys' ),
					'properties' => array(
						'user_id'      => array( 'type' => 'integer' ),
						'updated_keys' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'      => array( 'type' => 'string' ),
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
						'priority'        => 0.7,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for updating user meta.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$user_id         = (int) ( $input['user_id'] ?? 0 );
		$current_user_id = \get_current_user_id();

		// Users can update their own meta (with restrictions)
		if ( $current_user_id === $user_id ) {
			return \current_user_can( 'edit_user', $user_id );
		}

		// Otherwise require edit_users capability
		return \current_user_can( 'edit_users' );
	}

	/**
	 * Execute the update user meta operation.
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

		if ( empty( $input['meta'] ) || ! \is_array( $input['meta'] ) ) {
			return array(
				'error' => array(
					'code'    => 'invalid_meta',
					'message' => 'Meta must be provided as an object.',
				),
			);
		}

		$allow_private   = ! empty( $input['allow_private'] );
		$updated_keys    = array();
		$current_user_id = \get_current_user_id();

		// Protected meta keys that should not be modified directly
		$protected_keys = array(
			'wp_capabilities',
			'wp_user_level',
			'session_tokens',
			'wp_user-settings',
			'wp_user-settings-time',
		);

		foreach ( $input['meta'] as $meta_key => $meta_value ) {
			if ( ! \is_string( $meta_key ) ) {
				continue;
			}

			$meta_key = \sanitize_key( $meta_key );

			// Skip empty keys
			if ( empty( $meta_key ) ) {
				continue;
			}

			// Check if private meta is allowed
			if ( ! $allow_private && \str_starts_with( $meta_key, '_' ) ) {
				continue;
			}

			// Protect certain meta keys
			if ( \in_array( $meta_key, $protected_keys, true ) ) {
				// Only allow admins to modify protected keys
				if ( ! \current_user_can( 'manage_options' ) ) {
					continue;
				}
			}

			// Additional security: users can only modify their own non-protected meta
			if ( $current_user_id === $user_id && \str_starts_with( $meta_key, 'wp_' ) ) {
				continue;
			}

			// Update the meta
			$updated = \update_user_meta( $user_id, $meta_key, $meta_value );

			// update_user_meta returns false if the value is the same, but that's still "successful"
			if ( $updated === false && \get_user_meta( $user_id, $meta_key, true ) !== $meta_value ) {
				continue;
			}

			$updated_keys[] = $meta_key;
		}

		$message = count( $updated_keys ) > 0
			? 'User meta updated successfully.'
			: 'No meta keys were updated.';

		return array(
			'user_id'      => $user_id,
			'updated_keys' => $updated_keys,
			'message'      => $message,
		);
	}
}
