<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Menus;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class DeleteMenu implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/delete-menu',
			array(
				'label'               => 'Delete Menu',
				'description'         => 'Delete a WordPress navigation menu and all its items.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'menu_identifier' ),
					'properties' => array(
						'menu_identifier' => array(
							'type'        => 'string',
							'description' => 'Menu ID, slug, or name to delete.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'menu_id' ),
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'menu_id' => array( 'type' => 'integer' ),
						'menu'    => array(
							'type'       => 'object',
							'properties' => array(
								'term_id'     => array( 'type' => 'integer' ),
								'name'        => array( 'type' => 'string' ),
								'slug'        => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'count'       => array( 'type' => 'integer' ),
							),
						),
						'items_deleted' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'locations_cleared' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.5,
						'readOnlyHint'    => false,
						'destructiveHint' => true,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for deleting menus.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_theme_options' );
	}

	/**
	 * Execute the delete menu operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$menu_identifier = \sanitize_text_field( (string) $input['menu_identifier'] );

		// Get the menu
		$menu = \wp_get_nav_menu_object( $menu_identifier );
		if ( ! $menu ) {
			return array(
				'success' => false,
				'menu_id' => 0,
				'message' => 'Menu not found.',
			);
		}

		$menu_id = $menu->term_id;

		// Store menu data for response
		$menu_data = array(
			'term_id'     => (int) $menu->term_id,
			'name'        => $menu->name,
			'slug'        => $menu->slug,
			'description' => $menu->description,
			'count'       => (int) $menu->count,
		);

		// Get menu items before deletion
		$menu_items = \wp_get_nav_menu_items( $menu_id );
		$items_deleted = array();
		if ( $menu_items ) {
			foreach ( $menu_items as $item ) {
				$items_deleted[] = (int) $item->ID;
			}
		}

		// Check which locations this menu is assigned to
		$locations_cleared = array();
		$assigned_locations = \get_nav_menu_locations();
		foreach ( $assigned_locations as $location => $assigned_menu_id ) {
			if ( (int) $assigned_menu_id === $menu_id ) {
				$locations_cleared[] = $location;
			}
		}

		// Delete the menu (this will also delete all menu items and clear location assignments)
		$result = \wp_delete_nav_menu( $menu );

		if ( \is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'menu_id' => $menu_id,
				'message' => 'Failed to delete menu: ' . $result->get_error_message(),
			);
		}

		if ( ! $result ) {
			return array(
				'success' => false,
				'menu_id' => $menu_id,
				'message' => 'Failed to delete menu.',
			);
		}

		return array(
			'success'           => true,
			'menu_id'           => $menu_id,
			'menu'              => $menu_data,
			'items_deleted'     => $items_deleted,
			'locations_cleared' => $locations_cleared,
			'message'           => 'Menu deleted successfully.',
		);
	}
}
