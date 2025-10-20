<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Posts;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListPosts implements RegistersAbility {

	public static function register(): void {
		$available_post_types    = array_values( (array) \get_post_types( array( 'public' => true ), 'names' ) );
		$available_post_types[]  = 'any'; // Special value to search all public post types
		$available_post_statuses = array_keys(
			\get_post_stati(
				array(
					'public'  => true,
					'private' => true,
				)
			)
		);

		// Ensure we always have at least some basic post statuses
		if ( empty( $available_post_statuses ) ) {
			$available_post_statuses = array( 'publish', 'draft', 'private', 'pending', 'future' );
		}

		\wp_register_ability(
			'core/list-posts',
			array(
				'label'               => 'List Posts',
				'description'         => 'List and search WordPress posts with various filters including search terms, post type, status, taxonomy filters, meta queries, and pagination.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'             => array(
							'type'        => 'string',
							'description' => 'Search term to match against post title and content.',
						),
						'post_type'          => array(
							'type'        => 'array',
							'description' => 'Post type(s) to filter by. Use "any" to search across all public post types.',
							'items'       => array(
								'type' => 'string',
								'enum' => $available_post_types,
							),
							'default'     => array( 'post' ),
						),
						'post_status'        => array(
							'type'        => 'array',
							'description' => 'Post status(es) to filter by.',
							'items'       => array(
								'type' => 'string',
								'enum' => $available_post_statuses,
							),
							'default'     => array( 'publish' ),
						),
						'author'             => array(
							'type'        => 'integer',
							'description' => 'Author user ID to filter by.',
						),
						'limit'              => array(
							'type'        => 'integer',
							'description' => 'Maximum number of posts to return.',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'offset'             => array(
							'type'        => 'integer',
							'description' => 'Number of posts to skip (for pagination).',
							'default'     => 0,
							'minimum'     => 0,
						),
						'orderby'            => array(
							'type'        => 'string',
							'description' => 'Field to order results by.',
							'enum'        => array( 'date', 'title', 'menu_order', 'rand', 'ID', 'author', 'name', 'modified', 'comment_count' ),
							'default'     => 'date',
						),
						'order'              => array(
							'type'        => 'string',
							'description' => 'Sort order.',
							'enum'        => array( 'ASC', 'DESC' ),
							'default'     => 'DESC',
						),
						'date_query'         => array(
							'type'        => 'object',
							'description' => 'Date query parameters.',
							'properties'  => array(
								'after'  => array(
									'type'        => 'string',
									'description' => 'Posts published after this date (Y-m-d format).',
								),
								'before' => array(
									'type'        => 'string',
									'description' => 'Posts published before this date (Y-m-d format).',
								),
								'year'   => array(
									'type'        => 'integer',
									'description' => 'Posts from this year.',
								),
								'month'  => array(
									'type'        => 'integer',
									'description' => 'Posts from this month (1-12).',
									'minimum'     => 1,
									'maximum'     => 12,
								),
							),
						),
						'meta_query'         => array(
							'type'        => 'array',
							'description' => 'Meta query conditions.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'key'     => array(
										'type'        => 'string',
										'description' => 'Meta key to query.',
									),
									'value'   => array(
										'type'        => 'string',
										'description' => 'Meta value to match.',
									),
									'compare' => array(
										'type'        => 'string',
										'description' => 'Comparison operator.',
										'enum'        => array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' ),
										'default'     => '=',
									),
								),
								'required'   => array( 'key' ),
							),
						),
						'tax_query'          => array(
							'type'        => 'array',
							'description' => 'Taxonomy query conditions.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'taxonomy' => array(
										'type'        => 'string',
										'description' => 'Taxonomy name.',
									),
									'field'    => array(
										'type'        => 'string',
										'description' => 'Field to match against.',
										'enum'        => array( 'term_id', 'name', 'slug', 'term_taxonomy_id' ),
										'default'     => 'term_id',
									),
									'terms'    => array(
										'type'        => 'array',
										'description' => 'Terms to match.',
										'items'       => array(
											'oneOf' => array(
												array( 'type' => 'string' ),
												array( 'type' => 'integer' ),
											),
										),
									),
									'operator' => array(
										'type'        => 'string',
										'description' => 'Operator for matching terms.',
										'enum'        => array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' ),
										'default'     => 'IN',
									),
								),
								'required'   => array( 'taxonomy', 'terms' ),
							),
						),
						'include_meta'       => array(
							'type'        => 'boolean',
							'description' => 'Include post meta in results.',
							'default'     => false,
						),
						'include_taxonomies' => array(
							'type'        => 'boolean',
							'description' => 'Include taxonomy terms in results.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'posts', 'total', 'found_posts' ),
					'properties' => array(
						'posts'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'id', 'post_type', 'status', 'title', 'link', 'date' ),
								'properties' => array(
									'id'         => array( 'type' => 'integer' ),
									'post_type'  => array( 'type' => 'string' ),
									'status'     => array( 'type' => 'string' ),
									'title'      => array( 'type' => 'string' ),
									'content'    => array( 'type' => 'string' ),
									'excerpt'    => array( 'type' => 'string' ),
									'link'       => array( 'type' => 'string' ),
									'date'       => array( 'type' => 'string' ),
									'modified'   => array( 'type' => 'string' ),
									'author'     => array( 'type' => 'integer' ),
									'slug'       => array( 'type' => 'string' ),

									'meta'       => array( 'type' => 'object' ),
									'taxonomies' => array( 'type' => 'object' ),
								),
							),
						),
						'total'       => array(
							'type'        => 'integer',
							'description' => 'Number of posts returned in this request',
						),
						'found_posts' => array(
							'type'        => 'integer',
							'description' => 'Total number of posts matching the query',
						),
						'max_pages'   => array(
							'type'        => 'integer',
							'description' => 'Maximum number of pages available',
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'content',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.9,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for listing posts.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		// Check if user can read posts for the requested post types
		$post_types = isset( $input['post_type'] ) ? (array) $input['post_type'] : array( 'post' );

		// Handle 'any' post type - expand to all public post types
		if ( in_array( 'any', $post_types, true ) ) {
			$post_types = array_values( (array) \get_post_types( array( 'public' => true ), 'names' ) );
		}

		foreach ( $post_types as $post_type ) {
			$post_type = \sanitize_key( (string) $post_type );
			if ( ! \post_type_exists( $post_type ) ) {
				continue;
			}
			$pto = \get_post_type_object( $post_type );
			if ( ! $pto ) {
				continue;
			}
			$cap = $pto->cap->read ?? 'read';
			if ( ! \current_user_can( $cap ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Execute the list posts operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		// Handle post types - expand 'any' to all public post types
		$post_types = isset( $input['post_type'] ) ? (array) $input['post_type'] : array( 'post' );
		if ( in_array( 'any', $post_types, true ) ) {
			$post_types = array_values( (array) \get_post_types( array( 'public' => true ), 'names' ) );
		}

		// Build WP_Query arguments
		$args = array(
			'post_type'      => $post_types,
			'post_status'    => isset( $input['post_status'] ) ? (array) $input['post_status'] : array( 'publish' ),
			'posts_per_page' => isset( $input['limit'] ) ? (int) $input['limit'] : 10,
			'offset'         => isset( $input['offset'] ) ? (int) $input['offset'] : 0,
			'orderby'        => isset( $input['orderby'] ) ? \sanitize_key( (string) $input['orderby'] ) : 'date',
			'order'          => isset( $input['order'] ) ? \sanitize_key( (string) $input['order'] ) : 'DESC',
			'no_found_rows'  => false, // We need found_posts for pagination
		);

		// Add search term
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = \sanitize_text_field( (string) $input['search'] );
		}

		// Add author filter
		if ( ! empty( $input['author'] ) ) {
			$args['author'] = (int) $input['author'];
		}

		// Add date query
		if ( ! empty( $input['date_query'] ) && \is_array( $input['date_query'] ) ) {
			$date_query = array();
			$date_input = $input['date_query'];

			if ( ! empty( $date_input['after'] ) ) {
				$date_query['after'] = \sanitize_text_field( (string) $date_input['after'] );
			}
			if ( ! empty( $date_input['before'] ) ) {
				$date_query['before'] = \sanitize_text_field( (string) $date_input['before'] );
			}
			if ( ! empty( $date_input['year'] ) ) {
				$date_query['year'] = (int) $date_input['year'];
			}
			if ( ! empty( $date_input['month'] ) ) {
				$date_query['month'] = (int) $date_input['month'];
			}

			if ( ! empty( $date_query ) ) {
				$args['date_query'] = array( $date_query );
			}
		}

		// Add meta query
		if ( ! empty( $input['meta_query'] ) && \is_array( $input['meta_query'] ) ) {
			$meta_query = array();
			foreach ( $input['meta_query'] as $meta_condition ) {
				if ( ! \is_array( $meta_condition ) || empty( $meta_condition['key'] ) ) {
					continue;
				}

				$condition = array(
					'key'     => \sanitize_key( (string) $meta_condition['key'] ),
					'compare' => isset( $meta_condition['compare'] ) ? \sanitize_key( (string) $meta_condition['compare'] ) : '=',
				);

				if ( isset( $meta_condition['value'] ) ) {
					$condition['value'] = \sanitize_text_field( (string) $meta_condition['value'] );
				}

				$meta_query[] = $condition;
			}

			if ( ! empty( $meta_query ) ) {
				$args['meta_query'] = $meta_query;
			}
		}

		// Add taxonomy query
		if ( ! empty( $input['tax_query'] ) && \is_array( $input['tax_query'] ) ) {
			$tax_query = array();
			foreach ( $input['tax_query'] as $tax_condition ) {
				if ( ! \is_array( $tax_condition ) || empty( $tax_condition['taxonomy'] ) || empty( $tax_condition['terms'] ) ) {
					continue;
				}

				$taxonomy = \sanitize_key( (string) $tax_condition['taxonomy'] );
				if ( ! \taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$condition = array(
					'taxonomy' => $taxonomy,
					'field'    => isset( $tax_condition['field'] ) ? \sanitize_key( (string) $tax_condition['field'] ) : 'term_id',
					'terms'    => \is_array( $tax_condition['terms'] ) ? $tax_condition['terms'] : array( $tax_condition['terms'] ),
					'operator' => isset( $tax_condition['operator'] ) ? \sanitize_key( (string) $tax_condition['operator'] ) : 'IN',
				);

				$tax_query[] = $condition;
			}

			if ( ! empty( $tax_query ) ) {
				$args['tax_query'] = $tax_query;
			}
		}

		// Execute query
		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return array(
				'posts'       => array(),
				'total'       => 0,
				'found_posts' => 0,
				'max_pages'   => 0,
			);
		}

		$include_meta       = ! empty( $input['include_meta'] );
		$include_taxonomies = ! empty( $input['include_taxonomies'] );

		$posts = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$post = \get_post();
			if ( ! $post ) {
				continue;
			}

			$post_data = array(
				'id'        => $post->ID,
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'title'     => (string) $post->post_title,
				'content'   => (string) $post->post_content,
				'excerpt'   => (string) $post->post_excerpt,
				'link'      => (string) \get_permalink( $post->ID ),
				'date'      => $post->post_date,
				'modified'  => $post->post_modified,
				'author'    => (int) $post->post_author,
				'slug'      => $post->post_name,
			);

			// Include meta if requested
			if ( $include_meta ) {
				$post_data['meta'] = \get_post_meta( $post->ID );
			}

			// Include taxonomies if requested
			if ( $include_taxonomies ) {
				$tax_map              = array();
				$supported_taxonomies = \get_object_taxonomies( $post->post_type, 'names' );
				foreach ( $supported_taxonomies as $tax ) {
					$terms           = \wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'all' ) );
					$tax_map[ $tax ] = array();
					if ( \is_wp_error( $terms ) ) {
						continue;
					}
					foreach ( $terms as $t ) {
						if ( ! ( $t instanceof \WP_Term ) ) {
							continue;
						}

						$tax_map[ $tax ][] = array(
							'id'     => (int) $t->term_id,
							'name'   => (string) $t->name,
							'slug'   => (string) $t->slug,
							'parent' => (int) $t->parent,
						);
					}
				}
				$post_data['taxonomies'] = $tax_map;
			}

			$posts[] = $post_data;
		}

		// Reset global post data
		\wp_reset_postdata();

		return array(
			'posts'       => $posts,
			'total'       => count( $posts ),
			'found_posts' => (int) $query->found_posts,
			'max_pages'   => (int) $query->max_num_pages,
		);
	}
}
