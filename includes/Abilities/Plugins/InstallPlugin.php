<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Plugins;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class InstallPlugin implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/install-plugin',
			array(
				'label'               => 'Install Plugin',
				'description'         => 'Install a WordPress plugin from the WordPress.org repository by slug or upload URL.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Plugin slug from WordPress.org repository (e.g., "akismet").',
						),
						'zip_url' => array(
							'type'        => 'string',
							'description' => 'Direct URL to plugin ZIP file (alternative to slug).',
						),
						'activate' => array(
							'type'        => 'boolean',
							'description' => 'Activate plugin after installation.',
							'default'     => false,
						),
						'network_activate' => array(
							'type'        => 'boolean',
							'description' => 'Activate plugin network-wide after installation (multisite only).',
							'default'     => false,
						),
						'overwrite' => array(
							'type'        => 'boolean',
							'description' => 'Overwrite existing plugin if it exists.',
							'default'     => false,
						),
					),
					'oneOf' => array(
						array( 'required' => array( 'slug' ) ),
						array( 'required' => array( 'zip_url' ) ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'installed' ),
					'properties' => array(
						'installed'   => array( 'type' => 'boolean' ),
						'activated'   => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
						'plugin_file' => array( 'type' => 'string' ),
						'plugin_info' => array(
							'type'       => 'object',
							'properties' => array(
								'name'    => array( 'type' => 'string' ),
								'version' => array( 'type' => 'string' ),
								'author'  => array( 'type' => 'string' ),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'plugins', 'installation' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for installing plugins.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'install_plugins' );
	}

	/**
	 * Execute the install plugin operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$slug             = isset( $input['slug'] ) ? \sanitize_key( (string) $input['slug'] ) : '';
		$zip_url          = isset( $input['zip_url'] ) ? \esc_url_raw( (string) $input['zip_url'] ) : '';
		$activate         = ! empty( $input['activate'] );
		$network_activate = ! empty( $input['network_activate'] );
		$overwrite        = ! empty( $input['overwrite'] );

		// Validate input
		if ( empty( $slug ) && empty( $zip_url ) ) {
			return array(
				'error' => array(
					'code'    => 'missing_source',
					'message' => 'Either slug or zip_url must be provided.',
				),
			);
		}

		// Ensure required WordPress functions are available
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		// Determine installation source
		$source = '';
		if ( ! empty( $slug ) ) {
			// WordPress.org repository
			$api = \plugins_api( 'plugin_information', array( 'slug' => $slug ) );
			if ( \is_wp_error( $api ) ) {
				return array(
					'error' => array(
						'code'    => 'plugin_not_found',
						'message' => 'Plugin not found in WordPress.org repository: ' . $api->get_error_message(),
					),
				);
			}
			$source = isset( $api->download_link ) ? $api->download_link : '';
		} else {
			// Direct ZIP URL
			$source = $zip_url;
		}

		// Check if plugin already exists
		$existing_plugins = \get_plugins();
		$plugin_file      = '';
		$plugin_exists    = false;

		// Try to determine plugin file from slug
		if ( ! empty( $slug ) ) {
			foreach ( $existing_plugins as $file => $data ) {
				if ( \str_starts_with( $file, $slug . '/' ) ) {
					$plugin_file   = $file;
					$plugin_exists = true;
					break;
				}
			}
		}

		if ( $plugin_exists && ! $overwrite ) {
			return array(
				'error' => array(
					'code'    => 'plugin_exists',
					'message' => 'Plugin already exists. Use overwrite=true to replace it.',
				),
			);
		}

		// Set up upgrader
		$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );

		// Install the plugin
		$result = $upgrader->install( $source );

		if ( \is_wp_error( $result ) ) {
			return array(
				'error' => array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
			);
		}

		if ( ! $result ) {
			return array(
				'error' => array(
					'code'    => 'installation_failed',
					'message' => 'Plugin installation failed for unknown reason.',
				),
			);
		}

		// Get the installed plugin file
		$plugin_file = $upgrader->plugin_info();
		if ( ! $plugin_file ) {
			// Try to find the plugin file
			$new_plugins = \get_plugins();
			foreach ( $new_plugins as $file => $data ) {
				if ( ! isset( $existing_plugins[ $file ] ) ) {
					$plugin_file = $file;
					break;
				}
			}
		}

		if ( ! $plugin_file ) {
			return array(
				'error' => array(
					'code'    => 'plugin_file_not_found',
					'message' => 'Plugin installed but plugin file could not be determined.',
				),
			);
		}

		// Get plugin information
		$all_plugins = \get_plugins();
		$plugin_data = $all_plugins[ $plugin_file ] ?? array();

		$response = array(
			'installed'   => true,
			'activated'   => false,
			'message'     => 'Plugin installed successfully.',
			'plugin_file' => $plugin_file,
			'plugin_info' => array(
				'name'    => $plugin_data['Name'] ?? '',
				'version' => $plugin_data['Version'] ?? '',
				'author'  => \wp_strip_all_tags( $plugin_data['Author'] ?? '' ),
			),
		);

		// Activate plugin if requested
		if ( $activate || $network_activate ) {
			$activation_result = \activate_plugin( $plugin_file, '', $network_activate, true );
			
			if ( \is_wp_error( $activation_result ) ) {
				$response['message'] .= ' However, activation failed: ' . $activation_result->get_error_message();
			} else {
				$is_activated = $network_activate 
					? ( \is_multisite() && \is_plugin_active_for_network( $plugin_file ) )
					: \is_plugin_active( $plugin_file );
				
				if ( $is_activated ) {
					$response['activated'] = true;
					$response['message'] = $network_activate 
						? 'Plugin installed and activated network-wide.'
						: 'Plugin installed and activated.';
				} else {
					$response['message'] .= ' However, activation verification failed.';
				}
			}
		}

		return $response;
	}
}
