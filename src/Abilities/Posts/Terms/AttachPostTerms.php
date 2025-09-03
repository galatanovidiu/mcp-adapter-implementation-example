<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts\Terms;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class AttachPostTerms implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/attach-post-terms',
			array(
				'label'               => 'Attach Post Terms',
				'description'         => 'Attach terms to a post in a supported taxonomy.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id', 'taxonomy', 'terms' ),
					'properties' => array(
						'id'                => array(
							'type'        => 'integer',
							'description' => 'Post ID.',
						),
						'taxonomy'          => array( 'type' => 'string' ),
						'terms'             => array(
							'type'  => 'array',
							'items' => array(),
						),
						'append'            => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'create_if_missing' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'attached_ids' ),
					'properties' => array(
						'attached_ids' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
				'permission_callback' => static function ( array $input ): bool {
					$post_id = (int) ( $input['id'] ?? 0 );
					$taxonomy = isset( $input['taxonomy'] ) ? \sanitize_key( (string) $input['taxonomy'] ) : '';
					if ( $post_id <= 0 || ! \taxonomy_exists( $taxonomy ) ) {
						return false; }
					$post = \get_post( $post_id );
					if ( ! $post ) {
						return false; }
					$tax = \get_taxonomy( $taxonomy );
					return $tax && isset( $tax->cap->assign_terms ) ? \current_user_can( $tax->cap->assign_terms ) && \current_user_can( 'edit_post', $post_id ) : \current_user_can( 'edit_post', $post_id );
				},
				'execute_callback'    => static function ( array $input ) {
					$post_id  = (int) $input['id'];
					$taxonomy = \sanitize_key( (string) $input['taxonomy'] );
					$post     = \get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'not_found', 'Post not found.' ); }
					if ( ! \is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
						return new \WP_Error( 'invalid_taxonomy', 'Taxonomy not supported by post type.' );
					}
					$append = array_key_exists( 'append', $input ) ? (bool) $input['append'] : true;
					$create_if_missing = ! empty( $input['create_if_missing'] );
					$term_ids = array();
					$terms_in = is_array( $input['terms'] ) ? $input['terms'] : array( $input['terms'] );
					foreach ( $terms_in as $t ) {
						if ( is_numeric( $t ) ) {
							$term_ids[] = (int) $t;
							continue; }
						if ( ! is_string( $t ) ) {
							continue;
						}

						$term = \get_term_by( 'slug', $t, $taxonomy );
						if ( ! $term ) {
							$term = \get_term_by( 'name', $t, $taxonomy ); }
						if ( $term instanceof \WP_Term ) {
							$term_ids[] = (int) $term->term_id;
						} elseif ( $create_if_missing && \current_user_can( 'manage_terms' ) ) {
							$created = \wp_insert_term( $t, $taxonomy );
							if ( ! \is_wp_error( $created ) && isset( $created['term_id'] ) ) {
								$term_ids[] = (int) $created['term_id']; }
						}
					}
					if ( ! empty( $term_ids ) ) {
						\wp_set_post_terms( $post_id, array_map( 'intval', $term_ids ), $taxonomy, $append ); }
					return array( 'attached_ids' => array_values( array_unique( array_map( 'intval', $term_ids ) ) ) );
				},
				'meta'                => array(),
			)
		);
	}
}
