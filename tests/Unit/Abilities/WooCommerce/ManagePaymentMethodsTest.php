<?php
/**
 * Unit tests for ManagePaymentMethods ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\ManagePaymentMethods;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ManagePaymentMethods ability functionality.
 */
final class ManagePaymentMethodsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}
	}

	public function test_ability_is_registered(): void {
		$this->assertAbilityRegistered( 'wpmcp-example/manage-payment-methods' );
	}

	public function test_list_action_returns_gateways(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$result = ManagePaymentMethods::execute(
			array(
				'action' => 'list',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'list', $result['action'] );
		$this->assertIsArray( $result['gateways'] );
	}

	public function test_enable_disable_and_configure_gateway(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$payment_gateways = WC()->payment_gateways->payment_gateways();
		if ( empty( $payment_gateways ) ) {
			$this->markTestSkipped( 'No payment gateways available.' );
		}

		$gateway_id          = array_key_first( $payment_gateways );
		$settings_option_key = 'woocommerce_' . $gateway_id . '_settings';
		$original_settings   = get_option( $settings_option_key, array() );

		$enable_result = ManagePaymentMethods::execute(
			array(
				'action'     => 'enable',
				'gateway_id' => $gateway_id,
			)
		);

		$this->assertTrue( $enable_result['success'] );
		$this->assertEquals( 'enable', $enable_result['action'] );

		$disable_result = ManagePaymentMethods::execute(
			array(
				'action'     => 'disable',
				'gateway_id' => $gateway_id,
			)
		);

		$this->assertTrue( $disable_result['success'] );
		$this->assertEquals( 'disable', $disable_result['action'] );

		$configure_result = ManagePaymentMethods::execute(
			array(
				'action'     => 'configure',
				'gateway_id' => $gateway_id,
				'settings'   => array(
					'title'        => 'Unit Test Gateway',
					'instructions' => 'Unit Test Instructions',
					'nested'       => array(
						'note' => 'Nested setting',
					),
				),
			)
		);

		$this->assertTrue( $configure_result['success'] );
		$this->assertEquals( 'configure', $configure_result['action'] );

		$updated_settings = get_option( $settings_option_key, array() );
		$this->assertEquals( 'Unit Test Gateway', $updated_settings['title'] ?? null );
		$this->assertEquals( 'Unit Test Instructions', $updated_settings['instructions'] ?? null );
		$this->assertIsArray( $updated_settings['nested'] ?? null );
		$this->assertEquals( 'Nested setting', $updated_settings['nested']['note'] ?? null );

		update_option( $settings_option_key, $original_settings );
	}
}
