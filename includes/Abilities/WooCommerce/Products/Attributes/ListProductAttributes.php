<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Attributes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ListProductAttributes implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/list-product-attributes',
			array(
				'label'               => 'List Product Attributes',
				'description'         => 'List WooCommerce product attributes with their terms and configuration.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'include_terms'       => array(
							'type'        => 'boolean',
							'description' => 'Include attribute terms/values.',
							'default'     => true,
						),
						'include_usage_count' => array(
							'type'        => 'boolean',
							'description' => 'Include usage count for each attribute.',
							'default'     => true,
						),
						'attribute_name'      => array(
							'type'        => 'string',
							'description' => 'Filter by specific attribute name.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'attributes' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'           => array( 'type' => 'integer' ),
									'name'         => array( 'type' => 'string' ),
									'slug'         => array( 'type' => 'string' ),
									'type'         => array( 'type' => 'string' ),
									'order_by'     => array( 'type' => 'string' ),
									'has_archives' => array( 'type' => 'boolean' ),
									'usage_count'  => array( 'type' => 'integer' ),
									'terms'        => array( 'type' => 'array' ),
								),
							),
						),
						'summary'    => array(
							'type'       => 'object',
							'properties' => array(
								'total_attributes' => array( 'type' => 'integer' ),
								'total_terms'      => array( 'type' => 'integer' ),
								'most_used'        => array( 'type' => 'string' ),
							),
						),
						'message'    => array( 'type' => 'string' ),
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
		return current_user_can( 'edit_products' ) || current_user_can( 'manage_product_terms' ) || current_user_can( 'manage_woocommerce' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'attributes' => array(),
				'summary'    => array(),
				'message'    => 'WooCommerce is not active.',
			);
		}

		$include_terms       = $input['include_terms'] ?? true;
		$include_usage_count = $input['include_usage_count'] ?? true;
		$attribute_name      = $input['attribute_name'] ?? '';

		// Get WooCommerce product attributes
		$wc_attributes = wc_get_attribute_taxonomies();
		$attributes    = array();
		$total_terms   = 0;
		$usage_counts  = array();

		foreach ( $wc_attributes as $wc_attribute ) {
			// Filter by attribute name if specified
			if ( ! empty( $attribute_name ) && $wc_attribute->attribute_name !== $attribute_name ) {
				continue;
			}

			$taxonomy    = wc_attribute_taxonomy_name( $wc_attribute->attribute_name );
			$terms       = array();
			$usage_count = 0;

			// Get terms for this attribute
			if ( $include_terms ) {
				$attribute_terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
					)
				);

				if ( ! is_wp_error( $attribute_terms ) ) {
					foreach ( $attribute_terms as $term ) {
						$terms[] = array(
							'id'          => $term->term_id,
							'name'        => $term->name,
							'slug'        => $term->slug,
							'description' => $term->description,
							'count'       => $term->count,
						);
						++$total_terms;
					}
				}
			}

			// Get usage count if requested
			if ( $include_usage_count ) {
				$usage_count                                   = self::get_attribute_usage_count( $taxonomy );
				$usage_counts[ $wc_attribute->attribute_name ] = $usage_count;
			}

			$attributes[] = array(
				'id'           => $wc_attribute->attribute_id,
				'name'         => $wc_attribute->attribute_name,
				'slug'         => $wc_attribute->attribute_name,
				'type'         => $wc_attribute->attribute_type,
				'order_by'     => $wc_attribute->attribute_orderby,
				'has_archives' => (bool) $wc_attribute->attribute_public,
				'usage_count'  => $usage_count,
				'terms'        => $terms,
			);
		}

		// Find most used attribute
		$most_used = '';
		if ( ! empty( $usage_counts ) ) {
			$most_used = array_search( max( $usage_counts ), $usage_counts );
		}

		$summary = array(
			'total_attributes' => count( $attributes ),
			'total_terms'      => $total_terms,
			'most_used'        => $most_used,
		);

		return array(
			'attributes' => $attributes,
			'summary'    => $summary,
			'message'    => sprintf( 'Found %d product attributes with %d total terms.', count( $attributes ), $total_terms ),
		);
	}

	private static function get_attribute_usage_count( string $taxonomy ): int {
		global $wpdb;

		// Count how many products use this attribute
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) 
			 FROM {$wpdb->posts} p 
			 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id 
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
			 WHERE p.post_type = 'product' 
			 AND p.post_status = 'publish' 
			 AND tt.taxonomy = %s",
				$taxonomy
			)
		);

		return (int) $count;
	}
}
