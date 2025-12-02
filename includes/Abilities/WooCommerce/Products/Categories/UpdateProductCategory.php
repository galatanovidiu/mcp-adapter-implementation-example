<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class UpdateProductCategory implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/update-product-category',
			array(
				'label'               => 'Update Product Category',
				'description'         => 'Update an existing WooCommerce product category with new information and settings.',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'category_id' ),
					'properties'           => array(
						'category_id'  => array(
							'type'        => 'integer',
							'description' => 'Category ID to update.',
							'minimum'     => 1,
						),
						'name'         => array(
							'type'        => 'string',
							'description' => 'Category name.',
						),
						'slug'         => array(
							'type'        => 'string',
							'description' => 'Category slug.',
						),
						'description'  => array(
							'type'        => 'string',
							'description' => 'Category description.',
						),
						'parent'       => array(
							'type'        => 'integer',
							'description' => 'Parent category ID (0 for top-level).',
						),
						'display_type' => array(
							'type'        => 'string',
							'description' => 'Category display type.',
							'enum'        => array( 'default', 'products', 'subcategories', 'both' ),
						),
						'image_id'     => array(
							'type'        => 'integer',
							'description' => 'Category image attachment ID (0 to remove).',
						),
						'menu_order'   => array(
							'type'        => 'integer',
							'description' => 'Menu order for category sorting.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'category'     => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'name'        => array( 'type' => 'string' ),
								'slug'        => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'parent'      => array( 'type' => 'integer' ),
								'count'       => array( 'type' => 'integer' ),
								'display'     => array( 'type' => 'string' ),
								'menu_order'  => array( 'type' => 'integer' ),
								'link'        => array( 'type' => 'string' ),
							),
						),
						'changes_made' => array( 'type' => 'array' ),
						'old_parent'   => array(
							'type'       => 'object',
							'properties' => array(
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
							),
						),
						'new_parent'   => array(
							'type'       => 'object',
							'properties' => array(
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
							),
						),
						'message'      => array( 'type' => 'string' ),
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
		return current_user_can( 'manage_product_terms' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success'      => false,
				'category'     => array(),
				'changes_made' => array(),
				'old_parent'   => array(),
				'new_parent'   => array(),
				'message'      => 'WooCommerce is not active.',
			);
		}

		$category_id = $input['category_id'];

		$category = get_term( $category_id, 'product_cat' );
		if ( is_wp_error( $category ) || ! $category ) {
			return array(
				'success'      => false,
				'category'     => array(),
				'changes_made' => array(),
				'old_parent'   => array(),
				'new_parent'   => array(),
				'message'      => 'Category not found.',
			);
		}

		$changes_made = array();
		$update_args  = array();

		// Store old parent info
		$old_parent = array();
		if ( $category->parent > 0 ) {
			$old_parent_term = get_term( $category->parent, 'product_cat' );
			if ( $old_parent_term && ! is_wp_error( $old_parent_term ) ) {
				$old_parent = array(
					'id'   => $old_parent_term->term_id,
					'name' => $old_parent_term->name,
				);
			}
		}

		// Update basic fields
		if ( isset( $input['name'] ) ) {
			$update_args['name'] = $input['name'];
			$changes_made[]      = 'name';
		}

		if ( isset( $input['slug'] ) ) {
			$update_args['slug'] = $input['slug'];
			$changes_made[]      = 'slug';
		}

		if ( isset( $input['description'] ) ) {
			$update_args['description'] = $input['description'];
			$changes_made[]             = 'description';
		}

		if ( isset( $input['parent'] ) ) {
			// Validate new parent
			if ( $input['parent'] > 0 ) {
				$new_parent_term = get_term( $input['parent'], 'product_cat' );
				if ( is_wp_error( $new_parent_term ) || ! $new_parent_term ) {
					return array(
						'success'      => false,
						'category'     => array(),
						'changes_made' => array(),
						'old_parent'   => $old_parent,
						'new_parent'   => array(),
						'message'      => 'New parent category not found.',
					);
				}

				// Check for circular reference
				if ( $input['parent'] === $category_id ) {
					return array(
						'success'      => false,
						'category'     => array(),
						'changes_made' => array(),
						'old_parent'   => $old_parent,
						'new_parent'   => array(),
						'message'      => 'Category cannot be its own parent.',
					);
				}

				// Check if new parent is a descendant
				$descendants = get_term_children( $category_id, 'product_cat' );
				if ( ! is_wp_error( $descendants ) && in_array( $input['parent'], $descendants ) ) {
					return array(
						'success'      => false,
						'category'     => array(),
						'changes_made' => array(),
						'old_parent'   => $old_parent,
						'new_parent'   => array(),
						'message'      => 'Cannot set a descendant category as parent.',
					);
				}
			}

			$update_args['parent'] = $input['parent'];
			$changes_made[]        = 'parent';
		}

		try {
			// Update the category
			if ( ! empty( $update_args ) ) {
				$result = wp_update_term( $category_id, 'product_cat', $update_args );

				if ( is_wp_error( $result ) ) {
					return array(
						'success'      => false,
						'category'     => array(),
						'changes_made' => array(),
						'old_parent'   => $old_parent,
						'new_parent'   => array(),
						'message'      => 'Error updating category: ' . $result->get_error_message(),
					);
				}
			}

			// Update meta fields
			if ( isset( $input['display_type'] ) ) {
				update_term_meta( $category_id, 'display_type', $input['display_type'] );
				$changes_made[] = 'display_type';
			}

			if ( isset( $input['image_id'] ) ) {
				if ( $input['image_id'] > 0 ) {
					update_term_meta( $category_id, 'thumbnail_id', $input['image_id'] );
				} else {
					delete_term_meta( $category_id, 'thumbnail_id' );
				}
				$changes_made[] = 'image';
			}

			if ( isset( $input['menu_order'] ) ) {
				update_term_meta( $category_id, 'order', $input['menu_order'] );
				$changes_made[] = 'menu_order';
			}

			// Get updated category
			$updated_category = get_term( $category_id, 'product_cat' );

			// Get new parent info
			$new_parent = array();
			if ( $updated_category->parent > 0 ) {
				$new_parent_term = get_term( $updated_category->parent, 'product_cat' );
				if ( $new_parent_term && ! is_wp_error( $new_parent_term ) ) {
					$new_parent = array(
						'id'   => $new_parent_term->term_id,
						'name' => $new_parent_term->name,
					);
				}
			}

			return array(
				'success'      => true,
				'category'     => array(
					'id'          => $updated_category->term_id,
					'name'        => $updated_category->name,
					'slug'        => $updated_category->slug,
					'description' => $updated_category->description,
					'parent'      => $updated_category->parent,
					'count'       => $updated_category->count,
					'display'     => get_term_meta( $category_id, 'display_type', true ),
					'menu_order'  => (int) get_term_meta( $category_id, 'order', true ),
					'link'        => get_term_link( $updated_category ),
				),
				'changes_made' => $changes_made,
				'old_parent'   => $old_parent,
				'new_parent'   => $new_parent,
				'message'      => sprintf(
					'Successfully updated category "%s". Changes: %s',
					$updated_category->name,
					! empty( $changes_made ) ? implode( ', ', $changes_made ) : 'none'
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'      => false,
				'category'     => array(),
				'changes_made' => array(),
				'old_parent'   => $old_parent,
				'new_parent'   => array(),
				'message'      => 'Error updating category: ' . $e->getMessage(),
			);
		}
	}
}
