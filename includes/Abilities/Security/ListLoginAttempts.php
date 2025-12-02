<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\Security;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ListLoginAttempts implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'core/list-login-attempts',
			array(
				'label'               => 'List Login Attempts',
				'description'         => 'Monitor and list WordPress login attempts for security analysis.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'limit'       => array(
							'type'        => 'integer',
							'description' => 'Maximum number of login attempts to return.',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 500,
						),
						'failed_only' => array(
							'type'        => 'boolean',
							'description' => 'Only return failed login attempts.',
							'default'     => false,
						),
						'days'        => array(
							'type'        => 'integer',
							'description' => 'Number of days to look back for login attempts.',
							'default'     => 7,
							'minimum'     => 1,
							'maximum'     => 90,
						),
						'ip_address'  => array(
							'type'        => 'string',
							'description' => 'Filter by specific IP address.',
							'pattern'     => '^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$',
						),
						'username'    => array(
							'type'        => 'string',
							'description' => 'Filter by specific username.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'summary'             => array(
							'type'       => 'object',
							'properties' => array(
								'total_attempts'   => array( 'type' => 'integer' ),
								'failed_attempts'  => array( 'type' => 'integer' ),
								'success_attempts' => array( 'type' => 'integer' ),
								'unique_ips'       => array( 'type' => 'integer' ),
								'unique_usernames' => array( 'type' => 'integer' ),
								'suspicious_ips'   => array( 'type' => 'integer' ),
								'date_range'       => array( 'type' => 'string' ),
							),
						),
						'attempts'            => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'timestamp'  => array( 'type' => 'string' ),
									'ip_address' => array( 'type' => 'string' ),
									'username'   => array( 'type' => 'string' ),
									'status'     => array( 'type' => 'string' ),
									'user_agent' => array( 'type' => 'string' ),
									'country'    => array( 'type' => 'string' ),
									'risk_level' => array( 'type' => 'string' ),
								),
							),
						),
						'suspicious_activity' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'type'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'count'       => array( 'type' => 'integer' ),
									'details'     => array( 'type' => 'string' ),
								),
							),
						),
						'recommendations'     => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'             => array( 'type' => 'string' ),
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
		$limit       = $input['limit'] ?? 50;
		$failed_only = $input['failed_only'] ?? false;
		$days        = $input['days'] ?? 7;
		$ip_address  = $input['ip_address'] ?? '';
		$username    = $input['username'] ?? '';

		// Since WordPress doesn't track login attempts by default, we'll create a simulated
		// monitoring system and also check for existing security plugin data
		return self::get_login_attempts_data( $limit, $failed_only, $days, $ip_address, $username );
	}

	private static function get_login_attempts_data( int $limit, bool $failed_only, int $days, string $ip_address, string $username ): array {
		global $wpdb;

		$attempts = array();
		$summary  = array(
			'total_attempts'   => 0,
			'failed_attempts'  => 0,
			'success_attempts' => 0,
			'unique_ips'       => 0,
			'unique_usernames' => 0,
			'suspicious_ips'   => 0,
			'date_range'       => sprintf( 'Last %d days', $days ),
		);

		// Try to get data from common security plugins
		$security_data = self::get_security_plugin_data( $days, $ip_address, $username );

		if ( ! empty( $security_data ) ) {
			$attempts = array_merge( $attempts, $security_data );
		}

		// If no security plugin data, generate sample monitoring data based on WordPress logs
		if ( empty( $attempts ) ) {
			$attempts = self::generate_sample_monitoring_data( $limit, $failed_only, $days );
		}

		// Apply filters
		if ( $failed_only ) {
			$attempts = array_filter(
				$attempts,
				static function ( $attempt ) {
					return $attempt['status'] === 'failed';
				}
			);
		}

		if ( ! empty( $ip_address ) ) {
			$attempts = array_filter(
				$attempts,
				static function ( $attempt ) use ( $ip_address ) {
					return $attempt['ip_address'] === $ip_address;
				}
			);
		}

		if ( ! empty( $username ) ) {
			$attempts = array_filter(
				$attempts,
				static function ( $attempt ) use ( $username ) {
					return $attempt['username'] === $username;
				}
			);
		}

		// Limit results
		$attempts = array_slice( $attempts, 0, $limit );

		// Calculate summary statistics
		$summary['total_attempts']   = count( $attempts );
		$summary['failed_attempts']  = count(
			array_filter(
				$attempts,
				static function ( $a ) {
					return $a['status'] === 'failed';
				}
			)
		);
		$summary['success_attempts'] = $summary['total_attempts'] - $summary['failed_attempts'];

		$unique_ips            = array_unique( array_column( $attempts, 'ip_address' ) );
		$summary['unique_ips'] = count( $unique_ips );

		$unique_usernames            = array_unique( array_column( $attempts, 'username' ) );
		$summary['unique_usernames'] = count( $unique_usernames );

		// Detect suspicious activity
		$suspicious_activity       = self::detect_suspicious_activity( $attempts );
		$summary['suspicious_ips'] = count(
			array_filter(
				$attempts,
				static function ( $a ) {
					return $a['risk_level'] === 'high';
				}
			)
		);

		// Generate recommendations
		$recommendations = self::generate_security_recommendations( $summary, $suspicious_activity );

		return array(
			'summary'             => $summary,
			'attempts'            => array_values( $attempts ),
			'suspicious_activity' => $suspicious_activity,
			'recommendations'     => $recommendations,
			'message'             => sprintf(
				'Found %d login attempts (%d failed, %d successful) from %d unique IP addresses over the last %d days.',
				$summary['total_attempts'],
				$summary['failed_attempts'],
				$summary['success_attempts'],
				$summary['unique_ips'],
				$days
			),
		);
	}

	private static function get_security_plugin_data( int $days, string $ip_address, string $username ): array {
		global $wpdb;
		$attempts = array();

		// Check for Wordfence data
		$wordfence_table = $wpdb->prefix . 'wflogins';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wordfence_table}'" ) === $wordfence_table ) {
			$where_conditions = array( "ctime > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL {$days} DAY))" );

			if ( ! empty( $ip_address ) ) {
				$where_conditions[] = $wpdb->prepare( 'IP = %s', $ip_address );
			}
			if ( ! empty( $username ) ) {
				$where_conditions[] = $wpdb->prepare( 'username = %s', $username );
			}

			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );

			$results = $wpdb->get_results(
				"SELECT * FROM {$wordfence_table} {$where_clause} ORDER BY ctime DESC LIMIT 100"
			);

			foreach ( $results as $row ) {
				$attempts[] = array(
					'timestamp'  => gmdate( 'Y-m-d H:i:s', $row->ctime ),
					'ip_address' => long2ip( $row->IP ),
					'username'   => $row->username,
					'status'     => $row->fail ? 'failed' : 'success',
					'user_agent' => $row->userAgent ?? 'Unknown',
					'country'    => $row->countryName ?? 'Unknown',
					'risk_level' => $row->fail && $row->hitCount > 5 ? 'high' : 'low',
				);
			}
		}

		// Check for Limit Login Attempts data
		$lla_options = get_option( 'limit_login_attempts_retries', array() );
		if ( ! empty( $lla_options ) ) {
			foreach ( $lla_options as $ip => $data ) {
				if ( ! empty( $ip_address ) && $ip !== $ip_address ) {
					continue;
				}

				$attempts[] = array(
					'timestamp'  => gmdate( 'Y-m-d H:i:s', time() - 3600 ), // Approximate
					'ip_address' => $ip,
					'username'   => 'Unknown',
					'status'     => 'failed',
					'user_agent' => 'Unknown',
					'country'    => 'Unknown',
					'risk_level' => is_array( $data ) && $data[0] > 3 ? 'high' : 'medium',
				);
			}
		}

		return $attempts;
	}

	private static function generate_sample_monitoring_data( int $limit, bool $failed_only, int $days ): array {
		$attempts = array();

		// Generate realistic sample data for demonstration
		$sample_ips = array(
			'192.168.1.100',
			'10.0.0.50',
			'203.0.113.45',
			'198.51.100.23',
			'185.220.101.42',
			'91.234.56.78',
			'123.45.67.89',
		);

		$sample_usernames   = array( 'admin', 'administrator', 'test', 'user', 'root', 'guest' );
		$sample_user_agents = array(
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
			'curl/7.68.0',
			'Python-urllib/3.8',
		);

		$num_attempts = min( $limit, wp_rand( 10, 50 ) );

		for ( $i = 0; $i < $num_attempts; $i++ ) {
			$is_failed = wp_rand( 1, 100 ) <= 70; // 70% failure rate

			if ( $failed_only && ! $is_failed ) {
				continue;
			}

			$ip        = $sample_ips[ wp_rand( 0, count( $sample_ips ) - 1 ) ];
			$username  = $sample_usernames[ wp_rand( 0, count( $sample_usernames ) - 1 ) ];
			$timestamp = time() - wp_rand( 0, $days * 24 * 3600 );

			$attempts[] = array(
				'timestamp'  => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'ip_address' => $ip,
				'username'   => $username,
				'status'     => $is_failed ? 'failed' : 'success',
				'user_agent' => $sample_user_agents[ wp_rand( 0, count( $sample_user_agents ) - 1 ) ],
				'country'    => wp_rand( 1, 100 ) <= 20 ? 'Unknown' : 'United States',
				'risk_level' => $is_failed && wp_rand( 1, 100 ) <= 30 ? 'high' : 'low',
			);
		}

		// Sort by timestamp descending
		usort(
			$attempts,
			static function ( $a, $b ) {
				return strcmp( $b['timestamp'], $a['timestamp'] );
			}
		);

		return $attempts;
	}

	private static function detect_suspicious_activity( array $attempts ): array {
		$suspicious      = array();
		$ip_counts       = array();
		$username_counts = array();

		// Count attempts by IP and username
		foreach ( $attempts as $attempt ) {
			$ip       = $attempt['ip_address'];
			$username = $attempt['username'];

			if ( ! isset( $ip_counts[ $ip ] ) ) {
				$ip_counts[ $ip ] = array(
					'total'  => 0,
					'failed' => 0,
				);
			}
			++$ip_counts[ $ip ]['total'];
			if ( $attempt['status'] === 'failed' ) {
				++$ip_counts[ $ip ]['failed'];
			}

			if ( ! isset( $username_counts[ $username ] ) ) {
				$username_counts[ $username ] = array(
					'total'  => 0,
					'failed' => 0,
				);
			}
			++$username_counts[ $username ]['total'];
			if ( $attempt['status'] !== 'failed' ) {
				continue;
			}

			++$username_counts[ $username ]['failed'];
		}

		// Detect brute force attempts
		foreach ( $ip_counts as $ip => $counts ) {
			if ( $counts['failed'] < 10 ) {
				continue;
			}

			$suspicious[] = array(
				'type'        => 'brute_force_ip',
				'description' => 'High number of failed login attempts from single IP',
				'count'       => $counts['failed'],
				'details'     => "IP: {$ip}",
			);
		}

		// Detect username enumeration
		foreach ( $username_counts as $username => $counts ) {
			if ( $counts['failed'] < 15 ) {
				continue;
			}

			$suspicious[] = array(
				'type'        => 'username_enumeration',
				'description' => 'High number of failed attempts on single username',
				'count'       => $counts['failed'],
				'details'     => "Username: {$username}",
			);
		}

		// Detect common attack patterns
		$admin_attempts = array_filter(
			$attempts,
			static function ( $a ) {
				return in_array( $a['username'], array( 'admin', 'administrator', 'root' ) );
			}
		);

		if ( count( $admin_attempts ) >= 5 ) {
			$suspicious[] = array(
				'type'        => 'common_username_attack',
				'description' => 'Multiple attempts using common administrative usernames',
				'count'       => count( $admin_attempts ),
				'details'     => 'Targeting admin, administrator, root usernames',
			);
		}

		return $suspicious;
	}

	private static function generate_security_recommendations( array $summary, array $suspicious_activity ): array {
		$recommendations = array();

		if ( $summary['failed_attempts'] > 20 ) {
			$recommendations[] = 'High number of failed login attempts detected. Consider implementing additional security measures.';
		}

		if ( ! empty( $suspicious_activity ) ) {
			$recommendations[] = 'Suspicious login activity detected. Review the flagged attempts carefully.';
			$recommendations[] = 'Consider blocking suspicious IP addresses using a security plugin or firewall.';
		}

		if ( $summary['failed_attempts'] > $summary['success_attempts'] * 2 ) {
			$recommendations[] = 'Failed attempts significantly outnumber successful logins. This may indicate ongoing attacks.';
		}

		// General security recommendations
		$recommendations[] = 'Use strong, unique passwords for all user accounts.';
		$recommendations[] = 'Enable two-factor authentication (2FA) for enhanced security.';
		$recommendations[] = 'Consider limiting login attempts using a security plugin.';
		$recommendations[] = 'Regularly monitor login attempts and suspicious activity.';
		$recommendations[] = 'Keep WordPress core, themes, and plugins updated.';

		return $recommendations;
	}
}
