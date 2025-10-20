<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetPostMeta implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-post-meta',
			array(
				'label'               => 'Get Post Meta',
				'description'         => 'Retrieve post meta for a post ID. Defaults to registered show_in_rest keys only.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'                => array(
							'type'        => 'integer',
							'description' => 'Post ID.',
						),
						'keys'              => array(
							'type'        => 'array',
							'description' => 'Optional list of meta keys to fetch (filtered by policy).',
							'items'       => array( 'type' => 'string' ),
						),
						'include_private'   => array(
							'type'        => 'boolean',
							'description' => 'Include meta keys starting with underscore.',
							'default'     => false,
						),
						'only_show_in_rest' => array(
							'type'        => 'boolean',
							'description' => 'Only include meta with show_in_rest = true.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'meta' ),
					'properties' => array(
						'meta' => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'content',
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
	 * Check permission for getting post meta.
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
	 * Execute the get post meta operation.
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

		$post_type         = $post->post_type;
		$include_private   = ! empty( $input['include_private'] );
		$only_show_in_rest = array_key_exists( 'only_show_in_rest', $input ) ? (bool) $input['only_show_in_rest'] : true;

		$registered = function_exists( 'get_registered_meta_keys' )
			? (array) \get_registered_meta_keys( 'post', $post_type )
			: array();

		$allowed_keys = array();
		foreach ( $registered as $key => $args ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( ! $include_private && str_starts_with( $key, '_' ) ) {
				continue;
			}
			$show_in_rest = false;
			if ( isset( $args['show_in_rest'] ) ) {
				$show_in_rest = is_bool( $args['show_in_rest'] ) ? (bool) $args['show_in_rest'] : true;
			}
			if ( $only_show_in_rest && ! $show_in_rest ) {
				continue;
			}
			$allowed_keys[ $key ] = isset( $args['single'] ) ? (bool) $args['single'] : true;
		}

		$requested_keys = array();
		if ( ! empty( $input['keys'] ) && is_array( $input['keys'] ) ) {
			foreach ( $input['keys'] as $k ) {
				if ( ! is_string( $k ) ) {
					continue;
				}

				$requested_keys[] = $k;
			}
		} else {
			$requested_keys = array_keys( $allowed_keys );
		}

		$meta_out = array();
		foreach ( $requested_keys as $k ) {
			if ( ! array_key_exists( $k, $allowed_keys ) ) {
				continue;
			}
			$single         = (bool) $allowed_keys[ $k ];
			$meta_out[ $k ] = \get_post_meta( $post_id, $k, $single );
		}

		return array( 'meta' => $meta_out );
	}
}
