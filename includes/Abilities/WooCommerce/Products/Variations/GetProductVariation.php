<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class GetProductVariation implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/get-product-variation',
			array(
				'label'               => 'Get Product Variation',
				'description'         => 'Retrieve detailed information about a specific WooCommerce product variation.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'variation_id' ),
					'properties' => array(
						'variation_id' => array(
							'type'        => 'integer',
							'description' => 'Variation ID to retrieve.',
							'minimum'     => 1,
						),
						'include_parent_info' => array(
							'type'        => 'boolean',
							'description' => 'Include parent product information.',
							'default'     => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'variation' => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array( 'type' => 'integer' ),
								'parent_id'      => array( 'type' => 'integer' ),
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
								'shipping_class' => array( 'type' => 'string' ),
								'tax_class'      => array( 'type' => 'string' ),
								'image'          => array( 'type' => 'object' ),
								'attributes'     => array( 'type' => 'object' ),
								'status'         => array( 'type' => 'string' ),
								'menu_order'     => array( 'type' => 'integer' ),
								'date_created'   => array( 'type' => 'string' ),
								'date_modified'  => array( 'type' => 'string' ),
								'date_on_sale_from' => array( 'type' => 'string' ),
								'date_on_sale_to'   => array( 'type' => 'string' ),
							),
						),
						'parent_product' => array(
							'type'       => 'object',
							'properties' => array(
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
								'slug' => array( 'type' => 'string' ),
								'type' => array( 'type' => 'string' ),
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
				'variation'      => null,
				'parent_product' => null,
				'message'        => 'WooCommerce is not active.',
			);
		}

		$variation_id = $input['variation_id'];
		$include_parent_info = $input['include_parent_info'] ?? true;

		$variation = wc_get_product( $variation_id );

		if ( ! $variation || ! $variation instanceof \WC_Product_Variation ) {
			return array(
				'variation'      => null,
				'parent_product' => null,
				'message'        => 'Variation not found.',
			);
		}

		// Format variation data
		$variation_data = self::format_detailed_variation( $variation );

		// Get parent product info if requested
		$parent_product = null;
		if ( $include_parent_info ) {
			$parent = wc_get_product( $variation->get_parent_id() );
			if ( $parent ) {
				$parent_product = array(
					'id'   => $parent->get_id(),
					'name' => $parent->get_name(),
					'slug' => $parent->get_slug(),
					'type' => $parent->get_type(),
				);
			}
		}

		return array(
			'variation'      => $variation_data,
			'parent_product' => $parent_product,
			'message'        => sprintf( 'Retrieved variation ID %d.', $variation->get_id() ),
		);
	}

	private static function format_detailed_variation( \WC_Product_Variation $variation ): array {
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
					'name'      => get_the_title( $variation->get_image_id() ),
				);
			}
		}

		return array(
			'id'             => $variation->get_id(),
			'parent_id'      => $variation->get_parent_id(),
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
			'shipping_class' => $variation->get_shipping_class(),
			'tax_class'      => $variation->get_tax_class(),
			'image'          => $image_data,
			'attributes'     => $variation->get_variation_attributes(),
			'status'         => $variation->get_status(),
			'menu_order'     => $variation->get_menu_order(),
			'date_created'   => $variation->get_date_created() ? $variation->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'date_modified'  => $variation->get_date_modified() ? $variation->get_date_modified()->date( 'Y-m-d H:i:s' ) : '',
			'date_on_sale_from' => $variation->get_date_on_sale_from() ? $variation->get_date_on_sale_from()->date( 'Y-m-d H:i:s' ) : '',
			'date_on_sale_to'   => $variation->get_date_on_sale_to() ? $variation->get_date_on_sale_to()->date( 'Y-m-d H:i:s' ) : '',
		);
	}
}
