<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ListProducts implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/list-products',
			array(
				'label'               => 'List WooCommerce Products',
				'description'         => 'List WooCommerce products with filtering, searching, and pagination options.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of products to return.',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of products to skip.',
							'default'     => 0,
							'minimum'     => 0,
						),
						'search' => array(
							'type'        => 'string',
							'description' => 'Search products by name, description, or SKU.',
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'Filter by product status.',
							'enum'        => array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ),
							'default'     => 'publish',
						),
						'type' => array(
							'type'        => 'string',
							'description' => 'Filter by product type.',
							'enum'        => array( 'simple', 'grouped', 'external', 'variable', 'any' ),
							'default'     => 'any',
						),
						'category' => array(
							'type'        => 'string',
							'description' => 'Filter by product category slug.',
						),
						'tag' => array(
							'type'        => 'string',
							'description' => 'Filter by product tag slug.',
						),
						'sku' => array(
							'type'        => 'string',
							'description' => 'Filter by specific SKU.',
						),
						'featured' => array(
							'type'        => 'boolean',
							'description' => 'Filter featured products only.',
						),
						'on_sale' => array(
							'type'        => 'boolean',
							'description' => 'Filter products on sale only.',
						),
						'stock_status' => array(
							'type'        => 'string',
							'description' => 'Filter by stock status.',
							'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
						),
						'min_price' => array(
							'type'        => 'number',
							'description' => 'Minimum price filter.',
							'minimum'     => 0,
						),
						'max_price' => array(
							'type'        => 'number',
							'description' => 'Maximum price filter.',
							'minimum'     => 0,
						),
						'orderby' => array(
							'type'        => 'string',
							'description' => 'Sort products by field.',
							'enum'        => array( 'date', 'title', 'menu_order', 'price', 'popularity', 'rating' ),
							'default'     => 'date',
						),
						'order' => array(
							'type'        => 'string',
							'description' => 'Sort order.',
							'enum'        => array( 'asc', 'desc' ),
							'default'     => 'desc',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'products' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'               => array( 'type' => 'integer' ),
									'name'             => array( 'type' => 'string' ),
									'slug'             => array( 'type' => 'string' ),
									'permalink'        => array( 'type' => 'string' ),
									'type'             => array( 'type' => 'string' ),
									'status'           => array( 'type' => 'string' ),
									'featured'         => array( 'type' => 'boolean' ),
									'catalog_visibility' => array( 'type' => 'string' ),
									'description'      => array( 'type' => 'string' ),
									'short_description' => array( 'type' => 'string' ),
									'sku'              => array( 'type' => 'string' ),
									'price'            => array( 'type' => 'string' ),
									'regular_price'    => array( 'type' => 'string' ),
									'sale_price'       => array( 'type' => 'string' ),
									'on_sale'          => array( 'type' => 'boolean' ),
									'stock_status'     => array( 'type' => 'string' ),
									'stock_quantity'   => array( 'type' => 'integer' ),
									'manage_stock'     => array( 'type' => 'boolean' ),
									'categories'       => array( 'type' => 'array' ),
									'tags'             => array( 'type' => 'array' ),
									'images'           => array( 'type' => 'array' ),
									'attributes'       => array( 'type' => 'array' ),
									'variations'       => array( 'type' => 'array' ),
									'average_rating'   => array( 'type' => 'string' ),
									'rating_count'     => array( 'type' => 'integer' ),
									'date_created'     => array( 'type' => 'string' ),
									'date_modified'    => array( 'type' => 'string' ),
								),
							),
						),
						'pagination' => array(
							'type'       => 'object',
							'properties' => array(
								'total'        => array( 'type' => 'integer' ),
								'total_pages'  => array( 'type' => 'integer' ),
								'current_page' => array( 'type' => 'integer' ),
								'per_page'     => array( 'type' => 'integer' ),
								'has_next'     => array( 'type' => 'boolean' ),
								'has_prev'     => array( 'type' => 'boolean' ),
							),
						),
						'filters_applied' => array( 'type' => 'object' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'ecommerce', 'products' ),
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

	public static function check_permission(): bool {
		return current_user_can( 'edit_products' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'products'    => array(),
				'pagination'  => array(),
				'filters_applied' => $input,
				'message'     => 'WooCommerce is not active.',
			);
		}

		$limit = $input['limit'] ?? 20;
		$offset = $input['offset'] ?? 0;
		$search = $input['search'] ?? '';
		$status = $input['status'] ?? 'publish';
		$type = $input['type'] ?? 'any';
		$category = $input['category'] ?? '';
		$tag = $input['tag'] ?? '';
		$sku = $input['sku'] ?? '';
		$featured = $input['featured'] ?? null;
		$on_sale = $input['on_sale'] ?? null;
		$stock_status = $input['stock_status'] ?? '';
		$min_price = $input['min_price'] ?? null;
		$max_price = $input['max_price'] ?? null;
		$orderby = $input['orderby'] ?? 'date';
		$order = $input['order'] ?? 'desc';

		// Build query args
		$args = array(
			'limit'   => $limit,
			'offset'  => $offset,
			'orderby' => $orderby,
			'order'   => $order,
			'return'  => 'objects',
		);

		// Add filters
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		if ( $status !== 'any' ) {
			$args['status'] = $status;
		}

		if ( $type !== 'any' ) {
			$args['type'] = $type;
		}

		if ( ! empty( $category ) ) {
			$args['category'] = array( $category );
		}

		if ( ! empty( $tag ) ) {
			$args['tag'] = array( $tag );
		}

		if ( ! empty( $sku ) ) {
			$args['sku'] = $sku;
		}

		if ( $featured !== null ) {
			$args['featured'] = $featured;
		}

		if ( $on_sale !== null ) {
			$args['on_sale'] = $on_sale;
		}

		if ( ! empty( $stock_status ) ) {
			$args['stock_status'] = $stock_status;
		}

		if ( $min_price !== null || $max_price !== null ) {
			$args['meta_query'] = array();
			
			if ( $min_price !== null ) {
				$args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => $min_price,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}

			if ( $max_price !== null ) {
				$args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => $max_price,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}
		}

		// Get products using WooCommerce function
		$products_query = wc_get_products( $args );
		
		// Get total count for pagination
		$total_args = $args;
		$total_args['limit'] = -1;
		$total_args['return'] = 'ids';
		$total_products = wc_get_products( $total_args );
		$total = is_array( $total_products ) ? count( $total_products ) : 0;

		// Format products
		$formatted_products = array();
		foreach ( $products_query as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$formatted_products[] = self::format_product( $product );
		}

		// Calculate pagination
		$current_page = floor( $offset / $limit ) + 1;
		$total_pages = ceil( $total / $limit );

		$pagination = array(
			'total'        => $total,
			'total_pages'  => $total_pages,
			'current_page' => $current_page,
			'per_page'     => $limit,
			'has_next'     => $current_page < $total_pages,
			'has_prev'     => $current_page > 1,
		);

		return array(
			'products'        => $formatted_products,
			'pagination'      => $pagination,
			'filters_applied' => array_filter( $input ),
			'message'         => sprintf(
				'Found %d products (showing %d-%d of %d total).',
				count( $formatted_products ),
				$offset + 1,
				min( $offset + $limit, $total ),
				$total
			),
		);
	}

	private static function format_product( \WC_Product $product ): array {
		$categories = array();
		$category_ids = $product->get_category_ids();
		foreach ( $category_ids as $cat_id ) {
			$category = get_term( $cat_id, 'product_cat' );
			if ( $category && ! is_wp_error( $category ) ) {
				$categories[] = array(
					'id'   => $category->term_id,
					'name' => $category->name,
					'slug' => $category->slug,
				);
			}
		}

		$tags = array();
		$tag_ids = $product->get_tag_ids();
		foreach ( $tag_ids as $tag_id ) {
			$tag = get_term( $tag_id, 'product_tag' );
			if ( $tag && ! is_wp_error( $tag ) ) {
				$tags[] = array(
					'id'   => $tag->term_id,
					'name' => $tag->name,
					'slug' => $tag->slug,
				);
			}
		}

		$images = array();
		$image_ids = $product->get_gallery_image_ids();
		if ( $product->get_image_id() ) {
			array_unshift( $image_ids, $product->get_image_id() );
		}
		foreach ( $image_ids as $image_id ) {
			$image = wp_get_attachment_image_src( $image_id, 'full' );
			if ( $image ) {
				$images[] = array(
					'id'  => $image_id,
					'src' => $image[0],
					'alt' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
				);
			}
		}

		$attributes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			$attributes[] = array(
				'name'      => $attribute->get_name(),
				'options'   => $attribute->get_options(),
				'visible'   => $attribute->get_visible(),
				'variation' => $attribute->get_variation(),
			);
		}

		$variations = array();
		if ( $product->is_type( 'variable' ) ) {
			$variation_ids = $product->get_children();
			foreach ( $variation_ids as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$variations[] = array(
						'id'            => $variation->get_id(),
						'sku'           => $variation->get_sku(),
						'price'         => $variation->get_price(),
						'regular_price' => $variation->get_regular_price(),
						'sale_price'    => $variation->get_sale_price(),
						'stock_status'  => $variation->get_stock_status(),
						'stock_quantity' => $variation->get_stock_quantity() ? (int) $variation->get_stock_quantity() : 0,
						'attributes'    => $variation->get_variation_attributes(),
					);
				}
			}
		}

		return array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'slug'              => $product->get_slug(),
			'permalink'         => $product->get_permalink(),
			'type'              => $product->get_type(),
			'status'            => $product->get_status(),
			'featured'          => $product->get_featured(),
			'catalog_visibility' => $product->get_catalog_visibility(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'sku'               => $product->get_sku(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'on_sale'           => $product->is_on_sale(),
			'stock_status'      => $product->get_stock_status(),
			'stock_quantity'    => $product->get_stock_quantity() ? (int) $product->get_stock_quantity() : 0,
			'manage_stock'      => $product->get_manage_stock(),
			'categories'        => $categories,
			'tags'              => $tags,
			'images'            => $images,
			'attributes'        => $attributes,
			'variations'        => $variations,
			'average_rating'    => $product->get_average_rating(),
			'rating_count'      => $product->get_rating_count(),
			'date_created'      => $product->get_date_created() ? $product->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'date_modified'     => $product->get_date_modified() ? $product->get_date_modified()->date( 'Y-m-d H:i:s' ) : null,
		);
	}
}
