<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetTerms implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/get-terms',
			array(
				'label'               => 'Get Terms',
				'description'         => 'List terms in a taxonomy with optional filters/pagination.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'taxonomy' ),
					'properties' => array(
						'taxonomy'   => array( 'type' => 'string' ),
						'search'     => array( 'type' => 'string' ),
						'parent'     => array( 'type' => 'integer' ),
						'hide_empty' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'include'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'exclude'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'orderby'    => array( 'type' => 'string' ),
						'order'      => array( 'type' => 'string' ),
						'per_page'   => array(
							'type'    => 'integer',
							'default' => 50,
						),
						'page'       => array(
							'type'    => 'integer',
							'default' => 1,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'terms' ),
					'properties' => array(
						'terms' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'id', 'name', 'slug' ),
								'properties' => array(
									'id'          => array( 'type' => 'integer' ),
									'name'        => array( 'type' => 'string' ),
									'slug'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'count'       => array( 'type' => 'integer' ),
									'parent'      => array( 'type' => 'integer' ),
								),
							),
						),
					),
				),
				'permission_callback' => static function (): bool {
					return \current_user_can( 'edit_posts' );
				},
				'execute_callback'    => static function ( array $input ) {
					$taxonomy = \sanitize_key( (string) $input['taxonomy'] );
					if ( ! \taxonomy_exists( $taxonomy ) ) {
						return new \WP_Error( 'invalid_taxonomy', 'Invalid taxonomy.' );
					}
					$args = array(
						'taxonomy'   => $taxonomy,
						'search'     => isset( $input['search'] ) ? (string) $input['search'] : '',
						'parent'     => isset( $input['parent'] ) ? (int) $input['parent'] : 0,
						'hide_empty' => ! empty( $input['hide_empty'] ),
						'include'    => isset( $input['include'] ) ? array_map( 'intval', (array) $input['include'] ) : array(),
						'exclude'    => isset( $input['exclude'] ) ? array_map( 'intval', (array) $input['exclude'] ) : array(),
						'orderby'    => isset( $input['orderby'] ) ? (string) $input['orderby'] : '',
						'order'      => isset( $input['order'] ) ? (string) $input['order'] : '',
						'number'     => isset( $input['per_page'] ) ? max( 1, (int) $input['per_page'] ) : 50,
						'offset'     => isset( $input['page'] ) ? ( max( 1, (int) $input['page'] ) - 1 ) * ( isset( $input['per_page'] ) ? max( 1, (int) $input['per_page'] ) : 50 ) : 0,
					);
					$terms = \get_terms( $args );
					if ( \is_wp_error( $terms ) ) {
						return $terms;
					}
					$out = array();
					foreach ( $terms as $t ) {
						if ( ! ( $t instanceof \WP_Term ) ) {
							continue;
						}

						$out[] = array(
							'id'          => (int) $t->term_id,
							'name'        => (string) $t->name,
							'slug'        => (string) $t->slug,
							'description' => (string) $t->description,
							'count'       => (int) $t->count,
							'parent'      => (int) $t->parent,
						);
					}
					return array( 'terms' => $out );
				},
				'meta'                => array(),
			)
		);
	}
}
