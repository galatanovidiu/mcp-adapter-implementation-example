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
						'force'       => array(
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
						'deleted'     => array( 'type' => 'boolean' ),
						'message'     => array( 'type' => 'string' ),
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
				'category'            => 'plugins',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'annotations' => array(
						'audience'             => array( 'user', 'assistant' ),
						'priority'             => 0.5,
						'readonly'         => false,
						'destructive'      => true,
						'idempotent'       => true,
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
	public static function check_permission( ?array $input = null ): bool {
		$input = $input ?? array();
		return \current_user_can( 'delete_plugins' );
	}

	/**
	 * Execute the delete plugin operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( ?array $input = null ) {
		$input = $input ?? array();
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
			return new \WP_Error( 'plugin_not_found', 'Plugin file not found.' );
		}

		$plugin_data = $all_plugins[ $plugin_file ];

		// Check if plugin is a must-use or drop-in plugin (cannot be deleted)
		$mu_plugins = \get_mu_plugins();
		$dropins    = \get_dropins();

		if ( isset( $mu_plugins[ $plugin_file ] ) ) {
			return new \WP_Error( 'cannot_delete_mu_plugin', 'Cannot delete must-use plugins.' );
		}

		if ( isset( $dropins[ $plugin_file ] ) ) {
			return new \WP_Error( 'cannot_delete_dropin', 'Cannot delete drop-in plugins.' );
		}

		// Check if plugin is currently active
		$is_active         = \is_plugin_active( $plugin_file );
		$is_network_active = \is_multisite() && \is_plugin_active_for_network( $plugin_file );

		if ( ( $is_active || $is_network_active ) && ! $force ) {
			return new \WP_Error( 'plugin_active', 'Plugin is currently active. Deactivate it first or use force=true.' );
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
				return new \WP_Error( 'deactivation_failed', 'Could not deactivate plugin before deletion.' );
			}
		}

		// Validate plugin file path for security
		if ( \validate_file( $plugin_file ) !== 0 ) {
			return new \WP_Error( 'invalid_plugin_path', 'Invalid plugin file path.' );
		}

		// Check if plugin directory is writable
		$plugin_dir = \dirname( WP_PLUGIN_DIR . '/' . $plugin_file );
		if ( ! \is_writable( $plugin_dir ) ) {
			return new \WP_Error( 'directory_not_writable', 'Plugin directory is not writable.' );
		}

		// Attempt to delete the plugin
		$result = \delete_plugins( array( $plugin_file ) );

		if ( \is_wp_error( $result ) ) {
			return new \WP_Error( $result->get_error_code(), $result->get_error_message() );
		}

		if ( ! $result ) {
			return new \WP_Error( 'deletion_failed', 'Plugin deletion failed for unknown reason.' );
		}

		// Clear plugin cache before verification
		\wp_cache_delete( 'plugins', 'plugins' );

		// Verify deletion was successful
		$updated_plugins = \get_plugins();
		if ( isset( $updated_plugins[ $plugin_file ] ) ) {
			return new \WP_Error( 'deletion_verification_failed', 'Plugin deletion completed but plugin still exists.' );
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
