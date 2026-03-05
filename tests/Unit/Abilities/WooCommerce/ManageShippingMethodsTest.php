<?php
/**
 * Unit tests for ManageShippingMethods ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\ManageShippingMethods;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ManageShippingMethods ability functionality.
 */
final class ManageShippingMethodsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}
	}

	public function test_ability_is_registered(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$this->assertAbilityRegistered( 'woo/manage-shipping-methods' );
	}

	public function test_list_zones_action_returns_zones(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$result = ManageShippingMethods::execute(
			array(
				'action' => 'list_zones',
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'list_zones', $result['action'] );
		$this->assertIsArray( $result['zones'] );
	}

	public function test_create_zone_add_method_and_configure(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$create_result = ManageShippingMethods::execute(
			array(
				'action'         => 'create_zone',
				'zone_name'      => 'Test Zone',
				'zone_locations' => array(
					array(
						'code' => 'US',
						'type' => 'country',
					),
				),
			)
		);

		$this->assertTrue( $create_result['success'] );
		$this->assertEquals( 'create_zone', $create_result['action'] );
		$this->assertGreaterThan( 0, $create_result['zone_id'] );

		$zone_id = (int) $create_result['zone_id'];

		$add_result = ManageShippingMethods::execute(
			array(
				'action'      => 'add_method',
				'zone_id'     => $zone_id,
				'method_type' => 'flat_rate',
				'method_title' => 'Test Flat Rate',
			)
		);

		$this->assertTrue( $add_result['success'] );
		$this->assertEquals( 'add_method', $add_result['action'] );
		$this->assertNotEmpty( $add_result['method_id'] );

		$method_id = (string) $add_result['method_id'];

		$configure_result = ManageShippingMethods::execute(
			array(
				'action'          => 'configure_method',
				'zone_id'         => $zone_id,
				'method_id'       => $method_id,
				'method_settings' => array(
					'cost'     => '10',
					'taxes'    => 'taxable',
					'advanced' => array(
						'notes' => 'Nested setting',
					),
				),
			)
		);

		$this->assertTrue( $configure_result['success'] );
		$this->assertEquals( 'configure_method', $configure_result['action'] );

		$list_result = ManageShippingMethods::execute(
			array(
				'action'  => 'list_methods',
				'zone_id' => $zone_id,
			)
		);

		$this->assertTrue( $list_result['success'] );
		$this->assertEquals( 'list_methods', $list_result['action'] );
		$this->assertIsArray( $list_result['methods'] );
		$this->assertNotEmpty( $list_result['methods'] );
	}
}
