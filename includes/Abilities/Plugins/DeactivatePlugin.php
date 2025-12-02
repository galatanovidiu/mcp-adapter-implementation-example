<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Plugins;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeactivatePlugin implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/deactivate-plugin',
			array(
				'label'               => 'Deactivate Plugin',
				'description'         => 'Deactivate a WordPress plugin by its plugin file path.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'plugin_file' ),
					'properties' => array(
						'plugin_file'  => array(
							'type'        => 'string',
							'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php").',
						),
						'network_wide' => array(
							'type'        => 'boolean',
							'description' => 'Deactivate plugin network-wide (multisite only).',
							'default'     => false,
						),
						'silent'       => array(
							'type'        => 'boolean',
							'description' => 'Suppress deactivation hooks.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'deactivated' ),
					'properties' => array(
						'deactivated' => array( 'type' => 'boolean' ),
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
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for deactivating plugins.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		$network_wide = ! empty( $input['network_wide'] );

		if ( $network_wide && \is_multisite() ) {
			return \current_user_can( 'manage_network_plugins' );
		}

		return \current_user_can( 'activate_plugins' );
	}

	/**
	 * Execute the deactivate plugin operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$plugin_file  = \sanitize_text_field( (string) $input['plugin_file'] );
		$network_wide = ! empty( $input['network_wide'] );
		$silent       = ! empty( $input['silent'] );

		// Ensure plugin functions are available
		if ( ! function_exists( 'get_plugins' ) ) {
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

		// Check if plugin is currently active
		$is_active         = \is_plugin_active( $plugin_file );
		$is_network_active = \is_multisite() && \is_plugin_active_for_network( $plugin_file );

		if ( ! $is_active && ! $is_network_active ) {
			return array(
				'deactivated' => true,
				'message'     => 'Plugin is already inactive.',
				'plugin_info' => array(
					'name'    => $plugin_data['Name'],
					'version' => $plugin_data['Version'],
				),
			);
		}

		// Check if trying to deactivate network-wide but plugin is only active on current site
		if ( $network_wide && ! $is_network_active ) {
			return array(
				'error' => array(
					'code'    => 'not_network_active',
					'message' => 'Plugin is not active network-wide.',
				),
			);
		}

		// Check if trying to deactivate site-wide but plugin is network active
		if ( ! $network_wide && $is_network_active && \is_multisite() ) {
			return array(
				'error' => array(
					'code'    => 'network_active',
					'message' => 'Plugin is active network-wide. Use network_wide=true to deactivate.',
				),
			);
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

		// Attempt to deactivate the plugin
		if ( $network_wide && \is_multisite() ) {
			\deactivate_plugins( $plugin_file, $silent, true );
		} else {
			\deactivate_plugins( $plugin_file, $silent, false );
		}

		// Verify deactivation was successful
		$is_still_active = $network_wide
			? ( \is_multisite() && \is_plugin_active_for_network( $plugin_file ) )
			: \is_plugin_active( $plugin_file );

		if ( $is_still_active ) {
			return array(
				'error' => array(
					'code'    => 'deactivation_failed',
					'message' => 'Plugin deactivation failed for unknown reason.',
				),
			);
		}

		return array(
			'deactivated' => true,
			'message'     => $network_wide ? 'Plugin deactivated network-wide.' : 'Plugin deactivated successfully.',
			'plugin_info' => array(
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
			),
		);
	}
}
