<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class CreateUser implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/create-user',
			array(
				'label'               => 'Create User',
				'description'         => 'Create a new WordPress user with specified details and role.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'login', 'email' ),
					'properties' => array(
						'login' => array(
							'type'        => 'string',
							'description' => 'User login name (username).',
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
							'description' => 'User password. If not provided, a random password will be generated.',
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
							'default'     => 'subscriber',
						),
						'meta' => array(
							'type'                 => 'object',
							'description'          => 'User meta fields to set.',
							'additionalProperties' => true,
						),
						'send_notification' => array(
							'type'        => 'boolean',
							'description' => 'Send new user notification email.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'id', 'login', 'email' ),
					'properties' => array(
						'id'           => array( 'type' => 'integer' ),
						'login'        => array( 'type' => 'string' ),
						'email'        => array( 'type' => 'string' ),
						'display_name' => array( 'type' => 'string' ),
						'role'         => array( 'type' => 'string' ),
						'password'     => array( 'type' => 'string' ),
						'message'      => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'users', 'management' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for creating users.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'create_users' );
	}

	/**
	 * Execute the create user operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$login = \sanitize_user( (string) $input['login'] );
		$email = \sanitize_email( (string) $input['email'] );

		// Validate required fields
		if ( empty( $login ) ) {
			return array(
				'error' => array(
					'code'    => 'invalid_login',
					'message' => 'Invalid or empty login name.',
				),
			);
		}

		if ( empty( $email ) || ! \is_email( $email ) ) {
			return array(
				'error' => array(
					'code'    => 'invalid_email',
					'message' => 'Invalid email address.',
				),
			);
		}

		// Check if user already exists
		if ( \username_exists( $login ) ) {
			return array(
				'error' => array(
					'code'    => 'user_exists',
					'message' => 'A user with this login already exists.',
				),
			);
		}

		if ( \email_exists( $email ) ) {
			return array(
				'error' => array(
					'code'    => 'email_exists',
					'message' => 'A user with this email already exists.',
				),
			);
		}

		// Generate password if not provided
		$password = ! empty( $input['password'] ) ? (string) $input['password'] : \wp_generate_password( 12, false );

		// Prepare user data
		$userdata = array(
			'user_login' => $login,
			'user_email' => $email,
			'user_pass'  => $password,
		);

		// Add optional fields
		if ( ! empty( $input['display_name'] ) ) {
			$userdata['display_name'] = \sanitize_text_field( (string) $input['display_name'] );
		}

		if ( ! empty( $input['first_name'] ) ) {
			$userdata['first_name'] = \sanitize_text_field( (string) $input['first_name'] );
		}

		if ( ! empty( $input['last_name'] ) ) {
			$userdata['last_name'] = \sanitize_text_field( (string) $input['last_name'] );
		}

		if ( ! empty( $input['nickname'] ) ) {
			$userdata['nickname'] = \sanitize_text_field( (string) $input['nickname'] );
		}

		if ( ! empty( $input['description'] ) ) {
			$userdata['description'] = \sanitize_textarea_field( (string) $input['description'] );
		}

		if ( ! empty( $input['url'] ) ) {
			$userdata['user_url'] = \esc_url_raw( (string) $input['url'] );
		}

		// Set role
		$role = ! empty( $input['role'] ) ? \sanitize_key( (string) $input['role'] ) : 'subscriber';
		if ( ! \get_role( $role ) ) {
			return array(
				'error' => array(
					'code'    => 'invalid_role',
					'message' => 'Invalid user role specified.',
				),
			);
		}
		$userdata['role'] = $role;

		// Create the user
		$user_id = \wp_insert_user( $userdata );

		if ( \is_wp_error( $user_id ) ) {
			return array(
				'error' => array(
					'code'    => $user_id->get_error_code(),
					'message' => $user_id->get_error_message(),
				),
			);
		}

		// Add user meta if provided
		if ( ! empty( $input['meta'] ) && \is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $meta_key => $meta_value ) {
				if ( ! \is_string( $meta_key ) ) {
					continue;
				}
				\update_user_meta( $user_id, \sanitize_key( $meta_key ), $meta_value );
			}
		}

		// Get the created user
		$user = \get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			return array(
				'error' => array(
					'code'    => 'user_creation_failed',
					'message' => 'User was created but could not be retrieved.',
				),
			);
		}

		// Send notification email if requested
		$send_notification = array_key_exists( 'send_notification', $input ) ? (bool) $input['send_notification'] : true;
		if ( $send_notification ) {
			\wp_new_user_notification( $user_id, null, 'both' );
		}

		return array(
			'id'           => $user_id,
			'login'        => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'role'         => $role,
			'password'     => $password,
			'message'      => $send_notification ? 'User created successfully. Notification email sent.' : 'User created successfully.',
		);
	}
}
