<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdatePostMeta implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/update-post-meta',
			array(
				'label'               => 'Update Post Meta',
				'description'         => 'Update post meta for a post ID. Only registered show_in_rest meta keys are permitted by default.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id', 'meta' ),
					'properties' => array(
						'id'                => array(
							'type'        => 'integer',
							'description' => 'Post ID.',
						),
						'meta'              => array(
							'type'                 => 'object',
							'description'          => 'Key/value map of meta to update.',
							'additionalProperties' => true,
						),
						'include_private'   => array(
							'type'        => 'boolean',
							'description' => 'Allow keys starting with underscore.',
							'default'     => false,
						),
						'only_show_in_rest' => array(
							'type'        => 'boolean',
							'description' => 'Only allow meta with show_in_rest = true.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'updated_keys' ),
					'properties' => array(
						'updated_keys' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(),
			)
		);
	}

	/**
	 * Check permission for updating post meta.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$post_id = (int) ( $input['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return false;
		}
		return \current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Execute the update post meta operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_id = (int) $input['id'];
		$post    = \get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', 'Post not found.' );
		}
		if ( empty( $input['meta'] ) || ! is_array( $input['meta'] ) ) {
			return new \WP_Error( 'invalid_meta', 'Meta must be an object.' );
		}

		$post_type = $post->post_type;
		$include_private = ! empty( $input['include_private'] );
		$only_show_in_rest = array_key_exists( 'only_show_in_rest', $input ) ? (bool) $input['only_show_in_rest'] : true;

		$registered = function_exists( 'get_registered_meta_keys' )
			? (array) \get_registered_meta_keys( 'post', $post_type )
			: array();

		$updated_keys = array();
		foreach ( $input['meta'] as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( ! $include_private && str_starts_with( $key, '_' ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $registered ) ) {
				continue; // Only allow registered meta.
			}
			$args = $registered[ $key ];

			// Enforce show_in_rest policy.
			$show_in_rest = false;
			if ( isset( $args['show_in_rest'] ) ) {
				$show_in_rest = is_bool( $args['show_in_rest'] ) ? (bool) $args['show_in_rest'] : true;
			}
			if ( $only_show_in_rest && ! $show_in_rest ) {
				continue;
			}

			// Permission per key.
			if ( ! \current_user_can( 'edit_post_meta', $post_id, $key ) ) {
				continue;
			}

			// Validate against schema if available.
			$schema = null;
			if ( is_array( $args['show_in_rest'] ?? null ) && isset( $args['show_in_rest']['schema'] ) ) {
				$schema = $args['show_in_rest']['schema'];
			} elseif ( isset( $args['type'] ) ) {
				$schema = array( 'type' => (string) $args['type'] );
			}
			if ( $schema ) {
				$valid = \rest_validate_value_from_schema( $value, $schema );
				if ( \is_wp_error( $valid ) ) {
					return $valid;
				}
			}

			$single = isset( $args['single'] ) ? (bool) $args['single'] : true;
			if ( $single ) {
				\update_post_meta( $post_id, $key, $value );
			} else {
				\delete_post_meta( $post_id, $key );
				$values = is_array( $value ) ? array_values( $value ) : array( $value );
				foreach ( $values as $v ) {
					\add_post_meta( $post_id, $key, $v, false );
				}
			}

			$updated_keys[] = $key;
		}

		return array( 'updated_keys' => array_values( array_unique( $updated_keys ) ) );
	}
}
