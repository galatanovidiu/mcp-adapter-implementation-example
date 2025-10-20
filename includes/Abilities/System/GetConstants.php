<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetConstants implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-constants',
			array(
				'label'               => 'Get Constants',
				'description'         => 'Retrieve WordPress configuration constants and their values.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'category'          => array(
							'type'        => 'string',
							'description' => 'Category of constants to retrieve. If not specified, returns all categories.',
							'enum'        => array( 'all', 'core', 'database', 'filesystem', 'debug', 'security', 'performance', 'multisite' ),
							'default'     => 'all',
						),
						'include_undefined' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include undefined constants with their default values. Default: false.',
							'default'     => false,
						),
						'filter'            => array(
							'type'        => 'string',
							'description' => 'Filter constants by name pattern.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'constants', 'summary' ),
					'properties' => array(
						'constants' => array(
							'type'       => 'object',
							'properties' => array(
								'core'        => array(
									'type'                 => 'object',
									'additionalProperties' => array( 'type' => 'string' ),
								),
								'database'    => array(
									'type'                 => 'object',
									'additionalProperties' => array( 'type' => 'string' ),
								),
								'filesystem'  => array(
									'type'                 => 'object',
									'additionalProperties' => array( 'type' => 'string' ),
								),
								'debug'       => array(
									'type'                 => 'object',
									'additionalProperties' => array( 'type' => 'string' ),
								),
								'security'    => array(
									'type'                 => 'object',
									'additionalProperties' => array( 'type' => 'string' ),
								),
								'performance' => array(
									'type'                 => 'object',
									'additionalProperties' => array( 'type' => 'string' ),
								),
								'multisite'   => array(
									'type'                 => 'object',
									'additionalProperties' => array( 'type' => 'string' ),
								),
							),
						),
						'summary'   => array(
							'type'       => 'object',
							'properties' => array(
								'total_constants' => array( 'type' => 'integer' ),
								'defined_count'   => array( 'type' => 'integer' ),
								'undefined_count' => array( 'type' => 'integer' ),
								'categories'      => array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'system',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'categories'  => array( 'system', 'configuration' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
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
	 * Check permission for getting constants.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Execute the get constants operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$category          = \sanitize_text_field( (string) ( $input['category'] ?? 'all' ) );
		$include_undefined = (bool) ( $input['include_undefined'] ?? false );
		$filter            = isset( $input['filter'] ) ? \sanitize_text_field( (string) $input['filter'] ) : '';

		$all_constants    = self::get_wordpress_constants( $include_undefined );
		$result_constants = array();
		$total_constants  = 0;
		$defined_count    = 0;
		$undefined_count  = 0;

		// Filter by category
		if ( $category === 'all' ) {
			$result_constants = $all_constants;
		} elseif ( isset( $all_constants[ $category ] ) ) {
			$result_constants = array( $category => $all_constants[ $category ] );
		} else {
			$result_constants = array();
		}

		// Apply name filter if specified
		if ( ! empty( $filter ) ) {
			foreach ( $result_constants as $cat => $constants ) {
				$filtered_constants = array();
				foreach ( $constants as $name => $value ) {
					if ( stripos( $name, $filter ) === false ) {
						continue;
					}

					$filtered_constants[ $name ] = $value;
				}
				$result_constants[ $cat ] = $filtered_constants;
			}
		}

		// Count constants
		foreach ( $result_constants as $constants ) {
			foreach ( $constants as $value ) {
				++$total_constants;
				if ( $value !== 'undefined' ) {
					++$defined_count;
				} else {
					++$undefined_count;
				}
			}
		}

		return array(
			'constants' => $result_constants,
			'summary'   => array(
				'total_constants' => $total_constants,
				'defined_count'   => $defined_count,
				'undefined_count' => $undefined_count,
				'categories'      => array_keys( $result_constants ),
			),
		);
	}

	/**
	 * Get WordPress constants organized by category.
	 *
	 * @param bool $include_undefined Whether to include undefined constants.
	 * @return array Array of constants organized by category.
	 */
	private static function get_wordpress_constants( bool $include_undefined ): array {
		$constants = array(
			'core'        => array(
				'ABSPATH'         => defined( 'ABSPATH' ) ? ABSPATH : 'undefined',
				'WPINC'           => defined( 'WPINC' ) ? WPINC : 'undefined',
				'WP_CONTENT_DIR'  => defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : 'undefined',
				'WP_CONTENT_URL'  => defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL : 'undefined',
				'WP_PLUGIN_DIR'   => defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : 'undefined',
				'WP_PLUGIN_URL'   => defined( 'WP_PLUGIN_URL' ) ? WP_PLUGIN_URL : 'undefined',
				'WPMU_PLUGIN_DIR' => defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : 'undefined',
				'WPMU_PLUGIN_URL' => defined( 'WPMU_PLUGIN_URL' ) ? WPMU_PLUGIN_URL : 'undefined',
				'WP_LANG_DIR'     => defined( 'WP_LANG_DIR' ) ? WP_LANG_DIR : 'undefined',
				'UPLOADS'         => defined( 'UPLOADS' ) ? UPLOADS : 'undefined',
			),
			'database'    => array(
				'DB_NAME'         => defined( 'DB_NAME' ) ? DB_NAME : 'undefined',
				'DB_USER'         => defined( 'DB_USER' ) ? DB_USER : 'undefined',
				'DB_PASSWORD'     => defined( 'DB_PASSWORD' ) ? '***HIDDEN***' : 'undefined',
				'DB_HOST'         => defined( 'DB_HOST' ) ? DB_HOST : 'undefined',
				'DB_CHARSET'      => defined( 'DB_CHARSET' ) ? DB_CHARSET : 'undefined',
				'DB_COLLATE'      => defined( 'DB_COLLATE' ) ? DB_COLLATE : 'undefined',
				'WP_ALLOW_REPAIR' => defined( 'WP_ALLOW_REPAIR' ) ? ( constant( 'WP_ALLOW_REPAIR' ) ? 'true' : 'false' ) : 'undefined',
			),
			'filesystem'  => array(
				'FS_METHOD'          => defined( 'FS_METHOD' ) ? constant( 'FS_METHOD' ) : 'undefined',
				'FTP_HOST'           => defined( 'FTP_HOST' ) ? constant( 'FTP_HOST' ) : 'undefined',
				'FTP_USER'           => defined( 'FTP_USER' ) ? constant( 'FTP_USER' ) : 'undefined',
				'FTP_PASS'           => defined( 'FTP_PASS' ) ? '***HIDDEN***' : 'undefined',
				'FTP_SSL'            => defined( 'FTP_SSL' ) ? ( constant( 'FTP_SSL' ) ? 'true' : 'false' ) : 'undefined',
				'DISALLOW_FILE_EDIT' => defined( 'DISALLOW_FILE_EDIT' ) ? ( constant( 'DISALLOW_FILE_EDIT' ) ? 'true' : 'false' ) : 'undefined',
				'DISALLOW_FILE_MODS' => defined( 'DISALLOW_FILE_MODS' ) ? ( constant( 'DISALLOW_FILE_MODS' ) ? 'true' : 'false' ) : 'undefined',
			),
			'debug'       => array(
				'WP_DEBUG'         => defined( 'WP_DEBUG' ) ? ( WP_DEBUG ? 'true' : 'false' ) : 'undefined',
				'WP_DEBUG_LOG'     => defined( 'WP_DEBUG_LOG' ) ? ( WP_DEBUG_LOG ? 'true' : 'false' ) : 'undefined',
				'WP_DEBUG_DISPLAY' => defined( 'WP_DEBUG_DISPLAY' ) ? ( WP_DEBUG_DISPLAY ? 'true' : 'false' ) : 'undefined',
				'SCRIPT_DEBUG'     => defined( 'SCRIPT_DEBUG' ) ? ( SCRIPT_DEBUG ? 'true' : 'false' ) : 'undefined',
				'SAVEQUERIES'      => defined( 'SAVEQUERIES' ) ? ( SAVEQUERIES ? 'true' : 'false' ) : 'undefined',
				'WP_LOCAL_DEV'     => defined( 'WP_LOCAL_DEV' ) ? ( constant( 'WP_LOCAL_DEV' ) ? 'true' : 'false' ) : 'undefined',
			),
			'security'    => array(
				'AUTH_KEY'                 => defined( 'AUTH_KEY' ) ? ( empty( AUTH_KEY ) ? 'empty' : 'defined' ) : 'undefined',
				'SECURE_AUTH_KEY'          => defined( 'SECURE_AUTH_KEY' ) ? ( empty( SECURE_AUTH_KEY ) ? 'empty' : 'defined' ) : 'undefined',
				'LOGGED_IN_KEY'            => defined( 'LOGGED_IN_KEY' ) ? ( empty( LOGGED_IN_KEY ) ? 'empty' : 'defined' ) : 'undefined',
				'NONCE_KEY'                => defined( 'NONCE_KEY' ) ? ( empty( NONCE_KEY ) ? 'empty' : 'defined' ) : 'undefined',
				'AUTH_SALT'                => defined( 'AUTH_SALT' ) ? ( empty( AUTH_SALT ) ? 'empty' : 'defined' ) : 'undefined',
				'SECURE_AUTH_SALT'         => defined( 'SECURE_AUTH_SALT' ) ? ( empty( SECURE_AUTH_SALT ) ? 'empty' : 'defined' ) : 'undefined',
				'LOGGED_IN_SALT'           => defined( 'LOGGED_IN_SALT' ) ? ( empty( LOGGED_IN_SALT ) ? 'empty' : 'defined' ) : 'undefined',
				'NONCE_SALT'               => defined( 'NONCE_SALT' ) ? ( empty( NONCE_SALT ) ? 'empty' : 'defined' ) : 'undefined',
				'FORCE_SSL_ADMIN'          => defined( 'FORCE_SSL_ADMIN' ) ? ( FORCE_SSL_ADMIN ? 'true' : 'false' ) : 'undefined',
				'DISALLOW_UNFILTERED_HTML' => defined( 'DISALLOW_UNFILTERED_HTML' ) ? ( constant( 'DISALLOW_UNFILTERED_HTML' ) ? 'true' : 'false' ) : 'undefined',
			),
			'performance' => array(
				'WP_MEMORY_LIMIT'     => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'undefined',
				'WP_MAX_MEMORY_LIMIT' => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : 'undefined',
				'WP_CACHE'            => defined( 'WP_CACHE' ) ? ( WP_CACHE ? 'true' : 'false' ) : 'undefined',
				'COMPRESS_SCRIPTS'    => defined( 'COMPRESS_SCRIPTS' ) ? ( COMPRESS_SCRIPTS ? 'true' : 'false' ) : 'undefined',
				'COMPRESS_CSS'        => defined( 'COMPRESS_CSS' ) ? ( COMPRESS_CSS ? 'true' : 'false' ) : 'undefined',
				'CONCATENATE_SCRIPTS' => defined( 'CONCATENATE_SCRIPTS' ) ? ( CONCATENATE_SCRIPTS ? 'true' : 'false' ) : 'undefined',
				'ENFORCE_GZIP'        => defined( 'ENFORCE_GZIP' ) ? ( ENFORCE_GZIP ? 'true' : 'false' ) : 'undefined',
			),
			'multisite'   => array(
				'WP_ALLOW_MULTISITE'   => defined( 'WP_ALLOW_MULTISITE' ) ? ( constant( 'WP_ALLOW_MULTISITE' ) ? 'true' : 'false' ) : 'undefined',
				'MULTISITE'            => defined( 'MULTISITE' ) ? ( MULTISITE ? 'true' : 'false' ) : 'undefined',
				'SUBDOMAIN_INSTALL'    => defined( 'SUBDOMAIN_INSTALL' ) ? ( SUBDOMAIN_INSTALL ? 'true' : 'false' ) : 'undefined',
				'DOMAIN_CURRENT_SITE'  => defined( 'DOMAIN_CURRENT_SITE' ) ? DOMAIN_CURRENT_SITE : 'undefined',
				'PATH_CURRENT_SITE'    => defined( 'PATH_CURRENT_SITE' ) ? PATH_CURRENT_SITE : 'undefined',
				'SITE_ID_CURRENT_SITE' => defined( 'SITE_ID_CURRENT_SITE' ) ? (string) SITE_ID_CURRENT_SITE : 'undefined',
				'BLOG_ID_CURRENT_SITE' => defined( 'BLOG_ID_CURRENT_SITE' ) ? (string) BLOG_ID_CURRENT_SITE : 'undefined',
			),
		);

		// Remove undefined constants if not requested
		if ( ! $include_undefined ) {
			foreach ( $constants as $category => $category_constants ) {
				$constants[ $category ] = array_filter(
					$category_constants,
					static function ( $value ) {
						return $value !== 'undefined';
					}
				);
			}
		}

		return $constants;
	}
}
