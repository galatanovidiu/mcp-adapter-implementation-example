<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Settings;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetSiteSettings implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-site-settings',
			array(
				'label'               => 'Get Site Settings',
				'description'         => 'Retrieve WordPress site settings organized by category (general, reading, discussion, media, writing, privacy).',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Optional category filter to get specific settings group.',
							'enum'        => array( 'general', 'reading', 'discussion', 'media', 'writing', 'privacy', 'permalink' ),
						),
						'include_private' => array(
							'type'        => 'boolean',
							'description' => 'Include settings that start with underscore.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'settings' ),
					'properties' => array(
						'settings' => array(
							'type'                 => 'object',
							'description'          => 'Site settings organized by category',
							'additionalProperties' => true,
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'settings', 'configuration' ),
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
	 * Check permission for getting site settings.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Execute the get site settings operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$category        = isset( $input['category'] ) ? \sanitize_key( (string) $input['category'] ) : '';
		$include_private = ! empty( $input['include_private'] );

		$settings = array();

		if ( empty( $category ) || 'general' === $category ) {
			$settings['general'] = self::get_general_settings();
		}

		if ( empty( $category ) || 'reading' === $category ) {
			$settings['reading'] = self::get_reading_settings();
		}

		if ( empty( $category ) || 'discussion' === $category ) {
			$settings['discussion'] = self::get_discussion_settings();
		}

		if ( empty( $category ) || 'media' === $category ) {
			$settings['media'] = self::get_media_settings();
		}

		if ( empty( $category ) || 'writing' === $category ) {
			$settings['writing'] = self::get_writing_settings();
		}

		if ( empty( $category ) || 'privacy' === $category ) {
			$settings['privacy'] = self::get_privacy_settings();
		}

		if ( empty( $category ) || 'permalink' === $category ) {
			$settings['permalink'] = self::get_permalink_settings();
		}

		return array(
			'settings' => $settings,
		);
	}

	/**
	 * Get general settings.
	 *
	 * @return array General settings.
	 */
	private static function get_general_settings(): array {
		return array(
			'blogname'                => \get_option( 'blogname' ),
			'blogdescription'         => \get_option( 'blogdescription' ),
			'siteurl'                 => \get_option( 'siteurl' ),
			'home'                    => \get_option( 'home' ),
			'admin_email'             => \get_option( 'admin_email' ),
			'users_can_register'      => (bool) \get_option( 'users_can_register' ),
			'default_role'            => \get_option( 'default_role' ),
			'timezone_string'         => \get_option( 'timezone_string' ),
			'gmt_offset'              => \get_option( 'gmt_offset' ),
			'date_format'             => \get_option( 'date_format' ),
			'time_format'             => \get_option( 'time_format' ),
			'start_of_week'           => (int) \get_option( 'start_of_week' ),
			'WPLANG'                  => \get_option( 'WPLANG' ),
		);
	}

	/**
	 * Get reading settings.
	 *
	 * @return array Reading settings.
	 */
	private static function get_reading_settings(): array {
		return array(
			'show_on_front'       => \get_option( 'show_on_front' ),
			'page_on_front'       => (int) \get_option( 'page_on_front' ),
			'page_for_posts'      => (int) \get_option( 'page_for_posts' ),
			'posts_per_page'      => (int) \get_option( 'posts_per_page' ),
			'posts_per_rss'       => (int) \get_option( 'posts_per_rss' ),
			'rss_use_excerpt'     => (bool) \get_option( 'rss_use_excerpt' ),
			'blog_public'         => (int) \get_option( 'blog_public' ),
		);
	}

	/**
	 * Get discussion settings.
	 *
	 * @return array Discussion settings.
	 */
	private static function get_discussion_settings(): array {
		return array(
			'default_pingback_flag'   => (bool) \get_option( 'default_pingback_flag' ),
			'default_ping_status'     => \get_option( 'default_ping_status' ),
			'default_comment_status'  => \get_option( 'default_comment_status' ),
			'require_name_email'      => (bool) \get_option( 'require_name_email' ),
			'comment_registration'    => (bool) \get_option( 'comment_registration' ),
			'close_comments_for_old_posts' => (bool) \get_option( 'close_comments_for_old_posts' ),
			'close_comments_days_old' => (int) \get_option( 'close_comments_days_old' ),
			'thread_comments'         => (bool) \get_option( 'thread_comments' ),
			'thread_comments_depth'   => (int) \get_option( 'thread_comments_depth' ),
			'page_comments'           => (bool) \get_option( 'page_comments' ),
			'comments_per_page'       => (int) \get_option( 'comments_per_page' ),
			'default_comments_page'   => \get_option( 'default_comments_page' ),
			'comment_order'           => \get_option( 'comment_order' ),
			'comments_notify'         => (bool) \get_option( 'comments_notify' ),
			'moderation_notify'       => (bool) \get_option( 'moderation_notify' ),
			'comment_moderation'      => (bool) \get_option( 'comment_moderation' ),
			'comment_previously_approved' => (bool) \get_option( 'comment_previously_approved' ),
			'comment_max_links'       => (int) \get_option( 'comment_max_links' ),
			'moderation_keys'         => \get_option( 'moderation_keys' ),
			'disallowed_keys'         => \get_option( 'disallowed_keys' ),
			'show_avatars'            => (bool) \get_option( 'show_avatars' ),
			'avatar_rating'           => \get_option( 'avatar_rating' ),
			'avatar_default'          => \get_option( 'avatar_default' ),
		);
	}

	/**
	 * Get media settings.
	 *
	 * @return array Media settings.
	 */
	private static function get_media_settings(): array {
		return array(
			'thumbnail_size_w'        => (int) \get_option( 'thumbnail_size_w' ),
			'thumbnail_size_h'        => (int) \get_option( 'thumbnail_size_h' ),
			'thumbnail_crop'          => (bool) \get_option( 'thumbnail_crop' ),
			'medium_size_w'           => (int) \get_option( 'medium_size_w' ),
			'medium_size_h'           => (int) \get_option( 'medium_size_h' ),
			'medium_large_size_w'     => (int) \get_option( 'medium_large_size_w' ),
			'medium_large_size_h'     => (int) \get_option( 'medium_large_size_h' ),
			'large_size_w'            => (int) \get_option( 'large_size_w' ),
			'large_size_h'            => (int) \get_option( 'large_size_h' ),
			'uploads_use_yearmonth_folders' => (bool) \get_option( 'uploads_use_yearmonth_folders' ),
		);
	}

	/**
	 * Get writing settings.
	 *
	 * @return array Writing settings.
	 */
	private static function get_writing_settings(): array {
		return array(
			'default_category'        => (int) \get_option( 'default_category' ),
			'default_post_format'     => \get_option( 'default_post_format' ),
			'mailserver_url'          => \get_option( 'mailserver_url' ),
			'mailserver_login'        => \get_option( 'mailserver_login' ),
			'mailserver_pass'         => \get_option( 'mailserver_pass' ),
			'mailserver_port'         => (int) \get_option( 'mailserver_port' ),
			'default_email_category'  => (int) \get_option( 'default_email_category' ),
			'use_balanceTags'         => (bool) \get_option( 'use_balanceTags' ),
		);
	}

	/**
	 * Get privacy settings.
	 *
	 * @return array Privacy settings.
	 */
	private static function get_privacy_settings(): array {
		return array(
			'wp_page_for_privacy_policy' => (int) \get_option( 'wp_page_for_privacy_policy' ),
		);
	}

	/**
	 * Get permalink settings.
	 *
	 * @return array Permalink settings.
	 */
	private static function get_permalink_settings(): array {
		return array(
			'permalink_structure'     => \get_option( 'permalink_structure' ),
			'category_base'           => \get_option( 'category_base' ),
			'tag_base'                => \get_option( 'tag_base' ),
		);
	}
}
