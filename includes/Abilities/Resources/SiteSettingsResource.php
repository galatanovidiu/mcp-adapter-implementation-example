<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Resources;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class SiteSettingsResource implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'resources/site-settings',
			array(
				'label'               => 'Site Settings Resource',
				'description'         => 'Access WordPress site settings as a resource',
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'settings',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'resource',
					),
					'uri'         => 'wordpress://settings',
					'mimeType'    => 'application/json',
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for reading site settings resource.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'read' );
	}

	/**
	 * Execute the site settings resource retrieval.
	 *
	 * @param array $input Input parameters.
	 * @return array|\\WP_Error Resource content or error.
	 */
	public static function execute( array $input ) {
		$settings = array(
			'blogname'        => \get_option( 'blogname' ),
			'blogdescription' => \get_option( 'blogdescription' ),
			'siteurl'         => \get_option( 'siteurl' ),
			'home'            => \get_option( 'home' ),
			'admin_email'     => \get_option( 'admin_email' ),
			'timezone'        => \get_option( 'timezone_string' ),
			'date_format'     => \get_option( 'date_format' ),
			'time_format'     => \get_option( 'time_format' ),
			'language'        => \get_option( 'WPLANG' ),
			'users_can_register' => (bool) \get_option( 'users_can_register' ),
			'posts_per_page'  => (int) \get_option( 'posts_per_page' ),
		);

		return array(
			'uri'      => 'wordpress://settings',
			'mimeType' => 'application/json',
			'text'     => \wp_json_encode( $settings, JSON_PRETTY_PRINT ),
		);
	}
}
