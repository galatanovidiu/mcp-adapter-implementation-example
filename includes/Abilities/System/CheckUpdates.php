<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class CheckUpdates implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/check-updates',
			array(
				'label'               => 'Check Updates',
				'description'         => 'Check for available WordPress core, plugin, and theme updates.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'force_check' => array(
							'type'        => 'boolean',
							'description' => 'Whether to force a fresh check (bypass cache). Default: false.',
							'default'     => false,
						),
						'check_core' => array(
							'type'        => 'boolean',
							'description' => 'Whether to check for WordPress core updates. Default: true.',
							'default'     => true,
						),
						'check_plugins' => array(
							'type'        => 'boolean',
							'description' => 'Whether to check for plugin updates. Default: true.',
							'default'     => true,
						),
						'check_themes' => array(
							'type'        => 'boolean',
							'description' => 'Whether to check for theme updates. Default: true.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'core', 'plugins', 'themes', 'summary' ),
					'properties' => array(
						'core' => array(
							'type'       => 'object',
							'properties' => array(
								'current_version' => array( 'type' => 'string' ),
								'latest_version'  => array( 'type' => 'string' ),
								'update_available' => array( 'type' => 'boolean' ),
								'update_type'     => array( 'type' => 'string' ),
								'download_url'    => array( 'type' => 'string' ),
								'package_url'     => array( 'type' => 'string' ),
								'last_checked'    => array( 'type' => 'string' ),
							),
						),
						'plugins' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'plugin'          => array( 'type' => 'string' ),
									'name'            => array( 'type' => 'string' ),
									'current_version' => array( 'type' => 'string' ),
									'latest_version'  => array( 'type' => 'string' ),
									'update_available' => array( 'type' => 'boolean' ),
									'package_url'     => array( 'type' => 'string' ),
									'details_url'     => array( 'type' => 'string' ),
									'compatibility'   => array( 'type' => 'string' ),
								),
							),
						),
						'themes' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'theme'           => array( 'type' => 'string' ),
									'name'            => array( 'type' => 'string' ),
									'current_version' => array( 'type' => 'string' ),
									'latest_version'  => array( 'type' => 'string' ),
									'update_available' => array( 'type' => 'boolean' ),
									'package_url'     => array( 'type' => 'string' ),
									'details_url'     => array( 'type' => 'string' ),
								),
							),
						),
						'summary' => array(
							'type'       => 'object',
							'properties' => array(
								'core_updates'   => array( 'type' => 'integer' ),
								'plugin_updates' => array( 'type' => 'integer' ),
								'theme_updates'  => array( 'type' => 'integer' ),
								'total_updates'  => array( 'type' => 'integer' ),
								'last_checked'   => array( 'type' => 'string' ),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'system', 'updates' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for checking updates.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'update_core' ) || \current_user_can( 'update_plugins' ) || \current_user_can( 'update_themes' );
	}

	/**
	 * Execute the check updates operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$force_check = (bool) ( $input['force_check'] ?? false );
		$check_core = (bool) ( $input['check_core'] ?? true );
		$check_plugins = (bool) ( $input['check_plugins'] ?? true );
		$check_themes = (bool) ( $input['check_themes'] ?? true );

		// Include necessary files
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		$result = array(
			'core'    => array(),
			'plugins' => array(),
			'themes'  => array(),
			'summary' => array(),
		);

		// Force update checks if requested
		if ( $force_check ) {
			if ( $check_core ) {
				\wp_version_check( array(), true );
			}
			if ( $check_plugins ) {
				\wp_update_plugins();
			}
			if ( $check_themes ) {
				\wp_update_themes();
			}
		}

		$core_updates = 0;
		$plugin_updates = 0;
		$theme_updates = 0;

		// Check WordPress Core Updates
		if ( $check_core && \current_user_can( 'update_core' ) ) {
			$core_update_data = \get_core_updates();
			$current_version = \get_bloginfo( 'version' );
			
			if ( ! empty( $core_update_data ) && ! empty( $core_update_data[0] ) ) {
				$update = $core_update_data[0];
				$update_available = isset( $update->response ) && $update->response === 'upgrade';
				
				if ( $update_available ) {
					$core_updates = 1;
				}

				$result['core'] = array(
					'current_version'  => $current_version,
					'latest_version'   => isset( $update->current ) ? $update->current : $current_version,
					'update_available' => $update_available,
					'update_type'      => isset( $update->response ) ? $update->response : 'latest',
					'download_url'     => isset( $update->download ) ? $update->download : '',
					'package_url'      => isset( $update->packages, $update->packages->full ) ? $update->packages->full : '',
					'last_checked'     => \get_option( '_transient_update_core' ) ? \date( 'Y-m-d H:i:s', \get_option( '_transient_timeout_update_core' ) - 12 * HOUR_IN_SECONDS ) : '',
				);
			} else {
				$result['core'] = array(
					'current_version'  => $current_version,
					'latest_version'   => $current_version,
					'update_available' => false,
					'update_type'      => 'latest',
					'download_url'     => '',
					'package_url'      => '',
					'last_checked'     => '',
				);
			}
		}

		// Check Plugin Updates
		if ( $check_plugins && \current_user_can( 'update_plugins' ) ) {
			$plugin_updates_data = \get_plugin_updates();
			
			foreach ( $plugin_updates_data as $plugin_file => $plugin_data ) {
				$plugin_updates++;
				
				$result['plugins'][] = array(
					'plugin'           => $plugin_file,
					'name'             => $plugin_data->Name,
					'current_version'  => $plugin_data->Version,
					'latest_version'   => isset( $plugin_data->update->new_version ) ? $plugin_data->update->new_version : $plugin_data->Version,
					'update_available' => true,
					'package_url'      => isset( $plugin_data->update->package ) ? $plugin_data->update->package : '',
					'details_url'      => isset( $plugin_data->update->url ) ? $plugin_data->update->url : '',
					'compatibility'    => isset( $plugin_data->update->compatibility ) ? \wp_json_encode( $plugin_data->update->compatibility ) : '',
				);
			}
		}

		// Check Theme Updates
		if ( $check_themes && \current_user_can( 'update_themes' ) ) {
			$theme_updates_data = \get_theme_updates();
			
			foreach ( $theme_updates_data as $theme_slug => $theme_data ) {
				$theme_updates++;
				
				$result['themes'][] = array(
					'theme'            => $theme_slug,
					'name'             => $theme_data->get( 'Name' ),
					'current_version'  => $theme_data->get( 'Version' ),
					'latest_version'   => isset( $theme_data->update['new_version'] ) ? $theme_data->update['new_version'] : $theme_data->get( 'Version' ),
					'update_available' => true,
					'package_url'      => isset( $theme_data->update['package'] ) ? $theme_data->update['package'] : '',
					'details_url'      => isset( $theme_data->update['url'] ) ? $theme_data->update['url'] : '',
				);
			}
		}

		// Summary
		$result['summary'] = array(
			'core_updates'   => $core_updates,
			'plugin_updates' => $plugin_updates,
			'theme_updates'  => $theme_updates,
			'total_updates'  => $core_updates + $plugin_updates + $theme_updates,
			'last_checked'   => \current_time( 'Y-m-d H:i:s' ),
		);

		return $result;
	}
}
