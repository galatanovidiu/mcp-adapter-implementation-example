<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class GetStoreSettings implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/get-store-settings',
			array(
				'label'               => 'Get WooCommerce Store Settings',
				'description'         => 'Retrieve WooCommerce store settings organized by category (general, products, shipping, tax, etc.).',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'category'         => array(
							'type'        => 'string',
							'description' => 'Filter settings by category.',
							'enum'        => array( 'general', 'products', 'shipping', 'tax', 'checkout', 'account', 'email', 'advanced', 'all' ),
							'default'     => 'all',
						),
						'include_defaults' => array(
							'type'        => 'boolean',
							'description' => 'Include default values for unset options.',
							'default'     => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'settings'   => array(
							'type'       => 'object',
							'properties' => array(
								'general'  => array( 'type' => 'object' ),
								'products' => array( 'type' => 'object' ),
								'shipping' => array( 'type' => 'object' ),
								'tax'      => array( 'type' => 'object' ),
								'checkout' => array( 'type' => 'object' ),
								'account'  => array( 'type' => 'object' ),
								'email'    => array( 'type' => 'object' ),
								'advanced' => array( 'type' => 'object' ),
							),
						),
						'store_info' => array(
							'type'       => 'object',
							'properties' => array(
								'currency'        => array( 'type' => 'string' ),
								'currency_symbol' => array( 'type' => 'string' ),
								'base_country'    => array( 'type' => 'string' ),
								'base_state'      => array( 'type' => 'string' ),
								'wc_version'      => array( 'type' => 'string' ),
								'store_address'   => array( 'type' => 'string' ),
								'store_city'      => array( 'type' => 'string' ),
								'store_postcode'  => array( 'type' => 'string' ),
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

	public static function check_permission(): bool {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'settings'   => array(),
				'store_info' => array(),
				'message'    => 'WooCommerce is not active.',
			);
		}

		$category         = $input['category'] ?? 'all';
		$include_defaults = $input['include_defaults'] ?? true;

		$settings = array();

		// Get general settings
		if ( $category === 'all' || $category === 'general' ) {
			$settings['general'] = self::get_general_settings( $include_defaults );
		}

		// Get product settings
		if ( $category === 'all' || $category === 'products' ) {
			$settings['products'] = self::get_product_settings( $include_defaults );
		}

		// Get shipping settings
		if ( $category === 'all' || $category === 'shipping' ) {
			$settings['shipping'] = self::get_shipping_settings( $include_defaults );
		}

		// Get tax settings
		if ( $category === 'all' || $category === 'tax' ) {
			$settings['tax'] = self::get_tax_settings( $include_defaults );
		}

		// Get checkout settings
		if ( $category === 'all' || $category === 'checkout' ) {
			$settings['checkout'] = self::get_checkout_settings( $include_defaults );
		}

		// Get account settings
		if ( $category === 'all' || $category === 'account' ) {
			$settings['account'] = self::get_account_settings( $include_defaults );
		}

		// Get email settings
		if ( $category === 'all' || $category === 'email' ) {
			$settings['email'] = self::get_email_settings( $include_defaults );
		}

		// Get advanced settings
		if ( $category === 'all' || $category === 'advanced' ) {
			$settings['advanced'] = self::get_advanced_settings( $include_defaults );
		}

		// Get store info
		$store_info = self::get_store_info();

		return array(
			'settings'   => $settings,
			'store_info' => $store_info,
			'message'    => sprintf( 'Retrieved WooCommerce %s settings.', $category === 'all' ? 'all' : $category ),
		);
	}

	private static function get_general_settings( bool $include_defaults ): array {
		return array(
			'woocommerce_store_address'      => get_option( 'woocommerce_store_address', $include_defaults ? '' : null ),
			'woocommerce_store_address_2'    => get_option( 'woocommerce_store_address_2', $include_defaults ? '' : null ),
			'woocommerce_store_city'         => get_option( 'woocommerce_store_city', $include_defaults ? '' : null ),
			'woocommerce_default_country'    => get_option( 'woocommerce_default_country', $include_defaults ? 'US:CA' : null ),
			'woocommerce_store_postcode'     => get_option( 'woocommerce_store_postcode', $include_defaults ? '' : null ),
			'woocommerce_currency'           => get_option( 'woocommerce_currency', $include_defaults ? 'USD' : null ),
			'woocommerce_currency_pos'       => get_option( 'woocommerce_currency_pos', $include_defaults ? 'left' : null ),
			'woocommerce_price_thousand_sep' => get_option( 'woocommerce_price_thousand_sep', $include_defaults ? ',' : null ),
			'woocommerce_price_decimal_sep'  => get_option( 'woocommerce_price_decimal_sep', $include_defaults ? '.' : null ),
			'woocommerce_price_num_decimals' => get_option( 'woocommerce_price_num_decimals', $include_defaults ? 2 : null ),
		);
	}

	private static function get_product_settings( bool $include_defaults ): array {
		return array(
			'woocommerce_shop_page_id'            => get_option( 'woocommerce_shop_page_id', $include_defaults ? 0 : null ),
			'woocommerce_cart_page_id'            => get_option( 'woocommerce_cart_page_id', $include_defaults ? 0 : null ),
			'woocommerce_checkout_page_id'        => get_option( 'woocommerce_checkout_page_id', $include_defaults ? 0 : null ),
			'woocommerce_myaccount_page_id'       => get_option( 'woocommerce_myaccount_page_id', $include_defaults ? 0 : null ),
			'woocommerce_manage_stock'            => get_option( 'woocommerce_manage_stock', $include_defaults ? 'yes' : null ),
			'woocommerce_hold_stock_minutes'      => get_option( 'woocommerce_hold_stock_minutes', $include_defaults ? 60 : null ),
			'woocommerce_notify_low_stock'        => get_option( 'woocommerce_notify_low_stock', $include_defaults ? 'yes' : null ),
			'woocommerce_notify_no_stock'         => get_option( 'woocommerce_notify_no_stock', $include_defaults ? 'yes' : null ),
			'woocommerce_stock_email_recipient'   => get_option( 'woocommerce_stock_email_recipient', $include_defaults ? get_option( 'admin_email' ) : null ),
			'woocommerce_notify_low_stock_amount' => get_option( 'woocommerce_notify_low_stock_amount', $include_defaults ? 2 : null ),
			'woocommerce_notify_no_stock_amount'  => get_option( 'woocommerce_notify_no_stock_amount', $include_defaults ? 0 : null ),
			'woocommerce_hide_out_of_stock_items' => get_option( 'woocommerce_hide_out_of_stock_items', $include_defaults ? 'no' : null ),
		);
	}

	private static function get_shipping_settings( bool $include_defaults ): array {
		return array(
			'woocommerce_calc_shipping'                  => get_option( 'woocommerce_calc_shipping', $include_defaults ? 'no' : null ),
			'woocommerce_enable_shipping_calc'           => get_option( 'woocommerce_enable_shipping_calc', $include_defaults ? 'yes' : null ),
			'woocommerce_shipping_cost_requires_address' => get_option( 'woocommerce_shipping_cost_requires_address', $include_defaults ? 'no' : null ),
			'woocommerce_ship_to_destination'            => get_option( 'woocommerce_ship_to_destination', $include_defaults ? 'billing' : null ),
			'woocommerce_shipping_debug_mode'            => get_option( 'woocommerce_shipping_debug_mode', $include_defaults ? 'no' : null ),
		);
	}

	private static function get_tax_settings( bool $include_defaults ): array {
		return array(
			'woocommerce_calc_taxes'            => get_option( 'woocommerce_calc_taxes', $include_defaults ? 'no' : null ),
			'woocommerce_prices_include_tax'    => get_option( 'woocommerce_prices_include_tax', $include_defaults ? 'no' : null ),
			'woocommerce_tax_based_on'          => get_option( 'woocommerce_tax_based_on', $include_defaults ? 'shipping' : null ),
			'woocommerce_shipping_tax_class'    => get_option( 'woocommerce_shipping_tax_class', $include_defaults ? 'inherit' : null ),
			'woocommerce_tax_round_at_subtotal' => get_option( 'woocommerce_tax_round_at_subtotal', $include_defaults ? 'no' : null ),
			'woocommerce_tax_display_shop'      => get_option( 'woocommerce_tax_display_shop', $include_defaults ? 'excl' : null ),
			'woocommerce_tax_display_cart'      => get_option( 'woocommerce_tax_display_cart', $include_defaults ? 'excl' : null ),
			'woocommerce_price_display_suffix'  => get_option( 'woocommerce_price_display_suffix', $include_defaults ? '' : null ),
		);
	}

	private static function get_checkout_settings( bool $include_defaults ): array {
		return array(
			'woocommerce_enable_guest_checkout'          => get_option( 'woocommerce_enable_guest_checkout', $include_defaults ? 'yes' : null ),
			'woocommerce_enable_checkout_login_reminder' => get_option( 'woocommerce_enable_checkout_login_reminder', $include_defaults ? 'no' : null ),
			'woocommerce_enable_signup_and_login_from_checkout' => get_option( 'woocommerce_enable_signup_and_login_from_checkout', $include_defaults ? 'no' : null ),
			'woocommerce_enable_myaccount_registration'  => get_option( 'woocommerce_enable_myaccount_registration', $include_defaults ? 'no' : null ),
			'woocommerce_registration_generate_username' => get_option( 'woocommerce_registration_generate_username', $include_defaults ? 'yes' : null ),
			'woocommerce_registration_generate_password' => get_option( 'woocommerce_registration_generate_password', $include_defaults ? 'yes' : null ),
		);
	}

	private static function get_account_settings( bool $include_defaults ): array {
		return array(
			'woocommerce_enable_reviews'                   => get_option( 'woocommerce_enable_reviews', $include_defaults ? 'yes' : null ),
			'woocommerce_review_rating_required'           => get_option( 'woocommerce_review_rating_required', $include_defaults ? 'yes' : null ),
			'woocommerce_review_rating_verification_label' => get_option( 'woocommerce_review_rating_verification_label', $include_defaults ? 'yes' : null ),
			'woocommerce_review_rating_verification_required' => get_option( 'woocommerce_review_rating_verification_required', $include_defaults ? 'no' : null ),
		);
	}

	private static function get_email_settings( bool $include_defaults ): array {
		return array(
			'woocommerce_email_from_name'             => get_option( 'woocommerce_email_from_name', $include_defaults ? get_option( 'blogname' ) : null ),
			'woocommerce_email_from_address'          => get_option( 'woocommerce_email_from_address', $include_defaults ? get_option( 'admin_email' ) : null ),
			'woocommerce_email_header_image'          => get_option( 'woocommerce_email_header_image', $include_defaults ? '' : null ),
			'woocommerce_email_footer_text'           => get_option( 'woocommerce_email_footer_text', $include_defaults ? '' : null ),
			'woocommerce_email_base_color'            => get_option( 'woocommerce_email_base_color', $include_defaults ? '#96588a' : null ),
			'woocommerce_email_background_color'      => get_option( 'woocommerce_email_background_color', $include_defaults ? '#f7f7f7' : null ),
			'woocommerce_email_body_background_color' => get_option( 'woocommerce_email_body_background_color', $include_defaults ? '#ffffff' : null ),
			'woocommerce_email_text_color'            => get_option( 'woocommerce_email_text_color', $include_defaults ? '#3c3c3c' : null ),
		);
	}

	private static function get_advanced_settings( bool $include_defaults ): array {
		return array(
			'woocommerce_cart_page_id'            => get_option( 'woocommerce_cart_page_id', $include_defaults ? 0 : null ),
			'woocommerce_checkout_page_id'        => get_option( 'woocommerce_checkout_page_id', $include_defaults ? 0 : null ),
			'woocommerce_myaccount_page_id'       => get_option( 'woocommerce_myaccount_page_id', $include_defaults ? 0 : null ),
			'woocommerce_terms_page_id'           => get_option( 'woocommerce_terms_page_id', $include_defaults ? 0 : null ),
			'woocommerce_force_ssl_checkout'      => get_option( 'woocommerce_force_ssl_checkout', $include_defaults ? 'no' : null ),
			'woocommerce_unforce_ssl_checkout'    => get_option( 'woocommerce_unforce_ssl_checkout', $include_defaults ? 'no' : null ),
			'woocommerce_cart_redirect_after_add' => get_option( 'woocommerce_cart_redirect_after_add', $include_defaults ? 'no' : null ),
			'woocommerce_enable_ajax_add_to_cart' => get_option( 'woocommerce_enable_ajax_add_to_cart', $include_defaults ? 'yes' : null ),
		);
	}

	private static function get_store_info(): array {
		$currency        = get_woocommerce_currency();
		$currency_symbol = get_woocommerce_currency_symbol( $currency );
		$base_location   = wc_get_base_location();

		return array(
			'currency'        => $currency,
			'currency_symbol' => $currency_symbol,
			'base_country'    => $base_location['country'],
			'base_state'      => $base_location['state'],
			'wc_version'      => defined( 'WC_VERSION' ) ? WC_VERSION : 'Unknown',
			'store_address'   => get_option( 'woocommerce_store_address', '' ),
			'store_city'      => get_option( 'woocommerce_store_city', '' ),
			'store_postcode'  => get_option( 'woocommerce_store_postcode', '' ),
		);
	}
}
