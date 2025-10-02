<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetThemeInfo implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-theme-info',
			array(
				'label'               => 'Get Theme Info',
				'description'         => 'Get detailed information about a specific WordPress theme.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'stylesheet' ),
					'properties' => array(
						'stylesheet' => array(
							'type'        => 'string',
							'description' => 'Theme stylesheet name (folder name).',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'stylesheet', 'name', 'version' ),
					'properties' => array(
						'stylesheet'        => array( 'type' => 'string' ),
						'template'          => array( 'type' => 'string' ),
						'name'              => array( 'type' => 'string' ),
						'version'           => array( 'type' => 'string' ),
						'description'       => array( 'type' => 'string' ),
						'author'            => array( 'type' => 'string' ),
						'author_uri'        => array( 'type' => 'string' ),
						'theme_uri'         => array( 'type' => 'string' ),
						'text_domain'       => array( 'type' => 'string' ),
						'domain_path'       => array( 'type' => 'string' ),
						'requires_wp'       => array( 'type' => 'string' ),
						'requires_php'      => array( 'type' => 'string' ),
						'tested_up_to'      => array( 'type' => 'string' ),
						'is_active'         => array( 'type' => 'boolean' ),
						'is_child_theme'    => array( 'type' => 'boolean' ),
						'parent_theme'      => array( 'type' => 'string' ),
						'parent_theme_info' => array(
							'type'       => 'object',
							'properties' => array(
								'name'    => array( 'type' => 'string' ),
								'version' => array( 'type' => 'string' ),
								'exists'  => array( 'type' => 'boolean' ),
							),
						),
						'screenshot'        => array( 'type' => 'string' ),
						'tags'              => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'update_available'  => array( 'type' => 'boolean' ),
						'new_version'       => array( 'type' => 'string' ),
						'update_info'       => array(
							'type'       => 'object',
							'properties' => array(
								'theme'   => array( 'type' => 'string' ),
								'url'     => array( 'type' => 'string' ),
								'package' => array( 'type' => 'string' ),
							),
						),
						'is_allowed'        => array( 'type' => 'boolean' ),
						'is_broken'         => array( 'type' => 'boolean' ),
						'errors'            => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'theme_root'        => array( 'type' => 'string' ),
						'theme_root_uri'    => array( 'type' => 'string' ),
						'stylesheet_dir'    => array( 'type' => 'string' ),
						'stylesheet_uri'    => array( 'type' => 'string' ),
						'template_dir'      => array( 'type' => 'string' ),
						'template_uri'      => array( 'type' => 'string' ),
						'supports'          => array(
							'type'       => 'object',
							'properties' => array(
								'post_thumbnails'     => array( 'type' => 'boolean' ),
								'custom_background'   => array( 'type' => 'boolean' ),
								'custom_header'       => array( 'type' => 'boolean' ),
								'custom_logo'         => array( 'type' => 'boolean' ),
								'menus'               => array( 'type' => 'boolean' ),
								'widgets'             => array( 'type' => 'boolean' ),
								'html5'               => array( 'type' => 'array' ),
								'post_formats'        => array( 'type' => 'array' ),
								'customize_selective_refresh' => array( 'type' => 'boolean' ),
								'editor_styles'       => array( 'type' => 'boolean' ),
								'dark_editor_style'   => array( 'type' => 'boolean' ),
								'disable_custom_colors' => array( 'type' => 'boolean' ),
								'disable_custom_font_sizes' => array( 'type' => 'boolean' ),
								'editor_color_palette' => array( 'type' => 'array' ),
								'editor_font_sizes'   => array( 'type' => 'array' ),
								'align_wide'          => array( 'type' => 'boolean' ),
								'responsive_embeds'   => array( 'type' => 'boolean' ),
							),
						),
						'customizer_settings' => array(
							'type'       => 'object',
							'properties' => array(
								'panels'   => array( 'type' => 'array' ),
								'sections' => array( 'type' => 'array' ),
								'controls' => array( 'type' => 'array' ),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'appearance', 'themes' ),
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
	 * Check permission for getting theme info.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'switch_themes' );
	}

	/**
	 * Execute the get theme info operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$stylesheet = \sanitize_text_field( (string) $input['stylesheet'] );

		// Get the theme
		$theme = \wp_get_theme( $stylesheet );

		if ( ! $theme->exists() ) {
			return array(
				'error' => array(
					'code'    => 'theme_not_found',
					'message' => 'Theme not found.',
				),
			);
		}

		$active_theme = \get_stylesheet();

		// Check if theme is broken
		$is_broken = ! $theme->exists() || $theme->errors();
		$errors = array();
		if ( $is_broken && $theme->errors() ) {
			$theme_errors = $theme->errors();
			if ( is_wp_error( $theme_errors ) ) {
				$errors = $theme_errors->get_error_messages();
			}
		}

		// Check if theme is allowed (for multisite)
		$is_allowed = true;
		if ( \is_multisite() ) {
			$allowed_themes = \get_site_option( 'allowedthemes' );
			$is_allowed = isset( $allowed_themes[ $stylesheet ] ) || \current_user_can( 'manage_network_themes' );
		}

		// Get parent theme info for child themes
		$parent_theme = '';
		$parent_theme_info = array();
		$is_child_theme = false;
		if ( $theme->get_template() !== $theme->get_stylesheet() ) {
			$is_child_theme = true;
			$parent_theme = $theme->get_template();
			$parent_theme_obj = \wp_get_theme( $parent_theme );
			$parent_theme_info = array(
				'name'    => $parent_theme_obj->get( 'Name' ),
				'version' => $parent_theme_obj->get( 'Version' ),
				'exists'  => $parent_theme_obj->exists(),
			);
		}

		// Get screenshot URL
		$screenshot = '';
		$screenshot_file = $theme->get_screenshot();
		if ( $screenshot_file ) {
			$screenshot = $theme->get_stylesheet_directory_uri() . '/' . $screenshot_file;
		}

		// Check for updates
		$update_themes = \get_site_transient( 'update_themes' );
		$update_available = false;
		$new_version = '';
		$update_info = array();
		if ( isset( $update_themes->response[ $stylesheet ] ) ) {
			$update_available = true;
			$update_data = $update_themes->response[ $stylesheet ];
			$new_version = $update_data['new_version'] ?? '';
			$update_info = array(
				'theme'   => $update_data['theme'] ?? '',
				'url'     => $update_data['url'] ?? '',
				'package' => $update_data['package'] ?? '',
			);
		}

		// Get theme support information
		$supports = array();
		if ( $stylesheet === $active_theme ) {
			// Only get theme support for active theme
			$supports = array(
				'post_thumbnails'     => \current_theme_supports( 'post-thumbnails' ),
				'custom_background'   => \current_theme_supports( 'custom-background' ),
				'custom_header'       => \current_theme_supports( 'custom-header' ),
				'custom_logo'         => \current_theme_supports( 'custom-logo' ),
				'menus'               => \current_theme_supports( 'menus' ),
				'widgets'             => \current_theme_supports( 'widgets' ),
				'html5'               => \get_theme_support( 'html5' ) ?: array(),
				'post_formats'        => \get_theme_support( 'post-formats' ) ?: array(),
				'customize_selective_refresh' => \current_theme_supports( 'customize-selective-refresh-widgets' ),
				'editor_styles'       => \current_theme_supports( 'editor-styles' ),
				'dark_editor_style'   => \current_theme_supports( 'dark-editor-style' ),
				'disable_custom_colors' => \current_theme_supports( 'disable-custom-colors' ),
				'disable_custom_font_sizes' => \current_theme_supports( 'disable-custom-font-sizes' ),
				'editor_color_palette' => \get_theme_support( 'editor-color-palette' ) ?: array(),
				'editor_font_sizes'   => \get_theme_support( 'editor-font-sizes' ) ?: array(),
				'align_wide'          => \current_theme_supports( 'align-wide' ),
				'responsive_embeds'   => \current_theme_supports( 'responsive-embeds' ),
			);
		}

		// Get customizer settings (basic info)
		$customizer_settings = array(
			'panels'   => array(),
			'sections' => array(),
			'controls' => array(),
		);

		// Only get customizer info for active theme to avoid conflicts
		if ( $stylesheet === $active_theme ) {
			global $wp_customize;
			if ( isset( $wp_customize ) ) {
				$customizer_settings = array(
					'panels'   => array_keys( $wp_customize->panels() ),
					'sections' => array_keys( $wp_customize->sections() ),
					'controls' => array_keys( $wp_customize->controls() ),
				);
			}
		}

		return array(
			'stylesheet'          => $stylesheet,
			'template'            => $theme->get_template(),
			'name'                => $theme->get( 'Name' ),
			'version'             => $theme->get( 'Version' ),
			'description'         => $theme->get( 'Description' ),
			'author'              => \wp_strip_all_tags( $theme->get( 'Author' ) ),
			'author_uri'          => $theme->get( 'AuthorURI' ),
			'theme_uri'           => $theme->get( 'ThemeURI' ),
			'text_domain'         => $theme->get( 'TextDomain' ),
			'domain_path'         => $theme->get( 'DomainPath' ),
			'requires_wp'         => $theme->get( 'RequiresWP' ) ?: '',
			'requires_php'        => $theme->get( 'RequiresPHP' ) ?: '',
			'tested_up_to'        => $theme->get( 'TestedUpTo' ) ?: '',
			'is_active'           => $stylesheet === $active_theme,
			'is_child_theme'      => $is_child_theme,
			'parent_theme'        => $parent_theme,
			'parent_theme_info'   => $parent_theme_info,
			'screenshot'          => $screenshot,
			'tags'                => $theme->get( 'Tags' ) ?: array(),
			'update_available'    => $update_available,
			'new_version'         => $new_version,
			'update_info'         => $update_info,
			'is_allowed'          => $is_allowed,
			'is_broken'           => $is_broken,
			'errors'              => $errors,
			'theme_root'          => $theme->get_theme_root(),
			'theme_root_uri'      => $theme->get_theme_root_uri(),
			'stylesheet_dir'      => $theme->get_stylesheet_directory(),
			'stylesheet_uri'      => $theme->get_stylesheet_directory_uri(),
			'template_dir'        => $theme->get_template_directory(),
			'template_uri'        => $theme->get_template_directory_uri(),
			'supports'            => $supports,
			'customizer_settings' => $customizer_settings,
		);
	}
}
