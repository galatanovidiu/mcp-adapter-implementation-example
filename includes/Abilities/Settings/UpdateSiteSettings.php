<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Settings;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdateSiteSettings implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/update-site-settings',
			array(
				'label'               => 'Update Site Settings',
				'description'         => 'Update WordPress site settings. Only allows updating safe, commonly modified settings to prevent site breakage.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'settings' ),
					'properties' => array(
						'settings' => array(
							'type'                 => 'object',
							'description'          => 'Settings to update, organized by category or as flat key-value pairs.',
							'additionalProperties' => true,
						),
						'validate_only' => array(
							'type'        => 'boolean',
							'description' => 'If true, only validate the settings without updating them.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'updated_settings' ),
					'properties' => array(
						'updated_settings' => array(
							'type'        => 'array',
							'description' => 'List of setting keys that were successfully updated.',
							'items'       => array( 'type' => 'string' ),
						),
						'validation_errors' => array(
							'type'        => 'array',
							'description' => 'List of validation errors if any occurred.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'setting' => array( 'type' => 'string' ),
									'error'   => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'settings', 'configuration' ),
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
	 * Check permission for updating site settings.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Execute the update site settings operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		if ( empty( $input['settings'] ) || ! \is_array( $input['settings'] ) ) {
			return array(
				'error' => array(
					'code'    => 'invalid_settings',
					'message' => 'Settings must be provided as an object.',
				),
			);
		}

		$validate_only     = ! empty( $input['validate_only'] );
		$updated_settings  = array();
		$validation_errors = array();

		// Flatten nested settings structure
		$flat_settings = self::flatten_settings( $input['settings'] );

		foreach ( $flat_settings as $setting_key => $setting_value ) {
			// Validate setting key is allowed
			if ( ! self::is_setting_allowed( $setting_key ) ) {
				$validation_errors[] = array(
					'setting' => $setting_key,
					'error'   => 'Setting is not allowed to be modified for security reasons.',
				);
				continue;
			}

			// Validate setting value
			$validation_result = self::validate_setting_value( $setting_key, $setting_value );
			if ( \is_wp_error( $validation_result ) ) {
				$validation_errors[] = array(
					'setting' => $setting_key,
					'error'   => $validation_result->get_error_message(),
				);
				continue;
			}

			// Update the setting if not in validation-only mode
			if ( ! $validate_only ) {
				$sanitized_value = self::sanitize_setting_value( $setting_key, $setting_value );
				$updated         = \update_option( $setting_key, $sanitized_value );
				if ( $updated || \get_option( $setting_key ) === $sanitized_value ) {
					$updated_settings[] = $setting_key;
				} else {
					$validation_errors[] = array(
						'setting' => $setting_key,
						'error'   => 'Failed to update setting.',
					);
				}
			} else {
				$updated_settings[] = $setting_key;
			}
		}

		$result = array(
			'updated_settings' => $updated_settings,
		);

		if ( ! empty( $validation_errors ) ) {
			$result['validation_errors'] = $validation_errors;
		}

		return $result;
	}

	/**
	 * Flatten nested settings structure.
	 *
	 * @param array $settings Settings array.
	 * @param string $prefix Prefix for nested keys.
	 * @return array Flattened settings.
	 */
	private static function flatten_settings( array $settings, string $prefix = '' ): array {
		$flat = array();

		foreach ( $settings as $key => $value ) {
			$full_key = $prefix ? $prefix . '.' . $key : $key;

			// If this is a category structure (general, reading, etc.), flatten it
			if ( \is_array( $value ) && self::is_settings_category( $key ) ) {
				$flat = array_merge( $flat, self::flatten_settings( $value, '' ) );
			} elseif ( \is_array( $value ) ) {
				$flat = array_merge( $flat, self::flatten_settings( $value, $full_key ) );
			} else {
				$flat[ $full_key ] = $value;
			}
		}

		return $flat;
	}

	/**
	 * Check if a key represents a settings category.
	 *
	 * @param string $key The key to check.
	 * @return bool Whether the key is a settings category.
	 */
	private static function is_settings_category( string $key ): bool {
		return \in_array( $key, array( 'general', 'reading', 'discussion', 'media', 'writing', 'privacy', 'permalink' ), true );
	}

	/**
	 * Check if a setting is allowed to be modified.
	 *
	 * @param string $setting_key The setting key.
	 * @return bool Whether the setting is allowed.
	 */
	private static function is_setting_allowed( string $setting_key ): bool {
		// List of allowed settings that are safe to modify
		$allowed_settings = array(
			// General settings
			'blogname',
			'blogdescription',
			'admin_email',
			'users_can_register',
			'default_role',
			'timezone_string',
			'gmt_offset',
			'date_format',
			'time_format',
			'start_of_week',
			'WPLANG',

			// Reading settings
			'show_on_front',
			'page_on_front',
			'page_for_posts',
			'posts_per_page',
			'posts_per_rss',
			'rss_use_excerpt',
			'blog_public',

			// Discussion settings
			'default_pingback_flag',
			'default_ping_status',
			'default_comment_status',
			'require_name_email',
			'comment_registration',
			'close_comments_for_old_posts',
			'close_comments_days_old',
			'thread_comments',
			'thread_comments_depth',
			'page_comments',
			'comments_per_page',
			'default_comments_page',
			'comment_order',
			'comments_notify',
			'moderation_notify',
			'comment_moderation',
			'comment_previously_approved',
			'comment_max_links',
			'moderation_keys',
			'disallowed_keys',
			'show_avatars',
			'avatar_rating',
			'avatar_default',

			// Media settings
			'thumbnail_size_w',
			'thumbnail_size_h',
			'thumbnail_crop',
			'medium_size_w',
			'medium_size_h',
			'medium_large_size_w',
			'medium_large_size_h',
			'large_size_w',
			'large_size_h',
			'uploads_use_yearmonth_folders',

			// Writing settings
			'default_category',
			'default_post_format',
			'use_balanceTags',

			// Privacy settings
			'wp_page_for_privacy_policy',

			// Permalink settings
			'permalink_structure',
			'category_base',
			'tag_base',
		);

		return \in_array( $setting_key, $allowed_settings, true );
	}

	/**
	 * Validate a setting value.
	 *
	 * @param string $setting_key The setting key.
	 * @param mixed $value The setting value.
	 * @return true|\WP_Error True if valid, WP_Error if invalid.
	 */
	private static function validate_setting_value( string $setting_key, $value ) {
		switch ( $setting_key ) {
			case 'admin_email':
				if ( ! \is_email( $value ) ) {
					return new \WP_Error( 'invalid_email', 'Admin email must be a valid email address.' );
				}
				break;

			case 'users_can_register':
			case 'rss_use_excerpt':
			case 'default_pingback_flag':
			case 'require_name_email':
			case 'comment_registration':
			case 'close_comments_for_old_posts':
			case 'thread_comments':
			case 'page_comments':
			case 'comments_notify':
			case 'moderation_notify':
			case 'comment_moderation':
			case 'comment_previously_approved':
			case 'show_avatars':
			case 'thumbnail_crop':
			case 'uploads_use_yearmonth_folders':
			case 'use_balanceTags':
				if ( ! \is_bool( $value ) && ! \in_array( $value, array( '0', '1', 0, 1 ), true ) ) {
					return new \WP_Error( 'invalid_boolean', 'Value must be boolean or 0/1.' );
				}
				break;

			case 'posts_per_page':
			case 'posts_per_rss':
			case 'close_comments_days_old':
			case 'thread_comments_depth':
			case 'comments_per_page':
			case 'comment_max_links':
			case 'thumbnail_size_w':
			case 'thumbnail_size_h':
			case 'medium_size_w':
			case 'medium_size_h':
			case 'medium_large_size_w':
			case 'medium_large_size_h':
			case 'large_size_w':
			case 'large_size_h':
			case 'page_on_front':
			case 'page_for_posts':
			case 'default_category':
			case 'default_email_category':
			case 'wp_page_for_privacy_policy':
				if ( ! \is_numeric( $value ) || (int) $value < 0 ) {
					return new \WP_Error( 'invalid_number', 'Value must be a non-negative number.' );
				}
				break;

			case 'start_of_week':
				if ( ! \is_numeric( $value ) || (int) $value < 0 || (int) $value > 6 ) {
					return new \WP_Error( 'invalid_day', 'Start of week must be between 0 (Sunday) and 6 (Saturday).' );
				}
				break;

			case 'blog_public':
				if ( ! \in_array( $value, array( '0', '1', '-1', '-2', 0, 1, -1, -2 ), true ) ) {
					return new \WP_Error( 'invalid_visibility', 'Blog visibility must be 1, 0, -1, or -2.' );
				}
				break;

			case 'show_on_front':
				if ( ! \in_array( $value, array( 'posts', 'page' ), true ) ) {
					return new \WP_Error( 'invalid_front_page', 'Show on front must be "posts" or "page".' );
				}
				break;

			case 'default_ping_status':
			case 'default_comment_status':
				if ( ! \in_array( $value, array( 'open', 'closed' ), true ) ) {
					return new \WP_Error( 'invalid_status', 'Status must be "open" or "closed".' );
				}
				break;

			case 'default_comments_page':
				if ( ! \in_array( $value, array( 'newest', 'oldest' ), true ) ) {
					return new \WP_Error( 'invalid_comments_page', 'Default comments page must be "newest" or "oldest".' );
				}
				break;

			case 'comment_order':
				if ( ! \in_array( $value, array( 'asc', 'desc' ), true ) ) {
					return new \WP_Error( 'invalid_comment_order', 'Comment order must be "asc" or "desc".' );
				}
				break;

			case 'avatar_rating':
				if ( ! \in_array( $value, array( 'G', 'PG', 'R', 'X' ), true ) ) {
					return new \WP_Error( 'invalid_avatar_rating', 'Avatar rating must be G, PG, R, or X.' );
				}
				break;

			case 'default_role':
				if ( ! \get_role( $value ) ) {
					return new \WP_Error( 'invalid_role', 'Default role does not exist.' );
				}
				break;
		}

		return true;
	}

	/**
	 * Sanitize a setting value.
	 *
	 * @param string $setting_key The setting key.
	 * @param mixed $value The setting value.
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_setting_value( string $setting_key, $value ) {
		switch ( $setting_key ) {
			case 'blogname':
			case 'blogdescription':
				return \sanitize_text_field( (string) $value );

			case 'admin_email':
				return \sanitize_email( (string) $value );

			case 'users_can_register':
			case 'rss_use_excerpt':
			case 'default_pingback_flag':
			case 'require_name_email':
			case 'comment_registration':
			case 'close_comments_for_old_posts':
			case 'thread_comments':
			case 'page_comments':
			case 'comments_notify':
			case 'moderation_notify':
			case 'comment_moderation':
			case 'comment_previously_approved':
			case 'show_avatars':
			case 'thumbnail_crop':
			case 'uploads_use_yearmonth_folders':
			case 'use_balanceTags':
				return (bool) $value ? 1 : 0;

			case 'posts_per_page':
			case 'posts_per_rss':
			case 'close_comments_days_old':
			case 'thread_comments_depth':
			case 'comments_per_page':
			case 'comment_max_links':
			case 'thumbnail_size_w':
			case 'thumbnail_size_h':
			case 'medium_size_w':
			case 'medium_size_h':
			case 'medium_large_size_w':
			case 'medium_large_size_h':
			case 'large_size_w':
			case 'large_size_h':
			case 'page_on_front':
			case 'page_for_posts':
			case 'default_category':
			case 'default_email_category':
			case 'wp_page_for_privacy_policy':
			case 'start_of_week':
			case 'blog_public':
				return (int) $value;

			case 'moderation_keys':
			case 'disallowed_keys':
				return \sanitize_textarea_field( (string) $value );

			case 'permalink_structure':
			case 'category_base':
			case 'tag_base':
				return \sanitize_text_field( (string) $value );

			default:
				return \sanitize_text_field( (string) $value );
		}
	}
}
