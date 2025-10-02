<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class RunUpdates implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/run-updates',
			array(
				'label'               => 'Run Updates',
				'description'         => 'Execute WordPress core, plugin, and theme updates.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'update_core' => array(
							'type'        => 'boolean',
							'description' => 'Whether to update WordPress core. Default: false.',
							'default'     => false,
						),
						'update_plugins' => array(
							'type'        => 'array',
							'description' => 'Array of plugin files to update. Empty array means update all available.',
							'items'       => array( 'type' => 'string' ),
						),
						'update_themes' => array(
							'type'        => 'array',
							'description' => 'Array of theme slugs to update. Empty array means update all available.',
							'items'       => array( 'type' => 'string' ),
						),
						'dry_run' => array(
							'type'        => 'boolean',
							'description' => 'Whether to perform a dry run (check what would be updated without actually updating). Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'results' ),
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'results' => array(
							'type'       => 'object',
							'properties' => array(
								'core' => array(
									'type'       => 'object',
									'properties' => array(
										'attempted'       => array( 'type' => 'boolean' ),
										'success'         => array( 'type' => 'boolean' ),
										'from_version'    => array( 'type' => 'string' ),
										'to_version'      => array( 'type' => 'string' ),
										'message'         => array( 'type' => 'string' ),
									),
								),
								'plugins' => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'plugin'       => array( 'type' => 'string' ),
											'name'         => array( 'type' => 'string' ),
											'success'      => array( 'type' => 'boolean' ),
											'from_version' => array( 'type' => 'string' ),
											'to_version'   => array( 'type' => 'string' ),
											'message'      => array( 'type' => 'string' ),
										),
									),
								),
								'themes' => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'theme'        => array( 'type' => 'string' ),
											'name'         => array( 'type' => 'string' ),
											'success'      => array( 'type' => 'boolean' ),
											'from_version' => array( 'type' => 'string' ),
											'to_version'   => array( 'type' => 'string' ),
											'message'      => array( 'type' => 'string' ),
										),
									),
								),
							),
						),
						'summary' => array(
							'type'       => 'object',
							'properties' => array(
								'total_attempted' => array( 'type' => 'integer' ),
								'total_successful' => array( 'type' => 'integer' ),
								'total_failed'    => array( 'type' => 'integer' ),
								'dry_run'         => array( 'type' => 'boolean' ),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'system', 'updates' ),
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
	 * Check permission for running updates.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$update_core = (bool) ( $input['update_core'] ?? false );
		$update_plugins = $input['update_plugins'] ?? array();
		$update_themes = $input['update_themes'] ?? array();

		if ( $update_core && ! \current_user_can( 'update_core' ) ) {
			return false;
		}

		if ( ! empty( $update_plugins ) && ! \current_user_can( 'update_plugins' ) ) {
			return false;
		}

		if ( ! empty( $update_themes ) && ! \current_user_can( 'update_themes' ) ) {
			return false;
		}

		return \current_user_can( 'update_core' ) || \current_user_can( 'update_plugins' ) || \current_user_can( 'update_themes' );
	}

	/**
	 * Execute the run updates operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$update_core = (bool) ( $input['update_core'] ?? false );
		$update_plugins = $input['update_plugins'] ?? array();
		$update_themes = $input['update_themes'] ?? array();
		$dry_run = (bool) ( $input['dry_run'] ?? false );

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
		if ( ! class_exists( 'Core_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$results = array(
			'core'    => array(),
			'plugins' => array(),
			'themes'  => array(),
		);

		$total_attempted = 0;
		$total_successful = 0;
		$total_failed = 0;

		// Update WordPress Core
		if ( $update_core && \current_user_can( 'update_core' ) ) {
			$total_attempted++;
			$core_updates = \get_core_updates();
			$current_version = \get_bloginfo( 'version' );

			if ( ! empty( $core_updates ) && isset( $core_updates[0] ) && $core_updates[0]->response === 'upgrade' ) {
				$update = $core_updates[0];
				$to_version = $update->current;

				if ( $dry_run ) {
					$results['core'] = array(
						'attempted'    => true,
						'success'      => true,
						'from_version' => $current_version,
						'to_version'   => $to_version,
						'message'      => 'Dry run: WordPress core would be updated from ' . $current_version . ' to ' . $to_version,
					);
					$total_successful++;
				} else {
					// Perform core update
					$upgrader = new \Core_Upgrader();
					$result = $upgrader->upgrade( $update );

					if ( \is_wp_error( $result ) ) {
						$results['core'] = array(
							'attempted'    => true,
							'success'      => false,
							'from_version' => $current_version,
							'to_version'   => $to_version,
							'message'      => 'Core update failed: ' . $result->get_error_message(),
						);
						$total_failed++;
					} elseif ( $result === false ) {
						$results['core'] = array(
							'attempted'    => true,
							'success'      => false,
							'from_version' => $current_version,
							'to_version'   => $to_version,
							'message'      => 'Core update failed: Unknown error',
						);
						$total_failed++;
					} else {
						$results['core'] = array(
							'attempted'    => true,
							'success'      => true,
							'from_version' => $current_version,
							'to_version'   => $to_version,
							'message'      => 'WordPress core updated successfully',
						);
						$total_successful++;
					}
				}
			} else {
				$results['core'] = array(
					'attempted'    => true,
					'success'      => false,
					'from_version' => $current_version,
					'to_version'   => $current_version,
					'message'      => 'No core updates available',
				);
				$total_failed++;
			}
		}

		// Update Plugins
		if ( ! empty( $update_plugins ) && \current_user_can( 'update_plugins' ) ) {
			$plugin_updates = \get_plugin_updates();

			// If empty array provided, update all available plugins
			if ( empty( $update_plugins ) ) {
				$update_plugins = array_keys( $plugin_updates );
			}

			foreach ( $update_plugins as $plugin_file ) {
				$total_attempted++;

				if ( ! isset( $plugin_updates[ $plugin_file ] ) ) {
					$results['plugins'][] = array(
						'plugin'       => $plugin_file,
						'name'         => $plugin_file,
						'success'      => false,
						'from_version' => 'Unknown',
						'to_version'   => 'Unknown',
						'message'      => 'No update available for this plugin',
					);
					$total_failed++;
					continue;
				}

				$plugin_data = $plugin_updates[ $plugin_file ];
				$from_version = $plugin_data->Version;
				$to_version = $plugin_data->update->new_version;

				if ( $dry_run ) {
					$results['plugins'][] = array(
						'plugin'       => $plugin_file,
						'name'         => $plugin_data->Name,
						'success'      => true,
						'from_version' => $from_version,
						'to_version'   => $to_version,
						'message'      => 'Dry run: Plugin would be updated from ' . $from_version . ' to ' . $to_version,
					);
					$total_successful++;
				} else {
					// Perform plugin update
					$upgrader = new \Plugin_Upgrader();
					$result = $upgrader->upgrade( $plugin_file );

					if ( \is_wp_error( $result ) ) {
						$results['plugins'][] = array(
							'plugin'       => $plugin_file,
							'name'         => $plugin_data->Name,
							'success'      => false,
							'from_version' => $from_version,
							'to_version'   => $to_version,
							'message'      => 'Plugin update failed: ' . $result->get_error_message(),
						);
						$total_failed++;
					} elseif ( $result === false ) {
						$results['plugins'][] = array(
							'plugin'       => $plugin_file,
							'name'         => $plugin_data->Name,
							'success'      => false,
							'from_version' => $from_version,
							'to_version'   => $to_version,
							'message'      => 'Plugin update failed: Unknown error',
						);
						$total_failed++;
					} else {
						$results['plugins'][] = array(
							'plugin'       => $plugin_file,
							'name'         => $plugin_data->Name,
							'success'      => true,
							'from_version' => $from_version,
							'to_version'   => $to_version,
							'message'      => 'Plugin updated successfully',
						);
						$total_successful++;
					}
				}
			}
		}

		// Update Themes
		if ( ! empty( $update_themes ) && \current_user_can( 'update_themes' ) ) {
			$theme_updates = \get_theme_updates();

			// If empty array provided, update all available themes
			if ( empty( $update_themes ) ) {
				$update_themes = array_keys( $theme_updates );
			}

			foreach ( $update_themes as $theme_slug ) {
				$total_attempted++;

				if ( ! isset( $theme_updates[ $theme_slug ] ) ) {
					$results['themes'][] = array(
						'theme'        => $theme_slug,
						'name'         => $theme_slug,
						'success'      => false,
						'from_version' => 'Unknown',
						'to_version'   => 'Unknown',
						'message'      => 'No update available for this theme',
					);
					$total_failed++;
					continue;
				}

				$theme_data = $theme_updates[ $theme_slug ];
				$from_version = $theme_data->get( 'Version' );
				$to_version = isset( $theme_data->update['new_version'] ) ? $theme_data->update['new_version'] : 'Unknown';

				if ( $dry_run ) {
					$results['themes'][] = array(
						'theme'        => $theme_slug,
						'name'         => $theme_data->get( 'Name' ),
						'success'      => true,
						'from_version' => $from_version,
						'to_version'   => $to_version,
						'message'      => 'Dry run: Theme would be updated from ' . $from_version . ' to ' . $to_version,
					);
					$total_successful++;
				} else {
					// Perform theme update
					$upgrader = new \Theme_Upgrader();
					$result = $upgrader->upgrade( $theme_slug );

					if ( \is_wp_error( $result ) ) {
						$results['themes'][] = array(
							'theme'        => $theme_slug,
							'name'         => $theme_data->get( 'Name' ),
							'success'      => false,
							'from_version' => $from_version,
							'to_version'   => $to_version,
							'message'      => 'Theme update failed: ' . $result->get_error_message(),
						);
						$total_failed++;
					} elseif ( $result === false ) {
						$results['themes'][] = array(
							'theme'        => $theme_slug,
							'name'         => $theme_data->get( 'Name' ),
							'success'      => false,
							'from_version' => $from_version,
							'to_version'   => $to_version,
							'message'      => 'Theme update failed: Unknown error',
						);
						$total_failed++;
					} else {
						$results['themes'][] = array(
							'theme'        => $theme_slug,
							'name'         => $theme_data->get( 'Name' ),
							'success'      => true,
							'from_version' => $from_version,
							'to_version'   => $to_version,
							'message'      => 'Theme updated successfully',
						);
						$total_successful++;
					}
				}
			}
		}

		$overall_success = $total_failed === 0 && $total_attempted > 0;
		$message = '';

		if ( $dry_run ) {
			$message = sprintf( 'Dry run completed: %d updates would be attempted', $total_attempted );
		} elseif ( $total_attempted === 0 ) {
			$message = 'No updates were requested';
		} elseif ( $overall_success ) {
			$message = sprintf( 'All %d updates completed successfully', $total_successful );
		} else {
			$message = sprintf( '%d of %d updates completed successfully, %d failed', $total_successful, $total_attempted, $total_failed );
		}

		return array(
			'success' => $overall_success,
			'results' => $results,
			'summary' => array(
				'total_attempted'  => $total_attempted,
				'total_successful' => $total_successful,
				'total_failed'     => $total_failed,
				'dry_run'          => $dry_run,
			),
			'message' => $message,
		);
	}
}
