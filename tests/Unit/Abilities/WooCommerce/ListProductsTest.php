<?php
/**
 * Unit tests for ListProducts ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\ListProducts;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ListProducts ability functionality.
 */
final class ListProductsTest extends TestCase {

	/**
	 * Test ability registration.
	 */
	public function test_ability_is_registered(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$this->assertAbilityRegistered( 'woo/list-products' );
	}

	/**
	 * Test filtering and falsey filters are preserved.
	 */
	public function test_filtering_and_false_filters(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$category_id = $this->create_test_term( 'Filter Category', 'product_cat' );
		$tag_id      = $this->create_test_term( 'Filter Tag', 'product_tag' );

		$product = $this->create_test_product(
			array(
				'name'       => 'Filter Product',
				'featured'   => false,
				'categories' => array( $category_id ),
				'tags'       => array( $tag_id ),
			)
		);

		$other_product = $this->create_test_product(
			array(
				'name'     => 'Featured Product',
				'featured' => true,
			)
		);

		$result = ListProducts::execute(
			array(
				'category' => 'filter-category',
				'tag'      => 'filter-tag',
				'featured' => false,
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['products'] );
		$this->assertSame( $product->get_id(), $result['products'][0]['id'] );
		$this->assertSame( false, $result['filters_applied']['featured'] );
		$this->assertSame( 'filter-category', $result['filters_applied']['category'] );
		$this->assertSame( 'filter-tag', $result['filters_applied']['tag'] );

		$this->assertNotSame( $other_product->get_id(), $result['products'][0]['id'] );
	}

	/**
	 * Test pagination and limit clamping.
	 */
	public function test_pagination_and_limit_clamp(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$this->create_test_product( array( 'name' => 'Product One' ) );
		$this->create_test_product( array( 'name' => 'Product Two' ) );
		$this->create_test_product( array( 'name' => 'Product Three' ) );

		$result = ListProducts::execute(
			array(
				'limit'  => 1,
				'offset' => 1,
				'order'  => 'asc',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 3, $result['pagination']['total'] );
		$this->assertSame( 2, $result['pagination']['current_page'] );
		$this->assertSame( 1, $result['pagination']['per_page'] );

		$clamped = ListProducts::execute(
			array(
				'limit' => 200,
			)
		);

		$this->assertSame( 100, $clamped['pagination']['per_page'] );
	}

	/**
	 * Test zero results message.
	 */
	public function test_zero_results_message(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$result = ListProducts::execute(
			array(
				'search' => 'no-products-match-this',
			)
		);

		$this->assertSame( 0, $result['pagination']['total'] );
		$this->assertSame( 'Found 0 products.', $result['message'] );
	}

	/**
	 * Create a test product with basic data.
	 *
	 * @param array $data Product data overrides.
	 * @return \WC_Product
	 */
	private function create_test_product( array $data ): \WC_Product {
		$product = new \WC_Product_Simple();
		$product->set_name( $data['name'] ?? 'Test Product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '12.00' );
		$product->set_featured( (bool) ( $data['featured'] ?? false ) );

		if ( ! empty( $data['categories'] ) ) {
			$product->set_category_ids( $data['categories'] );
		}

		if ( ! empty( $data['tags'] ) ) {
			$product->set_tag_ids( $data['tags'] );
		}

		$product_id = $product->save();
		update_post_meta( $product_id, '_test_post', 'true' );

		return wc_get_product( $product_id );
	}
}
