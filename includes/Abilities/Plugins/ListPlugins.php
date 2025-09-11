<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Plugins;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListPlugins implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-plugins',
			array(
				'label'               => 'List Plugins',
				'description'         => 'List all installed WordPress plugins with their status, version, and metadata.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'description' => 'Filter plugins by status.',
							'enum'        => array( 'all', 'active', 'inactive', 'must-use', 'dropins' ),
							'default'     => 'all',
						),
						'search' => array(
							'type'        => 'string',
							'description' => 'Search term to filter plugins by name or description.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'plugins' ),
					'properties' => array(
						'plugins' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'plugin_file', 'name', 'version', 'is_active' ),
								'properties' => array(
									'plugin_file'     => array( 'type' => 'string' ),
									'name'            => array( 'type' => 'string' ),
									'version'         => array( 'type' => 'string' ),
									'description'     => array( 'type' => 'string' ),
									'author'          => array( 'type' => 'string' ),
									'author_uri'      => array( 'type' => 'string' ),
									'plugin_uri'      => array( 'type' => 'string' ),
									'text_domain'     => array( 'type' => 'string' ),
									'domain_path'     => array( 'type' => 'string' ),
									'network'         => array( 'type' => 'boolean' ),
									'requires_wp'     => array( 'type' => 'string' ),
									'requires_php'    => array( 'type' => 'string' ),
									'is_active'       => array( 'type' => 'boolean' ),
									'is_network_active' => array( 'type' => 'boolean' ),
									'is_must_use'     => array( 'type' => 'boolean' ),
									'is_dropin'       => array( 'type' => 'boolean' ),
									'update_available' => array( 'type' => 'boolean' ),
									'new_version'     => array( 'type' => 'string' ),
								),
							),
						),
						'total' => array(
							'type'        => 'integer',
							'description' => 'Total number of plugins matching the criteria',
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'plugins', 'management' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.9,
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
	 * Check permission for listing plugins.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'activate_plugins' );
	}

	/**
	 * Execute the list plugins operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$status = isset( $input['status'] ) ? \sanitize_key( (string) $input['status'] ) : 'all';
		$search = isset( $input['search'] ) ? \sanitize_text_field( (string) $input['search'] ) : '';

		// Ensure plugin functions are available
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = \get_plugins();
		$mu_plugins  = \get_mu_plugins();
		$dropins     = \get_dropins();

		$active_plugins         = (array) \get_option( 'active_plugins', array() );
		$network_active_plugins = \is_multisite() ? (array) \get_site_option( 'active_sitewide_plugins', array() ) : array();

		// Get update information
		$update_plugins = \get_site_transient( 'update_plugins' );

		$plugins = array();

		// Process regular plugins
		if ( in_array( $status, array( 'all', 'active', 'inactive' ), true ) ) {
			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				$is_active         = \in_array( $plugin_file, $active_plugins, true );
				$is_network_active = isset( $network_active_plugins[ $plugin_file ] );

				// Filter by status
				if ( 'active' === $status && ! $is_active && ! $is_network_active ) {
					continue;
				}
				if ( 'inactive' === $status && ( $is_active || $is_network_active ) ) {
					continue;
				}

				// Search filter
				if ( ! empty( $search ) ) {
					$haystack = \strtolower( $plugin_data['Name'] . ' ' . $plugin_data['Description'] );
					if ( false === \strpos( $haystack, \strtolower( $search ) ) ) {
						continue;
					}
				}

				// Check for updates
				$update_available = false;
				$new_version      = '';
				if ( isset( $update_plugins->response[ $plugin_file ] ) ) {
					$update_available = true;
					$new_version      = $update_plugins->response[ $plugin_file ]->new_version ?? '';
				}

				$plugins[] = array(
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
					'is_active'         => $is_active || $is_network_active,
					'is_network_active' => $is_network_active,
					'is_must_use'       => false,
					'is_dropin'         => false,
					'update_available'  => $update_available,
					'new_version'       => $new_version,
				);
			}
		}

		// Process must-use plugins
		if ( in_array( $status, array( 'all', 'must-use' ), true ) ) {
			foreach ( $mu_plugins as $plugin_file => $plugin_data ) {
				// Search filter
				if ( ! empty( $search ) ) {
					$haystack = \strtolower( $plugin_data['Name'] . ' ' . $plugin_data['Description'] );
					if ( false === \strpos( $haystack, \strtolower( $search ) ) ) {
						continue;
					}
				}

				$plugins[] = array(
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
					'is_active'         => true, // MU plugins are always active
					'is_network_active' => false,
					'is_must_use'       => true,
					'is_dropin'         => false,
					'update_available'  => false,
					'new_version'       => '',
				);
			}
		}

		// Process drop-in plugins
		if ( in_array( $status, array( 'all', 'dropins' ), true ) ) {
			foreach ( $dropins as $plugin_file => $plugin_data ) {
				// Search filter
				if ( ! empty( $search ) ) {
					$haystack = \strtolower( $plugin_data['Name'] . ' ' . $plugin_data['Description'] );
					if ( false === \strpos( $haystack, \strtolower( $search ) ) ) {
						continue;
					}
				}

				$plugins[] = array(
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
					'is_active'         => true, // Drop-ins are always active
					'is_network_active' => false,
					'is_must_use'       => false,
					'is_dropin'         => true,
					'update_available'  => false,
					'new_version'       => '',
				);
			}
		}

		return array(
			'plugins' => $plugins,
			'total'   => count( $plugins ),
		);
	}
}
