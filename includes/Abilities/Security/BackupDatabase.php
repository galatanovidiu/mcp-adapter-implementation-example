<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\Security;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class BackupDatabase implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'core/backup-database',
			array(
				'label'               => 'Backup Database',
				'description'         => 'Create a backup of the WordPress database for security and maintenance purposes.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_uploads' => array(
							'type'        => 'boolean',
							'description' => 'Include uploads directory in backup (if supported).',
							'default'     => false,
						),
						'compression' => array(
							'type'        => 'string',
							'description' => 'Compression method for backup file.',
							'enum'        => array( 'none', 'gzip' ),
							'default'     => 'gzip',
						),
						'exclude_tables' => array(
							'type'        => 'array',
							'description' => 'Tables to exclude from backup (without prefix).',
							'items'       => array( 'type' => 'string' ),
							'default'     => array(),
						),
						'backup_name' => array(
							'type'        => 'string',
							'description' => 'Custom name for backup file (optional).',
							'pattern'     => '^[a-zA-Z0-9_-]+$',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'backup_info' => array(
							'type'       => 'object',
							'properties' => array(
								'filename'     => array( 'type' => 'string' ),
								'filepath'     => array( 'type' => 'string' ),
								'size'         => array( 'type' => 'integer' ),
								'size_human'   => array( 'type' => 'string' ),
								'created_at'   => array( 'type' => 'string' ),
								'compression'  => array( 'type' => 'string' ),
								'tables_count' => array( 'type' => 'integer' ),
							),
						),
						'database_info' => array(
							'type'       => 'object',
							'properties' => array(
								'total_tables'    => array( 'type' => 'integer' ),
								'backed_up_tables' => array( 'type' => 'integer' ),
								'excluded_tables'  => array( 'type' => 'array' ),
								'total_rows'      => array( 'type' => 'integer' ),
								'database_size'   => array( 'type' => 'string' ),
							),
						),
						'performance' => array(
							'type'       => 'object',
							'properties' => array(
								'duration'    => array( 'type' => 'number' ),
								'memory_used' => array( 'type' => 'string' ),
								'peak_memory' => array( 'type' => 'string' ),
							),
						),
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
					'categories' => array( 'security', 'system' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
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
		$include_uploads = $input['include_uploads'] ?? false;
		$compression = $input['compression'] ?? 'gzip';
		$exclude_tables = $input['exclude_tables'] ?? array();
		$backup_name = $input['backup_name'] ?? '';

		$start_time = microtime( true );
		$start_memory = memory_get_usage();

		$result = array(
			'success'         => false,
			'backup_info'     => array(),
			'database_info'   => array(),
			'performance'     => array(),
			'recommendations' => array(),
			'message'         => '',
		);

		// Create backup directory if it doesn't exist
		$backup_dir = WP_CONTENT_DIR . '/backups';
		if ( ! is_dir( $backup_dir ) ) {
			if ( ! wp_mkdir_p( $backup_dir ) ) {
				$result['message'] = 'Could not create backup directory.';
				return $result;
			}
		}

		// Check if backup directory is writable
		if ( ! is_writable( $backup_dir ) ) {
			$result['message'] = 'Backup directory is not writable.';
			return $result;
		}

		// Generate backup filename
		$timestamp = gmdate( 'Y-m-d-H-i-s' );
		$site_name = sanitize_file_name( get_bloginfo( 'name' ) );
		$filename_base = ! empty( $backup_name ) ? $backup_name : "wp-backup-{$site_name}-{$timestamp}";
		$filename = $filename_base . '.sql';
		
		if ( $compression === 'gzip' ) {
			$filename .= '.gz';
		}

		$filepath = $backup_dir . '/' . $filename;

		// Get database information
		$db_info = self::get_database_info( $exclude_tables );
		$result['database_info'] = $db_info;

		// Create the backup
		$backup_result = self::create_database_backup( $filepath, $compression, $exclude_tables );
		
		if ( ! $backup_result['success'] ) {
			$result['message'] = $backup_result['error'];
			return $result;
		}

		// Get backup file information
		if ( file_exists( $filepath ) ) {
			$file_size = filesize( $filepath );
			$result['backup_info'] = array(
				'filename'     => $filename,
				'filepath'     => $filepath,
				'size'         => $file_size,
				'size_human'   => size_format( $file_size ),
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
				'compression'  => $compression,
				'tables_count' => $backup_result['tables_count'],
			);
		}

		// Calculate performance metrics
		$end_time = microtime( true );
		$peak_memory = memory_get_peak_usage();
		
		$result['performance'] = array(
			'duration'    => round( $end_time - $start_time, 2 ),
			'memory_used' => size_format( memory_get_usage() - $start_memory ),
			'peak_memory' => size_format( $peak_memory ),
		);

		// Generate recommendations
		$recommendations = array();
		$recommendations[] = 'Store backup files in a secure location outside the web root.';
		$recommendations[] = 'Test backup restoration process periodically.';
		$recommendations[] = 'Consider automating regular database backups.';
		
		if ( $file_size > 50 * 1024 * 1024 ) { // 50MB
			$recommendations[] = 'Large database detected. Consider excluding unnecessary tables or using incremental backups.';
		}
		
		if ( $compression === 'none' ) {
			$recommendations[] = 'Consider using compression to reduce backup file size.';
		}

		$recommendations[] = 'Keep multiple backup versions and clean up old backups regularly.';
		$recommendations[] = 'Ensure backup files are included in your off-site backup strategy.';

		$result['recommendations'] = $recommendations;
		$result['success'] = true;
		$result['message'] = sprintf(
			'Database backup completed successfully. Created %s (%s) with %d tables in %s seconds.',
			$filename,
			$result['backup_info']['size_human'],
			$result['backup_info']['tables_count'],
			$result['performance']['duration']
		);

		return $result;
	}

	private static function get_database_info( array $exclude_tables ): array {
		global $wpdb;

		// Get all tables
		$all_tables = $wpdb->get_col( "SHOW TABLES" );
		$total_tables = count( $all_tables );

		// Filter excluded tables
		$prefixed_exclude = array_map( function( $table ) use ( $wpdb ) {
			return $wpdb->prefix . $table;
		}, $exclude_tables );

		$backed_up_tables = array_filter( $all_tables, function( $table ) use ( $prefixed_exclude ) {
			return ! in_array( $table, $prefixed_exclude );
		});

		$backed_up_count = count( $backed_up_tables );

		// Count total rows
		$total_rows = 0;
		foreach ( $backed_up_tables as $table ) {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			$total_rows += (int) $count;
		}

		// Get database size
		$database_name = DB_NAME;
		$size_result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb
				FROM information_schema.tables 
				WHERE table_schema = %s",
				$database_name
			)
		);

		$database_size = $size_result ? $size_result->size_mb . ' MB' : 'Unknown';

		return array(
			'total_tables'     => $total_tables,
			'backed_up_tables' => $backed_up_count,
			'excluded_tables'  => $exclude_tables,
			'total_rows'       => $total_rows,
			'database_size'    => $database_size,
		);
	}

	private static function create_database_backup( string $filepath, string $compression, array $exclude_tables ): array {
		global $wpdb;

		$tables_count = 0;

		try {
			// Open file handle
			if ( $compression === 'gzip' ) {
				$handle = gzopen( $filepath, 'w' );
			} else {
				$handle = fopen( $filepath, 'w' );
			}

			if ( ! $handle ) {
				return array(
					'success' => false,
					'error'   => 'Could not open backup file for writing.',
					'tables_count' => 0,
				);
			}

			// Write backup header
			$header = self::get_backup_header();
			self::write_to_backup( $handle, $header, $compression );

			// Get all tables
			$all_tables = $wpdb->get_col( "SHOW TABLES" );

			// Filter excluded tables
			$prefixed_exclude = array_map( function( $table ) use ( $wpdb ) {
				return $wpdb->prefix . $table;
			}, $exclude_tables );

			$tables_to_backup = array_filter( $all_tables, function( $table ) use ( $prefixed_exclude ) {
				return ! in_array( $table, $prefixed_exclude );
			});

			// Backup each table
			foreach ( $tables_to_backup as $table ) {
				$table_backup = self::backup_table( $table );
				if ( $table_backup ) {
					self::write_to_backup( $handle, $table_backup, $compression );
					$tables_count++;
				}
			}

			// Write backup footer
			$footer = self::get_backup_footer();
			self::write_to_backup( $handle, $footer, $compression );

			// Close file handle
			if ( $compression === 'gzip' ) {
				gzclose( $handle );
			} else {
				fclose( $handle );
			}

			return array(
				'success'       => true,
				'error'         => '',
				'tables_count'  => $tables_count,
			);

		} catch ( Exception $e ) {
			return array(
				'success'      => false,
				'error'        => 'Backup failed: ' . $e->getMessage(),
				'tables_count' => $tables_count,
			);
		}
	}

	private static function backup_table( string $table ): string {
		global $wpdb;

		$sql = '';

		// Get table structure
		$create_table = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		if ( $create_table ) {
			$sql .= "\n\n-- Table structure for table `{$table}`\n";
			$sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
			$sql .= $create_table[1] . ";\n\n";
		}

		// Get table data
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
		
		if ( ! empty( $rows ) ) {
			$sql .= "-- Dumping data for table `{$table}`\n";
			$sql .= "LOCK TABLES `{$table}` WRITE;\n";
			
			// Process rows in chunks to manage memory
			$chunk_size = 100;
			$chunks = array_chunk( $rows, $chunk_size );
			
			foreach ( $chunks as $chunk ) {
				$values = array();
				foreach ( $chunk as $row ) {
					$escaped_values = array();
					foreach ( $row as $value ) {
						if ( $value === null ) {
							$escaped_values[] = 'NULL';
						} else {
							$escaped_values[] = "'" . esc_sql( $value ) . "'";
						}
					}
					$values[] = '(' . implode( ',', $escaped_values ) . ')';
				}
				
				if ( ! empty( $values ) ) {
					$columns = '`' . implode( '`,`', array_keys( $rows[0] ) ) . '`';
					$sql .= "INSERT INTO `{$table}` ({$columns}) VALUES\n";
					$sql .= implode( ",\n", $values ) . ";\n";
				}
			}
			
			$sql .= "UNLOCK TABLES;\n\n";
		}

		return $sql;
	}

	private static function get_backup_header(): string {
		$timestamp = gmdate( 'Y-m-d H:i:s' );
		$wp_version = get_bloginfo( 'version' );
		$site_url = get_site_url();
		$db_name = DB_NAME;

		return "-- WordPress Database Backup\n" .
			   "-- Created: {$timestamp} GMT\n" .
			   "-- WordPress Version: {$wp_version}\n" .
			   "-- Site URL: {$site_url}\n" .
			   "-- Database: {$db_name}\n" .
			   "-- Generated by WordPress MCP Adapter\n\n" .
			   "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n" .
			   "SET AUTOCOMMIT = 0;\n" .
			   "START TRANSACTION;\n" .
			   "SET time_zone = \"+00:00\";\n\n";
	}

	private static function get_backup_footer(): string {
		return "\nCOMMIT;\n\n-- Backup completed\n";
	}

	private static function write_to_backup( $handle, string $data, string $compression ): void {
		if ( $compression === 'gzip' ) {
			gzwrite( $handle, $data );
		} else {
			fwrite( $handle, $data );
		}
	}
}
