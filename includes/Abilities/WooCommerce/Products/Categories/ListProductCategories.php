<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Categories;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ListProductCategories implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/list-product-categories',
			array(
				'label'               => 'List Product Categories',
				'description'         => 'List WooCommerce product categories with hierarchy, product counts, and filtering options.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'             => array(
							'type'        => 'integer',
							'description' => 'Maximum number of categories to return.',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 200,
						),
						'offset'            => array(
							'type'        => 'integer',
							'description' => 'Number of categories to skip.',
							'default'     => 0,
							'minimum'     => 0,
						),
						'search'            => array(
							'type'        => 'string',
							'description' => 'Search categories by name or description.',
						),
						'parent'            => array(
							'type'        => 'integer',
							'description' => 'Filter by parent category ID (0 for top-level).',
						),
						'hide_empty'        => array(
							'type'        => 'boolean',
							'description' => 'Hide categories with no products.',
							'default'     => false,
						),
						'include_hierarchy' => array(
							'type'        => 'boolean',
							'description' => 'Include hierarchical structure with children.',
							'default'     => true,
						),
						'orderby'           => array(
							'type'        => 'string',
							'description' => 'Sort categories by field.',
							'enum'        => array( 'name', 'count', 'term_id', 'slug', 'term_group' ),
							'default'     => 'name',
						),
						'order'             => array(
							'type'        => 'string',
							'description' => 'Sort order.',
							'enum'        => array( 'asc', 'desc' ),
							'default'     => 'asc',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'categories'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'integer' ),
									'name'        => array( 'type' => 'string' ),
									'slug'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'parent'      => array( 'type' => 'integer' ),
									'count'       => array( 'type' => 'integer' ),
									'image'       => array( 'type' => 'object' ),
									'display'     => array( 'type' => 'string' ),
									'menu_order'  => array( 'type' => 'integer' ),
									'children'    => array( 'type' => 'array' ),
									'ancestors'   => array( 'type' => 'array' ),
									'link'        => array( 'type' => 'string' ),
								),
							),
						),
						'hierarchy'       => array(
							'type'       => 'object',
							'properties' => array(
								'total_categories' => array( 'type' => 'integer' ),
								'top_level'        => array( 'type' => 'integer' ),
								'with_children'    => array( 'type' => 'integer' ),
								'max_depth'        => array( 'type' => 'integer' ),
							),
						),
						'pagination'      => array(
							'type'       => 'object',
							'properties' => array(
								'total'        => array( 'type' => 'integer' ),
								'total_pages'  => array( 'type' => 'integer' ),
								'current_page' => array( 'type' => 'integer' ),
								'per_page'     => array( 'type' => 'integer' ),
								'has_next'     => array( 'type' => 'boolean' ),
								'has_prev'     => array( 'type' => 'boolean' ),
							),
						),
						'filters_applied' => array( 'type' => 'object' ),
						'message'         => array( 'type' => 'string' ),
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
						'priority'        => 0.8,
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
		return current_user_can( 'manage_product_terms' ) || current_user_can( 'edit_products' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'categories'      => array(),
				'hierarchy'       => array(),
				'pagination'      => array(),
				'filters_applied' => $input,
				'message'         => 'WooCommerce is not active.',
			);
		}

		$limit             = $input['limit'] ?? 50;
		$offset            = $input['offset'] ?? 0;
		$search            = $input['search'] ?? '';
		$parent            = $input['parent'] ?? null;
		$hide_empty        = $input['hide_empty'] ?? false;
		$include_hierarchy = $input['include_hierarchy'] ?? true;
		$orderby           = $input['orderby'] ?? 'name';
		$order             = $input['order'] ?? 'asc';

		// Build query args
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => $hide_empty,
			'orderby'    => $orderby,
			'order'      => $order,
			'number'     => $limit,
			'offset'     => $offset,
		);

		// Add search
		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		// Add parent filter
		if ( $parent !== null ) {
			$args['parent'] = $parent;
		}

		// Get categories
		$categories = get_terms( $args );

		if ( is_wp_error( $categories ) ) {
			return array(
				'categories'      => array(),
				'hierarchy'       => array(),
				'pagination'      => array(),
				'filters_applied' => array_filter( $input ),
				'message'         => 'Error retrieving categories: ' . $categories->get_error_message(),
			);
		}

		// Format categories
		$formatted_categories = array();
		foreach ( $categories as $category ) {
			$formatted_categories[] = self::format_category( $category, $include_hierarchy );
		}

		// Get total count for pagination
		$total_args = $args;
		unset( $total_args['number'], $total_args['offset'] );
		$all_categories = get_terms( $total_args );
		$total          = is_wp_error( $all_categories ) ? 0 : count( $all_categories );

		// Calculate pagination
		$current_page = floor( $offset / $limit ) + 1;
		$total_pages  = ceil( $total / $limit );

		$pagination = array(
			'total'        => $total,
			'total_pages'  => $total_pages,
			'current_page' => $current_page,
			'per_page'     => $limit,
			'has_next'     => $current_page < $total_pages,
			'has_prev'     => $current_page > 1,
		);

		// Get hierarchy information
		$hierarchy = self::get_hierarchy_info();

		return array(
			'categories'      => $formatted_categories,
			'hierarchy'       => $hierarchy,
			'pagination'      => $pagination,
			'filters_applied' => array_filter( $input ),
			'message'         => sprintf(
				'Found %d categories (showing %d-%d of %d total).',
				count( $formatted_categories ),
				$offset + 1,
				min( $offset + $limit, $total ),
				$total
			),
		);
	}

	private static function format_category( \WP_Term $category, bool $include_hierarchy ): array {
		$image_data   = array();
		$thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
		if ( $thumbnail_id ) {
			$image     = wp_get_attachment_image_src( $thumbnail_id, 'full' );
			$thumbnail = wp_get_attachment_image_src( $thumbnail_id, 'thumbnail' );
			if ( $image ) {
				$image_data = array(
					'id'        => $thumbnail_id,
					'src'       => $image[0],
					'thumbnail' => $thumbnail ? $thumbnail[0] : $image[0],
					'alt'       => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
				);
			}
		}

		$data = array(
			'id'          => $category->term_id,
			'name'        => $category->name,
			'slug'        => $category->slug,
			'description' => $category->description,
			'parent'      => $category->parent,
			'count'       => $category->count,
			'image'       => $image_data,
			'display'     => get_term_meta( $category->term_id, 'display_type', true ) ?: 'default',
			'menu_order'  => get_term_meta( $category->term_id, 'order', true ) ?: 0,
			'link'        => get_term_link( $category ),
		);

		// Add hierarchy information if requested
		if ( $include_hierarchy ) {
			// Get children
			$children = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => $category->term_id,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);

			$data['children'] = is_wp_error( $children ) ? array() : $children;

			// Get ancestors
			$ancestors         = get_ancestors( $category->term_id, 'product_cat' );
			$data['ancestors'] = array_reverse( $ancestors );
		} else {
			$data['children']  = array();
			$data['ancestors'] = array();
		}

		return $data;
	}

	private static function get_hierarchy_info(): array {
		// Get all categories
		$all_categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'fields'     => 'all',
			)
		);

		if ( is_wp_error( $all_categories ) ) {
			return array(
				'total_categories' => 0,
				'top_level'        => 0,
				'with_children'    => 0,
				'max_depth'        => 0,
			);
		}

		$total_categories = count( $all_categories );
		$top_level        = 0;
		$with_children    = 0;
		$max_depth        = 0;

		foreach ( $all_categories as $category ) {
			if ( $category->parent === 0 ) {
				++$top_level;
			}

			// Check if has children
			$children = get_terms(
				array(
					'taxonomy' => 'product_cat',
					'parent'   => $category->term_id,
					'fields'   => 'ids',
				)
			);

			if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
				++$with_children;
			}

			// Calculate depth
			$ancestors = get_ancestors( $category->term_id, 'product_cat' );
			$depth     = count( $ancestors ) + 1;
			$max_depth = max( $max_depth, $depth );
		}

		return array(
			'total_categories' => $total_categories,
			'top_level'        => $top_level,
			'with_children'    => $with_children,
			'max_depth'        => $max_depth,
		);
	}
}
