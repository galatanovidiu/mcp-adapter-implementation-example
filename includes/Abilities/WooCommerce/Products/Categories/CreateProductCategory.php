<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class CreateProductCategory implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/create-product-category',
			array(
				'label'               => 'Create Product Category',
				'description'         => 'Create a new WooCommerce product category with optional parent and display settings.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Category name.',
						),
						'slug' => array(
							'type'        => 'string',
							'description' => 'Category slug (auto-generated if not provided).',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Category description.',
						),
						'parent' => array(
							'type'        => 'integer',
							'description' => 'Parent category ID (0 for top-level).',
							'default'     => 0,
						),
						'display_type' => array(
							'type'        => 'string',
							'description' => 'Category display type.',
							'enum'        => array( 'default', 'products', 'subcategories', 'both' ),
							'default'     => 'default',
						),
						'image_id' => array(
							'type'        => 'integer',
							'description' => 'Category image attachment ID.',
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'Menu order for category sorting.',
							'default'     => 0,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'category' => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'name'        => array( 'type' => 'string' ),
								'slug'        => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'parent'      => array( 'type' => 'integer' ),
								'count'       => array( 'type' => 'integer' ),
								'display'     => array( 'type' => 'string' ),
								'menu_order'  => array( 'type' => 'integer' ),
								'link'        => array( 'type' => 'string' ),
							),
						),
						'parent_info' => array(
							'type'       => 'object',
							'properties' => array(
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'ecommerce', 'catalog' ),
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

	public static function check_permission(): bool {
		return current_user_can( 'manage_product_terms' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success'     => false,
				'category'    => null,
				'parent_info' => null,
				'message'     => 'WooCommerce is not active.',
			);
		}

		$name = $input['name'];
		$slug = $input['slug'] ?? sanitize_title( $name );
		$description = $input['description'] ?? '';
		$parent = $input['parent'] ?? 0;
		$display_type = $input['display_type'] ?? 'default';
		$image_id = $input['image_id'] ?? 0;
		$menu_order = $input['menu_order'] ?? 0;

		// Validate parent category
		$parent_info = null;
		if ( $parent > 0 ) {
			$parent_category = get_term( $parent, 'product_cat' );
			if ( is_wp_error( $parent_category ) || ! $parent_category ) {
				return array(
					'success'     => false,
					'category'    => null,
					'parent_info' => null,
					'message'     => 'Parent category not found.',
				);
			}

			$parent_info = array(
				'id'   => $parent_category->term_id,
				'name' => $parent_category->name,
			);
		}

		try {
			// Create the category
			$result = wp_insert_term( $name, 'product_cat', array(
				'slug'        => $slug,
				'description' => $description,
				'parent'      => $parent,
			) );

			if ( is_wp_error( $result ) ) {
				return array(
					'success'     => false,
					'category'    => null,
					'parent_info' => $parent_info,
					'message'     => 'Error creating category: ' . $result->get_error_message(),
				);
			}

			$category_id = $result['term_id'];

			// Set display type
			update_term_meta( $category_id, 'display_type', $display_type );

			// Set image
			if ( $image_id > 0 ) {
				update_term_meta( $category_id, 'thumbnail_id', $image_id );
			}

			// Set menu order
			update_term_meta( $category_id, 'order', $menu_order );

			// Get the created category
			$created_category = get_term( $category_id, 'product_cat' );

			if ( is_wp_error( $created_category ) || ! $created_category ) {
				return array(
					'success'     => false,
					'category'    => array(),
					'parent_info' => $parent_info ?: array(),
					'message'     => 'Failed to retrieve created category.',
				);
			}

			return array(
				'success'     => true,
				'category'    => array(
					'id'          => $created_category->term_id,
					'name'        => $created_category->name,
					'slug'        => $created_category->slug,
					'description' => $created_category->description,
					'parent'      => $created_category->parent,
					'count'       => $created_category->count,
					'display'     => get_term_meta( $category_id, 'display_type', true ),
					'menu_order'  => get_term_meta( $category_id, 'order', true ),
					'link'        => get_term_link( $created_category ),
				),
				'parent_info' => $parent_info ?: array(),
				'message'     => sprintf(
					'Successfully created category "%s"%s.',
					$name,
					$parent_info ? ' under "' . $parent_info['name'] . '"' : ''
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success'     => false,
				'category'    => null,
				'parent_info' => $parent_info,
				'message'     => 'Error creating category: ' . $e->getMessage(),
			);
		}
	}
}
