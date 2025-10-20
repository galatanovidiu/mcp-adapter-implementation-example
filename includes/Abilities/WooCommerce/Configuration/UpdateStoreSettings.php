<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class UpdateStoreSettings implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/update-store-settings',
			array(
				'label'               => 'Update WooCommerce Store Settings',
				'description'         => 'Update WooCommerce store configuration settings including general, products, shipping, tax, and other settings.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Settings category to update.',
							'enum'        => array( 'general', 'products', 'shipping', 'tax', 'checkout', 'account', 'email', 'advanced' ),
						),
						'settings' => array(
							'type'                 => 'object',
							'description'          => 'Settings to update as key-value pairs.',
							'additionalProperties' => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'updated_settings' ),
					'properties' => array(
						'success'          => array( 'type' => 'boolean' ),
						'category'         => array( 'type' => 'string' ),
						'updated_settings' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'key'   => array( 'type' => 'string' ),
									'value' => array( 'type' => 'string' ),
								),
							),
						),
						'message'          => array( 'type' => 'string' ),
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
					'categories'  => array( 'ecommerce', 'configuration' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.8,
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
		$category = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : 'general';
		$settings = $input['settings'] ?? array();

		if ( empty( $settings ) ) {
			return array(
				'success' => false,
				'message' => 'No settings provided to update.',
			);
		}

		$updated_settings = array();
		$errors           = array();

		foreach ( $settings as $key => $value ) {
			$sanitized_key   = sanitize_key( $key );
			$sanitized_value = self::sanitize_setting_value( $sanitized_key, $value );

			// Validate setting
			$validation_result = self::validate_setting( $sanitized_key, $sanitized_value );
			if ( $validation_result !== true ) {
				$errors[] = $validation_result;
				continue;
			}

			// Update the setting
			$result = update_option( $sanitized_key, $sanitized_value );
			if ( ! $result ) {
				continue;
			}

			$updated_settings[] = array(
				'key'   => $sanitized_key,
				'value' => $sanitized_value,
			);
		}

		$success = count( $updated_settings ) > 0;
		$message = $success
			? sprintf( 'Updated %d settings successfully.', count( $updated_settings ) )
			: 'No settings were updated.';

		if ( ! empty( $errors ) ) {
			$message .= ' Errors: ' . implode( ', ', $errors );
		}

		return array(
			'success'          => $success,
			'category'         => $category,
			'updated_settings' => $updated_settings,
			'message'          => $message,
		);
	}

	private static function sanitize_setting_value( string $key, $value ) {
		// Define sanitization rules for different setting types
		$sanitization_rules = array(
			// General settings
			'woocommerce_store_address'                    => 'sanitize_text_field',
			'woocommerce_store_address_2'                  => 'sanitize_text_field',
			'woocommerce_store_city'                       => 'sanitize_text_field',
			'woocommerce_store_postcode'                   => 'sanitize_text_field',
			'woocommerce_default_country'                  => 'sanitize_text_field',
			'woocommerce_currency'                         => 'sanitize_text_field',
			'woocommerce_currency_pos'                     => 'sanitize_text_field',
			'woocommerce_price_thousand_sep'               => 'sanitize_text_field',
			'woocommerce_price_decimal_sep'                => 'sanitize_text_field',
			'woocommerce_price_num_decimals'               => 'absint',

			// Product settings
			'woocommerce_shop_page_id'                     => 'absint',
			'woocommerce_cart_page_id'                     => 'absint',
			'woocommerce_checkout_page_id'                 => 'absint',
			'woocommerce_myaccount_page_id'                => 'absint',
			'woocommerce_manage_stock'                     => 'sanitize_text_field',
			'woocommerce_hold_stock_minutes'               => 'absint',
			'woocommerce_notify_low_stock'                 => 'sanitize_text_field',
			'woocommerce_notify_no_stock'                  => 'sanitize_text_field',
			'woocommerce_stock_email_recipient'            => 'sanitize_email',
			'woocommerce_notify_low_stock_amount'          => 'absint',
			'woocommerce_notify_no_stock_amount'           => 'absint',
			'woocommerce_hide_out_of_stock_items'          => 'sanitize_text_field',

			// Shipping settings
			'woocommerce_calc_shipping'                    => 'sanitize_text_field',
			'woocommerce_enable_shipping_calc'             => 'sanitize_text_field',
			'woocommerce_shipping_cost_requires_address'   => 'sanitize_text_field',
			'woocommerce_ship_to_destination'              => 'sanitize_text_field',
			'woocommerce_shipping_debug_mode'              => 'sanitize_text_field',

			// Tax settings
			'woocommerce_calc_taxes'                       => 'sanitize_text_field',
			'woocommerce_prices_include_tax'               => 'sanitize_text_field',
			'woocommerce_tax_based_on'                     => 'sanitize_text_field',
			'woocommerce_shipping_tax_class'               => 'sanitize_text_field',
			'woocommerce_tax_round_at_subtotal'            => 'sanitize_text_field',
			'woocommerce_tax_display_shop'                 => 'sanitize_text_field',
			'woocommerce_tax_display_cart'                 => 'sanitize_text_field',
			'woocommerce_price_display_suffix'             => 'sanitize_text_field',

			// Checkout settings
			'woocommerce_enable_guest_checkout'            => 'sanitize_text_field',
			'woocommerce_enable_checkout_login_reminder'   => 'sanitize_text_field',
			'woocommerce_enable_signup_and_login_from_checkout' => 'sanitize_text_field',
			'woocommerce_enable_myaccount_registration'    => 'sanitize_text_field',
			'woocommerce_registration_generate_username'   => 'sanitize_text_field',
			'woocommerce_registration_generate_password'   => 'sanitize_text_field',

			// Account settings
			'woocommerce_enable_reviews'                   => 'sanitize_text_field',
			'woocommerce_review_rating_required'           => 'sanitize_text_field',
			'woocommerce_review_rating_verification_label' => 'sanitize_text_field',
			'woocommerce_review_rating_verification_required' => 'sanitize_text_field',

			// Email settings
			'woocommerce_email_from_name'                  => 'sanitize_text_field',
			'woocommerce_email_from_address'               => 'sanitize_email',
			'woocommerce_email_header_image'               => 'esc_url_raw',
			'woocommerce_email_footer_text'                => 'wp_kses_post',
			'woocommerce_email_base_color'                 => 'sanitize_hex_color',
			'woocommerce_email_background_color'           => 'sanitize_hex_color',
			'woocommerce_email_body_background_color'      => 'sanitize_hex_color',
			'woocommerce_email_text_color'                 => 'sanitize_hex_color',

			// Advanced settings
			'woocommerce_terms_page_id'                    => 'absint',
			'woocommerce_force_ssl_checkout'               => 'sanitize_text_field',
			'woocommerce_unforce_ssl_checkout'             => 'sanitize_text_field',
			'woocommerce_cart_redirect_after_add'          => 'sanitize_text_field',
			'woocommerce_enable_ajax_add_to_cart'          => 'sanitize_text_field',
		);

		$sanitization_function = $sanitization_rules[ $key ] ?? 'sanitize_text_field';

		if ( function_exists( $sanitization_function ) ) {
			return $sanitization_function( $value );
		}

		return sanitize_text_field( $value );
	}

	private static function validate_setting( string $key, $value ): bool|string {
		// Define validation rules for different settings
		$validation_rules = array(
			'woocommerce_currency'           => static function ( $value ) {
				$currencies = array_keys( get_woocommerce_currencies() );
				return in_array( $value, $currencies, true ) ? true : 'Invalid currency code.';
			},
			'woocommerce_default_country'    => static function ( $value ) {
				$countries = WC()->countries->get_countries();
				return array_key_exists( $value, $countries ) ? true : 'Invalid country code.';
			},
			'woocommerce_currency_pos'       => static function ( $value ) {
				$valid_positions = array( 'left', 'right', 'left_space', 'right_space' );
				return in_array( $value, $valid_positions, true ) ? true : 'Invalid currency position.';
			},
			'woocommerce_price_num_decimals' => static function ( $value ) {
				return $value >= 0 && $value <= 6 ? true : 'Number of decimals must be between 0 and 6.';
			},
			'woocommerce_email_from_address' => static function ( $value ) {
				return is_email( $value ) ? true : 'Invalid email address.';
			},
		);

		$validation_function = $validation_rules[ $key ] ?? null;

		if ( $validation_function && is_callable( $validation_function ) ) {
			return $validation_function( $value );
		}

		return true;
	}
}
