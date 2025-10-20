<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Menus;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetMenu implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-menu',
			array(
				'label'               => 'Get Menu',
				'description'         => 'Retrieve detailed information about a specific WordPress navigation menu.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'menu_identifier' ),
					'properties' => array(
						'menu_identifier' => array(
							'type'        => 'string',
							'description' => 'Menu ID, slug, or name to retrieve.',
						),
						'include_items'   => array(
							'type'        => 'boolean',
							'description' => 'Whether to include menu items. Default: true.',
							'default'     => true,
						),
						'hierarchical'    => array(
							'type'        => 'boolean',
							'description' => 'Whether to organize items hierarchically. Default: false.',
							'default'     => false,
						),
					),
				),
				'output_schema'       => array(
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
									'ID'          => array( 'type' => 'integer' ),
									'title'       => array( 'type' => 'string' ),
									'url'         => array( 'type' => 'string' ),
									'target'      => array( 'type' => 'string' ),
									'attr_title'  => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'classes'     => array( 'type' => 'string' ),
									'xfn'         => array( 'type' => 'string' ),
									'menu_order'  => array( 'type' => 'integer' ),
									'object'      => array( 'type' => 'string' ),
									'object_id'   => array( 'type' => 'integer' ),
									'type'        => array( 'type' => 'string' ),
									'type_label'  => array( 'type' => 'string' ),
									'parent_id'   => array( 'type' => 'integer' ),
									'children'    => array(
										'type'  => 'array',
										'items' => array( 'type' => 'object' ),
									),
								),
							),
						),
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
	 * Check permission for getting menu details.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_theme_options' );
	}

	/**
	 * Execute the get menu operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$menu_identifier = \sanitize_text_field( (string) $input['menu_identifier'] );
		$include_items   = (bool) ( $input['include_items'] ?? true );
		$hierarchical    = (bool) ( $input['hierarchical'] ?? false );

		// Get the menu
		$menu = \wp_get_nav_menu_object( $menu_identifier );

		if ( ! $menu ) {
			return array(
				'error' => array(
					'code'    => 'menu_not_found',
					'message' => 'Menu not found.',
				),
			);
		}

		// Get menu locations where this menu is assigned
		$menu_locations     = array();
		$assigned_locations = \get_nav_menu_locations();
		foreach ( $assigned_locations as $location => $assigned_menu_id ) {
			if ( (int) $assigned_menu_id !== (int) $menu->term_id ) {
				continue;
			}

			$menu_locations[] = $location;
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
				// Convert to array format
				$items_array = array();
				foreach ( $menu_items as $item ) {
					$item_data = array(
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
						'children'    => array(),
					);

					$items_array[ $item->ID ] = $item_data;
				}

				// Build hierarchical structure if requested
				if ( $hierarchical ) {
					$items_data = self::build_menu_hierarchy( $items_array );
				} else {
					$items_data = array_values( $items_array );
				}
			}

			$menu_data['items'] = $items_data;
		} else {
			$menu_data['items'] = array();
		}

		return $menu_data;
	}

	/**
	 * Build hierarchical menu structure.
	 *
	 * @param array $items Flat array of menu items.
	 * @return array Hierarchical array of menu items.
	 */
	private static function build_menu_hierarchy( array $items ): array {
		$hierarchy = array();
		$children  = array();

		// First pass: separate top-level items and children
		foreach ( $items as $item ) {
			if ( $item['parent_id'] === 0 ) {
				$hierarchy[] = $item;
			} else {
				if ( ! isset( $children[ $item['parent_id'] ] ) ) {
					$children[ $item['parent_id'] ] = array();
				}
				$children[ $item['parent_id'] ][] = $item;
			}
		}

		// Second pass: attach children to parents
		$hierarchy = self::attach_children( $hierarchy, $children );

		return $hierarchy;
	}

	/**
	 * Recursively attach children to parent menu items.
	 *
	 * @param array $items Array of menu items.
	 * @param array $children Array of children indexed by parent ID.
	 * @return array Menu items with children attached.
	 */
	private static function attach_children( array $items, array $children ): array {
		foreach ( $items as &$item ) {
			$item_id = $item['ID'];
			if ( ! isset( $children[ $item_id ] ) ) {
				continue;
			}

			$item['children'] = self::attach_children( $children[ $item_id ], $children );
		}

		return $items;
	}
}
