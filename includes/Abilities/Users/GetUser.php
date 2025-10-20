<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetUser implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-user',
			array(
				'label'               => 'Get User',
				'description'         => 'Retrieve detailed information about a specific WordPress user by ID, login, or email.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'                   => array(
							'type'        => 'integer',
							'description' => 'User ID.',
						),
						'login'                => array(
							'type'        => 'string',
							'description' => 'User login name.',
						),
						'email'                => array(
							'type'        => 'string',
							'description' => 'User email address.',
						),
						'include_meta'         => array(
							'type'        => 'boolean',
							'description' => 'Include user meta in results.',
							'default'     => true,
						),
						'include_capabilities' => array(
							'type'        => 'boolean',
							'description' => 'Include detailed user capabilities.',
							'default'     => true,
						),
					),
					'oneOf'      => array(
						array( 'required' => array( 'id' ) ),
						array( 'required' => array( 'login' ) ),
						array( 'required' => array( 'email' ) ),
					),
				),
				'output_schema'       => array(
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
						'status'       => array( 'type' => 'integer' ),
						'roles'        => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'capabilities' => array( 'type' => 'object' ),
						'meta'         => array( 'type' => 'object' ),
						'avatar_url'   => array( 'type' => 'string' ),
						'posts_count'  => array( 'type' => 'integer' ),
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
	 * Check permission for getting user information.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		// Users can view their own profile, admins can view any user
		$current_user_id = \get_current_user_id();

		// Get the target user ID
		$target_user_id = 0;
		if ( ! empty( $input['id'] ) ) {
			$target_user_id = (int) $input['id'];
		} elseif ( ! empty( $input['login'] ) ) {
			$user           = \get_user_by( 'login', \sanitize_user( (string) $input['login'] ) );
			$target_user_id = $user ? $user->ID : 0;
		} elseif ( ! empty( $input['email'] ) ) {
			$user           = \get_user_by( 'email', \sanitize_email( (string) $input['email'] ) );
			$target_user_id = $user ? $user->ID : 0;
		}

		// Allow users to view their own profile
		if ( $current_user_id === $target_user_id ) {
			return true;
		}

		// Otherwise require list_users capability
		return \current_user_can( 'list_users' );
	}

	/**
	 * Execute the get user operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$user = null;

		// Get user by ID, login, or email
		if ( ! empty( $input['id'] ) ) {
			$user = \get_user_by( 'ID', (int) $input['id'] );
		} elseif ( ! empty( $input['login'] ) ) {
			$user = \get_user_by( 'login', \sanitize_user( (string) $input['login'] ) );
		} elseif ( ! empty( $input['email'] ) ) {
			$user = \get_user_by( 'email', \sanitize_email( (string) $input['email'] ) );
		}

		if ( ! $user || ! $user instanceof \WP_User ) {
			return array(
				'error' => array(
					'code'    => 'user_not_found',
					'message' => 'User not found.',
				),
			);
		}

		$include_meta         = array_key_exists( 'include_meta', $input ) ? (bool) $input['include_meta'] : true;
		$include_capabilities = array_key_exists( 'include_capabilities', $input ) ? (bool) $input['include_capabilities'] : true;

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
			'status'       => (int) $user->user_status,
			'roles'        => array_values( $user->roles ),
			'avatar_url'   => \get_avatar_url( $user->ID ),
			'posts_count'  => (int) \count_user_posts( $user->ID ),
		);

		// Include capabilities if requested
		if ( $include_capabilities ) {
			$user_data['capabilities'] = $user->allcaps;
		}

		// Include meta if requested
		if ( $include_meta ) {
			$meta = \get_user_meta( $user->ID );

			// Process meta to handle serialized data and arrays
			$processed_meta = array();
			foreach ( $meta as $key => $values ) {
				if ( count( $values ) === 1 ) {
					$processed_meta[ $key ] = \maybe_unserialize( $values[0] );
				} else {
					$processed_meta[ $key ] = array_map( 'maybe_unserialize', $values );
				}
			}

			$user_data['meta'] = $processed_meta;
		}

		return $user_data;
	}
}
