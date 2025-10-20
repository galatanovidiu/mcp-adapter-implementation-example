<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Plugins;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetPluginInfo implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-plugin-info',
			array(
				'label'               => 'Get Plugin Info',
				'description'         => 'Get detailed information about a specific WordPress plugin.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'plugin_file' ),
					'properties' => array(
						'plugin_file' => array(
							'type'        => 'string',
							'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php").',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'plugin_file', 'name', 'version' ),
					'properties' => array(
						'plugin_file'       => array( 'type' => 'string' ),
						'name'              => array( 'type' => 'string' ),
						'version'           => array( 'type' => 'string' ),
						'description'       => array( 'type' => 'string' ),
						'author'            => array( 'type' => 'string' ),
						'author_uri'        => array( 'type' => 'string' ),
						'plugin_uri'        => array( 'type' => 'string' ),
						'text_domain'       => array( 'type' => 'string' ),
						'domain_path'       => array( 'type' => 'string' ),
						'network'           => array( 'type' => 'boolean' ),
						'requires_wp'       => array( 'type' => 'string' ),
						'requires_php'      => array( 'type' => 'string' ),
						'tested_up_to'      => array( 'type' => 'string' ),
						'is_active'         => array( 'type' => 'boolean' ),
						'is_network_active' => array( 'type' => 'boolean' ),
						'is_must_use'       => array( 'type' => 'boolean' ),
						'is_dropin'         => array( 'type' => 'boolean' ),
						'update_available'  => array( 'type' => 'boolean' ),
						'new_version'       => array( 'type' => 'string' ),
						'update_info'       => array(
							'type'       => 'object',
							'properties' => array(
								'compatibility'  => array( 'type' => 'object' ),
								'upgrade_notice' => array( 'type' => 'string' ),
								'tested'         => array( 'type' => 'string' ),
								'requires_php'   => array( 'type' => 'string' ),
							),
						),
						'file_size'         => array( 'type' => 'integer' ),
						'file_permissions'  => array( 'type' => 'string' ),
						'last_modified'     => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'plugins',
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
	 * Check permission for getting plugin info.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'activate_plugins' );
	}

	/**
	 * Execute the get plugin info operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$plugin_file = \sanitize_text_field( (string) $input['plugin_file'] );

		// Ensure plugin functions are available
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check in regular plugins
		$all_plugins = \get_plugins();
		$plugin_data = null;
		$plugin_type = 'regular';

		if ( isset( $all_plugins[ $plugin_file ] ) ) {
			$plugin_data = $all_plugins[ $plugin_file ];
		} else {
			// Check in must-use plugins
			$mu_plugins = \get_mu_plugins();
			if ( isset( $mu_plugins[ $plugin_file ] ) ) {
				$plugin_data = $mu_plugins[ $plugin_file ];
				$plugin_type = 'must-use';
			} else {
				// Check in drop-ins
				$dropins = \get_dropins();
				if ( isset( $dropins[ $plugin_file ] ) ) {
					$plugin_data = $dropins[ $plugin_file ];
					$plugin_type = 'dropin';
				}
			}
		}

		if ( ! $plugin_data ) {
			return array(
				'error' => array(
					'code'    => 'plugin_not_found',
					'message' => 'Plugin file not found.',
				),
			);
		}

		// Determine plugin status
		$is_active         = false;
		$is_network_active = false;
		$is_must_use       = $plugin_type === 'must-use';
		$is_dropin         = $plugin_type === 'dropin';

		if ( $plugin_type === 'regular' ) {
			$is_active         = \is_plugin_active( $plugin_file );
			$is_network_active = \is_multisite() && \is_plugin_active_for_network( $plugin_file );
		} elseif ( $is_must_use || $is_dropin ) {
			$is_active = true; // MU plugins and drop-ins are always active
		}

		// Get update information
		$update_available = false;
		$new_version      = '';
		$update_info      = array();

		if ( $plugin_type === 'regular' ) {
			$update_plugins = \get_site_transient( 'update_plugins' );
			if ( isset( $update_plugins->response[ $plugin_file ] ) ) {
				$update_available = true;
				$update_data      = $update_plugins->response[ $plugin_file ];
				$new_version      = $update_data->new_version ?? '';

				$update_info = array(
					'compatibility'  => $update_data->compatibility ?? new \stdClass(),
					'upgrade_notice' => $update_data->upgrade_notice ?? '',
					'tested'         => $update_data->tested ?? '',
					'requires_php'   => $update_data->requires_php ?? '',
				);
			}
		}

		// Get file information
		$plugin_path = '';
		if ( $plugin_type === 'regular' ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
		} elseif ( $plugin_type === 'must-use' ) {
			$plugin_path = WPMU_PLUGIN_DIR . '/' . $plugin_file;
		} elseif ( $plugin_type === 'dropin' ) {
			$plugin_path = WP_CONTENT_DIR . '/' . $plugin_file;
		}

		$file_size        = 0;
		$file_permissions = '';
		$last_modified    = '';

		if ( $plugin_path && \file_exists( $plugin_path ) ) {
			$file_size        = \filesize( $plugin_path );
			$file_perms       = \fileperms( $plugin_path );
			$file_permissions = \substr( \sprintf( '%o', $file_perms ), -4 );
			$last_modified    = \date( 'Y-m-d H:i:s', \filemtime( $plugin_path ) );
		}

		return array(
			'plugin_file'       => $plugin_file,
			'name'              => $plugin_data['Name'],
			'version'           => $plugin_data['Version'],
			'description'       => $plugin_data['Description'],
			'author'            => \wp_strip_all_tags( $plugin_data['Author'] ),
			'author_uri'        => $plugin_data['AuthorURI'],
			'plugin_uri'        => $plugin_data['PluginURI'],
			'text_domain'       => $plugin_data['TextDomain'],
			'domain_path'       => $plugin_data['DomainPath'],
			'network'           => (bool) $plugin_data['Network'],
			'requires_wp'       => $plugin_data['RequiresWP'],
			'requires_php'      => $plugin_data['RequiresPHP'],
			'tested_up_to'      => $plugin_data['TestedUpTo'] ?? '',
			'is_active'         => $is_active || $is_network_active,
			'is_network_active' => $is_network_active,
			'is_must_use'       => $is_must_use,
			'is_dropin'         => $is_dropin,
			'update_available'  => $update_available,
			'new_version'       => $new_version,
			'update_info'       => $update_info,
			'file_size'         => $file_size,
			'file_permissions'  => $file_permissions,
			'last_modified'     => $last_modified,
		);
	}
}
