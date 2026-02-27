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
					'type'                 => 'object',
					'required'             => array( 'name' ),
					'properties'           => array(
						'name'         => array(
							'type'        => 'string',
							'description' => 'Attribute name.',
						),
						'slug'         => array(
							'type'        => 'string',
							'description' => 'Attribute slug (auto-generated if not provided).',
						),
						'type'         => array(
							'type'        => 'string',
							'description' => 'Attribute type.',
							'enum'        => array( 'select', 'text' ),
							'default'     => 'select',
						),
						'order_by'     => array(
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
						'if_exists'    => array(
							'type'        => 'string',
							'description' => 'How to handle an existing attribute (error, use_existing, create_duplicate).',
							'enum'        => array( 'error', 'use_existing', 'create_duplicate' ),
							'default'     => 'error',
						),
						'terms'        => array(
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
						'success'       => array( 'type' => 'boolean' ),
						'exists'        => array( 'type' => 'boolean' ),
						'action'        => array( 'type' => 'string' ),
						'attribute'     => array(
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
						'term_errors'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'action'  => array( 'type' => 'string' ),
									'name'    => array( 'type' => 'string' ),
									'slug'    => array( 'type' => 'string' ),
									'message' => array( 'type' => 'string' ),
								),
							),
						),
						'message'       => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'ecommerce',
				'meta'                => array(
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
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
				'success'       => false,
				'exists'        => false,
				'action'        => 'error',
				'attribute'     => self::empty_attribute_payload(),
				'terms_created' => array(),
				'term_errors'   => array(),
				'message'       => 'WooCommerce is not active.',
			);
		}

		$name = sanitize_text_field( (string) $input['name'] );
		$slug = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '';
		if ( '' === $slug ) {
			$slug = sanitize_title( $name );
		}

		$type          = sanitize_key( (string) ( $input['type'] ?? 'select' ) );
		$allowed_types = array( 'select', 'text' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type = 'select';
		}

		$order_by          = sanitize_key( (string) ( $input['order_by'] ?? 'menu_order' ) );
		$allowed_order_bys = array( 'menu_order', 'name', 'name_num', 'id' );
		if ( ! in_array( $order_by, $allowed_order_bys, true ) ) {
			$order_by = 'menu_order';
		}

		$has_archives = ! empty( $input['has_archives'] );
		$terms        = self::sanitize_terms( $input['terms'] ?? array() );
		$if_exists    = self::normalize_if_exists( $input['if_exists'] ?? 'error' );

		$existing_attribute = self::find_existing_attribute( $slug, $name );
		if ( $existing_attribute ) {
			$existing_payload = self::build_attribute_payload( $existing_attribute );

			if ( 'use_existing' === $if_exists ) {
				return array(
					'success'       => true,
					'exists'        => true,
					'action'        => 'existing',
					'attribute'     => $existing_payload,
					'terms_created' => array(),
					'term_errors'   => array(),
					'message'       => 'Attribute already exists. Using existing attribute.',
				);
			}

			if ( 'create_duplicate' !== $if_exists ) {
				return array(
					'success'       => false,
					'exists'        => true,
					'action'        => 'exists',
					'attribute'     => $existing_payload,
					'terms_created' => array(),
					'term_errors'   => array(),
					'message'       => 'Attribute already exists. Set if_exists to use_existing or create_duplicate.',
				);
			}

			$slug = self::unique_attribute_slug( $slug !== '' ? $slug : $name );
		}

		// Check if attribute already exists
		$existing_attribute = wc_attribute_taxonomy_name( $slug );
		if ( taxonomy_exists( $existing_attribute ) ) {
			return array(
				'success'       => false,
				'exists'        => true,
				'action'        => 'exists',
				'attribute'     => self::empty_attribute_payload(),
				'terms_created' => array(),
				'term_errors'   => array(),
				'message'       => 'Attribute with this name already exists.',
			);
		}

		$term_errors = array();

		try {
			// Create the attribute
			$attribute_data = array(
				'name'         => $name,
				'slug'         => $slug,
				'type'         => $type,
				'order_by'     => $order_by,
				'has_archives' => $has_archives,
			);

			$attribute_id = wc_create_attribute( $attribute_data );

			if ( is_wp_error( $attribute_id ) ) {
				return array(
					'success'       => false,
					'exists'        => false,
					'action'        => 'error',
					'attribute'     => self::empty_attribute_payload(),
					'terms_created' => array(),
					'term_errors'   => $term_errors,
					'message'       => 'Error creating attribute: ' . $attribute_id->get_error_message(),
				);
			}

			// Get the created attribute
			$created_attributes = wc_get_attribute_taxonomies();
			$created_attribute  = null;
			foreach ( $created_attributes as $attr ) {
				if ( (int) $attr->attribute_id === (int) $attribute_id ) {
					$created_attribute = $attr;
					break;
				}
			}

			if ( ! $created_attribute ) {
				return array(
					'success'       => false,
					'exists'        => false,
					'action'        => 'error',
					'attribute'     => self::empty_attribute_payload(),
					'terms_created' => array(),
					'term_errors'   => $term_errors,
					'message'       => 'Failed to retrieve created attribute.',
				);
			}

			$taxonomy = wc_attribute_taxonomy_name( $slug );

			// Register the taxonomy
			register_taxonomy(
				$taxonomy,
				array( 'product' ),
				array(
					'labels'       => array(
						'name' => $name,
					),
					'public'       => $has_archives,
					'show_ui'      => true,
					'show_in_menu' => true,
				)
			);

			$terms_created = array();

			// Create initial terms if provided
			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term_data ) {
					$term_name        = $term_data['name'];
					$term_slug        = $term_data['slug'] ?? sanitize_title( $term_name );
					$term_description = $term_data['description'] ?? '';

					$term = wp_insert_term(
						$term_name,
						$taxonomy,
						array(
							'slug'        => $term_slug,
							'description' => $term_description,
						)
					);

					if ( is_wp_error( $term ) ) {
						$term_errors[] = array(
							'action'  => 'create',
							'name'    => $term_name,
							'slug'    => $term_slug,
							'message' => $term->get_error_message(),
						);
						continue;
					}

					$terms_created[] = array(
						'id'   => $term['term_id'],
						'name' => $term_name,
						'slug' => $term_slug,
					);
				}
			}

			$action = $existing_attribute ? 'duplicate' : 'created';
			return array(
				'success'       => true,
				'exists'        => (bool) $existing_attribute,
				'action'        => $action,
				'attribute'     => array(
					'id'           => (int) $created_attribute->attribute_id,
					'name'         => $created_attribute->attribute_label,
					'slug'         => $created_attribute->attribute_name,
					'type'         => $created_attribute->attribute_type,
					'order_by'     => $created_attribute->attribute_orderby,
					'has_archives' => (bool) $created_attribute->attribute_public,
					'taxonomy'     => $taxonomy,
				),
				'terms_created' => $terms_created,
				'term_errors'   => $term_errors,
				'message'       => sprintf(
					'Successfully %s attribute "%s" with %d terms.',
					$action === 'duplicate' ? 'duplicated' : 'created',
					$name,
					count( $terms_created )
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'       => false,
				'exists'        => false,
				'action'        => 'error',
				'attribute'     => self::empty_attribute_payload(),
				'terms_created' => array(),
				'term_errors'   => $term_errors,
				'message'       => 'Error creating attribute: ' . $e->getMessage(),
			);
		}
	}

	private static function empty_attribute_payload(): array {
		return array(
			'id'           => 0,
			'name'         => '',
			'slug'         => '',
			'type'         => '',
			'order_by'     => '',
			'has_archives' => false,
			'taxonomy'     => '',
		);
	}

	private static function normalize_if_exists( $value ): string {
		$value = sanitize_key( (string) $value );
		$allowed = array( 'error', 'use_existing', 'create_duplicate' );
		return in_array( $value, $allowed, true ) ? $value : 'error';
	}

	private static function find_existing_attribute( string $slug, string $name ) {
		$attributes = wc_get_attribute_taxonomies();
		foreach ( $attributes as $attribute ) {
			if ( ! isset( $attribute->attribute_name, $attribute->attribute_label ) ) {
				continue;
			}
			if ( $slug !== '' && $attribute->attribute_name === $slug ) {
				return $attribute;
			}
			if ( $name !== '' && strtolower( $attribute->attribute_label ) === strtolower( $name ) ) {
				return $attribute;
			}
		}
		return null;
	}

	private static function build_attribute_payload( $attribute ): array {
		$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
		return array(
			'id'           => (int) $attribute->attribute_id,
			'name'         => (string) $attribute->attribute_label,
			'slug'         => (string) $attribute->attribute_name,
			'type'         => (string) $attribute->attribute_type,
			'order_by'     => (string) $attribute->attribute_orderby,
			'has_archives' => (bool) $attribute->attribute_public,
			'taxonomy'     => $taxonomy,
		);
	}

	private static function unique_attribute_slug( string $base_slug ): string {
		$base_slug = sanitize_title( $base_slug );
		$slug      = $base_slug;
		$index     = 2;
		while ( $slug !== '' && wc_attribute_taxonomy_id_by_name( $slug ) ) {
			$slug = $base_slug . '-' . $index;
			$index++;
		}
		return $slug;
	}

	private static function sanitize_terms( $terms ): array {
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$sanitized_terms = array();

		foreach ( $terms as $term_data ) {
			if ( ! is_array( $term_data ) ) {
				continue;
			}

			$term_name = isset( $term_data['name'] ) ? sanitize_text_field( (string) $term_data['name'] ) : '';
			if ( '' === $term_name ) {
				continue;
			}

			$term_slug = isset( $term_data['slug'] ) ? sanitize_title( (string) $term_data['slug'] ) : '';
			if ( '' === $term_slug ) {
				$term_slug = sanitize_title( $term_name );
			}

			$term_description = isset( $term_data['description'] ) ? sanitize_textarea_field( (string) $term_data['description'] ) : '';

			$sanitized_terms[] = array(
				'name'        => $term_name,
				'slug'        => $term_slug,
				'description' => $term_description,
			);
		}

		return $sanitized_terms;
	}
}
