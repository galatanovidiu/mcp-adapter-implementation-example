<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Attributes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class CreateProductAttribute implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/create-product-attribute',
			array(
				'label'               => 'Create Product Attribute',
				'description'         => 'Create a new WooCommerce product attribute with optional terms/values.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Attribute name.',
						),
						'slug' => array(
							'type'        => 'string',
							'description' => 'Attribute slug (auto-generated if not provided).',
						),
						'type' => array(
							'type'        => 'string',
							'description' => 'Attribute type.',
							'enum'        => array( 'select', 'text' ),
							'default'     => 'select',
						),
						'order_by' => array(
							'type'        => 'string',
							'description' => 'Default sort order for terms.',
							'enum'        => array( 'menu_order', 'name', 'name_num', 'id' ),
							'default'     => 'menu_order',
						),
						'has_archives' => array(
							'type'        => 'boolean',
							'description' => 'Enable archives for this attribute.',
							'default'     => false,
						),
						'terms' => array(
							'type'        => 'array',
							'description' => 'Initial terms to create for this attribute.',
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
						'terms_created' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'   => array( 'type' => 'integer' ),
									'name' => array( 'type' => 'string' ),
									'slug' => array( 'type' => 'string' ),
								),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'ecommerce', 'catalog' ),
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
		return current_user_can( 'manage_product_terms' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'success'        => false,
				'attribute'      => null,
				'terms_created'  => array(),
				'message'        => 'WooCommerce is not active.',
			);
		}

		$name = $input['name'];
		$slug = $input['slug'] ?? sanitize_title( $name );
		$type = $input['type'] ?? 'select';
		$order_by = $input['order_by'] ?? 'menu_order';
		$has_archives = $input['has_archives'] ?? false;
		$terms = $input['terms'] ?? array();

		// Check if attribute already exists
		$existing_attribute = wc_attribute_taxonomy_name( $slug );
		if ( taxonomy_exists( $existing_attribute ) ) {
			return array(
				'success'        => false,
				'attribute'      => null,
				'terms_created'  => array(),
				'message'        => 'Attribute with this name already exists.',
			);
		}

		try {
			// Create the attribute
			$attribute_data = array(
				'attribute_name'     => $slug,
				'attribute_label'    => $name,
				'attribute_type'     => $type,
				'attribute_orderby'  => $order_by,
				'attribute_public'   => $has_archives ? 1 : 0,
			);

			$attribute_id = wc_create_attribute( $attribute_data );

			if ( is_wp_error( $attribute_id ) ) {
				return array(
					'success'        => false,
					'attribute'      => null,
					'terms_created'  => array(),
					'message'        => 'Error creating attribute: ' . $attribute_id->get_error_message(),
				);
			}

			// Get the created attribute
			$created_attributes = wc_get_attribute_taxonomies();
			$created_attribute = null;
			foreach ( $created_attributes as $attr ) {
				if ( $attr->attribute_id == $attribute_id ) {
					$created_attribute = $attr;
					break;
				}
			}

			if ( ! $created_attribute ) {
				return array(
					'success'        => false,
					'attribute'      => null,
					'terms_created'  => array(),
					'message'        => 'Failed to retrieve created attribute.',
				);
			}

			$taxonomy = wc_attribute_taxonomy_name( $slug );

			// Register the taxonomy
			register_taxonomy( $taxonomy, array( 'product' ), array(
				'labels' => array(
					'name' => $name,
				),
				'public'       => $has_archives,
				'show_ui'      => true,
				'show_in_menu' => true,
			) );

			$terms_created = array();

			// Create initial terms if provided
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term_data ) {
					$term_name = $term_data['name'];
					$term_slug = $term_data['slug'] ?? sanitize_title( $term_name );
					$term_description = $term_data['description'] ?? '';

					$term = wp_insert_term( $term_name, $taxonomy, array(
						'slug'        => $term_slug,
						'description' => $term_description,
					) );

					if ( ! is_wp_error( $term ) ) {
						$terms_created[] = array(
							'id'   => $term['term_id'],
							'name' => $term_name,
							'slug' => $term_slug,
						);
					}
				}
			}

			return array(
				'success'        => true,
				'attribute'      => array(
					'id'           => $created_attribute->attribute_id,
					'name'         => $created_attribute->attribute_label,
					'slug'         => $created_attribute->attribute_name,
					'type'         => $created_attribute->attribute_type,
					'order_by'     => $created_attribute->attribute_orderby,
					'has_archives' => (bool) $created_attribute->attribute_public,
					'taxonomy'     => $taxonomy,
				),
				'terms_created'  => $terms_created,
				'message'        => sprintf(
					'Successfully created attribute "%s" with %d terms.',
					$name,
					count( $terms_created )
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success'        => false,
				'attribute'      => null,
				'terms_created'  => array(),
				'message'        => 'Error creating attribute: ' . $e->getMessage(),
			);
		}
	}
}
