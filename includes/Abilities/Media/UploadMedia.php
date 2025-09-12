<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Media;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UploadMedia implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/upload-media',
			array(
				'label'               => 'Upload Media',
				'description'         => 'Upload media files to WordPress media library from URL or base64 data.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'file_data' ),
					'properties' => array(
						'file_data' => array(
							'type'        => 'string',
							'description' => 'File data as base64 encoded string or URL to download from.',
						),
						'filename' => array(
							'type'        => 'string',
							'description' => 'Desired filename. If not provided, will be generated from URL or timestamp.',
						),
						'title' => array(
							'type'        => 'string',
							'description' => 'Media title. If not provided, will use filename.',
						),
						'caption' => array(
							'type'        => 'string',
							'description' => 'Media caption.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Media description.',
						),
						'alt_text' => array(
							'type'        => 'string',
							'description' => 'Alternative text for images.',
						),
						'post_parent' => array(
							'type'        => 'integer',
							'description' => 'ID of the post to attach this media to.',
						),
						'generate_thumbnails' => array(
							'type'        => 'boolean',
							'description' => 'Whether to generate image thumbnails. Default: true.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'attachment_id', 'message' ),
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'attachment_id' => array( 'type' => 'integer' ),
						'url'           => array( 'type' => 'string' ),
						'filename'      => array( 'type' => 'string' ),
						'file_type'     => array( 'type' => 'string' ),
						'file_size'     => array( 'type' => 'integer' ),
						'dimensions'    => array(
							'type'       => 'object',
							'properties' => array(
								'width'  => array( 'type' => 'integer' ),
								'height' => array( 'type' => 'integer' ),
							),
						),
						'thumbnails'    => array(
							'type'       => 'object',
							'properties' => array(
								'thumbnail' => array( 'type' => 'string' ),
								'medium'    => array( 'type' => 'string' ),
								'large'     => array( 'type' => 'string' ),
								'full'      => array( 'type' => 'string' ),
							),
						),
						'metadata'      => array(
							'type'       => 'object',
							'properties' => array(
								'title'       => array( 'type' => 'string' ),
								'caption'     => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'alt_text'    => array( 'type' => 'string' ),
							),
						),
						'message'       => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'media', 'uploads' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => true,
					),
				),
			)
		);
	}

	/**
	 * Check permission for uploading media.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'upload_files' );
	}

	/**
	 * Execute the upload media operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		try {
			$file_data = (string) $input['file_data'];
			$filename = $input['filename'] ?? '';
			$title = $input['title'] ?? '';
			$caption = $input['caption'] ?? '';
			$description = $input['description'] ?? '';
			$alt_text = $input['alt_text'] ?? '';
			$post_parent = (int) ( $input['post_parent'] ?? 0 );
			$generate_thumbnails = (bool) ( $input['generate_thumbnails'] ?? true );

		// Include necessary WordPress files
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$upload_dir = \wp_upload_dir();
		if ( $upload_dir['error'] ) {
			return array(
				'success'       => false,
				'attachment_id' => 0,
				'message'       => 'Upload directory error: ' . $upload_dir['error'],
			);
		}

		$file_content = '';
		$original_filename = '';

		// Handle different input types
		if ( filter_var( $file_data, FILTER_VALIDATE_URL ) ) {
			// Download from URL
			$response = \wp_remote_get( $file_data, array(
				'timeout' => 30,
				'sslverify' => false,
			) );

			if ( is_wp_error( $response ) ) {
				return array(
					'success'       => false,
					'attachment_id' => 0,
					'message'       => 'Failed to download file: ' . $response->get_error_message(),
				);
			}

			$file_content = \wp_remote_retrieve_body( $response );
			$original_filename = basename( parse_url( $file_data, PHP_URL_PATH ) );
			
			// Get content type from headers
			$content_type = \wp_remote_retrieve_header( $response, 'content-type' );
			if ( $content_type ) {
				$extension = self::get_extension_from_mime_type( $content_type );
				if ( $extension && ! pathinfo( $original_filename, PATHINFO_EXTENSION ) ) {
					$original_filename .= '.' . $extension;
				}
			}
		} else {
			// Assume base64 encoded data
			$file_content = base64_decode( $file_data );
			if ( $file_content === false ) {
				return array(
					'success'       => false,
					'attachment_id' => 0,
					'message'       => 'Invalid base64 data provided.',
				);
			}
		}

		if ( empty( $file_content ) ) {
			return array(
				'success'       => false,
				'attachment_id' => 0,
				'message'       => 'File content is empty.',
			);
		}

		// Determine filename
		if ( empty( $filename ) ) {
			$filename = $original_filename ?: 'upload_' . time();
		}

		// Ensure filename has extension
		if ( ! pathinfo( $filename, PATHINFO_EXTENSION ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_buffer( $finfo, $file_content );
			finfo_close( $finfo );
			
			$extension = self::get_extension_from_mime_type( $mime_type );
			if ( $extension ) {
				$filename .= '.' . $extension;
			}
		}

		// Sanitize filename
		$filename = \sanitize_file_name( $filename );

		// Create temporary file
		$temp_file = \wp_tempnam( $filename );
		if ( ! $temp_file ) {
			return array(
				'success'       => false,
				'attachment_id' => 0,
				'message'       => 'Failed to create temporary file.',
			);
		}

		// Write content to temporary file
		$bytes_written = file_put_contents( $temp_file, $file_content );
		if ( $bytes_written === false ) {
			unlink( $temp_file );
			return array(
				'success'       => false,
				'attachment_id' => 0,
				'message'       => 'Failed to write file content.',
			);
		}

		// Prepare file array for wp_handle_upload
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $temp_file,
			'size'     => $bytes_written,
		);

		// Get MIME type
		$file_type = \wp_check_filetype( $filename );
		$file_array['type'] = $file_type['type'];

		// Validate file type
		if ( ! $file_type['type'] ) {
			unlink( $temp_file );
			return array(
				'success'       => false,
				'attachment_id' => 0,
				'message'       => 'Invalid or unsupported file type.',
			);
		}

		// Use WordPress upload directory directly (similar to REST API approach)
		$upload_dir = \wp_upload_dir();
		$unique_filename = \wp_unique_filename( $upload_dir['path'], $filename );
		$upload_path = $upload_dir['path'] . '/' . $unique_filename;
		
		// Move file to upload directory
		$move_result = move_uploaded_file( $temp_file, $upload_path );
		if ( ! $move_result ) {
			// Fallback: copy the file if move fails
			$move_result = copy( $temp_file, $upload_path );
		}
		
		// Clean up temp file
		if ( file_exists( $temp_file ) ) {
			unlink( $temp_file );
		}

		if ( ! $move_result ) {
			return array(
				'success'       => false,
				'attachment_id' => 0,
				'message'       => 'Failed to move uploaded file to destination.',
			);
		}

		// Create upload result array
		$upload_result = array(
			'file' => $upload_path,
			'url'  => $upload_dir['url'] . '/' . $unique_filename,
			'type' => $file_type['type'],
		);

		// Create attachment post
		$attachment_data = array(
			'post_mime_type' => $upload_result['type'],
			'post_title'     => $title ?: \sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => $description,
			'post_excerpt'   => $caption,
			'post_status'    => 'inherit',
			'post_parent'    => $post_parent,
		);

		$attachment_id = \wp_insert_attachment( $attachment_data, $upload_result['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			// Clean up uploaded file
			if ( file_exists( $upload_result['file'] ) ) {
				unlink( $upload_result['file'] );
			}
			
			return array(
				'success'       => false,
				'attachment_id' => 0,
				'message'       => 'Failed to create attachment: ' . $attachment_id->get_error_message(),
			);
		}

		// Set alt text for images
		if ( $alt_text && strpos( $upload_result['type'], 'image/' ) === 0 ) {
			\update_post_meta( $attachment_id, '_wp_attachment_image_alt', \sanitize_text_field( $alt_text ) );
		}

		// Generate attachment metadata and thumbnails
		$metadata = array();
		$thumbnails = array();
		$dimensions = array();

		if ( $generate_thumbnails ) {
			$attachment_metadata = \wp_generate_attachment_metadata( $attachment_id, $upload_result['file'] );
			if ( $attachment_metadata ) {
				\wp_update_attachment_metadata( $attachment_id, $attachment_metadata );
				$metadata = $attachment_metadata;

				// Get image dimensions
				if ( isset( $attachment_metadata['width'] ) && isset( $attachment_metadata['height'] ) ) {
					$dimensions = array(
						'width'  => $attachment_metadata['width'],
						'height' => $attachment_metadata['height'],
					);
				}

				// Get thumbnail URLs
				$thumbnails = array(
					'full' => \wp_get_attachment_url( $attachment_id ),
				);

				$image_sizes = array( 'thumbnail', 'medium', 'large' );
				foreach ( $image_sizes as $size ) {
					$image_data = \wp_get_attachment_image_src( $attachment_id, $size );
					if ( $image_data ) {
						$thumbnails[ $size ] = $image_data[0];
					}
				}
			}
		}

		return array(
			'success'       => true,
			'attachment_id' => $attachment_id,
			'url'           => $upload_result['url'],
			'filename'      => basename( $upload_result['file'] ),
			'file_type'     => $upload_result['type'],
			'file_size'     => filesize( $upload_result['file'] ),
			'dimensions'    => $dimensions,
			'thumbnails'    => $thumbnails,
			'metadata'      => array(
				'title'       => $title,
				'caption'     => $caption,
				'description' => $description,
				'alt_text'    => $alt_text,
			),
			'message'       => 'Media uploaded successfully.',
		);

		} catch ( \Exception $e ) {
			return array(
				'success'       => false,
				'attachment_id' => 0,
				'message'       => 'Upload failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Get file extension from MIME type.
	 *
	 * @param string $mime_type MIME type.
	 * @return string File extension or empty string.
	 */
	private static function get_extension_from_mime_type( string $mime_type ): string {
		$mime_to_ext = array(
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
			'image/svg+xml' => 'svg',
			'application/pdf' => 'pdf',
			'text/plain' => 'txt',
			'application/zip' => 'zip',
			'video/mp4'  => 'mp4',
			'audio/mpeg' => 'mp3',
			'audio/wav'  => 'wav',
		);

		return $mime_to_ext[ $mime_type ] ?? '';
	}
}
