<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class GetStoreStatus implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/get-store-status',
			array(
				'label'               => 'Get WooCommerce Store Status',
				'description'         => 'Retrieve comprehensive WooCommerce store health and status information.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_system_info' => array(
							'type'        => 'boolean',
							'description' => 'Include detailed system information.',
							'default'     => true,
						),
						'include_database_info' => array(
							'type'        => 'boolean',
							'description' => 'Include database status information.',
							'default'     => true,
						),
						'include_plugin_info' => array(
							'type'        => 'boolean',
							'description' => 'Include WooCommerce plugin and extension information.',
							'default'     => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'       => 'object',
							'properties' => array(
								'overall_status'    => array( 'type' => 'string' ),
								'woocommerce_active' => array( 'type' => 'boolean' ),
								'setup_complete'    => array( 'type' => 'boolean' ),
								'pages_configured'  => array( 'type' => 'boolean' ),
								'payment_methods'   => array( 'type' => 'integer' ),
								'shipping_methods'  => array( 'type' => 'integer' ),
								'products_count'    => array( 'type' => 'integer' ),
								'orders_count'      => array( 'type' => 'integer' ),
							),
						),
						'system_info' => array( 'type' => 'object' ),
						'database_info' => array( 'type' => 'object' ),
						'plugin_info' => array( 'type' => 'object' ),
						'recommendations' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'ecommerce', 'monitoring' ),
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
		$include_system_info = $input['include_system_info'] ?? true;
		$include_database_info = $input['include_database_info'] ?? true;
		$include_plugin_info = $input['include_plugin_info'] ?? true;

		// Check if WooCommerce is active
		$woocommerce_active = class_exists( 'WooCommerce' );

		$status = array(
			'overall_status'     => 'unknown',
			'woocommerce_active' => $woocommerce_active,
			'setup_complete'     => false,
			'pages_configured'   => false,
			'payment_methods'    => 0,
			'shipping_methods'   => 0,
			'products_count'     => 0,
			'orders_count'       => 0,
		);

		$system_info = array();
		$database_info = array();
		$plugin_info = array();
		$recommendations = array();

		if ( ! $woocommerce_active ) {
			$status['overall_status'] = 'inactive';
			$recommendations[] = 'WooCommerce plugin is not active. Please activate it to use e-commerce features.';
			
			return array(
				'status'          => $status,
				'system_info'     => $system_info,
				'database_info'   => $database_info,
				'plugin_info'     => $plugin_info,
				'recommendations' => $recommendations,
				'message'         => 'WooCommerce is not active.',
			);
		}

		// Get WooCommerce status
		$status = self::get_woocommerce_status();

		// Get system information if requested
		if ( $include_system_info ) {
			$system_info = self::get_system_info();
		}

		// Get database information if requested
		if ( $include_database_info ) {
			$database_info = self::get_database_info();
		}

		// Get plugin information if requested
		if ( $include_plugin_info ) {
			$plugin_info = self::get_plugin_info();
		}

		// Generate recommendations
		$recommendations = self::generate_recommendations( $status, $system_info, $database_info );

		return array(
			'status'          => $status,
			'system_info'     => $system_info,
			'database_info'   => $database_info,
			'plugin_info'     => $plugin_info,
			'recommendations' => $recommendations,
			'message'         => sprintf( 'Store status: %s. WooCommerce %s.', $status['overall_status'], $status['woocommerce_active'] ? 'active' : 'inactive' ),
		);
	}

	private static function get_woocommerce_status(): array {
		// Check if setup is complete
		$setup_complete = get_option( 'woocommerce_onboarding_profile', array() );
		$is_setup_complete = ! empty( $setup_complete ) && ! get_option( 'woocommerce_task_list_hidden', false );

		// Check if pages are configured
		$shop_page = get_option( 'woocommerce_shop_page_id' );
		$cart_page = get_option( 'woocommerce_cart_page_id' );
		$checkout_page = get_option( 'woocommerce_checkout_page_id' );
		$account_page = get_option( 'woocommerce_myaccount_page_id' );
		$pages_configured = ! empty( $shop_page ) && ! empty( $cart_page ) && ! empty( $checkout_page ) && ! empty( $account_page );

		// Count payment methods
		$payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$enabled_payment_methods = array_filter( $payment_gateways, function( $gateway ) {
			return $gateway->is_available();
		});

		// Count shipping methods
		$shipping_methods = 0;
		$shipping_zones = \WC_Shipping_Zones::get_zones();
		foreach ( $shipping_zones as $zone ) {
			$zone_methods = $zone['shipping_methods'];
			$shipping_methods += count( array_filter( $zone_methods, function( $method ) {
				return $method->is_enabled();
			}));
		}

		// Count products and orders
		$products_count = wp_count_posts( 'product' );
		$orders_count = wp_count_posts( 'shop_order' );

		// Determine overall status
		$overall_status = 'good';
		if ( ! $pages_configured ) {
			$overall_status = 'warning';
		}
		if ( count( $enabled_payment_methods ) === 0 ) {
			$overall_status = 'critical';
		}

		return array(
			'overall_status'     => $overall_status,
			'woocommerce_active' => true,
			'setup_complete'     => $is_setup_complete,
			'pages_configured'   => $pages_configured,
			'payment_methods'    => count( $enabled_payment_methods ),
			'shipping_methods'   => $shipping_methods,
			'products_count'     => isset( $products_count->publish ) ? (int) $products_count->publish : 0,
			'orders_count'       => isset( $orders_count->{'wc-completed'} ) ? (int) $orders_count->{'wc-completed'} : 0,
		);
	}

	private static function get_system_info(): array {
		global $wpdb;

		return array(
			'wp_version'      => get_bloginfo( 'version' ),
			'wc_version'      => defined( 'WC_VERSION' ) ? WC_VERSION : 'Unknown',
			'php_version'     => PHP_VERSION,
			'mysql_version'   => $wpdb->db_version(),
			'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'memory_limit'    => ini_get( 'memory_limit' ),
			'post_max_size'   => ini_get( 'post_max_size' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
		);
	}

	private static function get_database_info(): array {
		global $wpdb;

		// Get WooCommerce table status
		$wc_tables = array(
			$wpdb->prefix . 'woocommerce_sessions',
			$wpdb->prefix . 'woocommerce_api_keys',
			$wpdb->prefix . 'woocommerce_attribute_taxonomies',
			$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
			$wpdb->prefix . 'woocommerce_order_items',
			$wpdb->prefix . 'woocommerce_order_itemmeta',
			$wpdb->prefix . 'woocommerce_tax_rates',
			$wpdb->prefix . 'woocommerce_tax_rate_locations',
			$wpdb->prefix . 'woocommerce_shipping_zones',
			$wpdb->prefix . 'woocommerce_shipping_zone_locations',
			$wpdb->prefix . 'woocommerce_shipping_zone_methods',
			$wpdb->prefix . 'woocommerce_payment_tokens',
			$wpdb->prefix . 'woocommerce_payment_tokenmeta',
		);

		$tables_status = array();
		$missing_tables = array();

		foreach ( $wc_tables as $table ) {
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
			$tables_status[ $table ] = $table_exists;
			
			if ( ! $table_exists ) {
				$missing_tables[] = $table;
			}
		}

		return array(
			'tables_status'   => $tables_status,
			'missing_tables'  => $missing_tables,
			'tables_count'    => count( $wc_tables ),
			'missing_count'   => count( $missing_tables ),
			'database_prefix' => $wpdb->prefix,
		);
	}

	private static function get_plugin_info(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$wc_extensions = array();

		// Look for WooCommerce extensions
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			if ( strpos( $plugin_data['Name'], 'WooCommerce' ) !== false || 
				 strpos( $plugin_data['Description'], 'WooCommerce' ) !== false ||
				 strpos( $plugin_file, 'woocommerce' ) !== false ) {
				
				$wc_extensions[ $plugin_file ] = array(
					'name'        => $plugin_data['Name'],
					'version'     => $plugin_data['Version'],
					'description' => $plugin_data['Description'],
					'active'      => is_plugin_active( $plugin_file ),
				);
			}
		}

		return array(
			'woocommerce_core' => array(
				'version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'Unknown',
				'active'  => class_exists( 'WooCommerce' ),
			),
			'extensions'       => $wc_extensions,
			'extensions_count' => count( $wc_extensions ),
		);
	}

	private static function generate_recommendations( array $status, array $system_info, array $database_info ): array {
		$recommendations = array();

		if ( $status['overall_status'] === 'critical' ) {
			$recommendations[] = 'Critical issues detected with your WooCommerce setup.';
		}

		if ( ! $status['pages_configured'] ) {
			$recommendations[] = 'Configure WooCommerce pages (Shop, Cart, Checkout, My Account) for proper functionality.';
		}

		if ( $status['payment_methods'] === 0 ) {
			$recommendations[] = 'No payment methods are configured. Add at least one payment gateway.';
		}

		if ( $status['shipping_methods'] === 0 ) {
			$recommendations[] = 'No shipping methods are configured. Set up shipping zones and methods.';
		}

		if ( $status['products_count'] === 0 ) {
			$recommendations[] = 'No products found. Add products to start selling.';
		}

		if ( ! empty( $database_info['missing_tables'] ) ) {
			$recommendations[] = 'Some WooCommerce database tables are missing. Run WooCommerce database update.';
		}

		if ( ! empty( $system_info ) ) {
			$memory_limit = $system_info['memory_limit'];
			if ( $memory_limit && intval( $memory_limit ) < 256 ) {
				$recommendations[] = 'Consider increasing PHP memory limit to at least 256MB for better performance.';
			}

			$max_execution_time = $system_info['max_execution_time'];
			if ( $max_execution_time && intval( $max_execution_time ) < 30 ) {
				$recommendations[] = 'Consider increasing PHP max execution time for large operations.';
			}
		}

		if ( empty( $recommendations ) ) {
			$recommendations[] = 'Your WooCommerce store appears to be properly configured.';
		}

		$recommendations[] = 'Regularly backup your store data and keep WooCommerce updated.';
		$recommendations[] = 'Monitor store performance and customer experience regularly.';

		return $recommendations;
	}
}
