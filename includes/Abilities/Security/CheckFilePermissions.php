<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\Security;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class CheckFilePermissions implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'core/check-file-permissions',
			array(
				'label'               => 'Check File Permissions',
				'description'         => 'Audit WordPress file and directory permissions for security compliance.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'detailed'       => array(
							'type'        => 'boolean',
							'description' => 'Include detailed permission information for each file/directory.',
							'default'     => false,
						),
						'check_writable' => array(
							'type'        => 'boolean',
							'description' => 'Check if critical files are writable (potential security risk).',
							'default'     => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'summary'         => array(
							'type'       => 'object',
							'properties' => array(
								'total_checked'   => array( 'type' => 'integer' ),
								'warnings'        => array( 'type' => 'integer' ),
								'critical_issues' => array( 'type' => 'integer' ),
								'overall_status'  => array( 'type' => 'string' ),
							),
						),
						'wp_config'       => array(
							'type'       => 'object',
							'properties' => array(
								'permissions'    => array( 'type' => 'string' ),
								'writable'       => array( 'type' => 'boolean' ),
								'status'         => array( 'type' => 'string' ),
								'recommendation' => array( 'type' => 'string' ),
							),
						),
						'directories'     => array(
							'type'       => 'object',
							'properties' => array(
								'wp_content'  => array( 'type' => 'object' ),
								'wp_includes' => array( 'type' => 'object' ),
								'wp_admin'    => array( 'type' => 'object' ),
								'uploads'     => array( 'type' => 'object' ),
								'themes'      => array( 'type' => 'object' ),
								'plugins'     => array( 'type' => 'object' ),
							),
						),
						'critical_files'  => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'file'        => array( 'type' => 'string' ),
									'permissions' => array( 'type' => 'string' ),
									'writable'    => array( 'type' => 'boolean' ),
									'status'      => array( 'type' => 'string' ),
									'issue'       => array( 'type' => 'string' ),
								),
							),
						),
						'recommendations' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'         => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'security',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
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

	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function execute( array $input ): array {
		$detailed       = $input['detailed'] ?? false;
		$check_writable = $input['check_writable'] ?? true;

		$summary = array(
			'total_checked'   => 0,
			'warnings'        => 0,
			'critical_issues' => 0,
			'overall_status'  => 'good',
		);

		$recommendations = array();
		$critical_files  = array();
		$directories     = array();

		// Check wp-config.php
		$wp_config = self::check_wp_config( $check_writable );
		if ( $wp_config['status'] !== 'good' ) {
			if ( $wp_config['status'] === 'critical' ) {
				++$summary['critical_issues'];
			} else {
				++$summary['warnings'];
			}
		}
		++$summary['total_checked'];

		// Check critical directories
		$dirs_to_check = array(
			'wp_content'  => WP_CONTENT_DIR,
			'wp_includes' => ABSPATH . 'wp-includes',
			'wp_admin'    => ABSPATH . 'wp-admin',
			'uploads'     => wp_upload_dir()['basedir'],
			'themes'      => get_theme_root(),
			'plugins'     => WP_PLUGIN_DIR,
		);

		foreach ( $dirs_to_check as $name => $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}

			$dir_info             = self::check_directory_permissions( $path, $name, $detailed );
			$directories[ $name ] = $dir_info;
			++$summary['total_checked'];

			if ( $dir_info['status'] === 'good' ) {
				continue;
			}

			if ( $dir_info['status'] === 'critical' ) {
				++$summary['critical_issues'];
			} else {
				++$summary['warnings'];
			}
		}

		// Check critical files
		$files_to_check = array(
			'.htaccess',
			'index.php',
			'wp-load.php',
			'wp-settings.php',
		);

		foreach ( $files_to_check as $filename ) {
			$filepath = ABSPATH . $filename;
			if ( ! file_exists( $filepath ) ) {
				continue;
			}

			$file_info = self::check_file_permissions( $filepath, $filename, $check_writable );
			if ( $file_info['status'] !== 'good' || $detailed ) {
				$critical_files[] = $file_info;
			}
			++$summary['total_checked'];

			if ( $file_info['status'] === 'good' ) {
				continue;
			}

			if ( $file_info['status'] === 'critical' ) {
				++$summary['critical_issues'];
			} else {
				++$summary['warnings'];
			}
		}

		// Generate recommendations
		if ( $summary['critical_issues'] > 0 ) {
			$summary['overall_status'] = 'critical';
			$recommendations[]         = 'Critical security issues found. Review file permissions immediately.';
		} elseif ( $summary['warnings'] > 0 ) {
			$summary['overall_status'] = 'warning';
			$recommendations[]         = 'Some file permissions may need attention for optimal security.';
		}

		// Add general recommendations
		$recommendations[] = 'Regularly audit file permissions, especially after updates or plugin installations.';
		$recommendations[] = 'Ensure wp-config.php is not writable by web server.';
		$recommendations[] = 'Consider using a security plugin for ongoing monitoring.';

		return array(
			'summary'         => $summary,
			'wp_config'       => $wp_config,
			'directories'     => $directories,
			'critical_files'  => $critical_files,
			'recommendations' => $recommendations,
			'message'         => sprintf(
				'File permissions audit completed. Checked %d items with %d warnings and %d critical issues.',
				$summary['total_checked'],
				$summary['warnings'],
				$summary['critical_issues']
			),
		);
	}

	private static function check_wp_config( bool $check_writable ): array {
		$wp_config_path = ABSPATH . 'wp-config.php';

		if ( ! file_exists( $wp_config_path ) ) {
			// Check one level up
			$wp_config_path = dirname( ABSPATH ) . '/wp-config.php';
		}

		if ( ! file_exists( $wp_config_path ) ) {
			return array(
				'permissions'    => 'N/A',
				'writable'       => false,
				'status'         => 'critical',
				'recommendation' => 'wp-config.php not found. This is a critical issue.',
			);
		}

		$perms       = substr( sprintf( '%o', fileperms( $wp_config_path ) ), -4 );
		$is_writable = is_writable( $wp_config_path );

		$status         = 'good';
		$recommendation = 'File permissions are appropriate.';

		if ( $check_writable && $is_writable ) {
			$status         = 'warning';
			$recommendation = 'wp-config.php is writable. Consider changing permissions to 644 or 600.';
		}

		// Check if permissions are too open
		if ( intval( substr( $perms, -1 ) ) > 4 ) {
			$status         = 'critical';
			$recommendation = 'wp-config.php has world-readable permissions. Change to 644 or 600.';
		}

		return array(
			'permissions'    => $perms,
			'writable'       => $is_writable,
			'status'         => $status,
			'recommendation' => $recommendation,
		);
	}

	private static function check_directory_permissions( string $path, string $name, bool $detailed ): array {
		if ( ! is_dir( $path ) ) {
			return array(
				'path'        => $path,
				'permissions' => 'N/A',
				'writable'    => false,
				'status'      => 'critical',
				'issue'       => 'Directory not found',
			);
		}

		$perms       = substr( sprintf( '%o', fileperms( $path ) ), -4 );
		$is_writable = is_writable( $path );

		$status = 'good';
		$issue  = '';

		// Check for appropriate directory permissions
		$recommended_perms = array(
			'wp_content'  => '755',
			'uploads'     => '755',
			'themes'      => '755',
			'plugins'     => '755',
			'wp_includes' => '755',
			'wp_admin'    => '755',
		);

		$expected = $recommended_perms[ $name ] ?? '755';

		if ( $perms !== $expected ) {
			if ( intval( $perms ) > intval( $expected ) ) {
				$status = 'warning';
				$issue  = "Permissions ({$perms}) are more permissive than recommended ({$expected})";
			}
		}

		// Special checks for sensitive directories
		if ( in_array( $name, array( 'wp_includes', 'wp_admin' ) ) && $is_writable ) {
			$status = 'warning';
			$issue  = 'Directory should not be writable by web server for security';
		}

		$result = array(
			'path'        => $path,
			'permissions' => $perms,
			'writable'    => $is_writable,
			'status'      => $status,
		);

		if ( ! empty( $issue ) ) {
			$result['issue'] = $issue;
		}

		return $result;
	}

	private static function check_file_permissions( string $filepath, string $filename, bool $check_writable ): array {
		if ( ! file_exists( $filepath ) ) {
			return array(
				'file'        => $filename,
				'permissions' => 'N/A',
				'writable'    => false,
				'status'      => 'warning',
				'issue'       => 'File not found',
			);
		}

		$perms       = substr( sprintf( '%o', fileperms( $filepath ) ), -4 );
		$is_writable = is_writable( $filepath );

		$status = 'good';
		$issue  = '';

		// Check for overly permissive file permissions
		if ( intval( substr( $perms, -1 ) ) > 4 ) {
			$status = 'critical';
			$issue  = 'File has world-writable permissions';
		} elseif ( $check_writable && $is_writable && in_array( $filename, array( '.htaccess', 'index.php' ) ) ) {
			$status = 'warning';
			$issue  = 'File is writable by web server';
		}

		$result = array(
			'file'        => $filename,
			'permissions' => $perms,
			'writable'    => $is_writable,
			'status'      => $status,
		);

		if ( ! empty( $issue ) ) {
			$result['issue'] = $issue;
		}

		return $result;
	}
}
