<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Plugins;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ActivatePlugin implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/activate-plugin',
			array(
				'label'               => 'Activate Plugin',
				'description'         => 'Activate a WordPress plugin by its plugin file path.',
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
							'description' => 'Activate plugin network-wide (multisite only).',
							'default'     => false,
						),
						'silent'       => array(
							'type'        => 'boolean',
							'description' => 'Suppress activation hooks and redirect.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'activated' ),
					'properties' => array(
						'activated'   => array( 'type' => 'boolean' ),
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
	 * Check permission for activating plugins.
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
	 * Execute the activate plugin operation.
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

		// Check if plugin is already active
		$is_active         = \is_plugin_active( $plugin_file );
		$is_network_active = \is_multisite() && \is_plugin_active_for_network( $plugin_file );

		if ( $is_active || $is_network_active ) {
			return array(
				'activated'   => true,
				'message'     => 'Plugin is already active.',
				'plugin_info' => array(
					'name'    => $plugin_data['Name'],
					'version' => $plugin_data['Version'],
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

		// Check if plugin file is readable
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
		if ( ! \is_readable( $plugin_path ) ) {
			return array(
				'error' => array(
					'code'    => 'plugin_not_readable',
					'message' => 'Plugin file is not readable.',
				),
			);
		}

		// Attempt to activate the plugin
		$result = \activate_plugin( $plugin_file, '', $network_wide, $silent );

		if ( \is_wp_error( $result ) ) {
			return array(
				'error' => array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
			);
		}

		// Verify activation was successful
		$is_now_active = $network_wide
			? \is_plugin_active_for_network( $plugin_file )
			: \is_plugin_active( $plugin_file );

		if ( ! $is_now_active ) {
			return array(
				'error' => array(
					'code'    => 'activation_failed',
					'message' => 'Plugin activation failed for unknown reason.',
				),
			);
		}

		return array(
			'activated'   => true,
			'message'     => $network_wide ? 'Plugin activated network-wide.' : 'Plugin activated successfully.',
			'plugin_info' => array(
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
			),
		);
	}
}
