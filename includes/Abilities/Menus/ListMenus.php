<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Menus;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListMenus implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-menus',
			array(
				'label'               => 'List Menus',
				'description'         => 'List all WordPress navigation menus with their details and menu items.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_items'     => array(
							'type'        => 'boolean',
							'description' => 'Whether to include menu items for each menu. Default: false.',
							'default'     => false,
						),
						'include_locations' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include theme menu locations. Default: true.',
							'default'     => true,
						),
						'menu_id'           => array(
							'type'        => 'integer',
							'description' => 'Filter by specific menu ID.',
						),
						'menu_slug'         => array(
							'type'        => 'string',
							'description' => 'Filter by specific menu slug.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'menus', 'total_count' ),
					'properties' => array(
						'menus'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'term_id', 'name', 'slug' ),
								'properties' => array(
									'term_id'     => array( 'type' => 'integer' ),
									'name'        => array( 'type' => 'string' ),
									'slug'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'count'       => array( 'type' => 'integer' ),
									'locations'   => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'items'       => array(
										'type'  => 'array',
										'items' => array(
											'type'       => 'object',
											'properties' => array(
												'ID'      => array( 'type' => 'integer' ),
												'title'   => array( 'type' => 'string' ),
												'url'     => array( 'type' => 'string' ),
												'target'  => array( 'type' => 'string' ),
												'attr_title' => array( 'type' => 'string' ),
												'description' => array( 'type' => 'string' ),
												'classes' => array( 'type' => 'string' ),
												'xfn'     => array( 'type' => 'string' ),
												'menu_order' => array( 'type' => 'integer' ),
												'object'  => array( 'type' => 'string' ),
												'object_id' => array( 'type' => 'integer' ),
												'type'    => array( 'type' => 'string' ),
												'type_label' => array( 'type' => 'string' ),
												'parent_id' => array( 'type' => 'integer' ),
											),
										),
									),
								),
							),
						),
						'locations'   => array(
							'type'       => 'object',
							'properties' => array(
								'registered' => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'location' => array( 'type' => 'string' ),
											'name'     => array( 'type' => 'string' ),
											'menu_id'  => array( 'type' => 'integer' ),
										),
									),
								),
								'assigned'   => array(
									'type'                 => 'object',
									'additionalProperties' => array( 'type' => 'integer' ),
								),
							),
						),
						'total_count' => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'content',
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

	/**
	 * Check permission for listing menus.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_theme_options' );
	}

	/**
	 * Execute the list menus operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$include_items     = (bool) ( $input['include_items'] ?? false );
		$include_locations = (bool) ( $input['include_locations'] ?? true );
		$menu_id           = isset( $input['menu_id'] ) ? (int) $input['menu_id'] : 0;
		$menu_slug         = isset( $input['menu_slug'] ) ? \sanitize_title( (string) $input['menu_slug'] ) : '';

		// Get all menus
		$menu_args = array();
		if ( $menu_id > 0 ) {
			$menu_args['include'] = array( $menu_id );
		}
		if ( ! empty( $menu_slug ) ) {
			$menu_args['slug'] = $menu_slug;
		}

		$menus      = \wp_get_nav_menus( $menu_args );
		$menus_data = array();

		// Get menu locations
		$locations_data = array();
		if ( $include_locations ) {
			$registered_locations = \get_registered_nav_menus();
			$assigned_locations   = \get_nav_menu_locations();

			$registered_data = array();
			foreach ( $registered_locations as $location => $name ) {
				$assigned_menu_id  = isset( $assigned_locations[ $location ] ) ? (int) $assigned_locations[ $location ] : 0;
				$registered_data[] = array(
					'location' => $location,
					'name'     => $name,
					'menu_id'  => $assigned_menu_id,
				);
			}

			$locations_data = array(
				'registered' => $registered_data,
				'assigned'   => $assigned_locations,
			);
		}

		foreach ( $menus as $menu ) {
			// Get menu locations where this menu is assigned
			$menu_locations = array();
			if ( $include_locations ) {
				$assigned_locations = \get_nav_menu_locations();
				foreach ( $assigned_locations as $location => $assigned_menu_id ) {
					if ( (int) $assigned_menu_id !== (int) $menu->term_id ) {
						continue;
					}

					$menu_locations[] = $location;
				}
			}

			$menu_data = array(
				'term_id'     => (int) $menu->term_id,
				'name'        => $menu->name,
				'slug'        => $menu->slug,
				'description' => $menu->description,
				'count'       => (int) $menu->count,
				'locations'   => $menu_locations,
			);

			// Get menu items if requested
			if ( $include_items ) {
				$menu_items = \wp_get_nav_menu_items( $menu->term_id );
				$items_data = array();

				if ( $menu_items ) {
					foreach ( $menu_items as $item ) {
						$items_data[] = array(
							'ID'          => (int) $item->ID,
							'title'       => $item->title,
							'url'         => $item->url,
							'target'      => $item->target,
							'attr_title'  => $item->attr_title,
							'description' => $item->description,
							'classes'     => implode( ' ', $item->classes ),
							'xfn'         => $item->xfn,
							'menu_order'  => (int) $item->menu_order,
							'object'      => $item->object,
							'object_id'   => (int) $item->object_id,
							'type'        => $item->type,
							'type_label'  => $item->type_label,
							'parent_id'   => (int) $item->menu_item_parent,
						);
					}
				}

				$menu_data['items'] = $items_data;
			} else {
				$menu_data['items'] = array();
			}

			$menus_data[] = $menu_data;
		}

		return array(
			'menus'       => $menus_data,
			'locations'   => $locations_data,
			'total_count' => count( $menus_data ),
		);
	}
}
