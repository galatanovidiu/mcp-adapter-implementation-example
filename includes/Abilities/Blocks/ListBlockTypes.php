<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Blocks;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListBlockTypes implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/list-block-types',
			array(
				'label'               => 'List Gutenberg Block Types',
				'description'         => 'Return available Gutenberg blocks with descriptions and attribute schemas. Use this before creating/updating posts to get valid block names and attributes for block comments.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Optional block category filter.',
						),
						'search'   => array(
							'type'        => 'string',
							'description' => 'Optional search string to match against name/title/description.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'blocks' ),
					'properties' => array(
						'blocks' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'name', 'title' ),
								'properties' => array(
									'name'        => array( 'type' => 'string' ),
									'title'       => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'category'    => array( 'type' => 'string' ),
									'keywords'    => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'attributes'  => array(
										'type' => 'object',
										'additionalProperties' => true,
									),
									'supports'    => array(
										'type' => 'object',
										'additionalProperties' => true,
									),
								),
							),
						),
					),
				),
				'permission_callback' => array( static::class, 'check_permission' ),
				'execute_callback'    => array( static::class, 'execute' ),
				'meta'                => array(),
			)
		);
	}

	/**
	 * Check permission for listing block types.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_posts' );
	}

	/**
	 * Execute the list block types operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$category = isset( $input['category'] ) ? \sanitize_key( (string) $input['category'] ) : '';
		$search   = isset( $input['search'] ) ? \sanitize_text_field( (string) $input['search'] ) : '';

		$registry = \WP_Block_Type_Registry::get_instance();
		$types    = $registry->get_all_registered();

		$blocks = array();
		foreach ( $types as $type ) {
			if ( ! $type instanceof \WP_Block_Type ) {
				continue;
			}
			if ( $category && (string) ( $type->category ?? '' ) !== $category ) {
				continue;
			}

			$name = (string) $type->name;
			$title = $type->title ? (string) $type->title : $name;
			$description = $type->description ? (string) $type->description : '';

			if ( $search ) {
				$haystack = strtolower( $name . ' ' . $title . ' ' . $description );
				if ( false === strpos( $haystack, strtolower( $search ) ) ) {
					continue;
				}
			}

			$blocks[] = array(
				'name'        => $name,
				'title'       => $title,
				'description' => $description,
				'category'    => isset( $type->category ) ? (string) $type->category : '',
				'keywords'    => is_array( $type->keywords ) ? array_values( array_map( 'strval', $type->keywords ) ) : array(),
				'attributes'  => isset( $type->attributes ) && is_array( $type->attributes ) ? $type->attributes : new \stdClass(),
				'supports'    => isset( $type->supports ) && is_array( $type->supports ) ? $type->supports : new \stdClass(),
			);
		}

		return array(
			'blocks' => $blocks,
		);
	}
}
