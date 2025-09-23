<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class UpdateProductVariation implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/update-product-variation',
			array(
				'label'               => 'Update Product Variation',
				'description'         => 'Update an existing WooCommerce product variation with new pricing, stock, or attribute information.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'variation_id' ),
					'properties' => array(
						'variation_id' => array(
							'type'        => 'integer',
							'description' => 'Variation ID to update.',
							'minimum'     => 1,
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
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'Menu order for variation sorting.',
						),
						'attributes' => array(
							'type'        => 'object',
							'description' => 'Variation attributes (e.g., {"color": "red", "size": "large"}).',
							'additionalProperties' => array( 'type' => 'string' ),
						),
						'tax_class' => array(
							'type'        => 'string',
							'description' => 'Tax class for this variation.',
						),
						'shipping_class' => array(
							'type'        => 'string',
							'description' => 'Shipping class slug.',
						),
						'date_on_sale_from' => array(
							'type'        => 'string',
							'description' => 'Sale start date (YYYY-MM-DD HH:MM:SS).',
						),
						'date_on_sale_to' => array(
							'type'        => 'string',
							'description' => 'Sale end date (YYYY-MM-DD HH:MM:SS).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'variation' => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array( 'type' => 'integer' ),
								'parent_id'      => array( 'type' => 'integer' ),
								'sku'            => array( 'type' => 'string' ),
								'price'          => array( 'type' => 'string' ),
								'regular_price'  => array( 'type' => 'string' ),
								'sale_price'     => array( 'type' => 'string' ),
								'stock_status'   => array( 'type' => 'string' ),
								'stock_quantity' => array( 'type' => 'integer' ),
								'attributes'     => array( 'type' => 'object' ),
								'status'         => array( 'type' => 'string' ),
								'date_modified'  => array( 'type' => 'string' ),
							),
						),
						'changes_made' => array( 'type' => 'array' ),
						'parent_product' => array(
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
					'categories' => array( 'ecommerce', 'variations' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
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
				'success'        => false,
				'variation'      => null,
				'changes_made'   => array(),
				'parent_product' => null,
				'message'        => 'WooCommerce is not active.',
			);
		}

		$variation_id = $input['variation_id'];
		$variation = wc_get_product( $variation_id );

		if ( ! $variation || ! $variation instanceof \WC_Product_Variation ) {
			return array(
				'success'        => false,
				'variation'      => null,
				'changes_made'   => array(),
				'parent_product' => null,
				'message'        => 'Variation not found.',
			);
		}

		$changes_made = array();

		try {
			// Update SKU
			if ( isset( $input['sku'] ) ) {
				// Check if SKU already exists (excluding current variation)
				$existing_product_id = wc_get_product_id_by_sku( $input['sku'] );
				if ( $existing_product_id && $existing_product_id !== $variation_id ) {
					return array(
						'success'        => false,
						'variation'      => null,
						'changes_made'   => array(),
						'parent_product' => null,
						'message'        => 'SKU already exists on another product.',
					);
				}
				$variation->set_sku( $input['sku'] );
				$changes_made[] = 'sku';
			}

			// Update pricing
			if ( isset( $input['regular_price'] ) ) {
				$variation->set_regular_price( $input['regular_price'] );
				$changes_made[] = 'regular_price';
			}

			if ( isset( $input['sale_price'] ) ) {
				$variation->set_sale_price( $input['sale_price'] );
				$changes_made[] = 'sale_price';
			}

			// Update stock management
			if ( isset( $input['manage_stock'] ) ) {
				$variation->set_manage_stock( $input['manage_stock'] );
				$changes_made[] = 'manage_stock';
			}

			if ( isset( $input['stock_quantity'] ) ) {
				$variation->set_stock_quantity( $input['stock_quantity'] );
				$changes_made[] = 'stock_quantity';
			}

			if ( isset( $input['stock_status'] ) ) {
				$variation->set_stock_status( $input['stock_status'] );
				$changes_made[] = 'stock_status';
			}

			// Update physical properties
			if ( isset( $input['weight'] ) ) {
				$variation->set_weight( $input['weight'] );
				$changes_made[] = 'weight';
			}

			if ( isset( $input['dimensions'] ) ) {
				$dimensions = $input['dimensions'];
				if ( isset( $dimensions['length'] ) ) {
					$variation->set_length( $dimensions['length'] );
					$changes_made[] = 'length';
				}
				if ( isset( $dimensions['width'] ) ) {
					$variation->set_width( $dimensions['width'] );
					$changes_made[] = 'width';
				}
				if ( isset( $dimensions['height'] ) ) {
					$variation->set_height( $dimensions['height'] );
					$changes_made[] = 'height';
				}
			}

			// Update image
			if ( isset( $input['image_id'] ) ) {
				$variation->set_image_id( $input['image_id'] );
				$changes_made[] = 'image_id';
			}

			// Update status and menu order
			if ( isset( $input['status'] ) ) {
				$variation->set_status( $input['status'] );
				$changes_made[] = 'status';
			}

			if ( isset( $input['menu_order'] ) ) {
				$variation->set_menu_order( $input['menu_order'] );
				$changes_made[] = 'menu_order';
			}

			// Update attributes
			if ( isset( $input['attributes'] ) ) {
				$formatted_attributes = array();
				foreach ( $input['attributes'] as $attr_name => $attr_value ) {
					$attr_key = 'attribute_' . sanitize_title( $attr_name );
					$formatted_attributes[ $attr_key ] = $attr_value;
				}
				$variation->set_attributes( $formatted_attributes );
				$changes_made[] = 'attributes';
			}

			// Update tax class
			if ( isset( $input['tax_class'] ) ) {
				$variation->set_tax_class( $input['tax_class'] );
				$changes_made[] = 'tax_class';
			}

			// Update shipping class
			if ( isset( $input['shipping_class'] ) ) {
				$shipping_class = get_term_by( 'slug', $input['shipping_class'], 'product_shipping_class' );
				if ( $shipping_class ) {
					$variation->set_shipping_class_id( $shipping_class->term_id );
					$changes_made[] = 'shipping_class';
				}
			}

			// Update sale dates
			if ( isset( $input['date_on_sale_from'] ) ) {
				$variation->set_date_on_sale_from( $input['date_on_sale_from'] );
				$changes_made[] = 'date_on_sale_from';
			}

			if ( isset( $input['date_on_sale_to'] ) ) {
				$variation->set_date_on_sale_to( $input['date_on_sale_to'] );
				$changes_made[] = 'date_on_sale_to';
			}

			// Save the variation
			$variation->save();

			// Get parent product info
			$parent = wc_get_product( $variation->get_parent_id() );
			$parent_product = null;
			if ( $parent ) {
				$parent_product = array(
					'id'   => $parent->get_id(),
					'name' => $parent->get_name(),
				);
			}

			return array(
				'success'        => true,
				'variation'      => array(
					'id'             => $variation->get_id(),
					'parent_id'      => $variation->get_parent_id(),
					'sku'            => $variation->get_sku(),
					'price'          => $variation->get_price(),
					'regular_price'  => $variation->get_regular_price(),
					'sale_price'     => $variation->get_sale_price(),
					'stock_status'   => $variation->get_stock_status(),
					'stock_quantity' => $variation->get_stock_quantity() ? (int) $variation->get_stock_quantity() : 0,
					'attributes'     => $variation->get_variation_attributes(),
					'status'         => $variation->get_status(),
					'date_modified'  => $variation->get_date_modified() ? $variation->get_date_modified()->date( 'Y-m-d H:i:s' ) : '',
				),
				'changes_made'   => $changes_made,
				'parent_product' => $parent_product,
				'message'        => sprintf(
					'Successfully updated variation ID %d. Changes: %s',
					$variation->get_id(),
					! empty( $changes_made ) ? implode( ', ', $changes_made ) : 'none'
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success'        => false,
				'variation'      => null,
				'changes_made'   => array(),
				'parent_product' => null,
				'message'        => 'Error updating variation: ' . $e->getMessage(),
			);
		}
	}
}
