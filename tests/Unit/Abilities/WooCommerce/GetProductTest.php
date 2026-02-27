<?php
/**
 * Unit tests for GetProduct ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\GetProduct;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test GetProduct ability functionality.
 */
final class GetProductTest extends TestCase {

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

		$this->assertAbilityRegistered( 'woo/get-product' );
	}

	public function test_get_product_by_id(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		\wp_set_current_user( $user_id );

		$product = new \WC_Product_Simple();
		$product->set_name( 'Lookup Product' );
		$product->set_status( 'publish' );
		$product->set_sku( 'lookup-sku' );
		$product_id = $product->save();
		\update_post_meta( $product_id, '_test_post', 'true' );

		$result = GetProduct::execute(
			array(
				'id' => $product_id,
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( $product_id, $result['product']['id'] );
		$this->assertSame( 'lookup-sku', $result['product']['sku'] );
	}

	public function test_get_product_by_sanitized_sku(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		\wp_set_current_user( $user_id );

		$product = new \WC_Product_Simple();
		$product->set_name( 'SKU Lookup Product' );
		$product->set_status( 'publish' );
		$product->set_sku( 'sku-lookup' );
		$product_id = $product->save();
		\update_post_meta( $product_id, '_test_post', 'true' );

		$result = GetProduct::execute(
			array(
				'sku' => ' sku-lookup ',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( $product_id, $result['product']['id'] );
	}

	public function test_optional_sections_included(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		\wp_set_current_user( $user_id );

		$product_data = $this->create_variable_product_with_related_data();

		$result = GetProduct::execute(
			array(
				'id'                 => $product_data['product_id'],
				'include_variations' => true,
				'include_reviews'    => true,
				'include_related'    => true,
			)
		);

		$this->assertCount( 1, $result['product']['variations'] );
		$this->assertCount( 1, $result['product']['reviews'] );
		$this->assertCount( 1, $result['product']['related_products'] );
		$this->assertSame( $product_data['related_product_id'], $result['product']['related_products'][0]['id'] );
	}

	public function test_optional_sections_disabled_with_falsey_strings(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		\wp_set_current_user( $user_id );

		$product_data = $this->create_variable_product_with_related_data();

		$result = GetProduct::execute(
			array(
				'id'                 => $product_data['product_id'],
				'include_variations' => 'false',
				'include_reviews'    => '0',
				'include_related'    => 'false',
			)
		);

		$this->assertSame( array(), $result['product']['variations'] );
		$this->assertSame( array(), $result['product']['reviews'] ?? array() );
		$this->assertSame( array(), $result['product']['related_products'] ?? array() );
	}

	/**
	 * Create a variable product with variation, review, and related product.
	 *
	 * @return array<string, int> Product data IDs.
	 */
	private function create_variable_product_with_related_data(): array {
		$category_id = $this->create_test_term( 'Related Category', 'product_cat' );

		$variable_product = new \WC_Product_Variable();
		$variable_product->set_name( 'Variable Product' );
		$variable_product->set_status( 'publish' );
		$variable_product->set_category_ids( array( $category_id ) );

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'Small', 'Large' ) );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$variable_product->set_attributes( array( $attribute ) );

		$product_id = $variable_product->save();
		\update_post_meta( $product_id, '_test_post', 'true' );

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $product_id );
		$variation->set_regular_price( '9.99' );
		$variation->set_attributes( array( 'size' => 'Small' ) );
		$variation->set_sku( 'variation-sku' );
		$variation_id = $variation->save();
		\update_post_meta( $variation_id, '_test_post', 'true' );

		$comment_id = \wp_insert_comment(
			array(
				'comment_post_ID'  => $product_id,
				'comment_author'   => 'Reviewer',
				'comment_content'  => 'Great product.',
				'comment_type'     => 'review',
				'comment_approved' => 1,
			)
		);
		\update_comment_meta( $comment_id, 'rating', 5 );
		\update_comment_meta( $comment_id, 'verified', 'yes' );

		$related_product = new \WC_Product_Simple();
		$related_product->set_name( 'Related Product' );
		$related_product->set_status( 'publish' );
		$related_product->set_category_ids( array( $category_id ) );
		$related_product_id = $related_product->save();
		\update_post_meta( $related_product_id, '_test_post', 'true' );

		return array(
			'product_id'         => $product_id,
			'variation_id'       => $variation_id,
			'related_product_id' => $related_product_id,
		);
	}
}
