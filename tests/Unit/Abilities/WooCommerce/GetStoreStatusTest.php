<?php
/**
 * Unit tests for GetStoreStatus ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\GetStoreStatus;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test GetStoreStatus ability functionality.
 */
final class GetStoreStatusTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}
	}

	public function test_ability_is_registered(): void {
		$this->assertAbilityRegistered( 'wpmcp-example/get-store-status' );
	}

	public function test_execute_returns_status_with_defaults(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user_id );

		$result = GetStoreStatus::execute( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'system_info', $result );
		$this->assertArrayHasKey( 'database_info', $result );
		$this->assertArrayHasKey( 'plugin_info', $result );
		$this->assertArrayHasKey( 'recommendations', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertArrayHasKey( 'overall_status', $result['status'] );
		$this->assertTrue( $result['status']['woocommerce_active'] );
		$this->assertIsArray( $result['system_info'] );
		$this->assertIsArray( $result['database_info'] );
		$this->assertIsArray( $result['plugin_info'] );
	}

	public function test_optional_flags_disable_sections(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user_id );

		$result = GetStoreStatus::execute(
			array(
				'include_system_info'   => 'false',
				'include_database_info' => '0',
				'include_plugin_info'   => 'no',
			)
		);

		$this->assertSame( array(), $result['system_info'] );
		$this->assertSame( array(), $result['database_info'] );
		$this->assertSame( array(), $result['plugin_info'] );
	}
}
