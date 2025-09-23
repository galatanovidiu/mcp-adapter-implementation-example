<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Plugins;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeletePlugin implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/delete-plugin',
			array(
				'label'               => 'Delete Plugin',
				'description'         => 'Delete a WordPress plugin and all its files. Plugin must be deactivated first.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'plugin_file' ),
					'properties' => array(
						'plugin_file' => array(
							'type'        => 'string',
							'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php").',
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => 'Force deletion even if plugin is active (will deactivate first).',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'deleted' ),
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'plugin_info' => array(
							'type'       => 'object',
							'properties' => array(
								'name'    => array( 'type' => 'string' ),
								'version' => array( 'type' => 'string' ),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'plugins', 'management' ),
					'annotations' => array(
						'audience'             => array( 'user', 'assistant' ),
						'priority'             => 0.5,
						'readOnlyHint'         => false,
						'destructiveHint'      => true,
						'idempotentHint'       => true,
						'openWorldHint'        => false,
						'requiresConfirmation' => true,
					),
				),
			)
		);
	}

	/**
	 * Check permission for deleting plugins.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'delete_plugins' );
	}

	/**
	 * Execute the delete plugin operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$plugin_file = \sanitize_text_field( (string) $input['plugin_file'] );
		$force       = ! empty( $input['force'] );

		// Ensure plugin functions are available
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'delete_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Validate plugin file exists
		$all_plugins = \get_plugins();
		if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
			return array(
				'error' => array(
					'code'    => 'plugin_not_found',
					'message' => 'Plugin file not found.',
				),
			);
		}

		$plugin_data = $all_plugins[ $plugin_file ];

		// Check if plugin is a must-use or drop-in plugin (cannot be deleted)
		$mu_plugins = \get_mu_plugins();
		$dropins    = \get_dropins();

		if ( isset( $mu_plugins[ $plugin_file ] ) ) {
			return array(
				'error' => array(
					'code'    => 'cannot_delete_mu_plugin',
					'message' => 'Cannot delete must-use plugins.',
				),
			);
		}

		if ( isset( $dropins[ $plugin_file ] ) ) {
			return array(
				'error' => array(
					'code'    => 'cannot_delete_dropin',
					'message' => 'Cannot delete drop-in plugins.',
				),
			);
		}

		// Check if plugin is currently active
		$is_active         = \is_plugin_active( $plugin_file );
		$is_network_active = \is_multisite() && \is_plugin_active_for_network( $plugin_file );

		if ( ( $is_active || $is_network_active ) && ! $force ) {
			return array(
				'error' => array(
					'code'    => 'plugin_active',
					'message' => 'Plugin is currently active. Deactivate it first or use force=true.',
				),
			);
		}

		// Deactivate plugin if it's active and force is enabled
		if ( ( $is_active || $is_network_active ) && $force ) {
			if ( $is_network_active ) {
				\deactivate_plugins( $plugin_file, true, true );
			} else {
				\deactivate_plugins( $plugin_file, true, false );
			}

			// Verify deactivation
			$still_active = \is_plugin_active( $plugin_file ) || 
				( \is_multisite() && \is_plugin_active_for_network( $plugin_file ) );
			
			if ( $still_active ) {
				return array(
					'error' => array(
						'code'    => 'deactivation_failed',
						'message' => 'Could not deactivate plugin before deletion.',
					),
				);
			}
		}

		// Validate plugin file path for security
		if ( \validate_file( $plugin_file ) !== 0 ) {
			return array(
				'error' => array(
					'code'    => 'invalid_plugin_path',
					'message' => 'Invalid plugin file path.',
				),
			);
		}

		// Check if plugin directory is writable
		$plugin_dir = \dirname( WP_PLUGIN_DIR . '/' . $plugin_file );
		if ( ! \is_writable( $plugin_dir ) ) {
			return array(
				'error' => array(
					'code'    => 'directory_not_writable',
					'message' => 'Plugin directory is not writable.',
				),
			);
		}

		// Attempt to delete the plugin
		$result = \delete_plugins( array( $plugin_file ) );

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
					'code'    => 'deletion_failed',
					'message' => 'Plugin deletion failed for unknown reason.',
				),
			);
		}

		// Verify deletion was successful
		$updated_plugins = \get_plugins();
		if ( isset( $updated_plugins[ $plugin_file ] ) ) {
			return array(
				'error' => array(
					'code'    => 'deletion_verification_failed',
					'message' => 'Plugin deletion completed but plugin still exists.',
				),
			);
		}

		return array(
			'deleted'     => true,
			'message'     => 'Plugin deleted successfully.',
			'plugin_info' => array(
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
			),
		);
	}
}
