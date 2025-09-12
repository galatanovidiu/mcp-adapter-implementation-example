<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class UpdateProduct implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/update-product',
			array(
				'label'               => 'Update WooCommerce Product',
				'description'         => 'Update an existing WooCommerce product with new details and configuration.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Product ID to update.',
							'minimum'     => 1,
						),
						'name' => array(
							'type'        => 'string',
							'description' => 'Product name.',
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'Product status.',
							'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
						),
						'slug' => array(
							'type'        => 'string',
							'description' => 'Product slug.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Product description (HTML allowed).',
						),
						'short_description' => array(
							'type'        => 'string',
							'description' => 'Product short description (HTML allowed).',
						),
						'sku' => array(
							'type'        => 'string',
							'description' => 'Product SKU (must be unique).',
						),
						'regular_price' => array(
							'type'        => 'string',
							'description' => 'Product regular price.',
						),
						'sale_price' => array(
							'type'        => 'string',
							'description' => 'Product sale price.',
						),
						'manage_stock' => array(
							'type'        => 'boolean',
							'description' => 'Enable stock management.',
						),
						'stock_quantity' => array(
							'type'        => 'integer',
							'description' => 'Stock quantity (if manage_stock is true).',
							'minimum'     => 0,
						),
						'stock_status' => array(
							'type'        => 'string',
							'description' => 'Stock status.',
							'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
						),
						'weight' => array(
							'type'        => 'string',
							'description' => 'Product weight.',
						),
						'dimensions' => array(
							'type'       => 'object',
							'description' => 'Product dimensions.',
							'properties' => array(
								'length' => array( 'type' => 'string' ),
								'width'  => array( 'type' => 'string' ),
								'height' => array( 'type' => 'string' ),
							),
						),
						'categories' => array(
							'type'        => 'array',
							'description' => 'Product category IDs.',
							'items'       => array( 'type' => 'integer' ),
						),
						'tags' => array(
							'type'        => 'array',
							'description' => 'Product tag IDs.',
							'items'       => array( 'type' => 'integer' ),
						),
						'featured' => array(
							'type'        => 'boolean',
							'description' => 'Mark as featured product.',
						),
						'catalog_visibility' => array(
							'type'        => 'string',
							'description' => 'Catalog visibility.',
							'enum'        => array( 'visible', 'catalog', 'search', 'hidden' ),
						),
						'tax_status' => array(
							'type'        => 'string',
							'description' => 'Tax status.',
							'enum'        => array( 'taxable', 'shipping', 'none' ),
						),
						'tax_class' => array(
							'type'        => 'string',
							'description' => 'Tax class.',
						),
						'shipping_class' => array(
							'type'        => 'string',
							'description' => 'Shipping class slug.',
						),
						'external_url' => array(
							'type'        => 'string',
							'description' => 'External product URL (for external products).',
						),
						'button_text' => array(
							'type'        => 'string',
							'description' => 'External product button text.',
						),
						'grouped_products' => array(
							'type'        => 'array',
							'description' => 'Grouped product IDs (for grouped products).',
							'items'       => array( 'type' => 'integer' ),
						),
						'upsell_ids' => array(
							'type'        => 'array',
							'description' => 'Upsell product IDs.',
							'items'       => array( 'type' => 'integer' ),
						),
						'cross_sell_ids' => array(
							'type'        => 'array',
							'description' => 'Cross-sell product IDs.',
							'items'       => array( 'type' => 'integer' ),
						),
						'image_id' => array(
							'type'        => 'integer',
							'description' => 'Featured image attachment ID.',
						),
						'gallery_image_ids' => array(
							'type'        => 'array',
							'description' => 'Gallery image attachment IDs.',
							'items'       => array( 'type' => 'integer' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'product' => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array( 'type' => 'integer' ),
								'name'          => array( 'type' => 'string' ),
								'slug'          => array( 'type' => 'string' ),
								'type'          => array( 'type' => 'string' ),
								'status'        => array( 'type' => 'string' ),
								'sku'           => array( 'type' => 'string' ),
								'price'         => array( 'type' => 'string' ),
								'permalink'     => array( 'type' => 'string' ),
								'date_modified' => array( 'type' => 'string' ),
							),
						),
						'changes_made' => array( 'type' => 'array' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'ecommerce', 'products' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
						'readOnlyHint'    => false,
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
				'success' => false,
				'product' => null,
				'changes_made' => array(),
				'message' => 'WooCommerce is not active.',
			);
		}

		$product_id = $input['id'];
		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product instanceof \WC_Product ) {
			return array(
				'success' => false,
				'product' => null,
				'changes_made' => array(),
				'message' => 'Product not found.',
			);
		}

		$changes_made = array();

		try {
			// Update basic properties
			if ( isset( $input['name'] ) ) {
				$product->set_name( $input['name'] );
				$changes_made[] = 'name';
			}

			if ( isset( $input['status'] ) ) {
				$product->set_status( $input['status'] );
				$changes_made[] = 'status';
			}

			if ( isset( $input['slug'] ) ) {
				$product->set_slug( $input['slug'] );
				$changes_made[] = 'slug';
			}

			if ( isset( $input['description'] ) ) {
				$product->set_description( $input['description'] );
				$changes_made[] = 'description';
			}

			if ( isset( $input['short_description'] ) ) {
				$product->set_short_description( $input['short_description'] );
				$changes_made[] = 'short_description';
			}

			if ( isset( $input['sku'] ) ) {
				// Check if SKU already exists (excluding current product)
				$existing_product_id = wc_get_product_id_by_sku( $input['sku'] );
				if ( $existing_product_id && $existing_product_id !== $product_id ) {
					return array(
						'success' => false,
						'product' => null,
						'changes_made' => array(),
						'message' => 'SKU already exists on another product.',
					);
				}
				$product->set_sku( $input['sku'] );
				$changes_made[] = 'sku';
			}

			// Update pricing
			if ( isset( $input['regular_price'] ) ) {
				$product->set_regular_price( $input['regular_price'] );
				$changes_made[] = 'regular_price';
			}

			if ( isset( $input['sale_price'] ) ) {
				$product->set_sale_price( $input['sale_price'] );
				$changes_made[] = 'sale_price';
			}

			// Update stock management
			if ( isset( $input['manage_stock'] ) ) {
				$product->set_manage_stock( $input['manage_stock'] );
				$changes_made[] = 'manage_stock';
			}

			if ( isset( $input['stock_quantity'] ) ) {
				$product->set_stock_quantity( $input['stock_quantity'] );
				$changes_made[] = 'stock_quantity';
			}

			if ( isset( $input['stock_status'] ) ) {
				$product->set_stock_status( $input['stock_status'] );
				$changes_made[] = 'stock_status';
			}

			// Update physical properties
			if ( isset( $input['weight'] ) ) {
				$product->set_weight( $input['weight'] );
				$changes_made[] = 'weight';
			}

			if ( isset( $input['dimensions'] ) ) {
				$dimensions = $input['dimensions'];
				if ( isset( $dimensions['length'] ) ) {
					$product->set_length( $dimensions['length'] );
					$changes_made[] = 'length';
				}
				if ( isset( $dimensions['width'] ) ) {
					$product->set_width( $dimensions['width'] );
					$changes_made[] = 'width';
				}
				if ( isset( $dimensions['height'] ) ) {
					$product->set_height( $dimensions['height'] );
					$changes_made[] = 'height';
				}
			}

			// Update categories
			if ( isset( $input['categories'] ) ) {
				$product->set_category_ids( $input['categories'] );
				$changes_made[] = 'categories';
			}

			// Update tags
			if ( isset( $input['tags'] ) ) {
				$product->set_tag_ids( $input['tags'] );
				$changes_made[] = 'tags';
			}

			// Update featured status
			if ( isset( $input['featured'] ) ) {
				$product->set_featured( $input['featured'] );
				$changes_made[] = 'featured';
			}

			// Update catalog visibility
			if ( isset( $input['catalog_visibility'] ) ) {
				$product->set_catalog_visibility( $input['catalog_visibility'] );
				$changes_made[] = 'catalog_visibility';
			}

			// Update tax properties
			if ( isset( $input['tax_status'] ) ) {
				$product->set_tax_status( $input['tax_status'] );
				$changes_made[] = 'tax_status';
			}

			if ( isset( $input['tax_class'] ) ) {
				$product->set_tax_class( $input['tax_class'] );
				$changes_made[] = 'tax_class';
			}

			// Update shipping class
			if ( isset( $input['shipping_class'] ) ) {
				$shipping_class = get_term_by( 'slug', $input['shipping_class'], 'product_shipping_class' );
				if ( $shipping_class ) {
					$product->set_shipping_class_id( $shipping_class->term_id );
					$changes_made[] = 'shipping_class';
				}
			}

			// Update type-specific properties
			if ( $product->is_type( 'external' ) ) {
				if ( isset( $input['external_url'] ) ) {
					$product->set_product_url( $input['external_url'] );
					$changes_made[] = 'external_url';
				}
				if ( isset( $input['button_text'] ) ) {
					$product->set_button_text( $input['button_text'] );
					$changes_made[] = 'button_text';
				}
			}

			if ( $product->is_type( 'grouped' ) && isset( $input['grouped_products'] ) ) {
				$product->set_children( $input['grouped_products'] );
				$changes_made[] = 'grouped_products';
			}

			// Update upsells and cross-sells
			if ( isset( $input['upsell_ids'] ) ) {
				$product->set_upsell_ids( $input['upsell_ids'] );
				$changes_made[] = 'upsell_ids';
			}

			if ( isset( $input['cross_sell_ids'] ) ) {
				$product->set_cross_sell_ids( $input['cross_sell_ids'] );
				$changes_made[] = 'cross_sell_ids';
			}

			// Update images
			if ( isset( $input['image_id'] ) ) {
				$product->set_image_id( $input['image_id'] );
				$changes_made[] = 'image_id';
			}

			if ( isset( $input['gallery_image_ids'] ) ) {
				$product->set_gallery_image_ids( $input['gallery_image_ids'] );
				$changes_made[] = 'gallery_image_ids';
			}

			// Save the product
			$product->save();

			return array(
				'success' => true,
				'product' => array(
					'id'            => $product->get_id(),
					'name'          => $product->get_name(),
					'slug'          => $product->get_slug(),
					'type'          => $product->get_type(),
					'status'        => $product->get_status(),
					'sku'           => $product->get_sku(),
					'price'         => $product->get_price(),
					'permalink'     => $product->get_permalink(),
					'date_modified' => $product->get_date_modified() ? $product->get_date_modified()->date( 'Y-m-d H:i:s' ) : null,
				),
				'changes_made' => $changes_made,
				'message' => sprintf( 
					'Successfully updated product "%s" (ID: %d). Changes: %s',
					$product->get_name(),
					$product->get_id(),
					! empty( $changes_made ) ? implode( ', ', $changes_made ) : 'none'
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'product' => null,
				'changes_made' => array(),
				'message' => 'Error updating product: ' . $e->getMessage(),
			);
		}
	}
}
