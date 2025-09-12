<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class DuplicateProduct implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/duplicate-product',
			array(
				'label'               => 'Duplicate WooCommerce Product',
				'description'         => 'Create a copy of an existing WooCommerce product with optional modifications.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Product ID to duplicate.',
							'minimum'     => 1,
						),
						'name' => array(
							'type'        => 'string',
							'description' => 'New product name (defaults to "Copy of [Original Name]").',
						),
						'sku' => array(
							'type'        => 'string',
							'description' => 'New product SKU (must be unique).',
						),
						'status' => array(
							'type'        => 'string',
							'description' => 'New product status.',
							'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
							'default'     => 'draft',
						),
						'include_variations' => array(
							'type'        => 'boolean',
							'description' => 'Include variations when duplicating variable products.',
							'default'     => true,
						),
						'include_images' => array(
							'type'        => 'boolean',
							'description' => 'Include product images in the duplicate.',
							'default'     => true,
						),
						'include_reviews' => array(
							'type'        => 'boolean',
							'description' => 'Include product reviews in the duplicate.',
							'default'     => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'original_product' => array(
							'type'       => 'object',
							'properties' => array(
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
								'sku'  => array( 'type' => 'string' ),
								'type' => array( 'type' => 'string' ),
							),
						),
						'duplicated_product' => array(
							'type'       => 'object',
							'properties' => array(
								'id'           => array( 'type' => 'integer' ),
								'name'         => array( 'type' => 'string' ),
								'slug'         => array( 'type' => 'string' ),
								'sku'          => array( 'type' => 'string' ),
								'type'         => array( 'type' => 'string' ),
								'status'       => array( 'type' => 'string' ),
								'permalink'    => array( 'type' => 'string' ),
								'date_created' => array( 'type' => 'string' ),
							),
						),
						'duplicated_variations' => array( 'type' => 'integer' ),
						'duplicated_images' => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'ecommerce', 'products' ),
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
				'success' => false,
				'original_product' => null,
				'duplicated_product' => null,
				'duplicated_variations' => 0,
				'duplicated_images' => 0,
				'message' => 'WooCommerce is not active.',
			);
		}

		$product_id = $input['id'];
		$new_name = $input['name'] ?? '';
		$new_sku = $input['sku'] ?? '';
		$new_status = $input['status'] ?? 'draft';
		$include_variations = $input['include_variations'] ?? true;
		$include_images = $input['include_images'] ?? true;
		$include_reviews = $input['include_reviews'] ?? false;

		$original_product = wc_get_product( $product_id );

		if ( ! $original_product || ! $original_product instanceof \WC_Product ) {
			return array(
				'success' => false,
				'original_product' => null,
				'duplicated_product' => null,
				'duplicated_variations' => 0,
				'duplicated_images' => 0,
				'message' => 'Product not found.',
			);
		}

		// Store original product info
		$original_info = array(
			'id'   => $original_product->get_id(),
			'name' => $original_product->get_name(),
			'sku'  => $original_product->get_sku(),
			'type' => $original_product->get_type(),
		);

		try {
			// Create new product based on original type
			$product_type = $original_product->get_type();
			switch ( $product_type ) {
				case 'variable':
					$new_product = new \WC_Product_Variable();
					break;
				case 'grouped':
					$new_product = new \WC_Product_Grouped();
					break;
				case 'external':
					$new_product = new \WC_Product_External();
					break;
				case 'simple':
				default:
					$new_product = new \WC_Product_Simple();
					break;
			}

			// Set basic properties
			$duplicate_name = ! empty( $new_name ) ? $new_name : 'Copy of ' . $original_product->get_name();
			$new_product->set_name( $duplicate_name );
			$new_product->set_status( $new_status );
			$new_product->set_description( $original_product->get_description() );
			$new_product->set_short_description( $original_product->get_short_description() );

			// Handle SKU
			if ( ! empty( $new_sku ) ) {
				// Check if SKU already exists
				$existing_product_id = wc_get_product_id_by_sku( $new_sku );
				if ( $existing_product_id ) {
					return array(
						'success' => false,
						'original_product' => $original_info,
						'duplicated_product' => null,
						'duplicated_variations' => 0,
						'duplicated_images' => 0,
						'message' => 'SKU already exists.',
					);
				}
				$new_product->set_sku( $new_sku );
			} elseif ( $original_product->get_sku() ) {
				// Generate new SKU based on original
				$base_sku = $original_product->get_sku();
				$counter = 1;
				$new_sku = $base_sku . '-copy';
				
				while ( wc_get_product_id_by_sku( $new_sku ) ) {
					$counter++;
					$new_sku = $base_sku . '-copy-' . $counter;
				}
				$new_product->set_sku( $new_sku );
			}

			// Copy pricing
			$new_product->set_regular_price( $original_product->get_regular_price() );
			$new_product->set_sale_price( $original_product->get_sale_price() );

			// Copy stock settings
			$new_product->set_manage_stock( $original_product->get_manage_stock() );
			$new_product->set_stock_quantity( $original_product->get_stock_quantity() );
			$new_product->set_stock_status( $original_product->get_stock_status() );

			// Copy physical properties
			$new_product->set_weight( $original_product->get_weight() );
			$new_product->set_length( $original_product->get_length() );
			$new_product->set_width( $original_product->get_width() );
			$new_product->set_height( $original_product->get_height() );

			// Copy tax and shipping
			$new_product->set_tax_status( $original_product->get_tax_status() );
			$new_product->set_tax_class( $original_product->get_tax_class() );
			$new_product->set_shipping_class_id( $original_product->get_shipping_class_id() );

			// Copy categories and tags
			$new_product->set_category_ids( $original_product->get_category_ids() );
			$new_product->set_tag_ids( $original_product->get_tag_ids() );

			// Copy catalog settings
			$new_product->set_featured( false ); // Don't duplicate featured status
			$new_product->set_catalog_visibility( $original_product->get_catalog_visibility() );

			// Copy upsells and cross-sells
			$new_product->set_upsell_ids( $original_product->get_upsell_ids() );
			$new_product->set_cross_sell_ids( $original_product->get_cross_sell_ids() );

			// Copy type-specific properties
			if ( $product_type === 'external' ) {
				$new_product->set_product_url( $original_product->get_product_url() );
				$new_product->set_button_text( $original_product->get_button_text() );
			}

			if ( $product_type === 'grouped' ) {
				$new_product->set_children( $original_product->get_children() );
			}

			// Copy images if requested
			$duplicated_images = 0;
			if ( $include_images ) {
				if ( $original_product->get_image_id() ) {
					$new_product->set_image_id( $original_product->get_image_id() );
					$duplicated_images++;
				}

				$gallery_ids = $original_product->get_gallery_image_ids();
				if ( ! empty( $gallery_ids ) ) {
					$new_product->set_gallery_image_ids( $gallery_ids );
					$duplicated_images += count( $gallery_ids );
				}
			}

			// Copy attributes
			$new_product->set_attributes( $original_product->get_attributes() );

			// Save the duplicated product
			$new_product_id = $new_product->save();

			if ( ! $new_product_id ) {
				return array(
					'success' => false,
					'original_product' => $original_info,
					'duplicated_product' => null,
					'duplicated_variations' => 0,
					'duplicated_images' => 0,
					'message' => 'Failed to save duplicated product.',
				);
			}

			$duplicated_variations = 0;

			// Duplicate variations if requested
			if ( $include_variations && $product_type === 'variable' ) {
				$variation_ids = $original_product->get_children();
				foreach ( $variation_ids as $variation_id ) {
					$original_variation = wc_get_product( $variation_id );
					if ( $original_variation ) {
						$new_variation = new \WC_Product_Variation();
						$new_variation->set_parent_id( $new_product_id );
						
						// Copy variation properties
						$new_variation->set_regular_price( $original_variation->get_regular_price() );
						$new_variation->set_sale_price( $original_variation->get_sale_price() );
						$new_variation->set_manage_stock( $original_variation->get_manage_stock() );
						$new_variation->set_stock_quantity( $original_variation->get_stock_quantity() );
						$new_variation->set_stock_status( $original_variation->get_stock_status() );
						$new_variation->set_weight( $original_variation->get_weight() );
						$new_variation->set_length( $original_variation->get_length() );
						$new_variation->set_width( $original_variation->get_width() );
						$new_variation->set_height( $original_variation->get_height() );
						$new_variation->set_attributes( $original_variation->get_variation_attributes() );

						// Generate new SKU for variation if original has one
						if ( $original_variation->get_sku() ) {
							$base_variation_sku = $original_variation->get_sku();
							$counter = 1;
							$new_variation_sku = $base_variation_sku . '-copy';
							
							while ( wc_get_product_id_by_sku( $new_variation_sku ) ) {
								$counter++;
								$new_variation_sku = $base_variation_sku . '-copy-' . $counter;
							}
							$new_variation->set_sku( $new_variation_sku );
						}

						// Copy variation image
						if ( $include_images && $original_variation->get_image_id() ) {
							$new_variation->set_image_id( $original_variation->get_image_id() );
						}

						$new_variation->save();
						$duplicated_variations++;
					}
				}
			}

			// Copy reviews if requested
			if ( $include_reviews ) {
				self::duplicate_reviews( $product_id, $new_product_id );
			}

			// Get the duplicated product for response
			$duplicated_product = wc_get_product( $new_product_id );

			return array(
				'success' => true,
				'original_product' => $original_info,
				'duplicated_product' => array(
					'id'           => $duplicated_product->get_id(),
					'name'         => $duplicated_product->get_name(),
					'slug'         => $duplicated_product->get_slug(),
					'sku'          => $duplicated_product->get_sku(),
					'type'         => $duplicated_product->get_type(),
					'status'       => $duplicated_product->get_status(),
					'permalink'    => $duplicated_product->get_permalink(),
					'date_created' => $duplicated_product->get_date_created() ? $duplicated_product->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
				),
				'duplicated_variations' => $duplicated_variations,
				'duplicated_images' => $duplicated_images,
				'message' => sprintf(
					'Successfully duplicated product "%s" as "%s" (ID: %d)%s%s.',
					$original_info['name'],
					$duplicated_product->get_name(),
					$duplicated_product->get_id(),
					$duplicated_variations > 0 ? " with {$duplicated_variations} variations" : '',
					$duplicated_images > 0 ? " and {$duplicated_images} images" : ''
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'original_product' => $original_info,
				'duplicated_product' => null,
				'duplicated_variations' => 0,
				'duplicated_images' => 0,
				'message' => 'Error duplicating product: ' . $e->getMessage(),
			);
		}
	}

	private static function duplicate_reviews( int $original_product_id, int $new_product_id ): void {
		global $wpdb;

		// Get original product reviews
		$reviews = get_comments( array(
			'post_id' => $original_product_id,
			'type'    => 'review',
			'status'  => 'approve',
		) );

		foreach ( $reviews as $review ) {
			// Create duplicate review
			$review_data = array(
				'comment_post_ID'      => $new_product_id,
				'comment_author'       => $review->comment_author,
				'comment_author_email' => $review->comment_author_email,
				'comment_author_url'   => $review->comment_author_url,
				'comment_content'      => $review->comment_content,
				'comment_type'         => 'review',
				'comment_parent'       => 0,
				'user_id'              => $review->user_id,
				'comment_author_IP'    => $review->comment_author_IP,
				'comment_agent'        => $review->comment_agent,
				'comment_date'         => current_time( 'mysql' ),
				'comment_approved'     => 1,
			);

			$new_comment_id = wp_insert_comment( $review_data );

			if ( $new_comment_id ) {
				// Copy review rating
				$rating = get_comment_meta( $review->comment_ID, 'rating', true );
				if ( $rating ) {
					update_comment_meta( $new_comment_id, 'rating', $rating );
				}

				// Copy other review meta
				$verified = get_comment_meta( $review->comment_ID, 'verified', true );
				if ( $verified ) {
					update_comment_meta( $new_comment_id, 'verified', $verified );
				}
			}
		}
	}
}
