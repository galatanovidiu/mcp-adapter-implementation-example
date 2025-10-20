<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListTaxonomies implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-taxonomies',
			array(
				'label'               => 'List Taxonomies',
				'description'         => 'List available taxonomies; optionally filtered by post type.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'         => array(
							'type'        => 'string',
							'description' => 'Optional post type to filter taxonomies supported.',
						),
						'include_private'   => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'only_show_in_rest' => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'taxonomies' ),
					'properties' => array(
						'taxonomies' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'name' ),
								'properties' => array(
									'name'         => array( 'type' => 'string' ),
									'label'        => array( 'type' => 'string' ),
									'hierarchical' => array( 'type' => 'boolean' ),
									'show_in_rest' => array( 'type' => 'boolean' ),
									'object_types' => array( 'type' => 'array' ),
								),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'content',
				'meta'                => array(
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
	 * Check permission for listing taxonomies.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_posts' );
	}

	/**
	 * Execute the list taxonomies operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_type         = isset( $input['post_type'] ) ? \sanitize_key( (string) $input['post_type'] ) : '';
		$include_private   = ! empty( $input['include_private'] );
		$only_show_in_rest = array_key_exists( 'only_show_in_rest', $input ) ? (bool) $input['only_show_in_rest'] : true;

		$tax_objects = $post_type
			? (array) \get_object_taxonomies( $post_type, 'objects' )
			: (array) \get_taxonomies( array(), 'objects' );

		$out = array();
		foreach ( $tax_objects as $tax ) {
			if ( ! $tax instanceof \WP_Taxonomy ) {
				continue;
			}
			$name = (string) $tax->name;
			if ( ! $include_private && str_starts_with( $name, '_' ) ) {
				continue;
			}
			$show_in_rest = ! empty( $tax->show_in_rest );
			if ( $only_show_in_rest && ! $show_in_rest ) {
				continue;
			}
			$out[] = array(
				'name'         => $name,
				'label'        => $tax->label ? (string) $tax->label : $name,
				'hierarchical' => (bool) $tax->hierarchical,
				'show_in_rest' => $show_in_rest,
				'object_types' => array_values( (array) $tax->object_type ),
			);
		}
		return array( 'taxonomies' => $out );
	}
}
