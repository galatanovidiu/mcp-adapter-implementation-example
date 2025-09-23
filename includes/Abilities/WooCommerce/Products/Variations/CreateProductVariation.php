<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class CreateProductVariation implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/create-product-variation',
			array(
				'label'               => 'Create Product Variation',
				'description'         => 'Create a new variation for a variable WooCommerce product with specific attributes and pricing.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'product_id', 'attributes' ),
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'Variable product ID to create variation for.',
							'minimum'     => 1,
						),
						'attributes' => array(
							'type'        => 'object',
							'description' => 'Variation attributes (e.g., {"color": "red", "size": "large"}).',
							'additionalProperties' => array( 'type' => 'string' ),
						),
						'sku' => array(
							'type'        => 'string',
							'description' => 'Variation SKU (must be unique).',
						),
						'regular_price' => array(
							'type'        => 'string',
							'description' => 'Variation regular price.',
						),
						'sale_price' => array(
							'type'        => 'string',
							'description' => 'Variation sale price.',
						),
						'manage_stock' => array(
							'type'        => 'boolean',
							'description' => 'Enable stock management for this variation.',
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
							'description' => 'Variation weight.',
						),
						'dimensions' => array(
							'type'       => 'object',
							'description' => 'Variation dimensions.',
							'properties' => array(
								'length' => array( 'type' => 'string' ),
								'width'  => array( 'type' => 'string' ),
								'height' => array( 'type' => 'string' ),
							),
						),
						'image_id' => array(
							'type'        => 'integer',
							'description' => 'Variation image attachment ID.',
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'Variation status.',
							'enum'        => array( 'publish', 'draft', 'private' ),
							'default'     => 'publish',
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'Menu order for variation sorting.',
							'default'     => 0,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'parent_product' => array(
							'type'       => 'object',
							'properties' => array(
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
								'type' => array( 'type' => 'string' ),
							),
						),
						'variation' => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array( 'type' => 'integer' ),
								'sku'            => array( 'type' => 'string' ),
								'price'          => array( 'type' => 'string' ),
								'regular_price'  => array( 'type' => 'string' ),
								'sale_price'     => array( 'type' => 'string' ),
								'stock_status'   => array( 'type' => 'string' ),
								'stock_quantity' => array( 'type' => 'integer' ),
								'attributes'     => array( 'type' => 'object' ),
								'status'         => array( 'type' => 'string' ),
								'date_created'   => array( 'type' => 'string' ),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'ecommerce', 'variations' ),
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
		return current_user_can( 'edit_products' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success'        => false,
				'parent_product' => null,
				'variation'      => null,
				'message'        => 'WooCommerce is not active.',
			);
		}

		$product_id = $input['product_id'];
		$attributes = $input['attributes'];

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product instanceof \WC_Product ) {
			return array(
				'success'        => false,
				'parent_product' => null,
				'variation'      => null,
				'message'        => 'Product not found.',
			);
		}

		if ( ! $product->is_type( 'variable' ) ) {
			return array(
				'success'        => false,
				'parent_product' => array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
					'type' => $product->get_type(),
				),
				'variation'      => null,
				'message'        => 'Product is not a variable product.',
			);
		}

		try {
			// Create new variation
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product_id );

			// Set attributes
			$formatted_attributes = array();
			foreach ( $attributes as $attr_name => $attr_value ) {
				$attr_key = 'attribute_' . sanitize_title( $attr_name );
				$formatted_attributes[ $attr_key ] = $attr_value;
			}
			$variation->set_attributes( $formatted_attributes );

			// Set optional properties
			if ( ! empty( $input['sku'] ) ) {
				// Check if SKU already exists
				$existing_product_id = wc_get_product_id_by_sku( $input['sku'] );
				if ( $existing_product_id ) {
					return array(
						'success'        => false,
						'parent_product' => array(
							'id'   => $product->get_id(),
							'name' => $product->get_name(),
							'type' => $product->get_type(),
						),
						'variation'      => null,
						'message'        => 'SKU already exists.',
					);
				}
				$variation->set_sku( $input['sku'] );
			}

			if ( ! empty( $input['regular_price'] ) ) {
				$variation->set_regular_price( $input['regular_price'] );
			}

			if ( ! empty( $input['sale_price'] ) ) {
				$variation->set_sale_price( $input['sale_price'] );
			}

			// Set stock management
			$manage_stock = $input['manage_stock'] ?? false;
			$variation->set_manage_stock( $manage_stock );

			if ( $manage_stock && isset( $input['stock_quantity'] ) ) {
				$variation->set_stock_quantity( $input['stock_quantity'] );
			}

			if ( ! empty( $input['stock_status'] ) ) {
				$variation->set_stock_status( $input['stock_status'] );
			}

			// Set physical properties
			if ( ! empty( $input['weight'] ) ) {
				$variation->set_weight( $input['weight'] );
			}

			if ( ! empty( $input['dimensions'] ) ) {
				$dimensions = $input['dimensions'];
				if ( ! empty( $dimensions['length'] ) ) {
					$variation->set_length( $dimensions['length'] );
				}
				if ( ! empty( $dimensions['width'] ) ) {
					$variation->set_width( $dimensions['width'] );
				}
				if ( ! empty( $dimensions['height'] ) ) {
					$variation->set_height( $dimensions['height'] );
				}
			}

			// Set image
			if ( ! empty( $input['image_id'] ) ) {
				$variation->set_image_id( $input['image_id'] );
			}

			// Set status and menu order
			$variation->set_status( $input['status'] ?? 'publish' );
			$variation->set_menu_order( $input['menu_order'] ?? 0 );

			// Save the variation
			$variation_id = $variation->save();

			if ( ! $variation_id ) {
				return array(
					'success'        => false,
					'parent_product' => array(
						'id'   => $product->get_id(),
						'name' => $product->get_name(),
						'type' => $product->get_type(),
					),
					'variation'      => null,
					'message'        => 'Failed to create variation.',
				);
			}

			// Get the saved variation for response
			$saved_variation = wc_get_product( $variation_id );

			return array(
				'success'        => true,
				'parent_product' => array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
					'type' => $product->get_type(),
				),
				'variation'      => array(
					'id'             => $saved_variation->get_id(),
					'sku'            => $saved_variation->get_sku(),
					'price'          => $saved_variation->get_price(),
					'regular_price'  => $saved_variation->get_regular_price(),
					'sale_price'     => $saved_variation->get_sale_price(),
					'stock_status'   => $saved_variation->get_stock_status(),
					'stock_quantity' => $saved_variation->get_stock_quantity() ? (int) $saved_variation->get_stock_quantity() : 0,
					'attributes'     => $saved_variation->get_variation_attributes(),
					'status'         => $saved_variation->get_status(),
					'date_created'   => $saved_variation->get_date_created() ? $saved_variation->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
				),
				'message'        => sprintf(
					'Successfully created variation for "%s" with attributes: %s',
					$product->get_name(),
					implode( ', ', array_map( function( $k, $v ) { return "$k: $v"; }, array_keys( $attributes ), $attributes ) )
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success'        => false,
				'parent_product' => array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
					'type' => $product->get_type(),
				),
				'variation'      => null,
				'message'        => 'Error creating variation: ' . $e->getMessage(),
			);
		}
	}
}
