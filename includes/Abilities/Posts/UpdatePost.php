<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdatePost implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/update-post',
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
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'content',
				'meta'                => array(
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Check permission for updating a post.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$post_id = (int) ( $input['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return false;
		}
		return \current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Execute the update post operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$post_id = (int) $input['id'];
		$post    = \get_post( $post_id );
		if ( ! $post ) {
			return array(
				'error' => array(
					'code'    => 'not_found',
					'message' => 'Post not found.',
				),
			);
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
		$requested_status = null;
		if ( array_key_exists( 'status', $input ) ) {
			$requested_status       = \sanitize_key( (string) $input['status'] );
			$postarr['post_status'] = $requested_status;
		}
		if ( array_key_exists( 'author', $input ) ) {
			$postarr['post_author'] = (int) $input['author'];
		}

		$has_tax_input  = ( ! empty( $input['tax_input'] ) && \is_array( $input['tax_input'] ) );
		$has_meta_input = array_key_exists( 'meta', $input );

		if ( $requested_status && $requested_status !== $post->post_status && self::status_requires_publish_cap( $requested_status ) ) {
			$pto = \get_post_type_object( $post->post_type );
			if ( $pto ) {
				$publish_cap = $pto->cap->publish_posts ?? 'publish_posts';
				if ( ! \current_user_can( $publish_cap ) ) {
					return array(
						'error' => array(
							'code'    => 'insufficient_permissions',
							'message' => 'You do not have permission to publish this post type.',
						),
					);
				}
			}
		}

		$updated = \wp_update_post( $postarr, true );
		if ( \is_wp_error( $updated ) ) {
			return array(
				'error' => array(
					'code'    => $updated->get_error_code(),
					'message' => $updated->get_error_message(),
				),
			);
		}
		$updated_post = \get_post( $post_id );
		if ( ! $updated_post ) {
			return array(
				'error' => array(
					'code'    => 'update_failed',
					'message' => 'Post updated but could not be loaded.',
				),
			);
		}

		if ( $has_meta_input ) {
			if ( empty( $input['meta'] ) || ! \is_array( $input['meta'] ) ) {
				return array(
					'error' => array(
						'code'    => 'invalid_meta',
						'message' => 'Meta must be an object.',
					),
				);
			}

			$post_type         = $updated_post->post_type;
			$include_private   = false;
			$only_show_in_rest = true;
			$registered        = function_exists( 'get_registered_meta_keys' )
				? (array) \get_registered_meta_keys( 'post', $post_type )
				: array();

			foreach ( $input['meta'] as $key => $value ) {
				if ( ! is_string( $key ) ) {
					continue;
				}
				if ( ! $include_private && str_starts_with( $key, '_' ) ) {
					continue;
				}
				if ( ! array_key_exists( $key, $registered ) ) {
					continue;
				}
				$args = $registered[ $key ];

				$show_in_rest = false;
				if ( isset( $args['show_in_rest'] ) ) {
					$show_in_rest = is_bool( $args['show_in_rest'] ) ? (bool) $args['show_in_rest'] : true;
				}
				if ( $only_show_in_rest && ! $show_in_rest ) {
					continue;
				}

				if ( ! \current_user_can( 'edit_post_meta', $post_id, $key ) ) {
					continue;
				}

				$schema = null;
				if ( is_array( $args['show_in_rest'] ?? null ) && isset( $args['show_in_rest']['schema'] ) ) {
					$schema = $args['show_in_rest']['schema'];
				} elseif ( isset( $args['type'] ) ) {
					$schema = array( 'type' => (string) $args['type'] );
				}
				if ( $schema ) {
					$valid = \rest_validate_value_from_schema( $value, $schema );
					if ( \is_wp_error( $valid ) ) {
						return array(
							'error' => array(
								'code'    => $valid->get_error_code(),
								'message' => $valid->get_error_message(),
							),
						);
					}
				}

				$single = isset( $args['single'] ) ? (bool) $args['single'] : true;
				if ( $single ) {
					\update_post_meta( $post_id, $key, $value );
				} else {
					\delete_post_meta( $post_id, $key );
					$values = is_array( $value ) ? array_values( $value ) : array( $value );
					foreach ( $values as $meta_value ) {
						\add_post_meta( $post_id, $key, $meta_value, false );
					}
				}
			}
		}

		if ( $has_tax_input ) {
			$append               = array_key_exists( 'append_terms', $input ) ? (bool) $input['append_terms'] : true;
			$create_if_missing    = ! empty( $input['create_terms_if_missing'] );
			$supported_taxonomies = \get_object_taxonomies( $updated_post->post_type, 'names' );
			foreach ( $input['tax_input'] as $taxonomy => $terms_in ) {
				$taxonomy = \sanitize_key( (string) $taxonomy );
				if ( ! \taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				if ( ! \in_array( $taxonomy, $supported_taxonomies, true ) ) {
					continue;
				}
				$taxonomy_object = \get_taxonomy( $taxonomy );
				if ( ! $taxonomy_object ) {
					continue;
				}
				$assign_cap = $taxonomy_object->cap->assign_terms ?? 'assign_terms';
				if ( ! \current_user_can( $assign_cap ) ) {
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
					} elseif ( $create_if_missing ) {
						$manage_cap = $taxonomy_object->cap->manage_terms ?? 'manage_terms';
						if ( ! \current_user_can( $manage_cap ) ) {
							continue;
						}
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
	}

	private static function status_requires_publish_cap( string $status ): bool {
		return in_array( $status, array( 'publish', 'private', 'future' ), true );
	}
}
