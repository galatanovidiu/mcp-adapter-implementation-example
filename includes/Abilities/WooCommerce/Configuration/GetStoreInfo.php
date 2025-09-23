<?php

namespace OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

class GetStoreInfo implements RegistersAbility {

	public static function register(): void {
		wp_register_ability(
			'woo/get-store-info',
			array(
				'label'               => 'Get WooCommerce Store Info',
				'description'         => 'Retrieve comprehensive WooCommerce store information and overview statistics.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_stats' => array(
							'type'        => 'boolean',
							'description' => 'Include detailed store statistics.',
							'default'     => true,
						),
						'include_recent_activity' => array(
							'type'        => 'boolean',
							'description' => 'Include recent store activity.',
							'default'     => true,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'store_info' => array(
							'type'       => 'object',
							'properties' => array(
								'store_name'      => array( 'type' => 'string' ),
								'store_url'       => array( 'type' => 'string' ),
								'currency'        => array( 'type' => 'string' ),
								'currency_symbol' => array( 'type' => 'string' ),
								'base_location'   => array( 'type' => 'object' ),
								'wc_version'      => array( 'type' => 'string' ),
								'setup_status'    => array( 'type' => 'string' ),
							),
						),
						'statistics' => array(
							'type'       => 'object',
							'properties' => array(
								'products'   => array( 'type' => 'object' ),
								'orders'     => array( 'type' => 'object' ),
								'customers'  => array( 'type' => 'object' ),
								'revenue'    => array( 'type' => 'object' ),
							),
						),
						'recent_activity' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'type'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'date'        => array( 'type' => 'string' ),
									'amount'      => array( 'type' => 'string' ),
								),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'ecommerce', 'information' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.9,
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
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'view_woocommerce_reports' );
	}

	public static function execute( array $input ): array {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array(
				'store_info'      => array(),
				'statistics'      => array(),
				'recent_activity' => array(),
				'message'         => 'WooCommerce is not active.',
			);
		}

		$include_stats = $input['include_stats'] ?? true;
		$include_recent_activity = $input['include_recent_activity'] ?? true;

		// Get basic store information
		$store_info = self::get_basic_store_info();

		// Get statistics if requested
		$statistics = array();
		if ( $include_stats ) {
			$statistics = self::get_store_statistics();
		}

		// Get recent activity if requested
		$recent_activity = array();
		if ( $include_recent_activity ) {
			$recent_activity = self::get_recent_activity();
		}

		return array(
			'store_info'      => $store_info,
			'statistics'      => $statistics,
			'recent_activity' => $recent_activity,
			'message'         => sprintf( 'Store info retrieved for %s.', $store_info['store_name'] ),
		);
	}

	private static function get_basic_store_info(): array {
		$currency = get_woocommerce_currency();
		$base_location = wc_get_base_location();

		return array(
			'store_name'      => get_bloginfo( 'name' ),
			'store_url'       => get_site_url(),
			'currency'        => $currency,
			'currency_symbol' => get_woocommerce_currency_symbol( $currency ),
			'base_location'   => array(
				'country'      => $base_location['country'],
				'state'        => $base_location['state'],
				'country_name' => WC()->countries->countries[ $base_location['country'] ] ?? $base_location['country'],
				'state_name'   => WC()->countries->get_states( $base_location['country'] )[ $base_location['state'] ] ?? $base_location['state'],
			),
			'wc_version'      => defined( 'WC_VERSION' ) ? WC_VERSION : 'Unknown',
			'setup_status'    => get_option( 'woocommerce_onboarding_profile' ) ? 'completed' : 'pending',
		);
	}

	private static function get_store_statistics(): array {
		// Product statistics
		$product_counts = wp_count_posts( 'product' );
		$product_stats = array(
			'total'     => array_sum( (array) $product_counts ),
			'published' => $product_counts->publish ?? 0,
			'draft'     => $product_counts->draft ?? 0,
			'private'   => $product_counts->private ?? 0,
		);

		// Order statistics
		$order_counts = wp_count_posts( 'shop_order' );
		$order_stats = array(
			'total'     => array_sum( (array) $order_counts ),
			'completed' => $order_counts->{'wc-completed'} ?? 0,
			'processing' => $order_counts->{'wc-processing'} ?? 0,
			'pending'   => $order_counts->{'wc-pending'} ?? 0,
			'cancelled' => $order_counts->{'wc-cancelled'} ?? 0,
			'refunded'  => $order_counts->{'wc-refunded'} ?? 0,
		);

		// Customer statistics
		$customer_stats = array(
			'total'          => count_users()['total_users'],
			'customers_only' => self::get_customer_count(),
		);

		// Revenue statistics (last 30 days)
		$revenue_stats = self::get_revenue_statistics();

		return array(
			'products'  => $product_stats,
			'orders'    => $order_stats,
			'customers' => $customer_stats,
			'revenue'   => $revenue_stats,
		);
	}

	private static function get_customer_count(): int {
		$users = get_users( array(
			'role__in' => array( 'customer', 'shop_manager' ),
			'fields'   => 'ID',
		) );

		return count( $users );
	}

	private static function get_revenue_statistics(): array {
		global $wpdb;

		$thirty_days_ago = date( 'Y-m-d', strtotime( '-30 days' ) );

		// Get orders from last 30 days
		$orders = wc_get_orders( array(
			'status'      => array( 'wc-completed', 'wc-processing' ),
			'date_after'  => $thirty_days_ago,
			'limit'       => -1,
		) );

		$total_revenue = 0;
		$order_count = 0;

		foreach ( $orders as $order ) {
			$total_revenue += (float) $order->get_total();
			$order_count++;
		}

		$average_order_value = $order_count > 0 ? $total_revenue / $order_count : 0;

		return array(
			'last_30_days' => array(
				'total_revenue'       => number_format( $total_revenue, 2 ),
				'orders_count'        => $order_count,
				'average_order_value' => number_format( $average_order_value, 2 ),
			),
			'currency' => get_woocommerce_currency(),
		);
	}

	private static function get_recent_activity(): array {
		$activities = array();

		// Get recent orders
		$recent_orders = wc_get_orders( array(
			'limit'   => 5,
			'orderby' => 'date',
			'order'   => 'DESC',
		) );

		foreach ( $recent_orders as $order ) {
			$activities[] = array(
				'type'        => 'order',
				'description' => sprintf( 'Order #%s (%s)', $order->get_order_number(), $order->get_status() ),
				'date'        => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
				'amount'      => $order->get_formatted_order_total(),
			);
		}

		// Get recent products
		$recent_products = wc_get_products( array(
			'limit'   => 3,
			'orderby' => 'date',
			'order'   => 'DESC',
			'status'  => 'publish',
		) );

		foreach ( $recent_products as $product ) {
			$activities[] = array(
				'type'        => 'product',
				'description' => sprintf( 'Product "%s" created', $product->get_name() ),
				'date'        => $product->get_date_created() ? $product->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
				'amount'      => $product->get_price_html(),
			);
		}

		// Sort activities by date
		usort( $activities, function( $a, $b ) {
			return strcmp( $b['date'], $a['date'] );
		});

		return array_slice( $activities, 0, 10 );
	}
}
