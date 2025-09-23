<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\Security;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class UpdateSalts implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'core/update-salts',
			array(
				'label'               => 'Update Security Salts',
				'description'         => 'Regenerate WordPress security salts and keys for enhanced security.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'backup_current' => array(
							'type'        => 'boolean',
							'description' => 'Create backup of current salts before updating.',
							'default'     => true,
						),
						'force_logout' => array(
							'type'        => 'boolean',
							'description' => 'Force logout all users after updating salts.',
							'default'     => true,
						),
						'custom_salts' => array(
							'type'        => 'object',
							'description' => 'Custom salt values to use (leave empty for auto-generation).',
							'properties'  => array(
								'AUTH_KEY'         => array( 'type' => 'string' ),
								'SECURE_AUTH_KEY'  => array( 'type' => 'string' ),
								'LOGGED_IN_KEY'    => array( 'type' => 'string' ),
								'NONCE_KEY'        => array( 'type' => 'string' ),
								'AUTH_SALT'        => array( 'type' => 'string' ),
								'SECURE_AUTH_SALT' => array( 'type' => 'string' ),
								'LOGGED_IN_SALT'   => array( 'type' => 'string' ),
								'NONCE_SALT'       => array( 'type' => 'string' ),
							),
							'additionalProperties' => false,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'salts_updated' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'backup_created' => array( 'type' => 'boolean' ),
						'backup_location' => array( 'type' => 'string' ),
						'users_logged_out' => array( 'type' => 'integer' ),
						'wp_config_updated' => array( 'type' => 'boolean' ),
						'recommendations' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'security', 'configuration' ),
					'annotations' => array(
						'audience'             => array( 'user', 'assistant' ),
						'priority'             => 0.5,
						'readOnlyHint'         => false,
						'destructiveHint'      => true,
						'idempotentHint'       => false,
						'openWorldHint'        => false,
						'requiresConfirmation' => true,
					),
				),
			)
		);
	}

	public static function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function execute( array $input ): array {
		$backup_current = $input['backup_current'] ?? true;
		$force_logout = $input['force_logout'] ?? true;
		$custom_salts = $input['custom_salts'] ?? array();

		$result = array(
			'success'           => false,
			'salts_updated'     => array(),
			'backup_created'    => false,
			'backup_location'   => '',
			'users_logged_out'  => 0,
			'wp_config_updated' => false,
			'recommendations'   => array(),
			'message'           => '',
		);

		// Find wp-config.php
		$wp_config_path = self::find_wp_config();
		if ( ! $wp_config_path ) {
			$result['message'] = 'Could not locate wp-config.php file.';
			return $result;
		}

		// Check if wp-config.php is writable
		if ( ! is_writable( $wp_config_path ) ) {
			$result['message'] = 'wp-config.php is not writable. Cannot update salts.';
			$result['recommendations'][] = 'Make wp-config.php temporarily writable to update salts.';
			return $result;
		}

		// Create backup if requested
		if ( $backup_current ) {
			$backup_result = self::backup_wp_config( $wp_config_path );
			$result['backup_created'] = $backup_result['success'];
			$result['backup_location'] = $backup_result['location'];
		}

		// Generate new salts
		$salt_keys = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);

		$new_salts = array();
		foreach ( $salt_keys as $key ) {
			if ( ! empty( $custom_salts[ $key ] ) ) {
				$new_salts[ $key ] = $custom_salts[ $key ];
			} else {
				$new_salts[ $key ] = self::generate_salt();
			}
		}

		// Update wp-config.php
		$update_result = self::update_wp_config_salts( $wp_config_path, $new_salts );
		$result['wp_config_updated'] = $update_result['success'];
		$result['salts_updated'] = $update_result['updated_keys'];

		if ( ! $update_result['success'] ) {
			$result['message'] = 'Failed to update wp-config.php: ' . $update_result['error'];
			return $result;
		}

		// Force logout all users if requested
		if ( $force_logout ) {
			$logout_count = self::force_logout_all_users();
			$result['users_logged_out'] = $logout_count;
		}

		// Generate recommendations
		$result['recommendations'][] = 'Salts updated successfully. All user sessions have been invalidated.';
		$result['recommendations'][] = 'Users will need to log in again with their credentials.';
		$result['recommendations'][] = 'Consider updating salts regularly (every 6-12 months) for optimal security.';
		$result['recommendations'][] = 'Monitor for any issues with user authentication after the update.';

		if ( $result['backup_created'] ) {
			$result['recommendations'][] = 'Backup created at: ' . $result['backup_location'];
		}

		$result['success'] = true;
		$result['message'] = sprintf(
			'Successfully updated %d security salts. %s users logged out.',
			count( $result['salts_updated'] ),
			$result['users_logged_out']
		);

		return $result;
	}

	private static function find_wp_config(): ?string {
		$wp_config_path = ABSPATH . 'wp-config.php';
		
		if ( file_exists( $wp_config_path ) ) {
			return $wp_config_path;
		}

		// Check one level up
		$wp_config_path = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $wp_config_path ) ) {
			return $wp_config_path;
		}

		return null;
	}

	private static function backup_wp_config( string $wp_config_path ): array {
		$backup_dir = WP_CONTENT_DIR . '/backups';
		if ( ! is_dir( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		$backup_filename = 'wp-config-backup-' . date( 'Y-m-d-H-i-s' ) . '.php';
		$backup_path = $backup_dir . '/' . $backup_filename;

		if ( copy( $wp_config_path, $backup_path ) ) {
			return array(
				'success'  => true,
				'location' => $backup_path,
			);
		}

		return array(
			'success'  => false,
			'location' => '',
		);
	}

	private static function generate_salt( int $length = 64 ): string {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
		$salt = '';
		$chars_length = strlen( $chars );

		for ( $i = 0; $i < $length; $i++ ) {
			$salt .= $chars[ wp_rand( 0, $chars_length - 1 ) ];
		}

		return $salt;
	}

	private static function update_wp_config_salts( string $wp_config_path, array $new_salts ): array {
		$wp_config_content = file_get_contents( $wp_config_path );
		if ( $wp_config_content === false ) {
			return array(
				'success'      => false,
				'updated_keys' => array(),
				'error'        => 'Could not read wp-config.php',
			);
		}

		$updated_keys = array();
		$original_content = $wp_config_content;

		foreach ( $new_salts as $key => $salt ) {
			// Pattern to match the define statement
			$pattern = "/define\s*\(\s*['\"]" . preg_quote( $key, '/' ) . "['\"]\s*,\s*['\"][^'\"]*['\"]\s*\)\s*;/";
			$replacement = "define( '" . $key . "', '" . addslashes( $salt ) . "' );";

			if ( preg_match( $pattern, $wp_config_content ) ) {
				$wp_config_content = preg_replace( $pattern, $replacement, $wp_config_content );
				$updated_keys[] = $key;
			} else {
				// If the salt doesn't exist, add it before the "That's all" comment
				$insert_pattern = "/\/\*\*#@\+\s*\*\s*That's all, stop editing!/";
				if ( preg_match( $insert_pattern, $wp_config_content ) ) {
					$insert_replacement = $replacement . "\n\n/**#@+\n * That's all, stop editing!";
					$wp_config_content = preg_replace( $insert_pattern, $insert_replacement, $wp_config_content );
					$updated_keys[] = $key;
				}
			}
		}

		// Only write if we actually changed something
		if ( $wp_config_content !== $original_content ) {
			if ( file_put_contents( $wp_config_path, $wp_config_content ) === false ) {
				return array(
					'success'      => false,
					'updated_keys' => array(),
					'error'        => 'Could not write to wp-config.php',
				);
			}
		}

		return array(
			'success'      => true,
			'updated_keys' => $updated_keys,
			'error'        => '',
		);
	}

	private static function force_logout_all_users(): int {
		global $wpdb;

		// Get count of active sessions before clearing
		$session_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'session_tokens'"
		);

		// Clear all user sessions
		$wpdb->delete(
			$wpdb->usermeta,
			array( 'meta_key' => 'session_tokens' ),
			array( '%s' )
		);

		// Also clear any user meta that might contain session data
		$wpdb->query(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '%session%'"
		);

		return (int) $session_count;
	}
}
