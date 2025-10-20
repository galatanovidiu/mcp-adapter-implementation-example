<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class GetProductCategory implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/get-product-category',
			array(
				'label'               => 'Get Product Category',
				'description'         => 'Retrieve detailed information about a specific WooCommerce product category including hierarchy and products.',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'category_id' ),
					'properties'           => array(
						'category_id'       => array(
							'type'        => 'integer',
							'description' => 'Category ID to retrieve.',
							'minimum'     => 1,
						),
						'include_products'  => array(
							'type'        => 'boolean',
							'description' => 'Include products in this category.',
							'default'     => true,
						),
						'include_children'  => array(
							'type'        => 'boolean',
							'description' => 'Include child categories.',
							'default'     => true,
						),
						'include_ancestors' => array(
							'type'        => 'boolean',
							'description' => 'Include ancestor categories.',
							'default'     => true,
						),
						'products_limit'    => array(
							'type'        => 'integer',
							'description' => 'Maximum number of products to include.',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'category'   => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'name'        => array( 'type' => 'string' ),
								'slug'        => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'parent'      => array( 'type' => 'integer' ),
								'count'       => array( 'type' => 'integer' ),
								'image'       => array( 'type' => 'object' ),
								'display'     => array( 'type' => 'string' ),
								'menu_order'  => array( 'type' => 'integer' ),
								'link'        => array( 'type' => 'string' ),
							),
						),
						'products'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'    => array( 'type' => 'integer' ),
									'name'  => array( 'type' => 'string' ),
									'slug'  => array( 'type' => 'string' ),
									'price' => array( 'type' => 'string' ),
									'image' => array( 'type' => 'string' ),
									'link'  => array( 'type' => 'string' ),
								),
							),
						),
						'children'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'    => array( 'type' => 'integer' ),
									'name'  => array( 'type' => 'string' ),
									'slug'  => array( 'type' => 'string' ),
									'count' => array( 'type' => 'integer' ),
									'link'  => array( 'type' => 'string' ),
								),
							),
						),
						'ancestors'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'   => array( 'type' => 'integer' ),
									'name' => array( 'type' => 'string' ),
									'slug' => array( 'type' => 'string' ),
									'link' => array( 'type' => 'string' ),
								),
							),
						),
						'breadcrumb' => array( 'type' => 'string' ),
						'message'    => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'ecommerce',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'categories'  => array( 'ecommerce', 'catalog' ),
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

	public static function check_permission(): bool {
		return current_user_can( 'manage_product_terms' ) || current_user_can( 'edit_products' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'category'   => array(),
				'products'   => array(),
				'children'   => array(),
				'ancestors'  => array(),
				'breadcrumb' => '',
				'message'    => 'WooCommerce is not active.',
			);
		}

		$category_id       = $input['category_id'];
		$include_products  = $input['include_products'] ?? true;
		$include_children  = $input['include_children'] ?? true;
		$include_ancestors = $input['include_ancestors'] ?? true;
		$products_limit    = $input['products_limit'] ?? 10;

		$category = get_term( $category_id, 'product_cat' );

		if ( is_wp_error( $category ) || ! $category ) {
			return array(
				'category'   => array(),
				'products'   => array(),
				'children'   => array(),
				'ancestors'  => array(),
				'breadcrumb' => '',
				'message'    => 'Category not found.',
			);
		}

		// Format category data
		$category_data = self::format_detailed_category( $category );

		// Get products if requested
		$products = array();
		if ( $include_products ) {
			$products = self::get_category_products( $category_id, $products_limit );
		}

		// Get children if requested
		$children = array();
		if ( $include_children ) {
			$children = self::get_category_children( $category_id );
		}

		// Get ancestors if requested
		$ancestors  = array();
		$breadcrumb = '';
		if ( $include_ancestors ) {
			$ancestors  = self::get_category_ancestors( $category_id );
			$breadcrumb = self::build_breadcrumb( $ancestors, $category );
		}

		return array(
			'category'   => $category_data,
			'products'   => $products,
			'children'   => $children,
			'ancestors'  => $ancestors,
			'breadcrumb' => $breadcrumb,
			'message'    => sprintf( 'Retrieved category "%s" with %d products and %d children.', $category->name, count( $products ), count( $children ) ),
		);
	}

	private static function format_detailed_category( \WP_Term $category ): array {
		$image_data   = array();
		$thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
		if ( $thumbnail_id ) {
			$image     = wp_get_attachment_image_src( $thumbnail_id, 'full' );
			$thumbnail = wp_get_attachment_image_src( $thumbnail_id, 'thumbnail' );
			if ( $image ) {
				$image_data = array(
					'id'        => $thumbnail_id,
					'src'       => $image[0],
					'thumbnail' => $thumbnail ? $thumbnail[0] : $image[0],
					'alt'       => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
					'name'      => get_the_title( $thumbnail_id ),
				);
			}
		}

		return array(
			'id'          => $category->term_id,
			'name'        => $category->name,
			'slug'        => $category->slug,
			'description' => $category->description,
			'parent'      => $category->parent,
			'count'       => $category->count,
			'image'       => $image_data,
			'display'     => get_term_meta( $category->term_id, 'display_type', true ) ?: 'default',
			'menu_order'  => (int) get_term_meta( $category->term_id, 'order', true ),
			'link'        => get_term_link( $category ),
		);
	}

	private static function get_category_products( int $category_id, int $limit ): array {
		$products = wc_get_products(
			array(
				'category' => array( $category_id ),
				'limit'    => $limit,
				'status'   => 'publish',
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$formatted_products = array();
		foreach ( $products as $product ) {
			$formatted_products[] = array(
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'slug'  => $product->get_slug(),
				'price' => $product->get_price_html(),
				'image' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				'link'  => $product->get_permalink(),
			);
		}

		return $formatted_products;
	}

	private static function get_category_children( int $category_id ): array {
		$children_terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $category_id,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $children_terms ) ) {
			return array();
		}

		$children = array();
		foreach ( $children_terms as $child ) {
			$children[] = array(
				'id'    => $child->term_id,
				'name'  => $child->name,
				'slug'  => $child->slug,
				'count' => $child->count,
				'link'  => get_term_link( $child ),
			);
		}

		return $children;
	}

	private static function get_category_ancestors( int $category_id ): array {
		$ancestor_ids = get_ancestors( $category_id, 'product_cat' );
		$ancestor_ids = array_reverse( $ancestor_ids ); // Start from root

		$ancestors = array();
		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );
			if ( ! $ancestor || is_wp_error( $ancestor ) ) {
				continue;
			}

			$ancestors[] = array(
				'id'   => $ancestor->term_id,
				'name' => $ancestor->name,
				'slug' => $ancestor->slug,
				'link' => get_term_link( $ancestor ),
			);
		}

		return $ancestors;
	}

	private static function build_breadcrumb( array $ancestors, \WP_Term $category ): string {
		$breadcrumb_parts = array();

		// Add ancestors
		foreach ( $ancestors as $ancestor ) {
			$breadcrumb_parts[] = $ancestor['name'];
		}

		// Add current category
		$breadcrumb_parts[] = $category->name;

		return implode( ' > ', $breadcrumb_parts );
	}
}
