<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class CreateProduct implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/create-product',
			array(
				'label'               => 'Create WooCommerce Product',
				'description'         => 'Create a new WooCommerce product with specified details and configuration.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'name', 'type' ),
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Product name.',
						),
						'type' => array(
							'type'        => 'string',
							'description' => 'Product type.',
							'enum'        => array( 'simple', 'grouped', 'external', 'variable' ),
							'default'     => 'simple',
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'Product status.',
							'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
							'default'     => 'publish',
						),
						'slug' => array(
							'type'        => 'string',
							'description' => 'Product slug (auto-generated if not provided).',
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
							'default'     => false,
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
							'default'     => 'instock',
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
							'default'     => false,
						),
						'catalog_visibility' => array(
							'type'        => 'string',
							'description' => 'Catalog visibility.',
							'enum'        => array( 'visible', 'catalog', 'search', 'hidden' ),
							'default'     => 'visible',
						),
						'tax_status' => array(
							'type'        => 'string',
							'description' => 'Tax status.',
							'enum'        => array( 'taxable', 'shipping', 'none' ),
							'default'     => 'taxable',
						),
						'tax_class' => array(
							'type'        => 'string',
							'description' => 'Tax class.',
							'default'     => '',
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
								'id'           => array( 'type' => 'integer' ),
								'name'         => array( 'type' => 'string' ),
								'slug'         => array( 'type' => 'string' ),
								'type'         => array( 'type' => 'string' ),
								'status'       => array( 'type' => 'string' ),
								'sku'          => array( 'type' => 'string' ),
								'price'        => array( 'type' => 'string' ),
								'permalink'    => array( 'type' => 'string' ),
								'date_created' => array( 'type' => 'string' ),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'ecommerce', 'products' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => true,
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
				'message' => 'WooCommerce is not active.',
			);
		}

		$name = $input['name'];
		$type = $input['type'] ?? 'simple';
		$status = $input['status'] ?? 'publish';

		try {
			// Create product based on type
			switch ( $type ) {
				case 'variable':
					$product = new \WC_Product_Variable();
					break;
				case 'grouped':
					$product = new \WC_Product_Grouped();
					break;
				case 'external':
					$product = new \WC_Product_External();
					break;
				case 'simple':
				default:
					$product = new \WC_Product_Simple();
					break;
			}

			// Set basic properties
			$product->set_name( $name );
			$product->set_status( $status );

			if ( ! empty( $input['slug'] ) ) {
				$product->set_slug( $input['slug'] );
			}

			if ( ! empty( $input['description'] ) ) {
				$product->set_description( $input['description'] );
			}

			if ( ! empty( $input['short_description'] ) ) {
				$product->set_short_description( $input['short_description'] );
			}

			if ( ! empty( $input['sku'] ) ) {
				// Check if SKU already exists
				$existing_product_id = wc_get_product_id_by_sku( $input['sku'] );
				if ( $existing_product_id ) {
					return array(
						'success' => false,
						'product' => null,
						'message' => 'SKU already exists.',
					);
				}
				$product->set_sku( $input['sku'] );
			}

			// Set pricing
			if ( ! empty( $input['regular_price'] ) ) {
				$product->set_regular_price( $input['regular_price'] );
			}

			if ( ! empty( $input['sale_price'] ) ) {
				$product->set_sale_price( $input['sale_price'] );
			}

			// Set stock management
			$manage_stock = $input['manage_stock'] ?? false;
			$product->set_manage_stock( $manage_stock );

			if ( $manage_stock && isset( $input['stock_quantity'] ) ) {
				$product->set_stock_quantity( $input['stock_quantity'] );
			}

			if ( ! empty( $input['stock_status'] ) ) {
				$product->set_stock_status( $input['stock_status'] );
			}

			// Set physical properties
			if ( ! empty( $input['weight'] ) ) {
				$product->set_weight( $input['weight'] );
			}

			if ( ! empty( $input['dimensions'] ) ) {
				$dimensions = $input['dimensions'];
				if ( ! empty( $dimensions['length'] ) ) {
					$product->set_length( $dimensions['length'] );
				}
				if ( ! empty( $dimensions['width'] ) ) {
					$product->set_width( $dimensions['width'] );
				}
				if ( ! empty( $dimensions['height'] ) ) {
					$product->set_height( $dimensions['height'] );
				}
			}

			// Set categories
			if ( ! empty( $input['categories'] ) ) {
				$product->set_category_ids( $input['categories'] );
			}

			// Set tags
			if ( ! empty( $input['tags'] ) ) {
				$product->set_tag_ids( $input['tags'] );
			}

			// Set featured status
			if ( isset( $input['featured'] ) ) {
				$product->set_featured( $input['featured'] );
			}

			// Set catalog visibility
			if ( ! empty( $input['catalog_visibility'] ) ) {
				$product->set_catalog_visibility( $input['catalog_visibility'] );
			}

			// Set tax properties
			if ( ! empty( $input['tax_status'] ) ) {
				$product->set_tax_status( $input['tax_status'] );
			}

			if ( isset( $input['tax_class'] ) ) {
				$product->set_tax_class( $input['tax_class'] );
			}

			// Set shipping class
			if ( ! empty( $input['shipping_class'] ) ) {
				$shipping_class = get_term_by( 'slug', $input['shipping_class'], 'product_shipping_class' );
				if ( $shipping_class ) {
					$product->set_shipping_class_id( $shipping_class->term_id );
				}
			}

			// Set type-specific properties
			if ( $type === 'external' ) {
				if ( ! empty( $input['external_url'] ) ) {
					$product->set_product_url( $input['external_url'] );
				}
				if ( ! empty( $input['button_text'] ) ) {
					$product->set_button_text( $input['button_text'] );
				}
			}

			if ( $type === 'grouped' && ! empty( $input['grouped_products'] ) ) {
				$product->set_children( $input['grouped_products'] );
			}

			// Set upsells and cross-sells
			if ( ! empty( $input['upsell_ids'] ) ) {
				$product->set_upsell_ids( $input['upsell_ids'] );
			}

			if ( ! empty( $input['cross_sell_ids'] ) ) {
				$product->set_cross_sell_ids( $input['cross_sell_ids'] );
			}

			// Set images
			if ( ! empty( $input['image_id'] ) ) {
				$product->set_image_id( $input['image_id'] );
			}

			if ( ! empty( $input['gallery_image_ids'] ) ) {
				$product->set_gallery_image_ids( $input['gallery_image_ids'] );
			}

			// Save the product
			$product_id = $product->save();

			if ( ! $product_id ) {
				return array(
					'success' => false,
					'product' => null,
					'message' => 'Failed to create product.',
				);
			}

			// Get the saved product for response
			$saved_product = wc_get_product( $product_id );

			return array(
				'success' => true,
				'product' => array(
					'id'           => $saved_product->get_id(),
					'name'         => $saved_product->get_name(),
					'slug'         => $saved_product->get_slug(),
					'type'         => $saved_product->get_type(),
					'status'       => $saved_product->get_status(),
					'sku'          => $saved_product->get_sku(),
					'price'        => $saved_product->get_price(),
					'permalink'    => $saved_product->get_permalink(),
					'date_created' => $saved_product->get_date_created() ? $saved_product->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
				),
				'message' => sprintf( 'Successfully created product "%s" with ID %d.', $saved_product->get_name(), $saved_product->get_id() ),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'product' => null,
				'message' => 'Error creating product: ' . $e->getMessage(),
			);
		}
	}
}
