<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetSystemInfo implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-system-info',
			array(
				'label'               => 'Get System Info',
				'description'         => 'Retrieve comprehensive WordPress and server system information.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_server_info' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include server information. Default: true.',
							'default'     => true,
						),
						'include_database_info' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include database information. Default: true.',
							'default'     => true,
						),
						'include_theme_info' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include active theme information. Default: true.',
							'default'     => true,
						),
						'include_plugin_info' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include plugin information. Default: false.',
							'default'     => false,
						),
						'include_constants' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include WordPress constants. Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'wordpress', 'server' ),
					'properties' => array(
						'wordpress' => array(
							'type'       => 'object',
							'properties' => array(
								'version'        => array( 'type' => 'string' ),
								'multisite'      => array( 'type' => 'boolean' ),
								'site_url'       => array( 'type' => 'string' ),
								'home_url'       => array( 'type' => 'string' ),
								'admin_url'      => array( 'type' => 'string' ),
								'language'       => array( 'type' => 'string' ),
								'timezone'       => array( 'type' => 'string' ),
								'date_format'    => array( 'type' => 'string' ),
								'time_format'    => array( 'type' => 'string' ),
								'debug_mode'     => array( 'type' => 'boolean' ),
								'memory_limit'   => array( 'type' => 'string' ),
								'max_upload'     => array( 'type' => 'string' ),
								'post_max_size'  => array( 'type' => 'string' ),
								'max_execution'  => array( 'type' => 'string' ),
							),
						),
						'server' => array(
							'type'       => 'object',
							'properties' => array(
								'software'       => array( 'type' => 'string' ),
								'php_version'    => array( 'type' => 'string' ),
								'mysql_version'  => array( 'type' => 'string' ),
								'server_ip'      => array( 'type' => 'string' ),
								'server_name'    => array( 'type' => 'string' ),
								'document_root'  => array( 'type' => 'string' ),
								'user_agent'     => array( 'type' => 'string' ),
								'https'          => array( 'type' => 'boolean' ),
							),
						),
						'database' => array(
							'type'       => 'object',
							'properties' => array(
								'name'           => array( 'type' => 'string' ),
								'host'           => array( 'type' => 'string' ),
								'charset'        => array( 'type' => 'string' ),
								'collate'        => array( 'type' => 'string' ),
								'prefix'         => array( 'type' => 'string' ),
								'size'           => array( 'type' => 'string' ),
								'tables_count'   => array( 'type' => 'integer' ),
							),
						),
						'theme' => array(
							'type'       => 'object',
							'properties' => array(
								'name'           => array( 'type' => 'string' ),
								'version'        => array( 'type' => 'string' ),
								'author'         => array( 'type' => 'string' ),
								'template'       => array( 'type' => 'string' ),
								'stylesheet'     => array( 'type' => 'string' ),
								'parent_theme'   => array( 'type' => 'string' ),
							),
						),
						'plugins' => array(
							'type'       => 'object',
							'properties' => array(
								'active_count'   => array( 'type' => 'integer' ),
								'inactive_count' => array( 'type' => 'integer' ),
								'total_count'    => array( 'type' => 'integer' ),
								'must_use_count' => array( 'type' => 'integer' ),
								'active_plugins' => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'name'    => array( 'type' => 'string' ),
											'version' => array( 'type' => 'string' ),
											'file'    => array( 'type' => 'string' ),
										),
									),
								),
							),
						),
						'constants' => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'string' ),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'system', 'monitoring' ),
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
	 * Check permission for getting system information.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Execute the get system info operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$include_server_info = (bool) ( $input['include_server_info'] ?? true );
		$include_database_info = (bool) ( $input['include_database_info'] ?? true );
		$include_theme_info = (bool) ( $input['include_theme_info'] ?? true );
		$include_plugin_info = (bool) ( $input['include_plugin_info'] ?? false );
		$include_constants = (bool) ( $input['include_constants'] ?? false );

		$result = array();

		// WordPress Information
		$result['wordpress'] = array(
			'version'       => \get_bloginfo( 'version' ),
			'multisite'     => \is_multisite(),
			'site_url'      => \site_url(),
			'home_url'      => \home_url(),
			'admin_url'     => \admin_url(),
			'language'      => \get_locale(),
			'timezone'      => \get_option( 'timezone_string' ) ?: \get_option( 'gmt_offset' ),
			'date_format'   => \get_option( 'date_format' ),
			'time_format'   => \get_option( 'time_format' ),
			'debug_mode'    => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'memory_limit'  => \ini_get( 'memory_limit' ),
			'max_upload'    => \size_format( \wp_max_upload_size() ),
			'post_max_size' => \ini_get( 'post_max_size' ),
			'max_execution' => \ini_get( 'max_execution_time' ) . 's',
		);

		// Server Information
		if ( $include_server_info ) {
			global $wpdb;
			
			$result['server'] = array(
				'software'      => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
				'php_version'   => \phpversion(),
				'mysql_version' => $wpdb->db_version(),
				'server_ip'     => isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : 'Unknown',
				'server_name'   => isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'Unknown',
				'document_root' => isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : 'Unknown',
				'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown',
				'https'         => \is_ssl(),
			);
		} else {
			$result['server'] = array();
		}

		// Database Information
		if ( $include_database_info ) {
			global $wpdb;
			
			// Get database size
			$db_size = 0;
			$tables_count = 0;
			
			$tables = $wpdb->get_results( "SHOW TABLE STATUS", ARRAY_A );
			if ( $tables ) {
				$tables_count = count( $tables );
				foreach ( $tables as $table ) {
					$db_size += $table['Data_length'] + $table['Index_length'];
				}
			}

			$result['database'] = array(
				'name'         => DB_NAME,
				'host'         => DB_HOST,
				'charset'      => DB_CHARSET,
				'collate'      => DB_COLLATE,
				'prefix'       => $wpdb->prefix,
				'size'         => \size_format( $db_size ),
				'tables_count' => $tables_count,
			);
		} else {
			$result['database'] = array();
		}

		// Theme Information
		if ( $include_theme_info ) {
			$theme = \wp_get_theme();
			$parent_theme = $theme->parent();
			
			$result['theme'] = array(
				'name'         => $theme->get( 'Name' ),
				'version'      => $theme->get( 'Version' ),
				'author'       => $theme->get( 'Author' ),
				'template'     => $theme->get_template(),
				'stylesheet'   => $theme->get_stylesheet(),
				'parent_theme' => $parent_theme ? $parent_theme->get( 'Name' ) : '',
			);
		} else {
			$result['theme'] = array();
		}

		// Plugin Information
		if ( $include_plugin_info ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins = \get_plugins();
			$active_plugins = \get_option( 'active_plugins', array() );
			$mu_plugins = \get_mu_plugins();

			$active_plugin_data = array();
			foreach ( $active_plugins as $plugin_file ) {
				if ( isset( $all_plugins[ $plugin_file ] ) ) {
					$plugin = $all_plugins[ $plugin_file ];
					$active_plugin_data[] = array(
						'name'    => $plugin['Name'],
						'version' => $plugin['Version'],
						'file'    => $plugin_file,
					);
				}
			}

			$result['plugins'] = array(
				'active_count'   => count( $active_plugins ),
				'inactive_count' => count( $all_plugins ) - count( $active_plugins ),
				'total_count'    => count( $all_plugins ),
				'must_use_count' => count( $mu_plugins ),
				'active_plugins' => $active_plugin_data,
			);
		} else {
			$result['plugins'] = (object) array();
		}

		// WordPress Constants
		if ( $include_constants ) {
			$constants = array(
				'WP_DEBUG'           => defined( 'WP_DEBUG' ) ? ( WP_DEBUG ? 'true' : 'false' ) : 'undefined',
				'WP_DEBUG_LOG'       => defined( 'WP_DEBUG_LOG' ) ? ( WP_DEBUG_LOG ? 'true' : 'false' ) : 'undefined',
				'WP_DEBUG_DISPLAY'   => defined( 'WP_DEBUG_DISPLAY' ) ? ( WP_DEBUG_DISPLAY ? 'true' : 'false' ) : 'undefined',
				'WP_CACHE'           => defined( 'WP_CACHE' ) ? ( WP_CACHE ? 'true' : 'false' ) : 'undefined',
				'CONCATENATE_SCRIPTS' => defined( 'CONCATENATE_SCRIPTS' ) ? ( CONCATENATE_SCRIPTS ? 'true' : 'false' ) : 'undefined',
				'COMPRESS_SCRIPTS'   => defined( 'COMPRESS_SCRIPTS' ) ? ( COMPRESS_SCRIPTS ? 'true' : 'false' ) : 'undefined',
				'COMPRESS_CSS'       => defined( 'COMPRESS_CSS' ) ? ( COMPRESS_CSS ? 'true' : 'false' ) : 'undefined',
				'WP_LOCAL_DEV'       => defined( 'WP_LOCAL_DEV' ) ? ( constant( 'WP_LOCAL_DEV' ) ? 'true' : 'false' ) : 'undefined',
				'SCRIPT_DEBUG'       => defined( 'SCRIPT_DEBUG' ) ? ( SCRIPT_DEBUG ? 'true' : 'false' ) : 'undefined',
				'WP_MEMORY_LIMIT'    => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'undefined',
				'WP_MAX_MEMORY_LIMIT' => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : 'undefined',
				'ABSPATH'            => defined( 'ABSPATH' ) ? ABSPATH : 'undefined',
				'WP_CONTENT_DIR'     => defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : 'undefined',
				'WP_CONTENT_URL'     => defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL : 'undefined',
				'WP_PLUGIN_DIR'      => defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : 'undefined',
				'WP_PLUGIN_URL'      => defined( 'WP_PLUGIN_URL' ) ? WP_PLUGIN_URL : 'undefined',
				'UPLOADS'            => defined( 'UPLOADS' ) ? UPLOADS : 'undefined',
			);

			$result['constants'] = $constants;
		} else {
			$result['constants'] = (object) array();
		}

		return $result;
	}
}
