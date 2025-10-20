<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class ManagePaymentMethods implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/manage-payment-methods',
			array(
				'label'               => 'Manage WooCommerce Payment Methods',
				'description'         => 'Enable, disable, and configure WooCommerce payment gateways and methods.',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'action' ),
					'properties'           => array(
						'action'     => array(
							'type'        => 'string',
							'description' => 'Action to perform: list, enable, disable, configure.',
							'enum'        => array( 'list', 'enable', 'disable', 'configure' ),
						),
						'gateway_id' => array(
							'type'        => 'string',
							'description' => 'Payment gateway ID (required for enable/disable/configure actions).',
						),
						'settings'   => array(
							'type'                 => 'object',
							'description'          => 'Gateway settings to configure (for configure action).',
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
						'gateway_id' => array( 'type' => 'string' ),
						'gateways'   => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'          => array( 'type' => 'string' ),
									'title'       => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'enabled'     => array( 'type' => 'boolean' ),
									'available'   => array( 'type' => 'boolean' ),
									'supports'    => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'settings'    => array( 'type' => 'object' ),
								),
							),
						),
						'message'    => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'ecommerce',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
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
		$action     = sanitize_text_field( $input['action'] );
		$gateway_id = isset( $input['gateway_id'] ) ? sanitize_text_field( $input['gateway_id'] ) : '';
		$settings   = $input['settings'] ?? array();

		switch ( $action ) {
			case 'list':
				return self::list_payment_gateways();

			case 'enable':
				return self::enable_payment_gateway( $gateway_id );

			case 'disable':
				return self::disable_payment_gateway( $gateway_id );

			case 'configure':
				return self::configure_payment_gateway( $gateway_id, $settings );

			default:
				return array(
					'success' => false,
					'action'  => $action,
					'message' => 'Invalid action specified.',
				);
		}
	}

	private static function list_payment_gateways(): array {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$gateways_data    = array();

		foreach ( $payment_gateways as $gateway ) {
			$gateways_data[] = array(
				'id'          => $gateway->id,
				'title'       => $gateway->get_title(),
				'description' => $gateway->get_description(),
				'enabled'     => $gateway->is_available(),
				'available'   => $gateway->is_available(),
				'supports'    => $gateway->supports,
				'settings'    => $gateway->settings ?? array(),
			);
		}

		return array(
			'success'  => true,
			'action'   => 'list',
			'gateways' => $gateways_data,
			'message'  => 'Payment gateways retrieved successfully.',
		);
	}

	private static function enable_payment_gateway( string $gateway_id ): array {
		if ( empty( $gateway_id ) ) {
			return array(
				'success' => false,
				'action'  => 'enable',
				'message' => 'Gateway ID is required.',
			);
		}

		$payment_gateways = WC()->payment_gateways->payment_gateways();

		if ( ! isset( $payment_gateways[ $gateway_id ] ) ) {
			return array(
				'success'    => false,
				'action'     => 'enable',
				'gateway_id' => $gateway_id,
				'message'    => 'Payment gateway not found.',
			);
		}

		$gateway = $payment_gateways[ $gateway_id ];

		// Enable the gateway
		$gateway_settings            = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
		$gateway_settings['enabled'] = 'yes';
		update_option( 'woocommerce_' . $gateway_id . '_settings', $gateway_settings );

		return array(
			'success'    => true,
			'action'     => 'enable',
			'gateway_id' => $gateway_id,
			'message'    => sprintf( 'Payment gateway "%s" enabled successfully.', $gateway->get_title() ),
		);
	}

	private static function disable_payment_gateway( string $gateway_id ): array {
		if ( empty( $gateway_id ) ) {
			return array(
				'success' => false,
				'action'  => 'disable',
				'message' => 'Gateway ID is required.',
			);
		}

		$payment_gateways = WC()->payment_gateways->payment_gateways();

		if ( ! isset( $payment_gateways[ $gateway_id ] ) ) {
			return array(
				'success'    => false,
				'action'     => 'disable',
				'gateway_id' => $gateway_id,
				'message'    => 'Payment gateway not found.',
			);
		}

		$gateway = $payment_gateways[ $gateway_id ];

		// Disable the gateway
		$gateway_settings            = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );
		$gateway_settings['enabled'] = 'no';
		update_option( 'woocommerce_' . $gateway_id . '_settings', $gateway_settings );

		return array(
			'success'    => true,
			'action'     => 'disable',
			'gateway_id' => $gateway_id,
			'message'    => sprintf( 'Payment gateway "%s" disabled successfully.', $gateway->get_title() ),
		);
	}

	private static function configure_payment_gateway( string $gateway_id, array $settings ): array {
		if ( empty( $gateway_id ) ) {
			return array(
				'success' => false,
				'action'  => 'configure',
				'message' => 'Gateway ID is required.',
			);
		}

		$payment_gateways = WC()->payment_gateways->payment_gateways();

		if ( ! isset( $payment_gateways[ $gateway_id ] ) ) {
			return array(
				'success'    => false,
				'action'     => 'configure',
				'gateway_id' => $gateway_id,
				'message'    => 'Payment gateway not found.',
			);
		}

		$gateway = $payment_gateways[ $gateway_id ];

		// Get current settings
		$gateway_settings = get_option( 'woocommerce_' . $gateway_id . '_settings', array() );

		// Update settings
		foreach ( $settings as $key => $value ) {
			$gateway_settings[ $key ] = sanitize_text_field( $value );
		}

		update_option( 'woocommerce_' . $gateway_id . '_settings', $gateway_settings );

		return array(
			'success'    => true,
			'action'     => 'configure',
			'gateway_id' => $gateway_id,
			'message'    => sprintf( 'Payment gateway "%s" configured successfully.', $gateway->get_title() ),
		);
	}
}
