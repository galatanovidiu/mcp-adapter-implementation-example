<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Media;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GenerateImageSizes implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/generate-image-sizes',
			array(
				'label'               => 'Generate Image Sizes',
				'description'         => 'Regenerate image thumbnails and sizes for existing attachments.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'attachment_id'    => array(
							'type'        => 'integer',
							'description' => 'Specific attachment ID to regenerate. If not provided, will process multiple attachments.',
						),
						'attachment_ids'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of attachment IDs to regenerate.',
						),
						'image_sizes'      => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Specific image sizes to generate. If not provided, generates all registered sizes.',
						),
						'force_regenerate' => array(
							'type'        => 'boolean',
							'description' => 'Force regeneration even if thumbnails already exist. Default: false.',
							'default'     => false,
						),
						'only_missing'     => array(
							'type'        => 'boolean',
							'description' => 'Only generate missing thumbnails. Default: true.',
							'default'     => true,
						),
						'limit'            => array(
							'type'        => 'integer',
							'description' => 'Maximum number of attachments to process in one request. Default: 10.',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 50,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'processed_count' ),
					'properties' => array(
						'success'         => array( 'type' => 'boolean' ),
						'processed_count' => array( 'type' => 'integer' ),
						'results'         => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'attachment_id'   => array( 'type' => 'integer' ),
									'filename'        => array( 'type' => 'string' ),
									'success'         => array( 'type' => 'boolean' ),
									'generated_sizes' => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'skipped_sizes'   => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'error'           => array( 'type' => 'string' ),
									'thumbnails'      => array(
										'type'       => 'object',
										'properties' => array(
											'thumbnail' => array( 'type' => 'string' ),
											'medium'    => array( 'type' => 'string' ),
											'large'     => array( 'type' => 'string' ),
											'full'      => array( 'type' => 'string' ),
										),
									),
								),
							),
						),
						'message'         => array( 'type' => 'string' ),
						'available_sizes' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
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
						'priority'        => 0.6,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for generating image sizes.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'upload_files' );
	}

	/**
	 * Execute the generate image sizes operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$attachment_id    = $input['attachment_id'] ?? 0;
		$attachment_ids   = $input['attachment_ids'] ?? array();
		$requested_sizes  = $input['image_sizes'] ?? array();
		$force_regenerate = (bool) ( $input['force_regenerate'] ?? false );
		$only_missing     = (bool) ( $input['only_missing'] ?? true );
		$limit            = (int) ( $input['limit'] ?? 10 );

		// Include necessary WordPress files
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Get available image sizes
		$available_sizes = array_keys( \wp_get_additional_image_sizes() );
		$available_sizes = array_merge( $available_sizes, array( 'thumbnail', 'medium', 'medium_large', 'large' ) );
		$available_sizes = array_unique( $available_sizes );

		// Validate requested sizes
		if ( ! empty( $requested_sizes ) ) {
			$invalid_sizes = array_diff( $requested_sizes, $available_sizes );
			if ( ! empty( $invalid_sizes ) ) {
				return array(
					'error' => array(
						'code'            => 'invalid_image_sizes',
						'message'         => 'Invalid image sizes: ' . implode( ', ', $invalid_sizes ),
						'available_sizes' => $available_sizes,
					),
				);
			}
		}

		// Determine which attachments to process
		$attachments_to_process = array();

		if ( $attachment_id > 0 ) {
			$attachments_to_process = array( $attachment_id );
		} elseif ( ! empty( $attachment_ids ) ) {
			$attachments_to_process = array_slice( array_unique( array_map( 'intval', $attachment_ids ) ), 0, $limit );
		} else {
			// Get recent image attachments if no specific IDs provided
			$query_args = array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			);

			$attachments_to_process = \get_posts( $query_args );
		}

		if ( empty( $attachments_to_process ) ) {
			return array(
				'error' => array(
					'code'    => 'no_attachments',
					'message' => 'No attachments found to process.',
				),
			);
		}

		$results         = array();
		$processed_count = 0;

		foreach ( $attachments_to_process as $attachment_id ) {
			$attachment_id = (int) $attachment_id;

			// Verify attachment exists and is an image
			$attachment = \get_post( $attachment_id );
			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				$results[] = array(
					'attachment_id'   => $attachment_id,
					'filename'        => '',
					'success'         => false,
					'error'           => 'Attachment not found.',
					'generated_sizes' => array(),
					'skipped_sizes'   => array(),
					'thumbnails'      => array(),
				);
				continue;
			}

			$mime_type = \get_post_mime_type( $attachment_id );
			if ( strpos( $mime_type, 'image/' ) !== 0 ) {
				$results[] = array(
					'attachment_id'   => $attachment_id,
					'filename'        => basename( \get_attached_file( $attachment_id ) ),
					'success'         => false,
					'error'           => 'Attachment is not an image.',
					'generated_sizes' => array(),
					'skipped_sizes'   => array(),
					'thumbnails'      => array(),
				);
				continue;
			}

			$file_path = \get_attached_file( $attachment_id );
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$results[] = array(
					'attachment_id'   => $attachment_id,
					'filename'        => basename( $file_path ?: '' ),
					'success'         => false,
					'error'           => 'Original file not found.',
					'generated_sizes' => array(),
					'skipped_sizes'   => array(),
					'thumbnails'      => array(),
				);
				continue;
			}

			$filename        = basename( $file_path );
			$generated_sizes = array();
			$skipped_sizes   = array();

			try {
				// Get current metadata
				$current_metadata = \wp_get_attachment_metadata( $attachment_id );
				$existing_sizes   = isset( $current_metadata['sizes'] ) ? array_keys( $current_metadata['sizes'] ) : array();

				// Determine which sizes to generate
				$sizes_to_generate = empty( $requested_sizes ) ? $available_sizes : $requested_sizes;

				// Generate new metadata
				if ( $force_regenerate || empty( $current_metadata ) ) {
					// Full regeneration
					$new_metadata = \wp_generate_attachment_metadata( $attachment_id, $file_path );
					if ( $new_metadata ) {
						\wp_update_attachment_metadata( $attachment_id, $new_metadata );
						$generated_sizes = isset( $new_metadata['sizes'] ) ? array_keys( $new_metadata['sizes'] ) : array();
					}
				} else {
					// Selective generation
					foreach ( $sizes_to_generate as $size ) {
						$should_generate = false;

						if ( $only_missing ) {
							// Only generate if missing
							$should_generate = ! in_array( $size, $existing_sizes, true );
						} else {
							// Generate all requested sizes
							$should_generate = true;
						}

						if ( $should_generate ) {
							// Get size dimensions
							$size_data = \wp_get_additional_image_sizes();
							$width     = null;
							$height    = null;

							if ( isset( $size_data[ $size ] ) ) {
								$width  = $size_data[ $size ]['width'];
								$height = $size_data[ $size ]['height'];
							} else {
								// Handle built-in sizes
								switch ( $size ) {
									case 'thumbnail':
										$width  = (int) \get_option( 'thumbnail_size_w' );
										$height = (int) \get_option( 'thumbnail_size_h' );
										break;
									case 'medium':
										$width  = (int) \get_option( 'medium_size_w' );
										$height = (int) \get_option( 'medium_size_h' );
										break;
									case 'large':
										$width  = (int) \get_option( 'large_size_w' );
										$height = (int) \get_option( 'large_size_h' );
										break;
									case 'medium_large':
										$width  = (int) \get_option( 'medium_large_size_w' );
										$height = (int) \get_option( 'medium_large_size_h' );
										break;
								}
							}

							if ( $width && $height ) {
								$resized = \image_make_intermediate_size( $file_path, $width, $height, true );
								if ( $resized ) {
									$generated_sizes[] = $size;

									// Update metadata with new size
									if ( ! isset( $current_metadata['sizes'] ) ) {
										$current_metadata['sizes'] = array();
									}
									$current_metadata['sizes'][ $size ] = $resized;
								}
							} else {
								$skipped_sizes[] = $size;
							}
						} else {
							$skipped_sizes[] = $size;
						}
					}

					// Update metadata if we generated new sizes
					if ( ! empty( $generated_sizes ) ) {
						\wp_update_attachment_metadata( $attachment_id, $current_metadata );
					}
				}

				// Get thumbnail URLs
				$thumbnails = array(
					'full' => \wp_get_attachment_url( $attachment_id ),
				);

				$standard_sizes = array( 'thumbnail', 'medium', 'large' );
				foreach ( $standard_sizes as $size ) {
					$image_data = \wp_get_attachment_image_src( $attachment_id, $size );
					if ( ! $image_data ) {
						continue;
					}

					$thumbnails[ $size ] = $image_data[0];
				}

				$results[] = array(
					'attachment_id'   => $attachment_id,
					'filename'        => $filename,
					'success'         => true,
					'generated_sizes' => $generated_sizes,
					'skipped_sizes'   => $skipped_sizes,
					'error'           => '',
					'thumbnails'      => $thumbnails,
				);

				++$processed_count;
			} catch ( \Throwable $e ) {
				$results[] = array(
					'attachment_id'   => $attachment_id,
					'filename'        => $filename,
					'success'         => false,
					'error'           => 'Generation failed: ' . $e->getMessage(),
					'generated_sizes' => array(),
					'skipped_sizes'   => array(),
					'thumbnails'      => array(),
				);
			}
		}

		$success_count = count(
			array_filter(
				$results,
				static function ( $result ) {
					return $result['success'];
				}
			)
		);

		return array(
			'success'         => $success_count > 0,
			'processed_count' => $processed_count,
			'results'         => $results,
			'message'         => sprintf(
				'Processed %d attachments. %d successful, %d failed.',
				count( $results ),
				$success_count,
				count( $results ) - $success_count
			),
			'available_sizes' => $available_sizes,
		);
	}
}
