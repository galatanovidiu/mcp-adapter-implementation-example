<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Tags;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ListProductTags implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/list-product-tags',
			array(
				'label'               => 'List Product Tags',
				'description'         => 'List WooCommerce product tags with product counts and usage statistics.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of tags to return.',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 200,
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of tags to skip.',
							'default'     => 0,
							'minimum'     => 0,
						),
						'search' => array(
							'type'        => 'string',
							'description' => 'Search tags by name or description.',
						),
						'hide_empty' => array(
							'type'        => 'boolean',
							'description' => 'Hide tags with no products.',
							'default'     => false,
						),
						'include_products' => array(
							'type'        => 'boolean',
							'description' => 'Include sample products for each tag.',
							'default'     => false,
						),
						'min_count' => array(
							'type'        => 'integer',
							'description' => 'Minimum product count for tags.',
							'minimum'     => 0,
						),
						'orderby' => array(
							'type'        => 'string',
							'description' => 'Sort tags by field.',
							'enum'        => array( 'name', 'count', 'term_id', 'slug' ),
							'default'     => 'name',
						),
						'order' => array(
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
						'tags' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'integer' ),
									'name'        => array( 'type' => 'string' ),
									'slug'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'count'       => array( 'type' => 'integer' ),
									'link'        => array( 'type' => 'string' ),
									'products'    => array( 'type' => 'array' ),
								),
							),
						),
						'statistics' => array(
							'type'       => 'object',
							'properties' => array(
								'total_tags'      => array( 'type' => 'integer' ),
								'used_tags'       => array( 'type' => 'integer' ),
								'unused_tags'     => array( 'type' => 'integer' ),
								'most_used_tag'   => array( 'type' => 'string' ),
								'average_usage'   => array( 'type' => 'number' ),
							),
						),
						'pagination' => array(
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
				'tags'            => array(),
				'statistics'      => array(),
				'pagination'      => array(),
				'filters_applied' => $input,
				'message'         => 'WooCommerce is not active.',
			);
		}

		$limit = $input['limit'] ?? 50;
		$offset = $input['offset'] ?? 0;
		$search = $input['search'] ?? '';
		$hide_empty = $input['hide_empty'] ?? false;
		$include_products = $input['include_products'] ?? false;
		$min_count = $input['min_count'] ?? null;
		$orderby = $input['orderby'] ?? 'name';
		$order = $input['order'] ?? 'asc';

		// Build query args
		$args = array(
			'taxonomy'   => 'product_tag',
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

		// Get tags
		$tags = get_terms( $args );

		if ( is_wp_error( $tags ) ) {
			return array(
				'tags'            => array(),
				'statistics'      => array(),
				'pagination'      => array(),
				'filters_applied' => array_filter( $input ),
				'message'         => 'Error retrieving tags: ' . $tags->get_error_message(),
			);
		}

		// Format tags
		$formatted_tags = array();
		foreach ( $tags as $tag ) {
			// Apply min_count filter
			if ( $min_count !== null && $tag->count < $min_count ) {
				continue;
			}

			$formatted_tag = self::format_tag( $tag, $include_products );
			$formatted_tags[] = $formatted_tag;
		}

		// Get total count for pagination
		$total_args = $args;
		unset( $total_args['number'], $total_args['offset'] );
		$all_tags = get_terms( $total_args );
		$total = is_wp_error( $all_tags ) ? 0 : count( $all_tags );

		// Apply min_count filter to total if needed
		if ( $min_count !== null ) {
			$filtered_total = 0;
			foreach ( $all_tags as $tag ) {
				if ( $tag->count >= $min_count ) {
					$filtered_total++;
				}
			}
			$total = $filtered_total;
		}

		// Calculate pagination
		$current_page = floor( $offset / $limit ) + 1;
		$total_pages = ceil( $total / $limit );

		$pagination = array(
			'total'        => $total,
			'total_pages'  => $total_pages,
			'current_page' => $current_page,
			'per_page'     => $limit,
			'has_next'     => $current_page < $total_pages,
			'has_prev'     => $current_page > 1,
		);

		// Get statistics
		$statistics = self::get_tag_statistics();

		return array(
			'tags'            => $formatted_tags,
			'statistics'      => $statistics,
			'pagination'      => $pagination,
			'filters_applied' => array_filter( $input ),
			'message'         => sprintf(
				'Found %d tags (showing %d-%d of %d total).',
				count( $formatted_tags ),
				$offset + 1,
				min( $offset + $limit, $total ),
				$total
			),
		);
	}

	private static function format_tag( \WP_Term $tag, bool $include_products ): array {
		$data = array(
			'id'          => $tag->term_id,
			'name'        => $tag->name,
			'slug'        => $tag->slug,
			'description' => $tag->description,
			'count'       => $tag->count,
			'link'        => get_term_link( $tag ),
			'products'    => array(),
		);

		// Include sample products if requested
		if ( $include_products && $tag->count > 0 ) {
			$products = wc_get_products( array(
				'tag'    => array( $tag->slug ),
				'limit'  => 5,
				'status' => 'publish',
			) );

			foreach ( $products as $product ) {
				$data['products'][] = array(
					'id'    => $product->get_id(),
					'name'  => $product->get_name(),
					'price' => $product->get_price(),
					'image' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
				);
			}
		}

		return $data;
	}

	private static function get_tag_statistics(): array {
		// Get all tags
		$all_tags = get_terms( array(
			'taxonomy'   => 'product_tag',
			'hide_empty' => false,
		) );

		if ( is_wp_error( $all_tags ) ) {
			return array(
				'total_tags'    => 0,
				'used_tags'     => 0,
				'unused_tags'   => 0,
				'most_used_tag' => '',
				'average_usage' => 0,
			);
		}

		$total_tags = count( $all_tags );
		$used_tags = 0;
		$unused_tags = 0;
		$total_usage = 0;
		$most_used_tag = '';
		$max_count = 0;

		foreach ( $all_tags as $tag ) {
			if ( $tag->count > 0 ) {
				$used_tags++;
				$total_usage += $tag->count;
				
				if ( $tag->count > $max_count ) {
					$max_count = $tag->count;
					$most_used_tag = $tag->name;
				}
			} else {
				$unused_tags++;
			}
		}

		$average_usage = $used_tags > 0 ? round( $total_usage / $used_tags, 2 ) : 0;

		return array(
			'total_tags'    => $total_tags,
			'used_tags'     => $used_tags,
			'unused_tags'   => $unused_tags,
			'most_used_tag' => $most_used_tag,
			'average_usage' => $average_usage,
		);
	}
}
