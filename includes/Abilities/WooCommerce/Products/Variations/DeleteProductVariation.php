<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Variations;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class DeleteProductVariation implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/delete-product-variation',
			array(
				'label'               => 'Delete Product Variation',
				'description'         => 'Delete a specific WooCommerce product variation. Can move to trash or permanently delete.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'variation_id' ),
					'properties' => array(
						'variation_id' => array(
							'type'        => 'integer',
							'description' => 'Variation ID to delete.',
							'minimum'     => 1,
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => 'Force permanent deletion (skip trash).',
							'default'     => false,
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
								'id'         => array( 'type' => 'integer' ),
								'parent_id'  => array( 'type' => 'integer' ),
								'sku'        => array( 'type' => 'string' ),
								'attributes' => array( 'type' => 'object' ),
							),
						),
						'parent_product' => array(
							'type'       => 'object',
							'properties' => array(
								'id'                => array( 'type' => 'integer' ),
								'name'              => array( 'type' => 'string' ),
								'remaining_variations' => array( 'type' => 'integer' ),
							),
						),
						'permanent' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'ecommerce', 'variations' ),
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
				'success'        => false,
				'variation'      => null,
				'parent_product' => null,
				'permanent'      => false,
				'message'        => 'WooCommerce is not active.',
			);
		}

		$variation_id = $input['variation_id'];
		$force = $input['force'] ?? false;

		$variation = wc_get_product( $variation_id );

		if ( ! $variation || ! $variation instanceof \WC_Product_Variation ) {
			return array(
				'success'        => false,
				'variation'      => null,
				'parent_product' => null,
				'permanent'      => false,
				'message'        => 'Variation not found.',
			);
		}

		// Store variation info before deletion
		$variation_info = array(
			'id'         => $variation->get_id(),
			'parent_id'  => $variation->get_parent_id(),
			'sku'        => $variation->get_sku(),
			'attributes' => $variation->get_variation_attributes(),
		);

		// Get parent product info
		$parent = wc_get_product( $variation->get_parent_id() );
		$parent_info = null;
		$remaining_variations = 0;

		if ( $parent ) {
			$remaining_variations = count( $parent->get_children() ) - 1; // Subtract the one we're deleting
			$parent_info = array(
				'id'                   => $parent->get_id(),
				'name'                 => $parent->get_name(),
				'remaining_variations' => $remaining_variations,
			);
		}

		try {
			// Delete the variation
			$deleted = $variation->delete( $force );

			if ( ! $deleted ) {
				return array(
					'success'        => false,
					'variation'      => $variation_info,
					'parent_product' => $parent_info,
					'permanent'      => false,
					'message'        => 'Failed to delete variation.',
				);
			}

			$action = $force ? 'permanently deleted' : 'moved to trash';

			return array(
				'success'        => true,
				'variation'      => $variation_info,
				'parent_product' => $parent_info,
				'permanent'      => $force,
				'message'        => sprintf(
					'Successfully %s variation ID %d (SKU: %s). Parent product "%s" now has %d remaining variations.',
					$action,
					$variation_info['id'],
					$variation_info['sku'] ?: 'none',
					$parent_info ? $parent_info['name'] : 'Unknown',
					$remaining_variations
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success'        => false,
				'variation'      => $variation_info,
				'parent_product' => $parent_info,
				'permanent'      => false,
				'message'        => 'Error deleting variation: ' . $e->getMessage(),
			);
		}
	}
}
