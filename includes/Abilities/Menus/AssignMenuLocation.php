<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Menus;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class AssignMenuLocation implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/assign-menu-location',
			array(
				'label'               => 'Assign Menu Location',
				'description'         => 'Assign WordPress menus to theme menu locations.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'assignments' ),
					'properties' => array(
						'assignments' => array(
							'type'        => 'array',
							'description' => 'Array of menu location assignments.',
							'items'       => array(
								'type'       => 'object',
								'required'   => array( 'location' ),
								'properties' => array(
									'location'        => array(
										'type'        => 'string',
										'description' => 'Theme menu location identifier.',
									),
									'menu_identifier' => array(
										'type'        => 'string',
										'description' => 'Menu ID, slug, or name to assign. Leave empty to unassign.',
									),
								),
							),
						),
						'replace_all' => array(
							'type'        => 'boolean',
							'description' => 'Whether to replace all existing assignments (true) or only update specified ones (false). Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'assignments' ),
					'properties' => array(
						'success'             => array( 'type' => 'boolean' ),
						'assignments'         => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'location'  => array( 'type' => 'string' ),
									'menu_id'   => array( 'type' => 'integer' ),
									'menu_name' => array( 'type' => 'string' ),
									'action'    => array( 'type' => 'string' ),
									'success'   => array( 'type' => 'boolean' ),
									'message'   => array( 'type' => 'string' ),
								),
							),
						),
						'updated_assignments' => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'integer' ),
						),
						'message'             => array( 'type' => 'string' ),
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
						'priority'        => 0.6,
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
	 * Check permission for assigning menu locations.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_theme_options' );
	}

	/**
	 * Execute the assign menu location operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$assignments = $input['assignments'] ?? array();
		$replace_all = (bool) ( $input['replace_all'] ?? false );

		if ( empty( $assignments ) || ! is_array( $assignments ) ) {
			return array(
				'success' => false,
				'message' => 'No assignments provided.',
			);
		}

		// Get registered locations and current assignments
		$registered_locations = \get_registered_nav_menus();
		$current_assignments  = \get_nav_menu_locations();
		$new_assignments      = $replace_all ? array() : $current_assignments;

		$assignment_results = array();
		$success_count      = 0;
		$error_count        = 0;

		foreach ( $assignments as $assignment ) {
			$location        = \sanitize_text_field( (string) ( $assignment['location'] ?? '' ) );
			$menu_identifier = isset( $assignment['menu_identifier'] ) ? \sanitize_text_field( (string) $assignment['menu_identifier'] ) : '';

			$result = array(
				'location'  => $location,
				'menu_id'   => 0,
				'menu_name' => '',
				'action'    => '',
				'success'   => false,
				'message'   => '',
			);

			// Validate location
			if ( empty( $location ) ) {
				$result['message']    = 'Location is required.';
				$assignment_results[] = $result;
				++$error_count;
				continue;
			}

			if ( ! isset( $registered_locations[ $location ] ) ) {
				$result['message']    = 'Location not registered in theme.';
				$assignment_results[] = $result;
				++$error_count;
				continue;
			}

			// Handle unassignment (empty menu_identifier)
			if ( empty( $menu_identifier ) ) {
				$new_assignments[ $location ] = 0;
				$result['action']             = 'unassigned';
				$result['success']            = true;
				$result['message']            = 'Location unassigned successfully.';
				$assignment_results[]         = $result;
				++$success_count;
				continue;
			}

			// Get menu
			$menu = \wp_get_nav_menu_object( $menu_identifier );
			if ( ! $menu ) {
				$result['message']    = 'Menu not found.';
				$assignment_results[] = $result;
				++$error_count;
				continue;
			}

			// Assign menu to location
			$new_assignments[ $location ] = $menu->term_id;
			$result['menu_id']            = (int) $menu->term_id;
			$result['menu_name']          = $menu->name;
			$result['action']             = 'assigned';
			$result['success']            = true;
			$result['message']            = 'Menu assigned to location successfully.';
			$assignment_results[]         = $result;
			++$success_count;
		}

		// Update menu locations
		\set_theme_mod( 'nav_menu_locations', $new_assignments );

		// Determine overall success
		$overall_success = $error_count === 0;
		$message         = '';

		if ( $overall_success ) {
			$message = 'All menu location assignments completed successfully.';
		} elseif ( $success_count > 0 ) {
			$message = sprintf(
				'Partial success: %d assignments completed, %d failed.',
				$success_count,
				$error_count
			);
		} else {
			$message = 'All menu location assignments failed.';
		}

		return array(
			'success'             => $overall_success,
			'assignments'         => $assignment_results,
			'updated_assignments' => $new_assignments,
			'message'             => $message,
		);
	}
}
