<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class InstallTheme implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/install-theme',
			array(
				'label'               => 'Install Theme',
				'description'         => 'Install a WordPress theme from the repository or upload URL.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'theme' ),
					'properties' => array(
						'theme' => array(
							'type'        => 'string',
							'description' => 'Theme slug from WordPress.org repository or download URL.',
						),
						'activate' => array(
							'type'        => 'boolean',
							'description' => 'Whether to activate the theme after installation. Default: false.',
							'default'     => false,
						),
						'overwrite' => array(
							'type'        => 'boolean',
							'description' => 'Whether to overwrite existing theme. Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success' ),
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'theme_slug'    => array( 'type' => 'string' ),
						'theme_info'    => array(
							'type'       => 'object',
							'properties' => array(
								'name'        => array( 'type' => 'string' ),
								'version'     => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'author'      => array( 'type' => 'string' ),
								'stylesheet'  => array( 'type' => 'string' ),
							),
						),
						'activated'     => array( 'type' => 'boolean' ),
						'message'       => array( 'type' => 'string' ),
						'install_log'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'appearance', 'installation' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
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
	 * Check permission for installing themes.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'install_themes' );
	}

	/**
	 * Execute the install theme operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$theme_identifier = \sanitize_text_field( (string) $input['theme'] );
		$activate = (bool) ( $input['activate'] ?? false );
		$overwrite = (bool) ( $input['overwrite'] ?? false );

		// Include necessary WordPress files
		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}
		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$install_log = array();
		$theme_slug = '';
		$download_url = '';

		// Determine if it's a URL or theme slug
		if ( filter_var( $theme_identifier, FILTER_VALIDATE_URL ) ) {
			$download_url = $theme_identifier;
			$theme_slug = basename( parse_url( $theme_identifier, PHP_URL_PATH ), '.zip' );
			$install_log[] = "Installing theme from URL: {$download_url}";
		} else {
			$theme_slug = $theme_identifier;
			$install_log[] = "Installing theme from WordPress.org: {$theme_slug}";

			// Get theme information from WordPress.org API
			$api = \themes_api( 'theme_information', array( 'slug' => $theme_slug ) );

			if ( is_wp_error( $api ) ) {
				return array(
					'error' => array(
						'code'    => 'theme_api_error',
						'message' => 'Could not retrieve theme information: ' . $api->get_error_message(),
					),
				);
			}

			$download_url = $api->download_link ?? '';
			$theme_name = $api->name ?? $theme_slug;
			$theme_version = $api->version ?? 'unknown';
			$install_log[] = "Found theme: {$theme_name} v{$theme_version}";
		}

		// Check if theme already exists
		$existing_theme = \wp_get_theme( $theme_slug );
		if ( $existing_theme->exists() && ! $overwrite ) {
			return array(
				'error' => array(
					'code'    => 'theme_already_exists',
					'message' => 'Theme already exists. Use overwrite parameter to replace it.',
					'theme'   => $theme_slug,
				),
			);
		}

		// Create a custom skin to capture installation messages
		$skin = new class() extends \WP_Upgrader_Skin {
			public $messages = array();

			public function feedback( $string, ...$args ) {
				if ( isset( $this->upgrader->strings[ $string ] ) ) {
					$string = $this->upgrader->strings[ $string ];
				}

				if ( strpos( $string, '%' ) !== false ) {
					if ( $args ) {
						$string = vsprintf( $string, $args );
					}
				}

				if ( empty( $string ) ) {
					return;
				}

				$this->messages[] = strip_tags( $string );
			}

			public function header() {}
			public function footer() {}
		};

		// Initialize the theme upgrader
		$upgrader = new \Theme_Upgrader( $skin );

		// Perform the installation
		$install_log[] = "Starting theme installation...";
		$result = $upgrader->install( $download_url );

		// Collect installation messages
		$install_log = array_merge( $install_log, $skin->messages );

		if ( is_wp_error( $result ) ) {
			return array(
				'error' => array(
					'code'        => 'installation_failed',
					'message'     => 'Theme installation failed: ' . $result->get_error_message(),
					'install_log' => $install_log,
				),
			);
		}

		if ( ! $result ) {
			return array(
				'error' => array(
					'code'        => 'installation_failed',
					'message'     => 'Theme installation failed for unknown reason.',
					'install_log' => $install_log,
				),
			);
		}

		// Get the installed theme information
		$installed_theme = null;
		$stylesheet = '';

		// Try to find the installed theme
		if ( isset( $upgrader->result ) && isset( $upgrader->result['destination_name'] ) ) {
			$stylesheet = $upgrader->result['destination_name'];
			$installed_theme = \wp_get_theme( $stylesheet );
		} else {
			// Fallback: try the theme slug
			$installed_theme = \wp_get_theme( $theme_slug );
			if ( $installed_theme->exists() ) {
				$stylesheet = $theme_slug;
			}
		}

		if ( ! $installed_theme || ! $installed_theme->exists() ) {
			return array(
				'error' => array(
					'code'        => 'theme_not_found_after_install',
					'message'     => 'Theme was installed but could not be found.',
					'install_log' => $install_log,
				),
			);
		}

		$install_log[] = "Theme installed successfully: {$installed_theme->get('Name')}";

		$activated = false;
		$previous_theme = '';

		// Activate theme if requested
		if ( $activate ) {
			$previous_theme = \get_stylesheet();
			
			// Check if theme is allowed (for multisite)
			$can_activate = true;
			if ( \is_multisite() ) {
				$allowed_themes = \get_site_option( 'allowedthemes' );
				$can_activate = isset( $allowed_themes[ $stylesheet ] ) || \current_user_can( 'manage_network_themes' );
			}

			if ( $can_activate ) {
				\switch_theme( $stylesheet );
				$activated = \get_stylesheet() === $stylesheet;
				
				if ( $activated ) {
					$install_log[] = "Theme activated successfully.";
				} else {
					$install_log[] = "Theme installation succeeded but activation failed.";
				}
			} else {
				$install_log[] = "Theme installation succeeded but activation not allowed on this site.";
			}
		}

		return array(
			'success'     => true,
			'theme_slug'  => $stylesheet,
			'theme_info'  => array(
				'name'        => $installed_theme->get( 'Name' ),
				'version'     => $installed_theme->get( 'Version' ),
				'description' => $installed_theme->get( 'Description' ),
				'author'      => \wp_strip_all_tags( $installed_theme->get( 'Author' ) ),
				'stylesheet'  => $stylesheet,
			),
			'activated'   => $activated,
			'message'     => 'Theme installed successfully.',
			'install_log' => $install_log,
		);
	}
}
