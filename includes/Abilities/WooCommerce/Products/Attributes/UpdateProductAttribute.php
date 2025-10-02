<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Attributes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class UpdateProductAttribute implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/update-product-attribute',
			array(
				'label'               => 'Update Product Attribute',
				'description'         => 'Update an existing WooCommerce product attribute and manage its terms.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'attribute_id' ),
					'properties' => array(
						'attribute_id' => array(
							'type'        => 'integer',
							'description' => 'Attribute ID to update.',
							'minimum'     => 1,
						),
						'name' => array(
							'type'        => 'string',
							'description' => 'Attribute name.',
						),
						'type' => array(
							'type'        => 'string',
							'description' => 'Attribute type.',
							'enum'        => array( 'select', 'text' ),
						),
						'order_by' => array(
							'type'        => 'string',
							'description' => 'Default sort order for terms.',
							'enum'        => array( 'menu_order', 'name', 'name_num', 'id' ),
						),
						'has_archives' => array(
							'type'        => 'boolean',
							'description' => 'Enable archives for this attribute.',
						),
						'add_terms' => array(
							'type'        => 'array',
							'description' => 'New terms to add to this attribute.',
							'items'       => array(
								'type'       => 'object',
								'required'   => array( 'name' ),
								'properties' => array(
									'name'        => array( 'type' => 'string' ),
									'slug'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
								),
							),
						),
						'update_terms' => array(
							'type'        => 'array',
							'description' => 'Existing terms to update.',
							'items'       => array(
								'type'       => 'object',
								'required'   => array( 'id' ),
								'properties' => array(
									'id'          => array( 'type' => 'integer' ),
									'name'        => array( 'type' => 'string' ),
									'slug'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
								),
							),
						),
						'delete_terms' => array(
							'type'        => 'array',
							'description' => 'Term IDs to delete from this attribute.',
							'items'       => array( 'type' => 'integer' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'attribute' => array(
							'type'       => 'object',
							'properties' => array(
								'id'           => array( 'type' => 'integer' ),
								'name'         => array( 'type' => 'string' ),
								'slug'         => array( 'type' => 'string' ),
								'type'         => array( 'type' => 'string' ),
								'order_by'     => array( 'type' => 'string' ),
								'has_archives' => array( 'type' => 'boolean' ),
								'taxonomy'     => array( 'type' => 'string' ),
							),
						),
						'changes_made' => array( 'type' => 'array' ),
						'terms_added' => array( 'type' => 'array' ),
						'terms_updated' => array( 'type' => 'array' ),
						'terms_deleted' => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'ecommerce', 'catalog' ),
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
				'success'        => false,
				'attribute'      => null,
				'changes_made'   => array(),
				'terms_added'    => array(),
				'terms_updated'  => array(),
				'terms_deleted'  => 0,
				'message'        => 'WooCommerce is not active.',
			);
		}

		$attribute_id = $input['attribute_id'];

		// Get the attribute
		$wc_attributes = wc_get_attribute_taxonomies();
		$attribute = null;
		foreach ( $wc_attributes as $attr ) {
			if ( $attr->attribute_id == $attribute_id ) {
				$attribute = $attr;
				break;
			}
		}

		if ( ! $attribute ) {
			return array(
				'success'        => false,
				'attribute'      => null,
				'changes_made'   => array(),
				'terms_added'    => array(),
				'terms_updated'  => array(),
				'terms_deleted'  => 0,
				'message'        => 'Attribute not found.',
			);
		}

		$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
		$changes_made = array();
		$terms_added = array();
		$terms_updated = array();
		$terms_deleted = 0;

		try {
			// Update attribute properties
			$update_data = array();
			
			if ( isset( $input['name'] ) ) {
				$update_data['attribute_label'] = $input['name'];
				$changes_made[] = 'name';
			}

			if ( isset( $input['type'] ) ) {
				$update_data['attribute_type'] = $input['type'];
				$changes_made[] = 'type';
			}

			if ( isset( $input['order_by'] ) ) {
				$update_data['attribute_orderby'] = $input['order_by'];
				$changes_made[] = 'order_by';
			}

			if ( isset( $input['has_archives'] ) ) {
				$update_data['attribute_public'] = $input['has_archives'] ? 1 : 0;
				$changes_made[] = 'has_archives';
			}

			// Update attribute if there are changes
			if ( ! empty( $update_data ) ) {
				$result = wc_update_attribute( $attribute_id, $update_data );
				if ( is_wp_error( $result ) ) {
					return array(
						'success'        => false,
						'attribute'      => null,
						'changes_made'   => array(),
						'terms_added'    => array(),
						'terms_updated'  => array(),
						'terms_deleted'  => 0,
						'message'        => 'Error updating attribute: ' . $result->get_error_message(),
					);
				}
			}

			// Add new terms
			if ( ! empty( $input['add_terms'] ) ) {
				foreach ( $input['add_terms'] as $term_data ) {
					$term_name = $term_data['name'];
					$term_slug = $term_data['slug'] ?? sanitize_title( $term_name );
					$term_description = $term_data['description'] ?? '';

					$term = wp_insert_term( $term_name, $taxonomy, array(
						'slug'        => $term_slug,
						'description' => $term_description,
					) );

					if ( ! is_wp_error( $term ) ) {
						$terms_added[] = array(
							'id'   => $term['term_id'],
							'name' => $term_name,
							'slug' => $term_slug,
						);
					}
				}
			}

			// Update existing terms
			if ( ! empty( $input['update_terms'] ) ) {
				foreach ( $input['update_terms'] as $term_data ) {
					$term_id = $term_data['id'];
					$update_args = array();

					if ( isset( $term_data['name'] ) ) {
						$update_args['name'] = $term_data['name'];
					}
					if ( isset( $term_data['slug'] ) ) {
						$update_args['slug'] = $term_data['slug'];
					}
					if ( isset( $term_data['description'] ) ) {
						$update_args['description'] = $term_data['description'];
					}

					if ( ! empty( $update_args ) ) {
						$result = wp_update_term( $term_id, $taxonomy, $update_args );
						if ( ! is_wp_error( $result ) ) {
							$terms_updated[] = array(
								'id'   => $term_id,
								'name' => $term_data['name'] ?? '',
							);
						}
					}
				}
			}

			// Delete terms
			if ( ! empty( $input['delete_terms'] ) ) {
				foreach ( $input['delete_terms'] as $term_id ) {
					$result = wp_delete_term( $term_id, $taxonomy );
					if ( ! is_wp_error( $result ) ) {
						$terms_deleted++;
					}
				}
			}

			// Get updated attribute info
			$updated_attributes = wc_get_attribute_taxonomies();
			$updated_attribute = null;
			foreach ( $updated_attributes as $attr ) {
				if ( $attr->attribute_id == $attribute_id ) {
					$updated_attribute = $attr;
					break;
				}
			}

			return array(
				'success'        => true,
				'attribute'      => array(
					'id'           => $updated_attribute->attribute_id,
					'name'         => $updated_attribute->attribute_label,
					'slug'         => $updated_attribute->attribute_name,
					'type'         => $updated_attribute->attribute_type,
					'order_by'     => $updated_attribute->attribute_orderby,
					'has_archives' => (bool) $updated_attribute->attribute_public,
					'taxonomy'     => $taxonomy,
				),
				'changes_made'   => $changes_made,
				'terms_added'    => $terms_added,
				'terms_updated'  => $terms_updated,
				'terms_deleted'  => $terms_deleted,
				'message'        => sprintf(
					'Successfully updated attribute "%s". Changes: %s. Terms: +%d, ~%d, -%d',
					$updated_attribute->attribute_label,
					! empty( $changes_made ) ? implode( ', ', $changes_made ) : 'none',
					count( $terms_added ),
					count( $terms_updated ),
					$terms_deleted
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success'        => false,
				'attribute'      => null,
				'changes_made'   => array(),
				'terms_added'    => array(),
				'terms_updated'  => array(),
				'terms_deleted'  => 0,
				'message'        => 'Error updating attribute: ' . $e->getMessage(),
			);
		}
	}
}
