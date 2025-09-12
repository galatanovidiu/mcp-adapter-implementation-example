<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class GetProduct implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/get-product',
			array(
				'label'               => 'Get WooCommerce Product',
				'description'         => 'Retrieve detailed information about a specific WooCommerce product by ID or SKU.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Product ID.',
							'minimum'     => 1,
						),
						'sku' => array(
							'type'        => 'string',
							'description' => 'Product SKU (alternative to ID).',
						),
						'include_variations' => array(
							'type'        => 'boolean',
							'description' => 'Include detailed variation information for variable products.',
							'default'     => true,
						),
						'include_reviews' => array(
							'type'        => 'boolean',
							'description' => 'Include recent product reviews.',
							'default'     => false,
						),
						'include_related' => array(
							'type'        => 'boolean',
							'description' => 'Include related and upsell products.',
							'default'     => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'product' => array(
							'type'       => 'object',
							'properties' => array(
								'id'                => array( 'type' => 'integer' ),
								'name'              => array( 'type' => 'string' ),
								'slug'              => array( 'type' => 'string' ),
								'permalink'         => array( 'type' => 'string' ),
								'type'              => array( 'type' => 'string' ),
								'status'            => array( 'type' => 'string' ),
								'featured'          => array( 'type' => 'boolean' ),
								'catalog_visibility' => array( 'type' => 'string' ),
								'description'       => array( 'type' => 'string' ),
								'short_description' => array( 'type' => 'string' ),
								'sku'               => array( 'type' => 'string' ),
								'price'             => array( 'type' => 'string' ),
								'regular_price'     => array( 'type' => 'string' ),
								'sale_price'        => array( 'type' => 'string' ),
								'on_sale'           => array( 'type' => 'boolean' ),
								'stock_status'      => array( 'type' => 'string' ),
								'stock_quantity'    => array( 'type' => 'integer' ),
								'manage_stock'      => array( 'type' => 'boolean' ),
								'weight'            => array( 'type' => 'string' ),
								'dimensions'        => array( 'type' => 'object' ),
								'shipping_class'    => array( 'type' => 'string' ),
								'tax_status'        => array( 'type' => 'string' ),
								'tax_class'         => array( 'type' => 'string' ),
								'categories'        => array( 'type' => 'array' ),
								'tags'              => array( 'type' => 'array' ),
								'images'            => array( 'type' => 'array' ),
								'attributes'        => array( 'type' => 'array' ),
								'variations'        => array( 'type' => 'array' ),
								'grouped_products'  => array( 'type' => 'array' ),
								'upsell_ids'        => array( 'type' => 'array' ),
								'cross_sell_ids'    => array( 'type' => 'array' ),
								'average_rating'    => array( 'type' => 'string' ),
								'rating_count'      => array( 'type' => 'integer' ),
								'reviews'           => array( 'type' => 'array' ),
								'related_products'  => array( 'type' => 'array' ),
								'date_created'      => array( 'type' => 'string' ),
								'date_modified'     => array( 'type' => 'string' ),
								'date_on_sale_from' => array( 'type' => 'string' ),
								'date_on_sale_to'   => array( 'type' => 'string' ),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
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
				'product' => null,
				'message' => 'WooCommerce is not active.',
			);
		}

		$product_id = $input['id'] ?? null;
		$sku = $input['sku'] ?? '';
		$include_variations = $input['include_variations'] ?? true;
		$include_reviews = $input['include_reviews'] ?? false;
		$include_related = $input['include_related'] ?? false;

		// Get product by SKU if provided instead of ID
		if ( empty( $product_id ) && ! empty( $sku ) ) {
			$product_id = wc_get_product_id_by_sku( $sku );
		}

		if ( empty( $product_id ) ) {
			return array(
				'product' => null,
				'message' => 'Product ID or SKU is required.',
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product instanceof \WC_Product ) {
			return array(
				'product' => null,
				'message' => 'Product not found.',
			);
		}

		// Format detailed product information
		$formatted_product = self::format_detailed_product( $product, $include_variations, $include_reviews, $include_related );

		return array(
			'product' => $formatted_product,
			'message' => sprintf( 'Retrieved product "%s" (ID: %d).', $product->get_name(), $product->get_id() ),
		);
	}

	private static function format_detailed_product( \WC_Product $product, bool $include_variations, bool $include_reviews, bool $include_related ): array {
		// Get basic product data
		$data = array(
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
			'weight'            => $product->get_weight(),
			'dimensions'        => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			),
			'shipping_class'    => $product->get_shipping_class(),
			'tax_status'        => $product->get_tax_status(),
			'tax_class'         => $product->get_tax_class(),
			'average_rating'    => $product->get_average_rating(),
			'rating_count'      => $product->get_rating_count(),
			'date_created'      => $product->get_date_created() ? $product->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'date_modified'     => $product->get_date_modified() ? $product->get_date_modified()->date( 'Y-m-d H:i:s' ) : null,
			'date_on_sale_from' => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date( 'Y-m-d H:i:s' ) : '',
			'date_on_sale_to'   => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date( 'Y-m-d H:i:s' ) : '',
		);

		// Add categories
		$categories = array();
		$category_ids = $product->get_category_ids();
		foreach ( $category_ids as $cat_id ) {
			$category = get_term( $cat_id, 'product_cat' );
			if ( $category && ! is_wp_error( $category ) ) {
				$categories[] = array(
					'id'          => $category->term_id,
					'name'        => $category->name,
					'slug'        => $category->slug,
					'description' => $category->description,
					'parent'      => $category->parent,
					'count'       => $category->count,
				);
			}
		}
		$data['categories'] = $categories;

		// Add tags
		$tags = array();
		$tag_ids = $product->get_tag_ids();
		foreach ( $tag_ids as $tag_id ) {
			$tag = get_term( $tag_id, 'product_tag' );
			if ( $tag && ! is_wp_error( $tag ) ) {
				$tags[] = array(
					'id'          => $tag->term_id,
					'name'        => $tag->name,
					'slug'        => $tag->slug,
					'description' => $tag->description,
					'count'       => $tag->count,
				);
			}
		}
		$data['tags'] = $tags;

		// Add images
		$images = array();
		$image_ids = $product->get_gallery_image_ids();
		if ( $product->get_image_id() ) {
			array_unshift( $image_ids, $product->get_image_id() );
		}
		foreach ( $image_ids as $image_id ) {
			$image = wp_get_attachment_image_src( $image_id, 'full' );
			$thumbnail = wp_get_attachment_image_src( $image_id, 'thumbnail' );
			if ( $image ) {
				$images[] = array(
					'id'        => $image_id,
					'src'       => $image[0],
					'thumbnail' => $thumbnail ? $thumbnail[0] : $image[0],
					'alt'       => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
					'name'      => get_the_title( $image_id ),
				);
			}
		}
		$data['images'] = $images;

		// Add attributes
		$attributes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			$attributes[] = array(
				'name'      => $attribute->get_name(),
				'options'   => $attribute->get_options(),
				'visible'   => $attribute->get_visible(),
				'variation' => $attribute->get_variation(),
				'position'  => $attribute->get_position(),
			);
		}
		$data['attributes'] = $attributes;

		// Add detailed variations if requested
		if ( $include_variations && $product->is_type( 'variable' ) ) {
			$variations = array();
			$variation_ids = $product->get_children();
			foreach ( $variation_ids as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( $variation ) {
					$variations[] = array(
						'id'             => $variation->get_id(),
						'sku'            => $variation->get_sku(),
						'price'          => $variation->get_price(),
						'regular_price'  => $variation->get_regular_price(),
						'sale_price'     => $variation->get_sale_price(),
						'on_sale'        => $variation->is_on_sale(),
						'stock_status'   => $variation->get_stock_status(),
						'stock_quantity' => $variation->get_stock_quantity(),
						'manage_stock'   => $variation->get_manage_stock(),
						'weight'         => $variation->get_weight(),
						'dimensions'     => array(
							'length' => $variation->get_length(),
							'width'  => $variation->get_width(),
							'height' => $variation->get_height(),
						),
						'image_id'       => $variation->get_image_id(),
						'attributes'     => $variation->get_variation_attributes(),
						'date_created'   => $variation->get_date_created() ? $variation->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
						'date_modified'  => $variation->get_date_modified() ? $variation->get_date_modified()->date( 'Y-m-d H:i:s' ) : null,
					);
				}
			}
			$data['variations'] = $variations;
		} else {
			$data['variations'] = array();
		}

		// Add grouped products for grouped product type
		if ( $product->is_type( 'grouped' ) ) {
			$grouped_products = array();
			$children = $product->get_children();
			foreach ( $children as $child_id ) {
				$child_product = wc_get_product( $child_id );
				if ( $child_product ) {
					$grouped_products[] = array(
						'id'    => $child_product->get_id(),
						'name'  => $child_product->get_name(),
						'price' => $child_product->get_price(),
						'sku'   => $child_product->get_sku(),
					);
				}
			}
			$data['grouped_products'] = $grouped_products;
		} else {
			$data['grouped_products'] = array();
		}

		// Add upsells and cross-sells
		$data['upsell_ids'] = $product->get_upsell_ids();
		$data['cross_sell_ids'] = $product->get_cross_sell_ids();

		// Add reviews if requested
		if ( $include_reviews ) {
			$reviews = array();
			$comments = get_comments( array(
				'post_id' => $product->get_id(),
				'status'  => 'approve',
				'type'    => 'review',
				'number'  => 10,
			) );

			foreach ( $comments as $comment ) {
				$reviews[] = array(
					'id'           => $comment->comment_ID,
					'author'       => $comment->comment_author,
					'author_email' => $comment->comment_author_email,
					'content'      => $comment->comment_content,
					'rating'       => get_comment_meta( $comment->comment_ID, 'rating', true ),
					'date_created' => $comment->comment_date,
					'verified'     => wc_review_is_from_verified_owner( $comment->comment_ID ),
				);
			}
			$data['reviews'] = $reviews;
		}

		// Add related products if requested
		if ( $include_related ) {
			$related_ids = wc_get_related_products( $product->get_id(), 5 );
			$related_products = array();
			foreach ( $related_ids as $related_id ) {
				$related_product = wc_get_product( $related_id );
				if ( $related_product ) {
					$related_products[] = array(
						'id'    => $related_product->get_id(),
						'name'  => $related_product->get_name(),
						'price' => $related_product->get_price(),
						'image' => wp_get_attachment_image_url( $related_product->get_image_id(), 'thumbnail' ),
					);
				}
			}
			$data['related_products'] = $related_products;
		}

		return $data;
	}
}
