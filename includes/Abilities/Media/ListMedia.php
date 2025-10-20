<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Media;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListMedia implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-media',
			array(
				'label'               => 'List Media',
				'description'         => 'List media library items with filtering, searching, and pagination options.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'mime_type'        => array(
							'type'        => 'string',
							'description' => 'Filter by MIME type (e.g., "image/jpeg", "image/*", "video/*").',
						),
						'search'           => array(
							'type'        => 'string',
							'description' => 'Search term to match against attachment title, content, or filename.',
						),
						'author'           => array(
							'type'        => 'integer',
							'description' => 'Filter by author user ID.',
						),
						'parent'           => array(
							'type'        => 'integer',
							'description' => 'Filter by parent post ID. Use 0 for unattached media.',
						),
						'date_query'       => array(
							'type'        => 'object',
							'description' => 'Date query parameters.',
							'properties'  => array(
								'after'  => array(
									'type'        => 'string',
									'description' => 'Media uploaded after this date (Y-m-d format).',
								),
								'before' => array(
									'type'        => 'string',
									'description' => 'Media uploaded before this date (Y-m-d format).',
								),
							),
						),
						'orderby'          => array(
							'type'        => 'string',
							'description' => 'Field to order results by.',
							'enum'        => array( 'date', 'title', 'name', 'modified', 'menu_order', 'rand', 'ID' ),
							'default'     => 'date',
						),
						'order'            => array(
							'type'        => 'string',
							'description' => 'Sort order.',
							'enum'        => array( 'ASC', 'DESC' ),
							'default'     => 'DESC',
						),
						'limit'            => array(
							'type'        => 'integer',
							'description' => 'Maximum number of media items to return.',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'offset'           => array(
							'type'        => 'integer',
							'description' => 'Number of media items to skip (for pagination).',
							'default'     => 0,
							'minimum'     => 0,
						),
						'include_metadata' => array(
							'type'        => 'boolean',
							'description' => 'Include attachment metadata in results.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'media', 'total' ),
					'properties' => array(
						'media' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'id', 'title', 'filename', 'url', 'mime_type', 'file_size' ),
								'properties' => array(
									'id'          => array( 'type' => 'integer' ),
									'title'       => array( 'type' => 'string' ),
									'filename'    => array( 'type' => 'string' ),
									'url'         => array( 'type' => 'string' ),
									'mime_type'   => array( 'type' => 'string' ),
									'file_size'   => array( 'type' => 'integer' ),
									'width'       => array( 'type' => 'integer' ),
									'height'      => array( 'type' => 'integer' ),
									'alt_text'    => array( 'type' => 'string' ),
									'caption'     => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'date'        => array( 'type' => 'string' ),
									'modified'    => array( 'type' => 'string' ),
									'author'      => array( 'type' => 'integer' ),
									'parent'      => array( 'type' => 'integer' ),
									'sizes'       => array( 'type' => 'object' ),
									'metadata'    => array( 'type' => 'object' ),
								),
							),
						),
						'total' => array(
							'type'        => 'integer',
							'description' => 'Total number of media items matching the query',
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'media',
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
	 * Check permission for listing media.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'upload_files' );
	}

	/**
	 * Execute the list media operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		// Build WP_Query arguments
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => isset( $input['limit'] ) ? max( 1, min( 100, (int) $input['limit'] ) ) : 20,
			'offset'         => isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0,
			'orderby'        => isset( $input['orderby'] ) ? \sanitize_key( (string) $input['orderby'] ) : 'date',
			'order'          => isset( $input['order'] ) ? \sanitize_key( (string) $input['order'] ) : 'DESC',
			'no_found_rows'  => false, // We need found_posts for pagination
		);

		// Add MIME type filter
		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = \sanitize_mime_type( (string) $input['mime_type'] );
		}

		// Add search filter
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = \sanitize_text_field( (string) $input['search'] );
		}

		// Add author filter
		if ( ! empty( $input['author'] ) ) {
			$args['author'] = (int) $input['author'];
		}

		// Add parent filter
		if ( array_key_exists( 'parent', $input ) ) {
			$args['post_parent'] = (int) $input['parent'];
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

			if ( ! empty( $date_query ) ) {
				$args['date_query'] = array( $date_query );
			}
		}

		// Execute query
		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return array(
				'media' => array(),
				'total' => 0,
			);
		}

		$include_metadata = array_key_exists( 'include_metadata', $input ) ? (bool) $input['include_metadata'] : true;
		$media_items      = array();

		while ( $query->have_posts() ) {
			$query->the_post();
			$attachment = \get_post();
			if ( ! $attachment ) {
				continue;
			}

			$attachment_id = $attachment->ID;
			$metadata      = \wp_get_attachment_metadata( $attachment_id );
			$file_path     = \get_attached_file( $attachment_id );
			$file_size     = $file_path && \file_exists( $file_path ) ? \filesize( $file_path ) : 0;

			$media_item = array(
				'id'          => $attachment_id,
				'title'       => $attachment->post_title,
				'filename'    => \basename( $file_path ?: '' ),
				'url'         => \wp_get_attachment_url( $attachment_id ),
				'mime_type'   => $attachment->post_mime_type,
				'file_size'   => (int) $file_size,
				'alt_text'    => \get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'caption'     => $attachment->post_excerpt,
				'description' => $attachment->post_content,
				'date'        => $attachment->post_date,
				'modified'    => $attachment->post_modified,
				'author'      => (int) $attachment->post_author,
				'parent'      => (int) $attachment->post_parent,
			);

			// Add image dimensions if available
			if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
				$media_item['width']  = (int) $metadata['width'];
				$media_item['height'] = (int) $metadata['height'];
			}

			// Add image sizes if available
			if ( \wp_attachment_is_image( $attachment_id ) ) {
				$sizes       = array();
				$image_sizes = \get_intermediate_image_sizes();

				foreach ( $image_sizes as $size ) {
					$image_data = \wp_get_attachment_image_src( $attachment_id, $size );
					if ( ! $image_data ) {
						continue;
					}

					$sizes[ $size ] = array(
						'url'    => $image_data[0],
						'width'  => (int) $image_data[1],
						'height' => (int) $image_data[2],
					);
				}

				$media_item['sizes'] = $sizes;
			}

			// Include metadata if requested
			if ( $include_metadata && $metadata ) {
				$media_item['metadata'] = $metadata;
			}

			$media_items[] = $media_item;
		}

		// Reset global post data
		\wp_reset_postdata();

		return array(
			'media' => $media_items,
			'total' => (int) $query->found_posts,
		);
	}
}
