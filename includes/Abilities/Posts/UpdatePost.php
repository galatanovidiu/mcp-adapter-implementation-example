<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdatePost implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'wpmcp-example/update-post',
			array(
				'label'               => 'Update Post',
				'description'         => 'Update a WordPress post by ID using HTML content. Supports WordPress block comments for full editor compatibility. Use list-block-types first to get available blocks and their attributes.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'                      => array(
							'type'        => 'integer',
							'description' => 'Post ID to update.',
						),

						'title'                   => array( 'type' => 'string' ),
						'content'                 => array(
							'type'        => 'string',
							'description' => 'Post content as HTML. Include WordPress block comments (<!-- wp:blockname {"attr":"value"} -->) for full block editor compatibility. Use wpmcp/list-block-types to get valid block names and attributes.',
						),
						'excerpt'                 => array( 'type' => 'string' ),
						'status'                  => array( 'type' => 'string' ),
						'author'                  => array( 'type' => 'integer' ),
						'meta'                    => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'tax_input'               => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'create_terms_if_missing' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'append_terms'            => array(
							'type'    => 'boolean',
							'default' => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'        => array( 'type' => 'integer' ),
						'post_type' => array( 'type' => 'string' ),
						'status'    => array( 'type' => 'string' ),
						'link'      => array( 'type' => 'string' ),
						'title'     => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => static function ( array $input ): bool {
					$post_id = (int) ( $input['id'] ?? 0 );
					if ( $post_id <= 0 ) {
						return false;
					}
					return \current_user_can( 'edit_post', $post_id );
				},
				'execute_callback'    => static function ( array $input ) {
					$post_id = (int) $input['id'];
					$post    = \get_post( $post_id );
					if ( ! $post ) {
						return new \WP_Error( 'not_found', 'Post not found.' );
					}

					$postarr = array(
						'ID' => $post_id,
					);
					if ( array_key_exists( 'title', $input ) ) {
						$postarr['post_title'] = \sanitize_text_field( (string) $input['title'] );
					}
					if ( array_key_exists( 'content', $input ) ) {
						$postarr['post_content'] = \wp_kses_post( (string) $input['content'] );
					}
					if ( array_key_exists( 'excerpt', $input ) ) {
						$postarr['post_excerpt'] = \wp_kses_post( (string) $input['excerpt'] );
					}
					if ( array_key_exists( 'status', $input ) ) {
						$postarr['post_status'] = \sanitize_key( (string) $input['status'] );
					}
					if ( array_key_exists( 'author', $input ) ) {
						$postarr['post_author'] = (int) $input['author'];
					}

					$has_tax_input = ( ! empty( $input['tax_input'] ) && \is_array( $input['tax_input'] ) );

					$updated = \wp_update_post( $postarr, true );
					if ( \is_wp_error( $updated ) ) {
						return $updated;
					}
					$updated_post = \get_post( $post_id );
					if ( ! $updated_post ) {
						return new \WP_Error( 'update_failed', 'Post updated but could not be loaded.' );
					}

					if ( $has_tax_input ) {
						$append = array_key_exists( 'append_terms', $input ) ? (bool) $input['append_terms'] : true;
						$create_if_missing = ! empty( $input['create_terms_if_missing'] );
						$supported_taxonomies = \get_object_taxonomies( $updated_post->post_type, 'names' );
						foreach ( $input['tax_input'] as $taxonomy => $terms_in ) {
							$taxonomy = \sanitize_key( (string) $taxonomy );
							if ( ! \taxonomy_exists( $taxonomy ) ) {
								continue;
							}
							if ( ! \in_array( $taxonomy, $supported_taxonomies, true ) ) {
								continue;
							}
							$term_ids = array();
							$terms_in = is_array( $terms_in ) ? $terms_in : array( $terms_in );
							foreach ( $terms_in as $t ) {
								if ( is_numeric( $t ) ) {
									$term_ids[] = (int) $t;
									continue;
								}
								if ( ! is_string( $t ) ) {
									continue;
								}

								$term = \get_term_by( 'slug', $t, $taxonomy );
								if ( ! $term ) {
									$term = \get_term_by( 'name', $t, $taxonomy );
								}
								if ( $term instanceof \WP_Term ) {
									$term_ids[] = (int) $term->term_id;
								} elseif ( $create_if_missing && \current_user_can( 'manage_terms' ) ) {
									$created = \wp_insert_term( $t, $taxonomy );
									if ( ! \is_wp_error( $created ) && isset( $created['term_id'] ) ) {
										$term_ids[] = (int) $created['term_id'];
									}
								}
							}
							if ( empty( $term_ids ) ) {
								continue;
							}

							\wp_set_post_terms( $post_id, array_map( 'intval', $term_ids ), $taxonomy, $append );
						}
					}

					return array(
						'id'        => $updated_post->ID,
						'post_type' => $updated_post->post_type,
						'status'    => $updated_post->post_status,
						'link'      => (string) \get_permalink( $updated_post->ID ),
						'title'     => (string) $updated_post->post_title,
					);
				},
				'meta'                => array(),
			)
		);
	}
}
