<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ManageShippingMethods implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/manage-shipping-methods',
			array(
				'label'               => 'Manage WooCommerce Shipping Methods',
				'description'         => 'Configure WooCommerce shipping zones, methods, and rates.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'action' ),
					'properties' => array(
						'action' => array(
							'type'        => 'string',
							'description' => 'Action to perform: list_zones, list_methods, create_zone, add_method, configure_method.',
							'enum'        => array( 'list_zones', 'list_methods', 'create_zone', 'add_method', 'configure_method' ),
						),
						'zone_id' => array(
							'type'        => 'integer',
							'description' => 'Shipping zone ID (required for zone-specific actions).',
						),
						'zone_name' => array(
							'type'        => 'string',
							'description' => 'Shipping zone name (for create_zone action).',
						),
						'zone_locations' => array(
							'type'        => 'array',
							'description' => 'Zone locations (for create_zone action).',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'code' => array( 'type' => 'string' ),
									'type' => array( 'type' => 'string' ),
								),
							),
						),
						'method_id' => array(
							'type'        => 'string',
							'description' => 'Shipping method ID (for method-specific actions).',
						),
						'method_type' => array(
							'type'        => 'string',
							'description' => 'Shipping method type (flat_rate, free_shipping, local_pickup).',
							'enum'        => array( 'flat_rate', 'free_shipping', 'local_pickup' ),
						),
						'method_title' => array(
							'type'        => 'string',
							'description' => 'Shipping method title.',
						),
						'method_settings' => array(
							'type'        => 'object',
							'description' => 'Shipping method settings.',
							'additionalProperties' => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'action' ),
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'action'     => array( 'type' => 'string' ),
						'zone_id'    => array( 'type' => 'integer' ),
						'method_id'  => array( 'type' => 'string' ),
						'zones'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'integer' ),
									'name'        => array( 'type' => 'string' ),
									'locations'   => array( 'type' => 'array' ),
									'methods'     => array( 'type' => 'array' ),
									'order'       => array( 'type' => 'integer' ),
								),
							),
						),
						'methods'    => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'string' ),
									'title'       => array( 'type' => 'string' ),
									'type'        => array( 'type' => 'string' ),
									'enabled'     => array( 'type' => 'boolean' ),
									'settings'    => array( 'type' => 'object' ),
								),
							),
						),
						'message'    => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'ecommerce', 'configuration' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.9,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	public static function check_permission(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	public static function execute( array $input ): array {
		$action = sanitize_text_field( $input['action'] );
		$zone_id = isset( $input['zone_id'] ) ? (int) $input['zone_id'] : 0;
		$zone_name = isset( $input['zone_name'] ) ? sanitize_text_field( $input['zone_name'] ) : '';
		$zone_locations = $input['zone_locations'] ?? array();
		$method_id = isset( $input['method_id'] ) ? sanitize_text_field( $input['method_id'] ) : '';
		$method_type = isset( $input['method_type'] ) ? sanitize_text_field( $input['method_type'] ) : '';
		$method_title = isset( $input['method_title'] ) ? sanitize_text_field( $input['method_title'] ) : '';
		$method_settings = $input['method_settings'] ?? array();

		switch ( $action ) {
			case 'list_zones':
				return self::list_shipping_zones();

			case 'list_methods':
				return self::list_shipping_methods( $zone_id );

			case 'create_zone':
				return self::create_shipping_zone( $zone_name, $zone_locations );

			case 'add_method':
				return self::add_shipping_method( $zone_id, $method_type, $method_title );

			case 'configure_method':
				return self::configure_shipping_method( $zone_id, $method_id, $method_settings );

			default:
				return array(
					'success' => false,
					'action'  => $action,
					'message' => 'Invalid action specified.',
				);
		}
	}

	private static function list_shipping_zones(): array {
		$zones = \WC_Shipping_Zones::get_zones();
		$zones_data = array();

		foreach ( $zones as $zone ) {
			$zone_obj = new \WC_Shipping_Zone( $zone['id'] );
			$methods = $zone_obj->get_shipping_methods();
			$methods_data = array();

			foreach ( $methods as $method ) {
				$methods_data[] = array(
					'id'       => $method->get_id(),
					'title'    => $method->get_title(),
					'type'     => $method->get_method_title(),
					'enabled'  => $method->is_enabled(),
					'settings' => $method->get_instance_option(),
				);
			}

			$zones_data[] = array(
				'id'        => $zone['id'],
				'name'      => $zone['zone_name'],
				'locations' => $zone['zone_locations'],
				'methods'   => $methods_data,
				'order'     => $zone['zone_order'],
			);
		}

		// Add "Rest of the World" zone
		$rest_of_world = new \WC_Shipping_Zone( 0 );
		$methods = $rest_of_world->get_shipping_methods();
		$methods_data = array();

		foreach ( $methods as $method ) {
			$methods_data[] = array(
				'id'       => $method->get_id(),
				'title'    => $method->get_title(),
				'type'     => $method->get_method_title(),
				'enabled'  => $method->is_enabled(),
				'settings' => $method->get_instance_option(),
			);
		}

		$zones_data[] = array(
			'id'        => 0,
			'name'      => 'Rest of the World',
			'locations' => array(),
			'methods'   => $methods_data,
			'order'     => 0,
		);

		return array(
			'success' => true,
			'action'  => 'list_zones',
			'zones'   => $zones_data,
			'message' => 'Shipping zones retrieved successfully.',
		);
	}

	private static function list_shipping_methods( int $zone_id ): array {
		$zone = new \WC_Shipping_Zone( $zone_id );
		$methods = $zone->get_shipping_methods();
		$methods_data = array();

		foreach ( $methods as $method ) {
			$methods_data[] = array(
				'id'       => $method->get_id(),
				'title'    => $method->get_title(),
				'type'     => $method->get_method_title(),
				'enabled'  => $method->is_enabled(),
				'settings' => $method->get_instance_option(),
			);
		}

		return array(
			'success'  => true,
			'action'   => 'list_methods',
			'zone_id'  => $zone_id,
			'methods'  => $methods_data,
			'message'  => 'Shipping methods retrieved successfully.',
		);
	}

	private static function create_shipping_zone( string $zone_name, array $zone_locations ): array {
		if ( empty( $zone_name ) ) {
			return array(
				'success' => false,
				'action'  => 'create_zone',
				'message' => 'Zone name is required.',
			);
		}

		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( $zone_name );

		// Add locations
		foreach ( $zone_locations as $location ) {
			$zone->add_location( $location['code'], $location['type'] );
		}

		$zone_id = $zone->save();

		return array(
			'success'  => true,
			'action'   => 'create_zone',
			'zone_id'  => $zone_id,
			'message'  => sprintf( 'Shipping zone "%s" created successfully.', $zone_name ),
		);
	}

	private static function add_shipping_method( int $zone_id, string $method_type, string $method_title ): array {
		if ( empty( $method_type ) ) {
			return array(
				'success' => false,
				'action'  => 'add_method',
				'message' => 'Method type is required.',
			);
		}

		$zone = new \WC_Shipping_Zone( $zone_id );
		$method_id = $zone->add_shipping_method( $method_type );

		if ( $method_id ) {
			$method = $zone->get_shipping_method( $method_id );
			if ( $method && ! empty( $method_title ) ) {
				$method->set_title( $method_title );
				$method->save();
			}

			return array(
				'success'   => true,
				'action'    => 'add_method',
				'zone_id'   => $zone_id,
				'method_id' => $method_id,
				'message'   => sprintf( 'Shipping method "%s" added to zone successfully.', $method_type ),
			);
		}

		return array(
			'success' => false,
			'action'  => 'add_method',
			'zone_id' => $zone_id,
			'message' => 'Failed to add shipping method.',
		);
	}

	private static function configure_shipping_method( int $zone_id, string $method_id, array $method_settings ): array {
		if ( empty( $method_id ) ) {
			return array(
				'success' => false,
				'action'  => 'configure_method',
				'message' => 'Method ID is required.',
			);
		}

		$zone = new \WC_Shipping_Zone( $zone_id );
		$method = $zone->get_shipping_method( $method_id );

		if ( ! $method ) {
			return array(
				'success'   => false,
				'action'    => 'configure_method',
				'zone_id'   => $zone_id,
				'method_id' => $method_id,
				'message'   => 'Shipping method not found.',
			);
		}

		// Update method settings
		foreach ( $method_settings as $key => $value ) {
			$method->set_instance_option( $key, sanitize_text_field( $value ) );
		}

		$method->save();

		return array(
			'success'   => true,
			'action'    => 'configure_method',
			'zone_id'   => $zone_id,
			'method_id' => $method_id,
			'message'   => 'Shipping method configured successfully.',
		);
	}
}
