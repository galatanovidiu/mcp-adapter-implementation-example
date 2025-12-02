<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ChangeUserRole implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/change-user-role',
			array(
				'label'               => 'Change User Role',
				'description'         => 'Change a user\'s role and capabilities in WordPress.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'user_id', 'role' ),
					'properties' => array(
						'user_id'             => array(
							'type'        => 'integer',
							'description' => 'User ID.',
						),
						'role'                => array(
							'type'        => 'string',
							'description' => 'New role for the user (administrator, editor, author, contributor, subscriber, or custom role).',
						),
						'add_capabilities'    => array(
							'type'        => 'array',
							'description' => 'Additional capabilities to grant to the user.',
							'items'       => array( 'type' => 'string' ),
						),
						'remove_capabilities' => array(
							'type'        => 'array',
							'description' => 'Capabilities to remove from the user.',
							'items'       => array( 'type' => 'string' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'user_id', 'previous_role', 'new_role' ),
					'properties' => array(
						'user_id'       => array( 'type' => 'integer' ),
						'previous_role' => array( 'type' => 'string' ),
						'new_role'      => array( 'type' => 'string' ),
						'capabilities'  => array( 'type' => 'object' ),
						'message'       => array( 'type' => 'string' ),
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
						'priority'        => 0.6,
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
	 * Check permission for changing user roles.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$user_id         = (int) ( $input['user_id'] ?? 0 );
		$current_user_id = \get_current_user_id();

		// Users cannot change their own role
		if ( $current_user_id === $user_id ) {
			return false;
		}

		// Check if user has promote_users capability
		if ( ! \current_user_can( 'promote_users' ) ) {
			return false;
		}

		// Additional check: cannot promote users to roles higher than current user
		$target_role = isset( $input['role'] ) ? \sanitize_key( (string) $input['role'] ) : '';
		if ( ! empty( $target_role ) ) {
			$role_obj = \get_role( $target_role );
			if ( $role_obj && isset( $role_obj->capabilities['manage_options'] ) && $role_obj->capabilities['manage_options'] ) {
				// Only administrators can promote to administrator role
				if ( ! \current_user_can( 'manage_options' ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Execute the change user role operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$user_id         = (int) $input['user_id'];
		$new_role        = \sanitize_key( (string) $input['role'] );
		$current_user_id = \get_current_user_id();

		// Prevent self-role change
		if ( $current_user_id === $user_id ) {
			return array(
				'error' => array(
					'code'    => 'cannot_change_own_role',
					'message' => 'You cannot change your own role.',
				),
			);
		}

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

		// Check if role exists
		$role_obj = \get_role( $new_role );
		if ( ! $role_obj ) {
			return array(
				'error' => array(
					'code'    => 'invalid_role',
					'message' => 'Invalid role specified.',
				),
			);
		}

		// Get current role
		$current_roles = $user->roles;
		$previous_role = ! empty( $current_roles ) ? $current_roles[0] : 'none';

		// Additional security check for administrator role
		if ( $new_role === 'administrator' && ! \current_user_can( 'manage_options' ) ) {
			return array(
				'error' => array(
					'code'    => 'insufficient_permissions',
					'message' => 'You do not have permission to promote users to administrator.',
				),
			);
		}

		// Change the user's role
		$user->set_role( $new_role );

		// Handle additional capabilities
		$add_capabilities = ! empty( $input['add_capabilities'] ) && \is_array( $input['add_capabilities'] )
			? array_map( 'sanitize_key', $input['add_capabilities'] )
			: array();

		$remove_capabilities = ! empty( $input['remove_capabilities'] ) && \is_array( $input['remove_capabilities'] )
			? array_map( 'sanitize_key', $input['remove_capabilities'] )
			: array();

		// Add capabilities
		foreach ( $add_capabilities as $cap ) {
			if ( empty( $cap ) ) {
				continue;
			}

			$user->add_cap( $cap );
		}

		// Remove capabilities
		foreach ( $remove_capabilities as $cap ) {
			if ( empty( $cap ) ) {
				continue;
			}

			$user->remove_cap( $cap );
		}

		// Get updated user to return current capabilities
		$updated_user = \get_user_by( 'ID', $user_id );

		$message = "User role changed from '{$previous_role}' to '{$new_role}'.";

		if ( ! empty( $add_capabilities ) ) {
			$message .= ' Added capabilities: ' . implode( ', ', $add_capabilities ) . '.';
		}

		if ( ! empty( $remove_capabilities ) ) {
			$message .= ' Removed capabilities: ' . implode( ', ', $remove_capabilities ) . '.';
		}

		return array(
			'user_id'       => $user_id,
			'previous_role' => $previous_role,
			'new_role'      => $new_role,
			'capabilities'  => $updated_user->allcaps,
			'message'       => $message,
		);
	}
}
