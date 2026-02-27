<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class CreateProductCategory implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/create-product-category',
			array(
				'label'               => 'Create Product Category',
				'description'         => 'Create a new WooCommerce product category with optional parent and display settings.',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'name' ),
					'properties'           => array(
						'name'         => array(
							'type'        => 'string',
							'description' => 'Category name.',
						),
						'slug'         => array(
							'type'        => 'string',
							'description' => 'Category slug (auto-generated if not provided).',
						),
						'description'  => array(
							'type'        => 'string',
							'description' => 'Category description.',
						),
						'parent'       => array(
							'type'        => 'integer',
							'description' => 'Parent category ID (0 for top-level).',
							'default'     => 0,
						),
						'display_type' => array(
							'type'        => 'string',
							'description' => 'Category display type.',
							'enum'        => array( 'default', 'products', 'subcategories', 'both' ),
							'default'     => 'default',
						),
						'image_id'     => array(
							'type'        => 'integer',
							'description' => 'Category image attachment ID.',
						),
						'menu_order'   => array(
							'type'        => 'integer',
							'description' => 'Menu order for category sorting.',
							'default'     => 0,
						),
						'if_exists'    => array(
							'type'        => 'string',
							'description' => 'How to handle an existing category (error, use_existing, create_duplicate).',
							'enum'        => array( 'error', 'use_existing', 'create_duplicate' ),
							'default'     => 'error',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'exists'      => array( 'type' => 'boolean' ),
						'action'      => array( 'type' => 'string' ),
						'category'    => array(
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
						'parent_info' => array(
							'type'       => 'object',
							'properties' => array(
								'id'   => array( 'type' => 'integer' ),
								'name' => array( 'type' => 'string' ),
							),
						),
						'message'     => array( 'type' => 'string' ),
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
				'success'     => false,
				'exists'      => false,
				'action'      => 'error',
				'category'    => self::empty_category_payload(),
				'parent_info' => self::empty_parent_payload(),
				'message'     => 'WooCommerce is not active.',
			);
		}

		$name = sanitize_text_field( (string) $input['name'] );
		$slug = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '';
		if ( '' === $slug ) {
			$slug = sanitize_title( $name );
		}
		$description  = isset( $input['description'] ) ? sanitize_textarea_field( (string) $input['description'] ) : '';
		$parent       = isset( $input['parent'] ) ? absint( $input['parent'] ) : 0;
		$display_type = $input['display_type'] ?? 'default';
		$image_id     = $input['image_id'] ?? 0;
		$menu_order   = $input['menu_order'] ?? 0;
		$if_exists    = self::normalize_if_exists( $input['if_exists'] ?? 'error' );

		$existing_category = self::find_existing_category( $slug, $name );
		if ( $existing_category ) {
			$existing_payload = self::build_category_payload( $existing_category );
			$existing_parent  = self::build_parent_payload( $existing_category->parent );

			if ( 'use_existing' === $if_exists ) {
				return array(
					'success'     => true,
					'exists'      => true,
					'action'      => 'existing',
					'category'    => $existing_payload,
					'parent_info' => $existing_parent,
					'message'     => 'Category already exists. Using existing category.',
				);
			}

			if ( 'create_duplicate' !== $if_exists ) {
				return array(
					'success'     => false,
					'exists'      => true,
					'action'      => 'exists',
					'category'    => $existing_payload,
					'parent_info' => $existing_parent,
					'message'     => 'Category already exists. Set if_exists to use_existing or create_duplicate.',
				);
			}

			$slug = self::unique_term_slug( $slug !== '' ? $slug : $name, 'product_cat' );
		}

		// Validate parent category
		$parent_info = null;
		if ( $parent > 0 ) {
			$parent_category = get_term( $parent, 'product_cat' );
			if ( is_wp_error( $parent_category ) || ! $parent_category ) {
				return array(
					'success'     => false,
					'exists'      => false,
					'action'      => 'error',
					'category'    => self::empty_category_payload(),
					'parent_info' => self::empty_parent_payload(),
					'message'     => 'Parent category not found.',
				);
			}

			$parent_info = array(
				'id'   => $parent_category->term_id,
				'name' => $parent_category->name,
			);
		}

		try {
			// Create the category
			$result = wp_insert_term(
				$name,
				'product_cat',
				array(
					'slug'        => $slug,
					'description' => $description,
					'parent'      => $parent,
				)
			);

			if ( is_wp_error( $result ) ) {
				return array(
					'success'     => false,
					'exists'      => false,
					'action'      => 'error',
					'category'    => self::empty_category_payload(),
					'parent_info' => $parent_info,
					'message'     => 'Error creating category: ' . $result->get_error_message(),
				);
			}

			$category_id = $result['term_id'];

			// Set display type
			update_term_meta( $category_id, 'display_type', $display_type );

			// Set image
			if ( $image_id > 0 ) {
				update_term_meta( $category_id, 'thumbnail_id', $image_id );
			}

			// Set menu order
			update_term_meta( $category_id, 'order', $menu_order );

			// Get the created category
			$created_category = get_term( $category_id, 'product_cat' );

			if ( is_wp_error( $created_category ) || ! $created_category ) {
				return array(
					'success'     => false,
					'exists'      => false,
					'action'      => 'error',
					'category'    => self::empty_category_payload(),
					'parent_info' => $parent_info ?: self::empty_parent_payload(),
					'message'     => 'Failed to retrieve created category.',
				);
			}

			$link = get_term_link( $created_category );
			if ( is_wp_error( $link ) ) {
				$link = '';
			}

			$action = $existing_category ? 'duplicate' : 'created';
			return array(
				'success'     => true,
				'exists'      => (bool) $existing_category,
				'action'      => $action,
				'category'    => array(
					'id'          => $created_category->term_id,
					'name'        => $created_category->name,
					'slug'        => $created_category->slug,
					'description' => $created_category->description,
					'parent'      => $created_category->parent,
					'count'       => $created_category->count,
					'display'     => get_term_meta( $category_id, 'display_type', true ),
					'menu_order'  => get_term_meta( $category_id, 'order', true ),
					'link'        => $link,
				),
				'parent_info' => $parent_info ?: array(),
				'message'     => sprintf(
					'Successfully %s category "%s"%s.',
					$action === 'duplicate' ? 'duplicated' : 'created',
					$name,
					$parent_info ? ' under "' . $parent_info['name'] . '"' : ''
				),
			);
		} catch ( \Throwable $e ) {
			return array(
				'success'     => false,
				'exists'      => false,
				'action'      => 'error',
				'category'    => self::empty_category_payload(),
				'parent_info' => $parent_info ?: self::empty_parent_payload(),
				'message'     => 'Error creating category: ' . $e->getMessage(),
			);
		}
	}

	private static function empty_category_payload(): array {
		return array(
			'id'          => 0,
			'name'        => '',
			'slug'        => '',
			'description' => '',
			'parent'      => 0,
			'count'       => 0,
			'display'     => '',
			'menu_order'  => 0,
			'link'        => '',
		);
	}

	private static function empty_parent_payload(): array {
		return array(
			'id'   => 0,
			'name' => '',
		);
	}

	private static function normalize_if_exists( $value ): string {
		$value = sanitize_key( (string) $value );
		$allowed = array( 'error', 'use_existing', 'create_duplicate' );
		return in_array( $value, $allowed, true ) ? $value : 'error';
	}

	private static function find_existing_category( string $slug, string $name ): ?\WP_Term {
		$term_id = 0;
		if ( $slug !== '' ) {
			$term_exists = term_exists( $slug, 'product_cat' );
			$term_id     = is_array( $term_exists ) ? (int) $term_exists['term_id'] : (int) $term_exists;
		}
		if ( ! $term_id && $name !== '' ) {
			$term_exists = term_exists( $name, 'product_cat' );
			$term_id     = is_array( $term_exists ) ? (int) $term_exists['term_id'] : (int) $term_exists;
		}
		if ( ! $term_id ) {
			return null;
		}
		$term = get_term( $term_id, 'product_cat' );
		return $term instanceof \WP_Term ? $term : null;
	}

	private static function build_category_payload( \WP_Term $term ): array {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			$link = '';
		}

		return array(
			'id'          => $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => $term->parent,
			'count'       => $term->count,
			'display'     => get_term_meta( $term->term_id, 'display_type', true ),
			'menu_order'  => get_term_meta( $term->term_id, 'order', true ),
			'link'        => $link,
		);
	}

	private static function build_parent_payload( int $parent_id ): array {
		if ( $parent_id <= 0 ) {
			return self::empty_parent_payload();
		}
		$parent = get_term( $parent_id, 'product_cat' );
		if ( ! $parent instanceof \WP_Term ) {
			return self::empty_parent_payload();
		}
		return array(
			'id'   => $parent->term_id,
			'name' => $parent->name,
		);
	}

	private static function unique_term_slug( string $slug, string $taxonomy ): string {
		$slug = sanitize_title( $slug );
		if ( $slug === '' ) {
			return $slug;
		}
		return wp_unique_term_slug( $slug, (object) array( 'taxonomy' => $taxonomy ) );
	}
}
