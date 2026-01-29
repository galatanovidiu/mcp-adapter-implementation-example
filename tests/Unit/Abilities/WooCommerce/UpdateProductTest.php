<?php
/**
 * Unit tests for UpdateProduct ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\UpdateProduct;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test UpdateProduct ability functionality.
 */
final class UpdateProductTest extends TestCase {

	/**
	 * Test ability registration.
	 */
	public function test_ability_is_registered(): void {
		$this->assertAbilityRegistered( 'woo/update-product' );
	}

	/**
	 * Test successful product update.
	 */
	public function test_successful_product_update(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$product = new \WC_Product_Simple();
		$product->set_name( 'Original Product' );
		$product->set_status( 'publish' );
		$product->set_sku( 'original-sku' );
		$product_id = $product->save();
		update_post_meta( $product_id, '_test_post', 'true' );

		$category_id = $this->create_test_term( 'Update Category', 'product_cat' );
		$tag_id      = $this->create_test_term( 'Update Tag', 'product_tag' );

		$input = array(
			'id'                 => $product_id,
			'name'               => 'Updated Product',
			'slug'               => 'Updated Product Slug',
			'sku'                => 'updated-sku',
			'regular_price'      => '29.99',
			'categories'         => array( $category_id ),
			'tags'               => array( $tag_id ),
			'catalog_visibility' => 'visible',
		);

		$result = UpdateProduct::execute( $input );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'product', $result );
		$this->assertSame( $product_id, $result['product']['id'] );

		$updated_product = wc_get_product( $product_id );
		$this->assertInstanceOf( \WC_Product::class, $updated_product );
		$this->assertSame( 'Updated Product', $updated_product->get_name() );
		$this->assertSame( 'updated-product-slug', $updated_product->get_slug() );
		$this->assertSame( 'updated-sku', $updated_product->get_sku() );
		$this->assertContains( $category_id, $updated_product->get_category_ids() );
		$this->assertContains( $tag_id, $updated_product->get_tag_ids() );
	}

	/**
	 * Test product update with duplicate SKU.
	 */
	public function test_product_update_with_duplicate_sku(): void {
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

		$target_product = new \WC_Product_Simple();
		$target_product->set_name( 'Target Product' );
		$target_product->set_status( 'publish' );
		$target_product->set_sku( 'target-sku' );
		$target_product_id = $target_product->save();
		update_post_meta( $target_product_id, '_test_post', 'true' );

		$result = UpdateProduct::execute(
			array(
				'id'  => $target_product_id,
				'sku' => 'duplicate-sku',
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'SKU already exists on another product.', $result['message'] );
	}
}
