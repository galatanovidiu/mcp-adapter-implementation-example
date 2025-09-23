<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts\Meta;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListPostMetaKeys implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-post-meta-keys',
			array(
				'label'               => 'List Post Meta Keys',
				'description'         => 'List registered post meta keys for a given post type (show_in_rest only by default).',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'post_type' ),
					'properties' => array(
						'post_type'         => array(
							'type'        => 'string',
							'description' => 'Post type to inspect.',
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
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'key', 'type', 'single' ),
								'properties' => array(
									'key'          => array( 'type' => 'string' ),
									'type'         => array( 'type' => 'string' ),
									'single'       => array( 'type' => 'boolean' ),
									'description'  => array( 'type' => 'string' ),
									'default'      => array(),
									'show_in_rest' => array( 'type' => 'boolean' ),
									'schema'       => array( 'type' => 'object' ),
								),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'content', 'metadata' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
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
	 * Check permission for listing post meta keys.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		// Access limited to editors.
		return \current_user_can( 'edit_posts' );
	}

	/**
	 * Execute the list post meta keys operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_type         = \sanitize_key( (string) $input['post_type'] );
		$include_private   = ! empty( $input['include_private'] );
		$only_show_in_rest = array_key_exists( 'only_show_in_rest', $input ) ? (bool) $input['only_show_in_rest'] : true;

		if ( ! \post_type_exists( $post_type ) ) {
			return new \WP_Error( 'invalid_post_type', 'Invalid post type.' );
		}

		$registered = function_exists( 'get_registered_meta_keys' )
			? (array) \get_registered_meta_keys( 'post', $post_type )
			: array();

		$result = array();
		foreach ( $registered as $key => $args ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( ! $include_private && str_starts_with( $key, '_' ) ) {
				continue;
			}
			$show_in_rest = false;
			$schema       = new \stdClass();
			if ( isset( $args['show_in_rest'] ) ) {
				if ( is_bool( $args['show_in_rest'] ) ) {
					$show_in_rest = (bool) $args['show_in_rest'];
				} elseif ( is_array( $args['show_in_rest'] ) ) {
					$show_in_rest = true;
					if ( isset( $args['show_in_rest']['schema'] ) && is_array( $args['show_in_rest']['schema'] ) ) {
						$schema = $args['show_in_rest']['schema'];
					}
				}
			}
			if ( $only_show_in_rest && ! $show_in_rest ) {
				continue;
			}

			$result[] = array(
				'key'          => $key,
				'type'         => isset( $args['type'] ) ? (string) $args['type'] : 'string',
				'single'       => isset( $args['single'] ) ? (bool) $args['single'] : true,
				'description'  => isset( $args['description'] ) ? (string) $args['description'] : '',
				'default'      => $args['default'] ?? null,
				'show_in_rest' => $show_in_rest,
				'schema'       => $schema,
			);
		}

		return array( 'meta' => $result );
	}
}
