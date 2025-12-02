<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class OptimizeDatabase implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/optimize-database',
			array(
				'label'               => 'Optimize Database',
				'description'         => 'Perform WordPress database optimization and cleanup operations.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'operations'      => array(
							'type'        => 'array',
							'description' => 'Array of optimization operations to perform. If empty, performs all safe operations.',
							'items'       => array(
								'type' => 'string',
								'enum' => array(
									'optimize_tables',
									'clean_revisions',
									'clean_spam_comments',
									'clean_trash_comments',
									'clean_transients',
									'clean_orphaned_meta',
									'clean_auto_drafts',
									'clean_trash_posts',
								),
							),
						),
						'dry_run'         => array(
							'type'        => 'boolean',
							'description' => 'Whether to perform a dry run (show what would be cleaned without actually doing it). Default: false.',
							'default'     => false,
						),
						'limit_revisions' => array(
							'type'        => 'integer',
							'description' => 'Number of revisions to keep per post when cleaning revisions. Default: 5.',
							'default'     => 5,
							'minimum'     => 0,
							'maximum'     => 50,
						),
						'older_than_days' => array(
							'type'        => 'integer',
							'description' => 'Only clean items older than this many days. Default: 30.',
							'default'     => 30,
							'minimum'     => 1,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'results' ),
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'results' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'operation'     => array( 'type' => 'string' ),
									'success'       => array( 'type' => 'boolean' ),
									'items_found'   => array( 'type' => 'integer' ),
									'items_cleaned' => array( 'type' => 'integer' ),
									'space_freed'   => array( 'type' => 'string' ),
									'message'       => array( 'type' => 'string' ),
								),
							),
						),
						'summary' => array(
							'type'       => 'object',
							'properties' => array(
								'total_operations'      => array( 'type' => 'integer' ),
								'successful_operations' => array( 'type' => 'integer' ),
								'total_items_cleaned'   => array( 'type' => 'integer' ),
								'total_space_freed'     => array( 'type' => 'string' ),
								'dry_run'               => array( 'type' => 'boolean' ),
							),
						),
						'message' => array( 'type' => 'string' ),
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
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
						'readOnlyHint'    => false,
						'destructiveHint' => true,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for database optimization.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Execute the optimize database operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		global $wpdb;

		$operations      = $input['operations'] ?? array();
		$dry_run         = (bool) ( $input['dry_run'] ?? false );
		$limit_revisions = (int) ( $input['limit_revisions'] ?? 5 );
		$older_than_days = (int) ( $input['older_than_days'] ?? 30 );

		// Default operations if none specified
		if ( empty( $operations ) ) {
			$operations = array(
				'optimize_tables',
				'clean_revisions',
				'clean_spam_comments',
				'clean_trash_comments',
				'clean_transients',
				'clean_auto_drafts',
				'clean_trash_posts',
			);
		}

		$results               = array();
		$total_items_cleaned   = 0;
		$total_space_freed     = 0;
		$successful_operations = 0;

		$cutoff_date = \date( 'Y-m-d H:i:s', strtotime( "-{$older_than_days} days" ) );

		foreach ( $operations as $operation ) {
			$result    = self::perform_operation( $operation, $dry_run, $limit_revisions, $cutoff_date );
			$results[] = $result;

			if ( ! $result['success'] ) {
				continue;
			}

			++$successful_operations;
			$total_items_cleaned += $result['items_cleaned'];
			// Parse space freed (remove 'B', 'KB', etc. and convert to bytes for summation)
			$space_bytes        = self::parse_size_to_bytes( $result['space_freed'] );
			$total_space_freed += $space_bytes;
		}

		$overall_success = $successful_operations === count( $operations );
		$message         = '';

		if ( $dry_run ) {
			$message = sprintf( 'Dry run completed: %d items would be cleaned across %d operations', $total_items_cleaned, count( $operations ) );
		} elseif ( $overall_success ) {
			$message = sprintf( 'Database optimization completed successfully: %d items cleaned, %s freed', $total_items_cleaned, \size_format( $total_space_freed ) );
		} else {
			$message = sprintf( '%d of %d operations completed successfully', $successful_operations, count( $operations ) );
		}

		return array(
			'success' => $overall_success,
			'results' => $results,
			'summary' => array(
				'total_operations'      => count( $operations ),
				'successful_operations' => $successful_operations,
				'total_items_cleaned'   => $total_items_cleaned,
				'total_space_freed'     => \size_format( $total_space_freed ),
				'dry_run'               => $dry_run,
			),
			'message' => $message,
		);
	}

	/**
	 * Perform a specific optimization operation.
	 *
	 * @param string $operation Operation to perform.
	 * @param bool   $dry_run Whether this is a dry run.
	 * @param int    $limit_revisions Number of revisions to keep.
	 * @param string $cutoff_date Date cutoff for cleaning old items.
	 * @return array Operation result.
	 */
	private static function perform_operation( string $operation, bool $dry_run, int $limit_revisions, string $cutoff_date ): array {
		global $wpdb;

		$result = array(
			'operation'     => $operation,
			'success'       => false,
			'items_found'   => 0,
			'items_cleaned' => 0,
			'space_freed'   => '0 B',
			'message'       => '',
		);

		switch ( $operation ) {
			case 'optimize_tables':
				$tables                = $wpdb->get_col( 'SHOW TABLES' );
				$result['items_found'] = count( $tables );

				if ( ! $dry_run ) {
					$optimized = 0;
					foreach ( $tables as $table ) {
						$wpdb->query( "OPTIMIZE TABLE `{$table}`" );
						++$optimized;
					}
					$result['items_cleaned'] = $optimized;
				} else {
					$result['items_cleaned'] = $result['items_found'];
				}

				$result['success'] = true;
				$result['message'] = $dry_run ? 'Would optimize all database tables' : 'Optimized all database tables';
				break;

			case 'clean_revisions':
				$revisions_query = "
					SELECT ID FROM {$wpdb->posts} 
					WHERE post_type = 'revision' 
					AND post_date < %s
					AND ID NOT IN (
						SELECT p.ID FROM {$wpdb->posts} p
						INNER JOIN (
							SELECT post_parent, MAX(post_date) as max_date
							FROM {$wpdb->posts}
							WHERE post_type = 'revision'
							GROUP BY post_parent
							ORDER BY max_date DESC
							LIMIT %d
						) recent ON p.post_parent = recent.post_parent
					)
				";

				$revisions             = $wpdb->get_col( $wpdb->prepare( $revisions_query, $cutoff_date, $limit_revisions ) );
				$result['items_found'] = count( $revisions );

				if ( ! $dry_run && ! empty( $revisions ) ) {
					$deleted = 0;
					foreach ( $revisions as $revision_id ) {
						if ( ! \wp_delete_post_revision( $revision_id ) ) {
							continue;
						}

						++$deleted;
					}
					$result['items_cleaned'] = $deleted;
				} else {
					$result['items_cleaned'] = $result['items_found'];
				}

				$result['success'] = true;
				$result['message'] = $dry_run ? "Would clean {$result['items_found']} old revisions" : "Cleaned {$result['items_cleaned']} old revisions";
				break;

			case 'clean_spam_comments':
				$spam_comments         = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam' AND comment_date < %s",
						$cutoff_date
					)
				);
				$result['items_found'] = count( $spam_comments );

				if ( ! $dry_run && ! empty( $spam_comments ) ) {
					$deleted = 0;
					foreach ( $spam_comments as $comment_id ) {
						if ( ! \wp_delete_comment( $comment_id, true ) ) {
							continue;
						}

						++$deleted;
					}
					$result['items_cleaned'] = $deleted;
				} else {
					$result['items_cleaned'] = $result['items_found'];
				}

				$result['success'] = true;
				$result['message'] = $dry_run ? "Would clean {$result['items_found']} spam comments" : "Cleaned {$result['items_cleaned']} spam comments";
				break;

			case 'clean_trash_comments':
				$trash_comments        = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'trash' AND comment_date < %s",
						$cutoff_date
					)
				);
				$result['items_found'] = count( $trash_comments );

				if ( ! $dry_run && ! empty( $trash_comments ) ) {
					$deleted = 0;
					foreach ( $trash_comments as $comment_id ) {
						if ( ! \wp_delete_comment( $comment_id, true ) ) {
							continue;
						}

						++$deleted;
					}
					$result['items_cleaned'] = $deleted;
				} else {
					$result['items_cleaned'] = $result['items_found'];
				}

				$result['success'] = true;
				$result['message'] = $dry_run ? "Would clean {$result['items_found']} trashed comments" : "Cleaned {$result['items_cleaned']} trashed comments";
				break;

			case 'clean_transients':
				$expired_transients    = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < %d",
						time()
					)
				);
				$result['items_found'] = count( $expired_transients );

				if ( ! $dry_run && ! empty( $expired_transients ) ) {
					$deleted = 0;
					foreach ( $expired_transients as $transient ) {
						$transient_name = str_replace( '_transient_timeout_', '', $transient );
						if ( ! \delete_transient( $transient_name ) ) {
							continue;
						}

						++$deleted;
					}
					$result['items_cleaned'] = $deleted;
				} else {
					$result['items_cleaned'] = $result['items_found'];
				}

				$result['success'] = true;
				$result['message'] = $dry_run ? "Would clean {$result['items_found']} expired transients" : "Cleaned {$result['items_cleaned']} expired transients";
				break;

			case 'clean_orphaned_meta':
				$orphaned_postmeta     = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL"
					)
				);
				$result['items_found'] = (int) $orphaned_postmeta;

				if ( ! $dry_run && $orphaned_postmeta > 0 ) {
					$deleted                 = $wpdb->query(
						"DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL"
					);
					$result['items_cleaned'] = (int) $deleted;
				} else {
					$result['items_cleaned'] = $result['items_found'];
				}

				$result['success'] = true;
				$result['message'] = $dry_run ? "Would clean {$result['items_found']} orphaned post meta" : "Cleaned {$result['items_cleaned']} orphaned post meta";
				break;

			case 'clean_auto_drafts':
				$auto_drafts           = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_date < %s",
						$cutoff_date
					)
				);
				$result['items_found'] = count( $auto_drafts );

				if ( ! $dry_run && ! empty( $auto_drafts ) ) {
					$deleted = 0;
					foreach ( $auto_drafts as $post_id ) {
						if ( ! \wp_delete_post( $post_id, true ) ) {
							continue;
						}

						++$deleted;
					}
					$result['items_cleaned'] = $deleted;
				} else {
					$result['items_cleaned'] = $result['items_found'];
				}

				$result['success'] = true;
				$result['message'] = $dry_run ? "Would clean {$result['items_found']} auto-draft posts" : "Cleaned {$result['items_cleaned']} auto-draft posts";
				break;

			case 'clean_trash_posts':
				$trash_posts           = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_date < %s",
						$cutoff_date
					)
				);
				$result['items_found'] = count( $trash_posts );

				if ( ! $dry_run && ! empty( $trash_posts ) ) {
					$deleted = 0;
					foreach ( $trash_posts as $post_id ) {
						if ( ! \wp_delete_post( $post_id, true ) ) {
							continue;
						}

						++$deleted;
					}
					$result['items_cleaned'] = $deleted;
				} else {
					$result['items_cleaned'] = $result['items_found'];
				}

				$result['success'] = true;
				$result['message'] = $dry_run ? "Would clean {$result['items_found']} trashed posts" : "Cleaned {$result['items_cleaned']} trashed posts";
				break;

			default:
				$result['message'] = 'Unknown operation: ' . $operation;
				break;
		}

		// Estimate space freed (rough calculation)
		if ( $result['success'] && $result['items_cleaned'] > 0 ) {
			$estimated_bytes       = $result['items_cleaned'] * 1024; // Rough estimate
			$result['space_freed'] = \size_format( $estimated_bytes );
		}

		return $result;
	}

	/**
	 * Parse size string to bytes.
	 *
	 * @param string $size Size string (e.g., "1.5 KB").
	 * @return int Size in bytes.
	 */
	private static function parse_size_to_bytes( string $size ): int {
		if ( preg_match( '/^(\d+(?:\.\d+)?)\s*([KMGT]?B?)$/i', trim( $size ), $matches ) ) {
			$number = (float) $matches[1];
			$unit   = strtoupper( $matches[2] );

			switch ( $unit ) {
				case 'TB':
					return (int) ( $number * 1024 * 1024 * 1024 * 1024 );
				case 'GB':
					return (int) ( $number * 1024 * 1024 * 1024 );
				case 'MB':
					return (int) ( $number * 1024 * 1024 );
				case 'KB':
					return (int) ( $number * 1024 );
				default:
					return (int) $number;
			}
		}

		return 0;
	}
}
