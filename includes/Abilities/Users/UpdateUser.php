<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdateUser implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/update-user',
			array(
				'label'               => 'Update User',
				'description'         => 'Update an existing WordPress user\'s profile information, role, and metadata.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'User ID to update.',
						),
						'login' => array(
							'type'        => 'string',
							'description' => 'User login name (username). Note: Changing login is generally not recommended.',
							'minLength'   => 1,
							'maxLength'   => 60,
						),
						'email' => array(
							'type'        => 'string',
							'description' => 'User email address.',
							'format'      => 'email',
						),
						'password' => array(
							'type'        => 'string',
							'description' => 'New password for the user.',
							'minLength'   => 8,
						),
						'display_name' => array(
							'type'        => 'string',
							'description' => 'Display name for the user.',
						),
						'first_name' => array(
							'type'        => 'string',
							'description' => 'User first name.',
						),
						'last_name' => array(
							'type'        => 'string',
							'description' => 'User last name.',
						),
						'nickname' => array(
							'type'        => 'string',
							'description' => 'User nickname.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'User biographical info.',
						),
						'url' => array(
							'type'        => 'string',
							'description' => 'User website URL.',
							'format'      => 'uri',
						),
						'role' => array(
							'type'        => 'string',
							'description' => 'User role (administrator, editor, author, contributor, subscriber, or custom role).',
						),
						'meta' => array(
							'type'                 => 'object',
							'description'          => 'User meta fields to update.',
							'additionalProperties' => true,
						),
						'send_notification' => array(
							'type'        => 'boolean',
							'description' => 'Send password change notification email (only if password is changed).',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'id', 'updated_fields' ),
					'properties' => array(
						'id'             => array( 'type' => 'integer' ),
						'updated_fields' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'        => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'users', 'profiles' ),
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
	 * Check permission for updating users.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$user_id = (int) ( $input['id'] ?? 0 );
		$current_user_id = \get_current_user_id();

		// Users can edit their own profile (with some restrictions)
		if ( $current_user_id === $user_id ) {
			// Check if they're trying to change their role
			if ( ! empty( $input['role'] ) && ! \current_user_can( 'promote_users' ) ) {
				return false;
			}
			return \current_user_can( 'edit_user', $user_id );
		}

		// Otherwise require edit_users capability
		return \current_user_can( 'edit_users' );
	}

	/**
	 * Execute the update user operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$user_id = (int) $input['id'];

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

		$userdata = array( 'ID' => $user_id );
		$updated_fields = array();
		$password_changed = false;

		// Update login (with caution)
		if ( array_key_exists( 'login', $input ) ) {
			$new_login = \sanitize_user( (string) $input['login'] );
			if ( empty( $new_login ) ) {
				return array(
					'error' => array(
						'code'    => 'invalid_login',
						'message' => 'Invalid login name.',
					),
				);
			}
			if ( $new_login !== $user->user_login && \username_exists( $new_login ) ) {
				return array(
					'error' => array(
						'code'    => 'login_exists',
						'message' => 'A user with this login already exists.',
					),
				);
			}
			$userdata['user_login'] = $new_login;
			$updated_fields[] = 'login';
		}

		// Update email
		if ( array_key_exists( 'email', $input ) ) {
			$new_email = \sanitize_email( (string) $input['email'] );
			if ( empty( $new_email ) || ! \is_email( $new_email ) ) {
				return array(
					'error' => array(
						'code'    => 'invalid_email',
						'message' => 'Invalid email address.',
					),
				);
			}
			if ( $new_email !== $user->user_email && \email_exists( $new_email ) ) {
				return array(
					'error' => array(
						'code'    => 'email_exists',
						'message' => 'A user with this email already exists.',
					),
				);
			}
			$userdata['user_email'] = $new_email;
			$updated_fields[] = 'email';
		}

		// Update password
		if ( array_key_exists( 'password', $input ) ) {
			$userdata['user_pass'] = (string) $input['password'];
			$updated_fields[] = 'password';
			$password_changed = true;
		}

		// Update other profile fields
		$profile_fields = array(
			'display_name' => 'display_name',
			'first_name'   => 'first_name',
			'last_name'    => 'last_name',
			'nickname'     => 'nickname',
			'description'  => 'description',
			'url'          => 'user_url',
		);

		foreach ( $profile_fields as $input_key => $user_key ) {
			if ( array_key_exists( $input_key, $input ) ) {
				$value = (string) $input[ $input_key ];
				if ( $input_key === 'url' ) {
					$value = \esc_url_raw( $value );
				} elseif ( $input_key === 'description' ) {
					$value = \sanitize_textarea_field( $value );
				} else {
					$value = \sanitize_text_field( $value );
				}
				$userdata[ $user_key ] = $value;
				$updated_fields[] = $input_key;
			}
		}

		// Update user data
		if ( count( $userdata ) > 1 ) { // More than just ID
			$result = \wp_update_user( $userdata );
			if ( \is_wp_error( $result ) ) {
				return array(
					'error' => array(
						'code'    => $result->get_error_code(),
						'message' => $result->get_error_message(),
					),
				);
			}
		}

		// Update role if specified
		if ( array_key_exists( 'role', $input ) ) {
			$new_role = \sanitize_key( (string) $input['role'] );
			if ( ! \get_role( $new_role ) ) {
				return array(
					'error' => array(
						'code'    => 'invalid_role',
						'message' => 'Invalid user role specified.',
					),
				);
			}

			// Check permission to change roles
			if ( ! \current_user_can( 'promote_users' ) ) {
				return array(
					'error' => array(
						'code'    => 'insufficient_permissions',
						'message' => 'You do not have permission to change user roles.',
					),
				);
			}

			$user->set_role( $new_role );
			$updated_fields[] = 'role';
		}

		// Update user meta if provided
		if ( ! empty( $input['meta'] ) && \is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $meta_key => $meta_value ) {
				if ( ! \is_string( $meta_key ) ) {
					continue;
				}
				\update_user_meta( $user_id, \sanitize_key( $meta_key ), $meta_value );
				$updated_fields[] = "meta.{$meta_key}";
			}
		}

		// Send notification email if password was changed
		$send_notification = array_key_exists( 'send_notification', $input ) ? (bool) $input['send_notification'] : true;
		$message = 'User updated successfully.';
		
		if ( $password_changed && $send_notification ) {
			\wp_password_change_notification( $user );
			$message .= ' Password change notification sent.';
		}

		return array(
			'id'             => $user_id,
			'updated_fields' => array_unique( $updated_fields ),
			'message'        => $message,
		);
	}
}
