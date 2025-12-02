<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListThemes implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-themes',
			array(
				'label'               => 'List Themes',
				'description'         => 'List all installed WordPress themes with their details.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_inactive' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include inactive themes. Default: true.',
							'default'     => true,
						),
						'include_broken'   => array(
							'type'        => 'boolean',
							'description' => 'Whether to include broken themes. Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'themes', 'active_theme' ),
					'properties' => array(
						'themes'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'stylesheet', 'name', 'version' ),
								'properties' => array(
									'stylesheet'       => array( 'type' => 'string' ),
									'template'         => array( 'type' => 'string' ),
									'name'             => array( 'type' => 'string' ),
									'version'          => array( 'type' => 'string' ),
									'description'      => array( 'type' => 'string' ),
									'author'           => array( 'type' => 'string' ),
									'author_uri'       => array( 'type' => 'string' ),
									'theme_uri'        => array( 'type' => 'string' ),
									'text_domain'      => array( 'type' => 'string' ),
									'domain_path'      => array( 'type' => 'string' ),
									'requires_wp'      => array( 'type' => 'string' ),
									'requires_php'     => array( 'type' => 'string' ),
									'tested_up_to'     => array( 'type' => 'string' ),
									'is_active'        => array( 'type' => 'boolean' ),
									'is_child_theme'   => array( 'type' => 'boolean' ),
									'parent_theme'     => array( 'type' => 'string' ),
									'screenshot'       => array( 'type' => 'string' ),
									'tags'             => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'update_available' => array( 'type' => 'boolean' ),
									'new_version'      => array( 'type' => 'string' ),
									'is_allowed'       => array( 'type' => 'boolean' ),
									'is_broken'        => array( 'type' => 'boolean' ),
									'errors'           => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
								),
							),
						),
						'active_theme' => array( 'type' => 'string' ),
						'total_count'  => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'appearance',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
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
	 * Check permission for listing themes.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'switch_themes' );
	}

	/**
	 * Execute the list themes operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$include_inactive = $input['include_inactive'] ?? true;
		$include_broken   = $input['include_broken'] ?? false;

		// Get all themes
		$all_themes   = \wp_get_themes( array( 'allowed' => null ) );
		$active_theme = \get_stylesheet();
		$themes_data  = array();

		// Get update information
		$update_themes = \get_site_transient( 'update_themes' );

		foreach ( $all_themes as $stylesheet => $theme ) {
			// Skip inactive themes if not requested
			if ( ! $include_inactive && $stylesheet !== $active_theme ) {
				continue;
			}

			// Check if theme is broken
			$is_broken = ! $theme->exists() || $theme->errors();
			if ( ! $include_broken && $is_broken ) {
				continue;
			}

			// Check if theme is allowed (for multisite)
			$is_allowed = true;
			if ( \is_multisite() ) {
				$allowed_themes = \get_site_option( 'allowedthemes' );
				$is_allowed     = isset( $allowed_themes[ $stylesheet ] ) || \current_user_can( 'manage_network_themes' );
			}

			// Get parent theme info for child themes
			$parent_theme   = '';
			$is_child_theme = false;
			if ( $theme->get_template() !== $theme->get_stylesheet() ) {
				$is_child_theme = true;
				$parent_theme   = $theme->get_template();
			}

			// Get screenshot URL
			$screenshot      = '';
			$screenshot_file = $theme->get_screenshot();
			if ( $screenshot_file ) {
				$screenshot = $theme->get_stylesheet_directory_uri() . '/' . $screenshot_file;
			}

			// Check for updates
			$update_available = false;
			$new_version      = '';
			if ( isset( $update_themes->response[ $stylesheet ] ) ) {
				$update_available = true;
				$new_version      = $update_themes->response[ $stylesheet ]['new_version'] ?? '';
			}

			// Get theme errors if any
			$errors = array();
			if ( $is_broken && $theme->errors() ) {
				$theme_errors = $theme->errors();
				if ( is_wp_error( $theme_errors ) ) {
					$errors = $theme_errors->get_error_messages();
				}
			}

			$theme_data = array(
				'stylesheet'       => $stylesheet,
				'template'         => $theme->get_template(),
				'name'             => $theme->get( 'Name' ),
				'version'          => $theme->get( 'Version' ),
				'description'      => $theme->get( 'Description' ),
				'author'           => \wp_strip_all_tags( $theme->get( 'Author' ) ),
				'author_uri'       => $theme->get( 'AuthorURI' ),
				'theme_uri'        => $theme->get( 'ThemeURI' ),
				'text_domain'      => $theme->get( 'TextDomain' ),
				'domain_path'      => $theme->get( 'DomainPath' ),
				'requires_wp'      => $theme->get( 'RequiresWP' ) ?: '',
				'requires_php'     => $theme->get( 'RequiresPHP' ) ?: '',
				'tested_up_to'     => $theme->get( 'TestedUpTo' ) ?: '',
				'is_active'        => $stylesheet === $active_theme,
				'is_child_theme'   => $is_child_theme,
				'parent_theme'     => $parent_theme,
				'screenshot'       => $screenshot,
				'tags'             => $theme->get( 'Tags' ) ?: array(),
				'update_available' => $update_available,
				'new_version'      => $new_version,
				'is_allowed'       => $is_allowed,
				'is_broken'        => $is_broken,
				'errors'           => $errors,
			);

			$themes_data[] = $theme_data;
		}

		return array(
			'themes'       => $themes_data,
			'active_theme' => $active_theme,
			'total_count'  => count( $themes_data ),
		);
	}
}
