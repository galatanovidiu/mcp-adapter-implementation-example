<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Media;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetAttachment implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-attachment',
			array(
				'label'               => 'Get Attachment',
				'description'         => 'Retrieve detailed information about a specific media attachment.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'id' ),
					'properties' => array(
						'id'               => array(
							'type'        => 'integer',
							'description' => 'Attachment ID.',
						),
						'include_metadata' => array(
							'type'        => 'boolean',
							'description' => 'Include detailed attachment metadata.',
							'default'     => true,
						),
						'include_exif'     => array(
							'type'        => 'boolean',
							'description' => 'Include EXIF data for images (if available).',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'id', 'title', 'filename', 'url', 'mime_type' ),
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
						'file_path'   => array( 'type' => 'string' ),
						'sizes'       => array( 'type' => 'object' ),
						'metadata'    => array( 'type' => 'object' ),
						'exif'        => array( 'type' => 'object' ),
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
	 * Check permission for getting attachment details.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'upload_files' );
	}

	/**
	 * Execute the get attachment operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$attachment_id = (int) $input['id'];

		// Check if attachment exists
		$attachment = \get_post( $attachment_id );
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return new \WP_Error( 'attachment_not_found', 'Attachment not found.' );
		}

		$include_metadata = array_key_exists( 'include_metadata', $input ) ? (bool) $input['include_metadata'] : true;
		$include_exif     = ! empty( $input['include_exif'] );

		$file_path = \get_attached_file( $attachment_id );
		$file_size = $file_path && \file_exists( $file_path ) ? \filesize( $file_path ) : 0;
		$metadata  = \wp_get_attachment_metadata( $attachment_id );

		$attachment_data = array(
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
			'file_path'   => $file_path ?: '',
		);

		// Add image dimensions if available
		if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
			$attachment_data['width']  = (int) $metadata['width'];
			$attachment_data['height'] = (int) $metadata['height'];
		}

		// Add image sizes if it's an image
		if ( \wp_attachment_is_image( $attachment_id ) ) {
			$sizes       = array();
			$image_sizes = \get_intermediate_image_sizes();

			// Add full size
			$full_image = \wp_get_attachment_image_src( $attachment_id, 'full' );
			if ( $full_image ) {
				$sizes['full'] = array(
					'url'    => $full_image[0],
					'width'  => (int) $full_image[1],
					'height' => (int) $full_image[2],
				);
			}

			// Add other sizes
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

			$attachment_data['sizes'] = $sizes;
		}

		// Include metadata if requested
		if ( $include_metadata && $metadata ) {
			$attachment_data['metadata'] = $metadata;
		}

		// Include EXIF data if requested and available
		if ( $include_exif && \wp_attachment_is_image( $attachment_id ) && $file_path && \file_exists( $file_path ) ) {
			$exif_data = array();

			if ( \function_exists( 'exif_read_data' ) ) {
				$exif = @\exif_read_data( $file_path );
				if ( $exif && \is_array( $exif ) ) {
					// Filter out binary data and keep only useful EXIF data
					$useful_exif  = array();
					$allowed_keys = array(
						'DateTime',
						'DateTimeOriginal',
						'DateTimeDigitized',
						'Make',
						'Model',
						'Software',
						'ExposureTime',
						'FNumber',
						'ISO',
						'ISOSpeedRatings',
						'FocalLength',
						'Flash',
						'WhiteBalance',
						'Orientation',
						'XResolution',
						'YResolution',
						'GPS',
						'Artist',
						'Copyright',
					);

					foreach ( $allowed_keys as $key ) {
						if ( ! isset( $exif[ $key ] ) || \is_array( $exif[ $key ] ) ) {
							continue;
						}

						$useful_exif[ $key ] = $exif[ $key ];
					}

					$exif_data = $useful_exif;
				}
			}

			$attachment_data['exif'] = $exif_data;
		}

		return $attachment_data;
	}
}
