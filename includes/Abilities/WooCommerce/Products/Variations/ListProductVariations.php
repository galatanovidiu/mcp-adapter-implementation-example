<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ListProductVariations implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/list-product-variations',
			array(
				'label'               => 'List Product Variations',
				'description'         => 'List all variations for a variable WooCommerce product with filtering and pagination.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'product_id' ),
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'Variable product ID to get variations for.',
							'minimum'     => 1,
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of variations to return.',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of variations to skip.',
							'default'     => 0,
							'minimum'     => 0,
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'Filter by variation status.',
							'enum'        => array( 'publish', 'draft', 'private', 'any' ),
							'default'     => 'publish',
						),
						'stock_status' => array(
							'type'        => 'string',
							'description' => 'Filter by stock status.',
							'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
						),
						'on_sale' => array(
							'type'        => 'boolean',
							'description' => 'Filter variations on sale only.',
						),
						'attributes' => array(
							'type'        => 'object',
							'description' => 'Filter by specific attribute values (e.g., {"color": "red", "size": "large"}).',
							'additionalProperties' => array( 'type' => 'string' ),
						),
						'orderby' => array(
							'type'        => 'string',
							'description' => 'Sort variations by field.',
							'enum'        => array( 'date', 'menu_order', 'price' ),
							'default'     => 'menu_order',
						),
						'order' => array(
							'type'        => 'string',
							'description' => 'Sort order.',
							'enum'        => array( 'asc', 'desc' ),
							'default'     => 'asc',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'parent_product' => array(
							'type'       => 'object',
							'properties' => array(
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
								'type' => array( 'type' => 'string' ),
							),
						),
						'variations' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'             => array( 'type' => 'integer' ),
									'sku'            => array( 'type' => 'string' ),
									'price'          => array( 'type' => 'string' ),
									'regular_price'  => array( 'type' => 'string' ),
									'sale_price'     => array( 'type' => 'string' ),
									'on_sale'        => array( 'type' => 'boolean' ),
									'stock_status'   => array( 'type' => 'string' ),
									'stock_quantity' => array( 'type' => 'integer' ),
									'manage_stock'   => array( 'type' => 'boolean' ),
									'weight'         => array( 'type' => 'string' ),
									'dimensions'     => array( 'type' => 'object' ),
									'image'          => array( 'type' => 'object' ),
									'attributes'     => array( 'type' => 'object' ),
									'status'         => array( 'type' => 'string' ),
									'date_created'   => array( 'type' => 'string' ),
									'date_modified'  => array( 'type' => 'string' ),
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
					'categories' => array( 'ecommerce', 'variations' ),
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
		return current_user_can( 'edit_products' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'parent_product'  => null,
				'variations'      => array(),
				'pagination'      => array(),
				'filters_applied' => $input,
				'message'         => 'WooCommerce is not active.',
			);
		}

		$product_id = $input['product_id'];
		$limit = $input['limit'] ?? 20;
		$offset = $input['offset'] ?? 0;
		$status = $input['status'] ?? 'publish';
		$stock_status = $input['stock_status'] ?? '';
		$on_sale = $input['on_sale'] ?? null;
		$attributes = $input['attributes'] ?? array();
		$orderby = $input['orderby'] ?? 'menu_order';
		$order = $input['order'] ?? 'asc';

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product instanceof \WC_Product ) {
			return array(
				'parent_product'  => null,
				'variations'      => array(),
				'pagination'      => array(),
				'filters_applied' => $input,
				'message'         => 'Product not found.',
			);
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return array(
				'parent_product'  => array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
					'type' => $product->get_type(),
				),
				'variations'      => array(),
				'pagination'      => array(),
				'filters_applied' => $input,
				'message'         => 'Product is not a variable product.',
			);
		}

		// Get all variation IDs
		$all_variation_ids = $product->get_children();
		
		// Apply filters
		$filtered_variation_ids = self::filter_variations( $all_variation_ids, $status, $stock_status, $on_sale, $attributes );

		// Apply sorting
		$sorted_variation_ids = self::sort_variations( $filtered_variation_ids, $orderby, $order );

		// Calculate pagination
		$total = count( $sorted_variation_ids );
		$current_page = floor( $offset / $limit ) + 1;
		$total_pages = ceil( $total / $limit );

		// Get variations for current page
		$page_variation_ids = array_slice( $sorted_variation_ids, $offset, $limit );
		$variations = array();

		foreach ( $page_variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && $variation instanceof \WC_Product_Variation ) {
				$variations[] = self::format_variation( $variation );
			}
		}

		$pagination = array(
			'total'        => $total,
			'total_pages'  => $total_pages,
			'current_page' => $current_page,
			'per_page'     => $limit,
			'has_next'     => $current_page < $total_pages,
			'has_prev'     => $current_page > 1,
		);

		return array(
			'parent_product' => array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
				'type' => $product->get_type(),
			),
			'variations'      => $variations,
			'pagination'      => $pagination,
			'filters_applied' => array_filter( $input ),
			'message'         => sprintf(
				'Found %d variations for "%s" (showing %d-%d of %d total).',
				count( $variations ),
				$product->get_name(),
				$offset + 1,
				min( $offset + $limit, $total ),
				$total
			),
		);
	}

	private static function filter_variations( array $variation_ids, string $status, string $stock_status, ?bool $on_sale, array $attributes ): array {
		$filtered_ids = array();

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation instanceof \WC_Product_Variation ) {
				continue;
			}

			// Filter by status
			if ( $status !== 'any' && $variation->get_status() !== $status ) {
				continue;
			}

			// Filter by stock status
			if ( ! empty( $stock_status ) && $variation->get_stock_status() !== $stock_status ) {
				continue;
			}

			// Filter by sale status
			if ( $on_sale !== null && $variation->is_on_sale() !== $on_sale ) {
				continue;
			}

			// Filter by attributes
			if ( ! empty( $attributes ) ) {
				$variation_attributes = $variation->get_variation_attributes();
				$matches = true;
				
				foreach ( $attributes as $attr_name => $attr_value ) {
					$attr_key = 'attribute_' . sanitize_title( $attr_name );
					if ( ! isset( $variation_attributes[ $attr_key ] ) || 
						 $variation_attributes[ $attr_key ] !== $attr_value ) {
						$matches = false;
						break;
					}
				}
				
				if ( ! $matches ) {
					continue;
				}
			}

			$filtered_ids[] = $variation_id;
		}

		return $filtered_ids;
	}

	private static function sort_variations( array $variation_ids, string $orderby, string $order ): array {
		if ( empty( $variation_ids ) ) {
			return $variation_ids;
		}

		// Get variations with sort data
		$variations_with_sort_data = array();
		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation ) {
				$sort_value = '';
				switch ( $orderby ) {
					case 'price':
						$sort_value = (float) $variation->get_price();
						break;
					case 'date':
						$sort_value = $variation->get_date_created() ? $variation->get_date_created()->getTimestamp() : 0;
						break;
					case 'menu_order':
					default:
						$sort_value = $variation->get_menu_order();
						break;
				}
				
				$variations_with_sort_data[] = array(
					'id'         => $variation_id,
					'sort_value' => $sort_value,
				);
			}
		}

		// Sort
		usort( $variations_with_sort_data, function( $a, $b ) use ( $order ) {
			if ( $order === 'desc' ) {
				return $b['sort_value'] <=> $a['sort_value'];
			}
			return $a['sort_value'] <=> $b['sort_value'];
		});

		return array_column( $variations_with_sort_data, 'id' );
	}

	private static function format_variation( \WC_Product_Variation $variation ): array {
		$image_data = array();
		if ( $variation->get_image_id() ) {
			$image = wp_get_attachment_image_src( $variation->get_image_id(), 'full' );
			$thumbnail = wp_get_attachment_image_src( $variation->get_image_id(), 'thumbnail' );
			if ( $image ) {
				$image_data = array(
					'id'        => $variation->get_image_id(),
					'src'       => $image[0],
					'thumbnail' => $thumbnail ? $thumbnail[0] : $image[0],
					'alt'       => get_post_meta( $variation->get_image_id(), '_wp_attachment_image_alt', true ),
				);
			}
		}

		return array(
			'id'             => $variation->get_id(),
			'sku'            => $variation->get_sku(),
			'price'          => $variation->get_price(),
			'regular_price'  => $variation->get_regular_price(),
			'sale_price'     => $variation->get_sale_price(),
			'on_sale'        => $variation->is_on_sale(),
			'stock_status'   => $variation->get_stock_status(),
			'stock_quantity' => $variation->get_stock_quantity() ? (int) $variation->get_stock_quantity() : 0,
			'manage_stock'   => $variation->get_manage_stock(),
			'weight'         => $variation->get_weight(),
			'dimensions'     => array(
				'length' => $variation->get_length(),
				'width'  => $variation->get_width(),
				'height' => $variation->get_height(),
			),
			'image'          => $image_data,
			'attributes'     => $variation->get_variation_attributes(),
			'status'         => $variation->get_status(),
			'date_created'   => $variation->get_date_created() ? $variation->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'date_modified'  => $variation->get_date_modified() ? $variation->get_date_modified()->date( 'Y-m-d H:i:s' ) : '',
		);
	}
}
