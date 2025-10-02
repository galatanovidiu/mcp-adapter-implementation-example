<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Menus;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetMenuLocations implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-menu-locations',
			array(
				'label'               => 'Get Menu Locations',
				'description'         => 'Retrieve WordPress theme menu locations and their assigned menus.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_unassigned' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include unassigned locations. Default: true.',
							'default'     => true,
						),
						'include_menu_details' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include detailed menu information. Default: true.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'locations', 'total_locations' ),
					'properties' => array(
						'locations' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'location', 'name', 'is_assigned' ),
								'properties' => array(
									'location'    => array( 'type' => 'string' ),
									'name'        => array( 'type' => 'string' ),
									'is_assigned' => array( 'type' => 'boolean' ),
									'menu_id'     => array( 'type' => 'integer' ),
									'menu'        => array(
										'type'       => 'object',
										'properties' => array(
											'term_id'     => array( 'type' => 'integer' ),
											'name'        => array( 'type' => 'string' ),
											'slug'        => array( 'type' => 'string' ),
											'description' => array( 'type' => 'string' ),
											'count'       => array( 'type' => 'integer' ),
										),
									),
								),
							),
						),
						'assignments' => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'integer' ),
						),
						'total_locations'   => array( 'type' => 'integer' ),
						'assigned_count'    => array( 'type' => 'integer' ),
						'unassigned_count'  => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'appearance', 'navigation' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
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
	 * Check permission for getting menu locations.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_theme_options' );
	}

	/**
	 * Execute the get menu locations operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$include_unassigned = (bool) ( $input['include_unassigned'] ?? true );
		$include_menu_details = (bool) ( $input['include_menu_details'] ?? true );

		// Get registered menu locations
		$registered_locations = \get_registered_nav_menus();
		$assigned_locations = \get_nav_menu_locations();

		$locations_data = array();
		$assigned_count = 0;
		$unassigned_count = 0;

		foreach ( $registered_locations as $location => $name ) {
			$is_assigned = isset( $assigned_locations[ $location ] ) && $assigned_locations[ $location ] > 0;
			$menu_id = $is_assigned ? (int) $assigned_locations[ $location ] : 0;

			if ( $is_assigned ) {
				$assigned_count++;
			} else {
				$unassigned_count++;
				if ( ! $include_unassigned ) {
					continue;
				}
			}

			$location_data = array(
				'location'    => $location,
				'name'        => $name,
				'is_assigned' => $is_assigned,
				'menu_id'     => $menu_id,
			);

			// Get menu details if requested and menu is assigned
			if ( $include_menu_details && $is_assigned ) {
				$menu = \wp_get_nav_menu_object( $menu_id );
				if ( $menu ) {
					$location_data['menu'] = array(
						'term_id'     => (int) $menu->term_id,
						'name'        => $menu->name,
						'slug'        => $menu->slug,
						'description' => $menu->description,
						'count'       => (int) $menu->count,
					);
				} else {
					// Menu ID exists but menu not found (orphaned assignment)
					$location_data['menu'] = array(
						'term_id'     => $menu_id,
						'name'        => 'Menu Not Found',
						'slug'        => '',
						'description' => 'This menu assignment is orphaned.',
						'count'       => 0,
					);
				}
			} else {
				$location_data['menu'] = array();
			}

			$locations_data[] = $location_data;
		}

		return array(
			'locations'        => $locations_data,
			'assignments'      => $assigned_locations,
			'total_locations'  => count( $registered_locations ),
			'assigned_count'   => $assigned_count,
			'unassigned_count' => $unassigned_count,
		);
	}
}
