<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Menus;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class CreateMenu implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/create-menu',
			array(
				'label'               => 'Create Menu',
				'description'         => 'Create a new WordPress navigation menu with optional menu items.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'menu_name' ),
					'properties' => array(
						'menu_name' => array(
							'type'        => 'string',
							'description' => 'The name of the menu to create.',
						),
						'menu_description' => array(
							'type'        => 'string',
							'description' => 'Optional description for the menu.',
						),
						'menu_items' => array(
							'type'        => 'array',
							'description' => 'Optional array of menu items to add to the menu.',
							'items'       => array(
								'type'       => 'object',
								'required'   => array( 'title', 'url' ),
								'properties' => array(
									'title'       => array( 'type' => 'string', 'description' => 'Menu item title' ),
									'url'         => array( 'type' => 'string', 'description' => 'Menu item URL' ),
									'target'      => array( 'type' => 'string', 'description' => 'Link target (_blank, _self, etc.)' ),
									'attr_title'  => array( 'type' => 'string', 'description' => 'Title attribute for the link' ),
									'description' => array( 'type' => 'string', 'description' => 'Menu item description' ),
									'classes'     => array( 'type' => 'string', 'description' => 'CSS classes for the menu item' ),
									'xfn'         => array( 'type' => 'string', 'description' => 'XFN relationship' ),
									'menu_order'  => array( 'type' => 'integer', 'description' => 'Menu item order' ),
									'parent_id'   => array( 'type' => 'integer', 'description' => 'Parent menu item ID for sub-items' ),
									'object_id'   => array( 'type' => 'integer', 'description' => 'Object ID for post/page/category items' ),
									'object'      => array( 'type' => 'string', 'description' => 'Object type (post, page, category, etc.)' ),
									'type'        => array( 'type' => 'string', 'description' => 'Menu item type (custom, post_type, taxonomy)' ),
								),
							),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'menu_id' ),
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
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
						'items_added' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'message'     => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => true,
					),
				),
			)
		);
	}

	/**
	 * Check permission for creating menus.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_theme_options' );
	}

	/**
	 * Execute the create menu operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$menu_name = \sanitize_text_field( (string) $input['menu_name'] );
		$menu_description = isset( $input['menu_description'] ) ? \sanitize_textarea_field( (string) $input['menu_description'] ) : '';
		$menu_items = $input['menu_items'] ?? array();

		// Validate menu name
		if ( empty( $menu_name ) ) {
			return array(
				'success' => false,
				'menu_id' => 0,
				'message' => 'Menu name is required.',
			);
		}

		// Check if menu with this name already exists
		$existing_menu = \wp_get_nav_menu_object( $menu_name );
		if ( $existing_menu ) {
			return array(
				'success' => false,
				'menu_id' => 0,
				'message' => 'A menu with this name already exists.',
			);
		}

		// Create the menu
		$menu_id = \wp_create_nav_menu( $menu_name );

		if ( \is_wp_error( $menu_id ) ) {
			return array(
				'success' => false,
				'menu_id' => 0,
				'message' => 'Failed to create menu: ' . $menu_id->get_error_message(),
			);
		}

		// Update menu description if provided
		if ( ! empty( $menu_description ) ) {
			\wp_update_term( $menu_id, 'nav_menu', array(
				'description' => $menu_description,
			) );
		}

		// Get the created menu object
		$menu = \wp_get_nav_menu_object( $menu_id );

		$items_added = array();

		// Add menu items if provided
		if ( ! empty( $menu_items ) && is_array( $menu_items ) ) {
			$items_added = self::add_menu_items( $menu_id, $menu_items );
		}

		// Prepare response
		$menu_data = array(
			'term_id'     => (int) $menu->term_id,
			'name'        => $menu->name,
			'slug'        => $menu->slug,
			'description' => $menu->description,
			'count'       => (int) $menu->count,
		);

		return array(
			'success'     => true,
			'menu_id'     => $menu_id,
			'menu'        => $menu_data,
			'items_added' => $items_added,
			'message'     => 'Menu created successfully.',
		);
	}

	/**
	 * Add menu items to a menu.
	 *
	 * @param int   $menu_id Menu ID.
	 * @param array $menu_items Array of menu items to add.
	 * @return array Array of added menu item IDs.
	 */
	private static function add_menu_items( int $menu_id, array $menu_items ): array {
		$items_added = array();

		foreach ( $menu_items as $item_data ) {
			$title = \sanitize_text_field( (string) ( $item_data['title'] ?? '' ) );
			$url = \esc_url_raw( (string) ( $item_data['url'] ?? '' ) );

			if ( empty( $title ) || empty( $url ) ) {
				continue; // Skip invalid items
			}

			$menu_item_args = array(
				'menu-item-title'       => $title,
				'menu-item-url'         => $url,
				'menu-item-status'      => 'publish',
			);

			// Optional fields
			if ( isset( $item_data['target'] ) ) {
				$menu_item_args['menu-item-target'] = \sanitize_text_field( (string) $item_data['target'] );
			}

			if ( isset( $item_data['attr_title'] ) ) {
				$menu_item_args['menu-item-attr-title'] = \sanitize_text_field( (string) $item_data['attr_title'] );
			}

			if ( isset( $item_data['description'] ) ) {
				$menu_item_args['menu-item-description'] = \sanitize_textarea_field( (string) $item_data['description'] );
			}

			if ( isset( $item_data['classes'] ) ) {
				$menu_item_args['menu-item-classes'] = \sanitize_text_field( (string) $item_data['classes'] );
			}

			if ( isset( $item_data['xfn'] ) ) {
				$menu_item_args['menu-item-xfn'] = \sanitize_text_field( (string) $item_data['xfn'] );
			}

			if ( isset( $item_data['parent_id'] ) ) {
				$menu_item_args['menu-item-parent-id'] = (int) $item_data['parent_id'];
			}

			if ( isset( $item_data['object_id'] ) ) {
				$menu_item_args['menu-item-object-id'] = (int) $item_data['object_id'];
			}

			if ( isset( $item_data['object'] ) ) {
				$menu_item_args['menu-item-object'] = \sanitize_text_field( (string) $item_data['object'] );
			}

			if ( isset( $item_data['type'] ) ) {
				$menu_item_args['menu-item-type'] = \sanitize_text_field( (string) $item_data['type'] );
			} else {
				$menu_item_args['menu-item-type'] = 'custom';
			}

			// Add the menu item
			$item_id = \wp_update_nav_menu_item( $menu_id, 0, $menu_item_args );

			if ( ! \is_wp_error( $item_id ) && $item_id > 0 ) {
				$items_added[] = $item_id;
			}
		}

		return $items_added;
	}
}
