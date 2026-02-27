<?php
/**
 * Unit tests for CreateProduct ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\CreateProduct;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test CreateProduct ability functionality.
 */
final class CreateProductTest extends TestCase {

	/**
	 * Test ability registration.
	 */
	public function test_ability_is_registered(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$this->assertAbilityRegistered( 'woo/create-product' );
	}

	/**
	 * Test successful product creation.
	 */
	public function test_successful_product_creation(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$category_id = $this->create_test_term( 'Test Category', 'product_cat' );
		$tag_id      = $this->create_test_term( 'Test Tag', 'product_tag' );

		$input = array(
			'name'               => 'Test Product',
			'type'               => 'simple',
			'status'             => 'publish',
			'slug'               => 'Test Product Slug',
			'sku'                => 'test-sku-001',
			'regular_price'      => '19.99',
			'sale_price'         => '14.99',
			'categories'         => array( $category_id ),
			'tags'               => array( $tag_id ),
			'catalog_visibility' => 'visible',
			'tax_status'         => 'taxable',
		);

		$result = CreateProduct::execute( $input );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'product', $result );
		$this->assertNotEmpty( $result['product']['id'] );

		$product = wc_get_product( $result['product']['id'] );
		$this->assertInstanceOf( \WC_Product::class, $product );
		$this->assertSame( 'Test Product', $product->get_name() );
		$this->assertSame( 'test-product-slug', $product->get_slug() );
		$this->assertSame( 'test-sku-001', $product->get_sku() );
		$this->assertContains( $category_id, $product->get_category_ids() );
		$this->assertContains( $tag_id, $product->get_tag_ids() );

		update_post_meta( $product->get_id(), '_test_post', 'true' );
	}

	/**
	 * Test product creation with duplicate SKU.
	 */
	public function test_product_creation_with_duplicate_sku(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$existing_product = new \WC_Product_Simple();
		$existing_product->set_name( 'Existing Product' );
		$existing_product->set_status( 'publish' );
		$existing_product->set_sku( 'duplicate-sku' );
		$existing_product_id = $existing_product->save();
		update_post_meta( $existing_product_id, '_test_post', 'true' );

		$result = CreateProduct::execute(
			array(
				'name' => 'New Product',
				'type' => 'simple',
				'sku'  => 'duplicate-sku',
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'SKU already exists.', $result['message'] );
	}

	/**
	 * Test product creation with missing name.
	 */
	public function test_product_creation_with_missing_name(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$result = CreateProduct::execute(
			array(
				'type' => 'simple',
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Product name is required.', $result['message'] );
	}
}
