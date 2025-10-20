<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class DeleteProductCategory implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/delete-product-category',
			array(
				'label'               => 'Delete Product Category',
				'description'         => 'Delete a WooCommerce product category with options for handling products and child categories.',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'category_id' ),
					'properties'           => array(
						'category_id'          => array(
							'type'        => 'integer',
							'description' => 'Category ID to delete.',
							'minimum'     => 1,
						),
						'force_delete'         => array(
							'type'        => 'boolean',
							'description' => 'Force delete even if category has products.',
							'default'     => false,
						),
						'reassign_products_to' => array(
							'type'        => 'integer',
							'description' => 'Category ID to reassign products to (0 for Uncategorized).',
							'default'     => 0,
						),
						'delete_children'      => array(
							'type'        => 'boolean',
							'description' => 'Also delete child categories.',
							'default'     => false,
						),
						'reassign_children_to' => array(
							'type'        => 'integer',
							'description' => 'Category ID to reassign child categories to (0 for top-level).',
							'default'     => 0,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'             => array( 'type' => 'boolean' ),
						'category'            => array(
							'type'       => 'object',
							'properties' => array(
								'id'             => array( 'type' => 'integer' ),
								'name'           => array( 'type' => 'string' ),
								'slug'           => array( 'type' => 'string' ),
								'count'          => array( 'type' => 'integer' ),
								'children_count' => array( 'type' => 'integer' ),
							),
						),
						'products_reassigned' => array( 'type' => 'integer' ),
						'children_handled'    => array(
							'type'       => 'object',
							'properties' => array(
								'deleted'    => array( 'type' => 'integer' ),
								'reassigned' => array( 'type' => 'integer' ),
							),
						),
						'reassign_info'       => array(
							'type'       => 'object',
							'properties' => array(
								'products_to' => array( 'type' => 'string' ),
								'children_to' => array( 'type' => 'string' ),
							),
						),
						'message'             => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'ecommerce',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'categories'  => array( 'ecommerce', 'catalog' ),
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
		return current_user_can( 'delete_terms' ) || current_user_can( 'manage_product_terms' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success'             => false,
				'category'            => array(),
				'products_reassigned' => 0,
				'children_handled'    => array(
					'deleted'    => 0,
					'reassigned' => 0,
				),
				'reassign_info'       => array(),
				'message'             => 'WooCommerce is not active.',
			);
		}

		$category_id          = $input['category_id'];
		$force_delete         = $input['force_delete'] ?? false;
		$reassign_products_to = $input['reassign_products_to'] ?? 0;
		$delete_children      = $input['delete_children'] ?? false;
		$reassign_children_to = $input['reassign_children_to'] ?? 0;

		$category = get_term( $category_id, 'product_cat' );
		if ( is_wp_error( $category ) || ! $category ) {
			return array(
				'success'             => false,
				'category'            => array(),
				'products_reassigned' => 0,
				'children_handled'    => array(
					'deleted'    => 0,
					'reassigned' => 0,
				),
				'reassign_info'       => array(),
				'message'             => 'Category not found.',
			);
		}

		// Check if it's the default "Uncategorized" category
		if ( $category->slug === 'uncategorized' ) {
			return array(
				'success'             => false,
				'category'            => array(
					'id'   => $category->term_id,
					'name' => $category->name,
				),
				'products_reassigned' => 0,
				'children_handled'    => array(
					'deleted'    => 0,
					'reassigned' => 0,
				),
				'reassign_info'       => array(),
				'message'             => 'Cannot delete the default "Uncategorized" category.',
			);
		}

		// Store category info before deletion
		$category_info = array(
			'id'             => $category->term_id,
			'name'           => $category->name,
			'slug'           => $category->slug,
			'count'          => $category->count,
			'children_count' => 0,
		);

		// Get children count
		$children = get_terms(
			array(
				'taxonomy' => 'product_cat',
				'parent'   => $category_id,
				'fields'   => 'ids',
			)
		);

		if ( ! is_wp_error( $children ) ) {
			$category_info['children_count'] = count( $children );
		}

		// Check if category has products and force_delete is false
		if ( ! $force_delete && $category->count > 0 ) {
			return array(
				'success'             => false,
				'category'            => $category_info,
				'products_reassigned' => 0,
				'children_handled'    => array(
					'deleted'    => 0,
					'reassigned' => 0,
				),
				'reassign_info'       => array(),
				'message'             => sprintf( 'Category "%s" has %d products. Use force_delete to delete anyway or specify reassign_products_to.', $category->name, $category->count ),
			);
		}

		try {
			$products_reassigned = 0;
			$children_handled    = array(
				'deleted'    => 0,
				'reassigned' => 0,
			);
			$reassign_info       = array();

			// Handle products
			if ( $category->count > 0 ) {
				$products_reassigned = self::reassign_products( $category_id, $reassign_products_to );

				if ( $reassign_products_to > 0 ) {
					$reassign_category            = get_term( $reassign_products_to, 'product_cat' );
					$reassign_info['products_to'] = $reassign_category ? $reassign_category->name : 'Unknown';
				} else {
					$reassign_info['products_to'] = 'Uncategorized';
				}
			}

			// Handle children
			if ( ! empty( $children ) ) {
				if ( $delete_children ) {
					foreach ( $children as $child_id ) {
						$child_result = wp_delete_term( $child_id, 'product_cat' );
						if ( is_wp_error( $child_result ) || ! $child_result ) {
							continue;
						}

						++$children_handled['deleted'];
					}
				} else {
					foreach ( $children as $child_id ) {
						$result = wp_update_term(
							$child_id,
							'product_cat',
							array(
								'parent' => $reassign_children_to,
							)
						);
						if ( is_wp_error( $result ) ) {
							continue;
						}

						++$children_handled['reassigned'];
					}

					if ( $reassign_children_to > 0 ) {
						$reassign_category            = get_term( $reassign_children_to, 'product_cat' );
						$reassign_info['children_to'] = $reassign_category ? $reassign_category->name : 'Unknown';
					} else {
						$reassign_info['children_to'] = 'Top-level';
					}
				}
			}

			// Delete the category
			$result = wp_delete_term( $category_id, 'product_cat' );

			if ( is_wp_error( $result ) || ! $result ) {
				return array(
					'success'             => false,
					'category'            => $category_info,
					'products_reassigned' => $products_reassigned,
					'children_handled'    => $children_handled,
					'reassign_info'       => $reassign_info,
					'message'             => 'Error deleting category.',
				);
			}

			return array(
				'success'             => true,
				'category'            => $category_info,
				'products_reassigned' => $products_reassigned,
				'children_handled'    => $children_handled,
				'reassign_info'       => $reassign_info,
				'message'             => sprintf(
					'Successfully deleted category "%s". Products reassigned: %d. Children %s: %d.',
					$category_info['name'],
					$products_reassigned,
					$delete_children ? 'deleted' : 'reassigned',
					$delete_children ? $children_handled['deleted'] : $children_handled['reassigned']
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'             => false,
				'category'            => $category_info,
				'products_reassigned' => 0,
				'children_handled'    => array(
					'deleted'    => 0,
					'reassigned' => 0,
				),
				'reassign_info'       => array(),
				'message'             => 'Error deleting category: ' . $e->getMessage(),
			);
		}
	}

	private static function reassign_products( int $from_category_id, int $to_category_id ): int {
		// Get products in the category
		$products = wc_get_products(
			array(
				'category' => array( $from_category_id ),
				'limit'    => -1,
				'status'   => 'any',
			)
		);

		$reassigned_count = 0;

		foreach ( $products as $product ) {
			$current_categories = $product->get_category_ids();

			// Remove the old category
			$new_categories = array_diff( $current_categories, array( $from_category_id ) );

			// Add the new category if specified
			if ( $to_category_id > 0 ) {
				$new_categories[] = $to_category_id;
			}

			// If no categories left, add Uncategorized
			if ( empty( $new_categories ) ) {
				$uncategorized = get_term_by( 'slug', 'uncategorized', 'product_cat' );
				if ( $uncategorized ) {
					$new_categories[] = $uncategorized->term_id;
				}
			}

			$product->set_category_ids( $new_categories );
			$product->save();
			++$reassigned_count;
		}

		return $reassigned_count;
	}
}
