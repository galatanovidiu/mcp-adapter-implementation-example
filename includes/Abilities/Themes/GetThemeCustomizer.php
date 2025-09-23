<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetThemeCustomizer implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-theme-customizer',
			array(
				'label'               => 'Get Theme Customizer',
				'description'         => 'Get WordPress theme customizer settings and configuration.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'stylesheet' => array(
							'type'        => 'string',
							'description' => 'Theme stylesheet name. If not provided, uses active theme.',
						),
						'include_values' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include current setting values. Default: true.',
							'default'     => true,
						),
						'include_controls' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include control definitions. Default: true.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'theme', 'panels', 'sections' ),
					'properties' => array(
						'theme'    => array( 'type' => 'string' ),
						'panels'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'string' ),
									'title'       => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'priority'    => array( 'type' => 'integer' ),
									'capability'  => array( 'type' => 'string' ),
									'sections'    => array( 'type' => 'array' ),
								),
							),
						),
						'sections' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'string' ),
									'title'       => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'priority'    => array( 'type' => 'integer' ),
									'capability'  => array( 'type' => 'string' ),
									'panel'       => array( 'type' => 'string' ),
									'controls'    => array( 'type' => 'array' ),
								),
							),
						),
						'controls' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'string' ),
									'label'       => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'type'        => array( 'type' => 'string' ),
									'section'     => array( 'type' => 'string' ),
									'priority'    => array( 'type' => 'integer' ),
									'capability'  => array( 'type' => 'string' ),
									'setting'     => array( 'type' => 'string' ),
									'choices'     => array( 'type' => 'object' ),
									'input_attrs' => array( 'type' => 'object' ),
									'value'       => array( 'type' => 'string' ),
									'default'     => array( 'type' => 'string' ),
								),
							),
						),
						'settings' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'        => array( 'type' => 'string' ),
									'type'      => array( 'type' => 'string' ),
									'default'   => array( 'type' => 'string' ),
									'transport' => array( 'type' => 'string' ),
									'value'     => array( 'type' => 'string' ),
									'dirty'     => array( 'type' => 'boolean' ),
								),
							),
						),
						'theme_supports' => array(
							'type'       => 'object',
							'properties' => array(
								'custom_background' => array( 'type' => 'boolean' ),
								'custom_header'     => array( 'type' => 'boolean' ),
								'custom_logo'       => array( 'type' => 'boolean' ),
								'customize_selective_refresh' => array( 'type' => 'boolean' ),
								'widgets'           => array( 'type' => 'boolean' ),
								'menus'             => array( 'type' => 'boolean' ),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'appearance', 'customization' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
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
	 * Check permission for accessing theme customizer.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'customize' );
	}

	/**
	 * Execute the get theme customizer operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$stylesheet = $input['stylesheet'] ?? \get_stylesheet();
		$include_values = $input['include_values'] ?? true;
		$include_controls = $input['include_controls'] ?? true;

		// Validate theme exists
		$theme = \wp_get_theme( $stylesheet );
		if ( ! $theme->exists() ) {
			return array(
				'error' => array(
					'code'    => 'theme_not_found',
					'message' => 'Theme not found.',
				),
			);
		}

		// Include customizer classes
		if ( ! class_exists( 'WP_Customize_Manager' ) ) {
			require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
		}

		// Create a temporary customizer instance
		$wp_customize = new \WP_Customize_Manager( array(
			'theme' => $stylesheet,
		) );

		// Initialize the customizer (this loads theme customizations)
		$wp_customize->setup_theme();

		// Get theme support information
		$theme_supports = array(
			'custom_background' => \current_theme_supports( 'custom-background' ),
			'custom_header'     => \current_theme_supports( 'custom-header' ),
			'custom_logo'       => \current_theme_supports( 'custom-logo' ),
			'customize_selective_refresh' => \current_theme_supports( 'customize-selective-refresh-widgets' ),
			'widgets'           => \current_theme_supports( 'widgets' ),
			'menus'             => \current_theme_supports( 'menus' ),
		);

		// Collect panels
		$panels_data = array();
		foreach ( $wp_customize->panels() as $panel_id => $panel ) {
			$panel_sections = array();
			foreach ( $wp_customize->sections() as $section_id => $section ) {
				if ( $section->panel === $panel_id ) {
					$panel_sections[] = $section_id;
				}
			}

			$panels_data[] = array(
				'id'          => $panel_id,
				'title'       => $panel->title,
				'description' => $panel->description,
				'priority'    => $panel->priority,
				'capability'  => $panel->capability,
				'sections'    => $panel_sections,
			);
		}

		// Collect sections
		$sections_data = array();
		foreach ( $wp_customize->sections() as $section_id => $section ) {
			$section_controls = array();
			if ( $include_controls ) {
				foreach ( $wp_customize->controls() as $control_id => $control ) {
					if ( $control->section === $section_id ) {
						$section_controls[] = $control_id;
					}
				}
			}

			$sections_data[] = array(
				'id'          => $section_id,
				'title'       => $section->title,
				'description' => $section->description,
				'priority'    => $section->priority,
				'capability'  => $section->capability,
				'panel'       => $section->panel,
				'controls'    => $section_controls,
			);
		}

		// Collect controls
		$controls_data = array();
		if ( $include_controls ) {
			foreach ( $wp_customize->controls() as $control_id => $control ) {
				$control_value = '';
				$control_default = '';

				if ( $include_values && isset( $control->setting ) ) {
					$setting = $wp_customize->get_setting( $control->setting->id );
					if ( $setting ) {
						$control_value = $setting->value();
						$control_default = $setting->default;
					}
				}

				$controls_data[] = array(
					'id'          => $control_id,
					'label'       => $control->label,
					'description' => $control->description,
					'type'        => $control->type,
					'section'     => $control->section,
					'priority'    => $control->priority,
					'capability'  => $control->capability,
					'setting'     => $control->setting ? $control->setting->id : '',
					'choices'     => isset( $control->choices ) ? $control->choices : array(),
					'input_attrs' => isset( $control->input_attrs ) ? $control->input_attrs : array(),
					'value'       => $control_value,
					'default'     => $control_default,
				);
			}
		}

		// Collect settings
		$settings_data = array();
		if ( $include_values ) {
			foreach ( $wp_customize->settings() as $setting_id => $setting ) {
				$settings_data[] = array(
					'id'        => $setting_id,
					'type'      => $setting->type,
					'default'   => $setting->default,
					'transport' => $setting->transport,
					'value'     => $setting->value(),
					'dirty'     => $setting->dirty,
				);
			}
		}

		return array(
			'theme'          => $stylesheet,
			'panels'         => $panels_data,
			'sections'       => $sections_data,
			'controls'       => $controls_data,
			'settings'       => $settings_data,
			'theme_supports' => $theme_supports,
		);
	}
}
