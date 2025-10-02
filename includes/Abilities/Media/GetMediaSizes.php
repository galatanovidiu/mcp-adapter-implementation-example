<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Media;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetMediaSizes implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-media-sizes',
			array(
				'label'               => 'Get Media Sizes',
				'description'         => 'Get available image sizes and their dimensions configured in WordPress.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_custom' => array(
							'type'        => 'boolean',
							'description' => 'Include custom image sizes added by themes/plugins.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'sizes' ),
					'properties' => array(
						'sizes' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'name', 'width', 'height', 'crop' ),
								'properties' => array(
									'name'        => array( 'type' => 'string' ),
									'width'       => array( 'type' => 'integer' ),
									'height'      => array( 'type' => 'integer' ),
									'crop'        => array( 'type' => 'boolean' ),
									'description' => array( 'type' => 'string' ),
									'is_default'  => array( 'type' => 'boolean' ),
								),
							),
						),
						'upload_limits' => array(
							'type'       => 'object',
							'properties' => array(
								'max_upload_size'    => array( 'type' => 'integer' ),
								'max_upload_size_mb' => array( 'type' => 'number' ),
								'allowed_mime_types' => array( 'type' => 'object' ),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'media', 'settings' ),
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

	/**
	 * Check permission for getting media sizes.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'upload_files' );
	}

	/**
	 * Execute the get media sizes operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$include_custom = array_key_exists( 'include_custom', $input ) ? (bool) $input['include_custom'] : true;

		$sizes = array();
		$default_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large' );

		// Get all registered image sizes
		$all_sizes = \wp_get_additional_image_sizes();
		
		// Add default WordPress sizes
		foreach ( $default_sizes as $size ) {
			$width = (int) \get_option( $size . '_size_w' );
			$height = (int) \get_option( $size . '_size_h' );
			$crop = (bool) \get_option( $size . '_crop' );

			$sizes[] = array(
				'name'        => $size,
				'width'       => $width,
				'height'      => $height,
				'crop'        => $crop,
				'description' => self::get_size_description( $size ),
				'is_default'  => true,
			);
		}

		// Add custom sizes if requested
		if ( $include_custom && ! empty( $all_sizes ) ) {
			foreach ( $all_sizes as $size_name => $size_data ) {
				$sizes[] = array(
					'name'        => $size_name,
					'width'       => (int) $size_data['width'],
					'height'      => (int) $size_data['height'],
					'crop'        => (bool) $size_data['crop'],
					'description' => self::get_size_description( $size_name ),
					'is_default'  => false,
				);
			}
		}

		// Add full size
		$sizes[] = array(
			'name'        => 'full',
			'width'       => 0, // No limit
			'height'      => 0, // No limit
			'crop'        => false,
			'description' => 'Original uploaded image size',
			'is_default'  => true,
		);

		// Get upload limits and allowed file types
		$max_upload_size = \wp_max_upload_size();
		$allowed_mime_types = \get_allowed_mime_types();

		$upload_limits = array(
			'max_upload_size'    => (int) $max_upload_size,
			'max_upload_size_mb' => round( $max_upload_size / ( 1024 * 1024 ), 2 ),
			'allowed_mime_types' => $allowed_mime_types,
		);

		return array(
			'sizes'         => $sizes,
			'upload_limits' => $upload_limits,
		);
	}

	/**
	 * Get a human-readable description for image sizes.
	 *
	 * @param string $size_name The size name.
	 * @return string Description of the size.
	 */
	private static function get_size_description( string $size_name ): string {
		$descriptions = array(
			'thumbnail'    => 'Small thumbnail image',
			'medium'       => 'Medium-sized image',
			'medium_large' => 'Medium-large sized image',
			'large'        => 'Large-sized image',
			'full'         => 'Original uploaded image',
		);

		return $descriptions[ $size_name ] ?? 'Custom image size';
	}
}
