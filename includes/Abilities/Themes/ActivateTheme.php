<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ActivateTheme implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/activate-theme',
			array(
				'label'               => 'Activate Theme',
				'description'         => 'Activate a WordPress theme.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'stylesheet' ),
					'properties' => array(
						'stylesheet' => array(
							'type'        => 'string',
							'description' => 'Theme stylesheet name (folder name) to activate.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'active_theme' ),
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'active_theme'   => array( 'type' => 'string' ),
						'previous_theme' => array( 'type' => 'string' ),
						'theme_info'     => array(
							'type'       => 'object',
							'properties' => array(
								'name'        => array( 'type' => 'string' ),
								'version'     => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'author'      => array( 'type' => 'string' ),
							),
						),
						'message'        => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'appearance',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
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
	 * Check permission for activating themes.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'switch_themes' );
	}

	/**
	 * Execute the activate theme operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$stylesheet     = \sanitize_text_field( (string) $input['stylesheet'] );
		$previous_theme = \get_stylesheet();

		// Check if theme exists
		$theme = \wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return array(
				'error' => array(
					'code'    => 'theme_not_found',
					'message' => 'Theme not found.',
				),
			);
		}

		// Check if theme is already active
		if ( $stylesheet === $previous_theme ) {
			return array(
				'success'        => true,
				'active_theme'   => $stylesheet,
				'previous_theme' => $previous_theme,
				'theme_info'     => array(
					'name'        => $theme->get( 'Name' ),
					'version'     => $theme->get( 'Version' ),
					'description' => $theme->get( 'Description' ),
					'author'      => \wp_strip_all_tags( $theme->get( 'Author' ) ),
				),
				'message'        => 'Theme is already active.',
			);
		}

		// Check if theme is broken
		if ( $theme->errors() ) {
			$errors         = $theme->errors();
			$error_messages = array();
			if ( is_wp_error( $errors ) ) {
				$error_messages = $errors->get_error_messages();
			}

			return array(
				'error' => array(
					'code'    => 'theme_broken',
					'message' => 'Theme has errors and cannot be activated.',
					'details' => $error_messages,
				),
			);
		}

		// Check if theme is allowed (for multisite)
		if ( \is_multisite() ) {
			$allowed_themes = \get_site_option( 'allowedthemes' );
			$is_allowed     = isset( $allowed_themes[ $stylesheet ] ) || \current_user_can( 'manage_network_themes' );

			if ( ! $is_allowed ) {
				return array(
					'error' => array(
						'code'    => 'theme_not_allowed',
						'message' => 'Theme is not allowed on this site.',
					),
				);
			}
		}

		// Check if it's a child theme and parent exists
		if ( $theme->get_template() !== $stylesheet ) {
			$parent_theme = \wp_get_theme( $theme->get_template() );
			if ( ! $parent_theme->exists() ) {
				return array(
					'error' => array(
						'code'    => 'parent_theme_missing',
						'message' => 'Parent theme is missing and required for this child theme.',
						'parent'  => $theme->get_template(),
					),
				);
			}
		}

		// Attempt to switch theme
		try {
			\switch_theme( $stylesheet );

			// Verify the switch was successful
			$current_theme = \get_stylesheet();
			if ( $current_theme !== $stylesheet ) {
				return array(
					'error' => array(
						'code'    => 'activation_failed',
						'message' => 'Theme activation failed for unknown reason.',
					),
				);
			}

			// Clear any cached data
			if ( function_exists( 'wp_cache_flush' ) ) {
				\wp_cache_flush();
			}

			return array(
				'success'        => true,
				'active_theme'   => $stylesheet,
				'previous_theme' => $previous_theme,
				'theme_info'     => array(
					'name'        => $theme->get( 'Name' ),
					'version'     => $theme->get( 'Version' ),
					'description' => $theme->get( 'Description' ),
					'author'      => \wp_strip_all_tags( $theme->get( 'Author' ) ),
				),
				'message'        => 'Theme activated successfully.',
			);
		} catch ( \Throwable $e ) {
			return array(
				'error' => array(
					'code'    => 'activation_exception',
					'message' => 'Theme activation failed: ' . $e->getMessage(),
				),
			);
		}
	}
}
