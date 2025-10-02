<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class DeleteProduct implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/delete-product',
			array(
				'label'               => 'Delete WooCommerce Product',
				'description'         => 'Delete a WooCommerce product by ID. Can move to trash or permanently delete.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Product ID to delete.',
							'minimum'     => 1,
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => 'Force permanent deletion (skip trash).',
							'default'     => false,
						),
						'delete_variations' => array(
							'type'        => 'boolean',
							'description' => 'Delete all variations for variable products.',
							'default'     => true,
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
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
								'sku'  => array( 'type' => 'string' ),
								'type' => array( 'type' => 'string' ),
							),
						),
						'deleted_variations' => array( 'type' => 'integer' ),
						'permanent' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'ecommerce', 'products' ),
					'annotations' => array(
						'audience'             => array( 'user', 'assistant' ),
						'priority'             => 0.6,
						'readOnlyHint'         => false,
						'destructiveHint'      => true,
						'idempotentHint'       => true,
						'openWorldHint'        => false,
						'requiresConfirmation' => true,
					),
				),
			)
		);
	}

	public static function check_permission(): bool {
		return current_user_can( 'delete_products' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success' => false,
				'product' => null,
				'deleted_variations' => 0,
				'permanent' => false,
				'message' => 'WooCommerce is not active.',
			);
		}

		$product_id = $input['id'];
		$force = $input['force'] ?? false;
		$delete_variations = $input['delete_variations'] ?? true;

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product instanceof \WC_Product ) {
			return array(
				'success' => false,
				'product' => null,
				'deleted_variations' => 0,
				'permanent' => false,
				'message' => 'Product not found.',
			);
		}

		// Store product info before deletion
		$product_info = array(
			'id'   => $product->get_id(),
			'name' => $product->get_name(),
			'sku'  => $product->get_sku(),
			'type' => $product->get_type(),
		);

		$deleted_variations = 0;

		try {
			// Delete variations if it's a variable product
			if ( $delete_variations && $product->is_type( 'variable' ) ) {
				$variation_ids = $product->get_children();
				foreach ( $variation_ids as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( $variation ) {
						$variation->delete( $force );
						$deleted_variations++;
					}
				}
			}

			// Delete the main product
			$deleted = $product->delete( $force );

			if ( ! $deleted ) {
				return array(
					'success' => false,
					'product' => $product_info,
					'deleted_variations' => 0,
					'permanent' => false,
					'message' => 'Failed to delete product.',
				);
			}

			$action = $force ? 'permanently deleted' : 'moved to trash';

			return array(
				'success' => true,
				'product' => $product_info,
				'deleted_variations' => $deleted_variations,
				'permanent' => $force,
				'message' => sprintf(
					'Successfully %s product "%s" (ID: %d)%s.',
					$action,
					$product_info['name'],
					$product_info['id'],
					$deleted_variations > 0 ? " and {$deleted_variations} variations" : ''
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'product' => $product_info,
				'deleted_variations' => $deleted_variations,
				'permanent' => false,
				'message' => 'Error deleting product: ' . $e->getMessage(),
			);
		}
	}
}
