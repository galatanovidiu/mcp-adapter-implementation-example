<?php
/**
 * Unit tests for DeleteProduct ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\DeleteProduct;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test DeleteProduct ability functionality.
 */
final class DeleteProductTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}
	}

	public function test_ability_is_registered(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$this->assertAbilityRegistered( 'woo/delete-product' );
	}

	public function test_delete_product_not_found(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'delete_products' );
		wp_set_current_user( $user_id );

		$result = DeleteProduct::execute(
			array(
				'id' => 999999,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Product not found.', $result['message'] );
	}

	public function test_trash_product(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'delete_products' );
		wp_set_current_user( $user_id );

		$product = new \WC_Product_Simple();
		$product->set_name( 'Delete Me' );
		$product->set_status( 'publish' );
		$product_id = $product->save();
		update_post_meta( $product_id, '_test_post', 'true' );

		$result = DeleteProduct::execute(
			array(
				'id'    => $product_id,
				'force' => false,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertFalse( $result['permanent'] );
		$this->assertStringContainsString( 'moved to trash', $result['message'] );

		$trashed_product = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product::class, $trashed_product );
		$this->assertSame( 'trash', $trashed_product->get_status() );
	}

	public function test_force_delete_variable_product_with_variations(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'delete_products' );
		wp_set_current_user( $user_id );

		$variable_product = new \WC_Product_Variable();
		$variable_product->set_name( 'Variable Delete Me' );

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'Small', 'Large' ) );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$variable_product->set_attributes( array( $attribute ) );

		$product_id = $variable_product->save();
		update_post_meta( $product_id, '_test_post', 'true' );

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $product_id );
		$variation->set_regular_price( '9.99' );
		$variation->set_attributes( array( 'size' => 'Small' ) );
		$variation_id = $variation->save();
		update_post_meta( $variation_id, '_test_post', 'true' );

		$this->assertGreaterThan( 0, $variation_id );

		$result = DeleteProduct::execute(
			array(
				'id'                => $product_id,
				'force'             => true,
				'delete_variations' => true,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['permanent'] );
		$this->assertGreaterThan( 0, $result['deleted_variations'] );
		$this->assertFalse( wc_get_product( $product_id ) );
		$this->assertFalse( wc_get_product( $variation_id ) );
	}
}
