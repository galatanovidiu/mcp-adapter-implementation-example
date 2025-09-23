<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Menus;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class UpdateMenu implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/update-menu',
			array(
				'label'               => 'Update Menu',
				'description'         => 'Update a WordPress navigation menu and its items.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'menu_identifier' ),
					'properties' => array(
						'menu_identifier' => array(
							'type'        => 'string',
							'description' => 'Menu ID, slug, or name to update.',
						),
						'menu_name' => array(
							'type'        => 'string',
							'description' => 'New menu name.',
						),
						'menu_description' => array(
							'type'        => 'string',
							'description' => 'New menu description.',
						),
						'add_items' => array(
							'type'        => 'array',
							'description' => 'Array of menu items to add.',
							'items'       => array(
								'type'       => 'object',
								'required'   => array( 'title', 'url' ),
								'properties' => array(
									'title'       => array( 'type' => 'string' ),
									'url'         => array( 'type' => 'string' ),
									'target'      => array( 'type' => 'string' ),
									'attr_title'  => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'classes'     => array( 'type' => 'string' ),
									'xfn'         => array( 'type' => 'string' ),
									'menu_order'  => array( 'type' => 'integer' ),
									'parent_id'   => array( 'type' => 'integer' ),
									'object_id'   => array( 'type' => 'integer' ),
									'object'      => array( 'type' => 'string' ),
									'type'        => array( 'type' => 'string' ),
								),
							),
						),
						'update_items' => array(
							'type'        => 'array',
							'description' => 'Array of existing menu items to update.',
							'items'       => array(
								'type'       => 'object',
								'required'   => array( 'item_id' ),
								'properties' => array(
									'item_id'     => array( 'type' => 'integer' ),
									'title'       => array( 'type' => 'string' ),
									'url'         => array( 'type' => 'string' ),
									'target'      => array( 'type' => 'string' ),
									'attr_title'  => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'classes'     => array( 'type' => 'string' ),
									'xfn'         => array( 'type' => 'string' ),
									'menu_order'  => array( 'type' => 'integer' ),
									'parent_id'   => array( 'type' => 'integer' ),
								),
							),
						),
						'remove_items' => array(
							'type'        => 'array',
							'description' => 'Array of menu item IDs to remove.',
							'items'       => array( 'type' => 'integer' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'menu_id' ),
					'properties' => array(
						'success'       => array( 'type' => 'boolean' ),
						'menu_id'       => array( 'type' => 'integer' ),
						'menu'          => array(
							'type'       => 'object',
							'properties' => array(
								'term_id'     => array( 'type' => 'integer' ),
								'name'        => array( 'type' => 'string' ),
								'slug'        => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'count'       => array( 'type' => 'integer' ),
							),
						),
						'items_added'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'items_updated' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'items_removed' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'updated_fields' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message'       => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'content', 'menus' ),
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
	 * Check permission for updating menus.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'edit_theme_options' );
	}

	/**
	 * Execute the update menu operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$menu_identifier = \sanitize_text_field( (string) $input['menu_identifier'] );
		$menu_name = isset( $input['menu_name'] ) ? \sanitize_text_field( (string) $input['menu_name'] ) : '';
		$menu_description = isset( $input['menu_description'] ) ? \sanitize_textarea_field( (string) $input['menu_description'] ) : '';
		$add_items = $input['add_items'] ?? array();
		$update_items = $input['update_items'] ?? array();
		$remove_items = $input['remove_items'] ?? array();

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
		$updated_fields = array();

		// Update menu name and description
		$update_args = array();
		if ( ! empty( $menu_name ) && $menu_name !== $menu->name ) {
			$update_args['name'] = $menu_name;
			$updated_fields[] = 'name';
		}

		if ( isset( $input['menu_description'] ) && $menu_description !== $menu->description ) {
			$update_args['description'] = $menu_description;
			$updated_fields[] = 'description';
		}

		if ( ! empty( $update_args ) ) {
			$result = \wp_update_term( $menu_id, 'nav_menu', $update_args );
			if ( \is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'menu_id' => $menu_id,
					'message' => 'Failed to update menu: ' . $result->get_error_message(),
				);
			}
		}

		$items_added = array();
		$items_updated = array();
		$items_removed = array();

		// Remove menu items
		if ( ! empty( $remove_items ) && is_array( $remove_items ) ) {
			foreach ( $remove_items as $item_id ) {
				$item_id = (int) $item_id;
				if ( $item_id > 0 ) {
					$result = \wp_delete_post( $item_id, true );
					if ( $result ) {
						$items_removed[] = $item_id;
					}
				}
			}
		}

		// Update existing menu items
		if ( ! empty( $update_items ) && is_array( $update_items ) ) {
			foreach ( $update_items as $item_data ) {
				$item_id = (int) ( $item_data['item_id'] ?? 0 );
				if ( $item_id <= 0 ) {
					continue;
				}

				$menu_item_args = array();

				if ( isset( $item_data['title'] ) ) {
					$menu_item_args['menu-item-title'] = \sanitize_text_field( (string) $item_data['title'] );
				}

				if ( isset( $item_data['url'] ) ) {
					$menu_item_args['menu-item-url'] = \esc_url_raw( (string) $item_data['url'] );
				}

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

				if ( ! empty( $menu_item_args ) ) {
					$result = \wp_update_nav_menu_item( $menu_id, $item_id, $menu_item_args );
					if ( ! \is_wp_error( $result ) && $result > 0 ) {
						$items_updated[] = $item_id;
					}
				}
			}
		}

		// Add new menu items
		if ( ! empty( $add_items ) && is_array( $add_items ) ) {
			foreach ( $add_items as $item_data ) {
				$title = \sanitize_text_field( (string) ( $item_data['title'] ?? '' ) );
				$url = \esc_url_raw( (string) ( $item_data['url'] ?? '' ) );

				if ( empty( $title ) || empty( $url ) ) {
					continue;
				}

				$menu_item_args = array(
					'menu-item-title'  => $title,
					'menu-item-url'    => $url,
					'menu-item-status' => 'publish',
					'menu-item-type'   => isset( $item_data['type'] ) ? \sanitize_text_field( (string) $item_data['type'] ) : 'custom',
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

				$item_id = \wp_update_nav_menu_item( $menu_id, 0, $menu_item_args );
				if ( ! \is_wp_error( $item_id ) && $item_id > 0 ) {
					$items_added[] = $item_id;
				}
			}
		}

		// Get updated menu object
		$updated_menu = \wp_get_nav_menu_object( $menu_id );

		$menu_data = array(
			'term_id'     => (int) $updated_menu->term_id,
			'name'        => $updated_menu->name,
			'slug'        => $updated_menu->slug,
			'description' => $updated_menu->description,
			'count'       => (int) $updated_menu->count,
		);

		return array(
			'success'        => true,
			'menu_id'        => $menu_id,
			'menu'           => $menu_data,
			'items_added'    => $items_added,
			'items_updated'  => $items_updated,
			'items_removed'  => $items_removed,
			'updated_fields' => $updated_fields,
			'message'        => 'Menu updated successfully.',
		);
	}
}
