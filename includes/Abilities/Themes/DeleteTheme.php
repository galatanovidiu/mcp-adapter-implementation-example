<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeleteTheme implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/delete-theme',
			array(
				'label'               => 'Delete Theme',
				'description'         => 'Delete a WordPress theme from the filesystem.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'stylesheet' ),
					'properties' => array(
						'stylesheet' => array(
							'type'        => 'string',
							'description' => 'Theme stylesheet name (folder name) to delete.',
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => 'Force deletion even if theme is active. Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success' ),
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'theme_slug'  => array( 'type' => 'string' ),
						'theme_name'  => array( 'type' => 'string' ),
						'message'     => array( 'type' => 'string' ),
						'files_removed' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'was_active'  => array( 'type' => 'boolean' ),
						'fallback_theme' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.5,
						'readOnlyHint'    => false,
						'destructiveHint' => true,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for deleting themes.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'delete_themes' );
	}

	/**
	 * Execute the delete theme operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$stylesheet = \sanitize_text_field( (string) $input['stylesheet'] );
		$force = (bool) ( $input['force'] ?? false );

		// Check if theme exists
		$theme = \wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return array(
				'error' => array(
					'code'    => 'theme_not_found',
					'message' => 'Theme not found.',
				),
			);
		}

		$theme_name = $theme->get( 'Name' );
		$active_theme = \get_stylesheet();
		$was_active = $stylesheet === $active_theme;
		$fallback_theme = '';

		// Prevent deletion of active theme unless forced
		if ( $was_active && ! $force ) {
			return array(
				'error' => array(
					'code'    => 'theme_is_active',
					'message' => 'Cannot delete active theme. Use force parameter or switch to another theme first.',
				),
			);
		}

		// Prevent deletion of default themes (unless forced)
		$default_themes = array( 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty' );
		if ( in_array( $stylesheet, $default_themes, true ) && ! $force ) {
			return array(
				'error' => array(
					'code'    => 'default_theme_protection',
					'message' => 'Cannot delete default WordPress theme. Use force parameter to override.',
				),
			);
		}

		// Check if this is a parent theme with active child themes
		$child_themes = array();
		$all_themes = \wp_get_themes();
		foreach ( $all_themes as $theme_stylesheet => $child_theme ) {
			if ( $child_theme->get_template() === $stylesheet && $theme_stylesheet !== $stylesheet ) {
				$child_themes[] = $theme_stylesheet;
			}
		}

		if ( ! empty( $child_themes ) && ! $force ) {
			return array(
				'error' => array(
					'code'    => 'has_child_themes',
					'message' => 'Cannot delete theme that has child themes. Child themes: ' . implode( ', ', $child_themes ),
					'child_themes' => $child_themes,
				),
			);
		}

		// If deleting active theme, switch to a fallback theme first
		if ( $was_active ) {
			// Find a suitable fallback theme
			$fallback_candidates = array( 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty' );
			
			foreach ( $fallback_candidates as $candidate ) {
				if ( $candidate !== $stylesheet ) {
					$fallback_theme_obj = \wp_get_theme( $candidate );
					if ( $fallback_theme_obj->exists() ) {
						$fallback_theme = $candidate;
						break;
					}
				}
			}

			// If no default theme found, find any other theme
			if ( ! $fallback_theme ) {
				foreach ( $all_themes as $theme_stylesheet => $candidate_theme ) {
					if ( $theme_stylesheet !== $stylesheet && $candidate_theme->exists() ) {
						$fallback_theme = $theme_stylesheet;
						break;
					}
				}
			}

			if ( ! $fallback_theme ) {
				return array(
					'error' => array(
						'code'    => 'no_fallback_theme',
						'message' => 'Cannot delete active theme: no other themes available to switch to.',
					),
				);
			}

			// Switch to fallback theme
			\switch_theme( $fallback_theme );
		}

		// Include necessary WordPress files
		if ( ! function_exists( 'delete_theme' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Get list of files before deletion for logging
		$theme_dir = $theme->get_stylesheet_directory();
		$files_removed = array();
		
		if ( is_dir( $theme_dir ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $theme_dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $iterator as $file ) {
				$files_removed[] = str_replace( $theme_dir . '/', '', $file->getPathname() );
			}
		}

		// Perform the deletion
		$result = \delete_theme( $stylesheet );

		if ( is_wp_error( $result ) ) {
			// If we switched themes, try to switch back
			if ( $was_active && $fallback_theme ) {
				\switch_theme( $stylesheet );
			}

			return array(
				'error' => array(
					'code'    => 'deletion_failed',
					'message' => 'Theme deletion failed: ' . $result->get_error_message(),
				),
			);
		}

		// Verify deletion was successful
		$theme_check = \wp_get_theme( $stylesheet );
		if ( $theme_check->exists() ) {
			return array(
				'error' => array(
					'code'    => 'deletion_incomplete',
					'message' => 'Theme deletion may not have completed successfully.',
				),
			);
		}

		// Clear any cached data
		if ( function_exists( 'wp_cache_flush' ) ) {
			\wp_cache_flush();
		}

		return array(
			'success'        => true,
			'theme_slug'     => $stylesheet,
			'theme_name'     => $theme_name,
			'message'        => 'Theme deleted successfully.',
			'files_removed'  => $files_removed,
			'was_active'     => $was_active,
			'fallback_theme' => $fallback_theme,
		);
	}
}
