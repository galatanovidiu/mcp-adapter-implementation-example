<?php
/**
 * Unit tests for DuplicateProduct ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Products\Basic\DuplicateProduct;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test DuplicateProduct ability functionality.
 */
final class DuplicateProductTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}
	}

	public function test_ability_is_registered(): void {
		$this->assertAbilityRegistered( 'woo/duplicate-product' );
	}

	public function test_duplicate_product_with_sku_conflict(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$original_product = new \WC_Product_Simple();
		$original_product->set_name( 'Original Product' );
		$original_product->set_status( 'publish' );
		$original_product->set_sku( 'original-sku' );
		$original_product_id = $original_product->save();
		update_post_meta( $original_product_id, '_test_post', 'true' );

		$existing_product = new \WC_Product_Simple();
		$existing_product->set_name( 'Existing Product' );
		$existing_product->set_status( 'publish' );
		$existing_product->set_sku( 'duplicate-sku' );
		$existing_product_id = $existing_product->save();
		update_post_meta( $existing_product_id, '_test_post', 'true' );

		$result = DuplicateProduct::execute(
			array(
				'id'  => $original_product_id,
				'sku' => 'duplicate-sku',
			)
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'SKU already exists.', $result['message'] );
		$this->assertNull( $result['duplicated_product'] );
	}

	public function test_duplicate_variable_product_with_variations_images_reviews(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$variable_product = new \WC_Product_Variable();
		$variable_product->set_name( 'Original Product' );
		$variable_product->set_status( 'publish' );

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'Small', 'Large' ) );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$variable_product->set_attributes( array( $attribute ) );

		$main_image_id    = $this->factory()->attachment->create();
		$gallery_image_id = $this->factory()->attachment->create();
		update_post_meta( $main_image_id, '_test_post', 'true' );
		update_post_meta( $gallery_image_id, '_test_post', 'true' );

		$variable_product->set_image_id( $main_image_id );
		$variable_product->set_gallery_image_ids( array( $gallery_image_id ) );

		$variable_product_id = $variable_product->save();
		update_post_meta( $variable_product_id, '_test_post', 'true' );

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $variable_product_id );
		$variation->set_regular_price( '9.99' );
		$variation->set_attributes( array( 'size' => 'Small' ) );
		$variation->set_sku( 'variation-sku' );
		$variation_id = $variation->save();
		update_post_meta( $variation_id, '_test_post', 'true' );

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $variable_product_id,
				'comment_author'   => 'Reviewer',
				'comment_content'  => 'Great product.',
				'comment_type'     => 'review',
				'comment_approved' => 1,
			)
		);
		update_comment_meta( $comment_id, 'rating', 5 );
		update_comment_meta( $comment_id, 'verified', 'yes' );

		$result = DuplicateProduct::execute(
			array(
				'id'                 => $variable_product_id,
				'name'               => ' New <strong>Name</strong> ',
				'sku'                => ' new-sku ',
				'status'             => 'publish',
				'include_variations' => true,
				'include_images'     => true,
				'include_reviews'    => true,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['duplicated_variations'] );
		$this->assertSame( 2, $result['duplicated_images'] );
		$this->assertNotEmpty( $result['duplicated_product']['id'] );

		$duplicated_product = wc_get_product( $result['duplicated_product']['id'] );
		$this->assertSame( 'New Name', $duplicated_product->get_name() );
		$this->assertSame( 'new-sku', $duplicated_product->get_sku() );
		$this->assertSame( $main_image_id, $duplicated_product->get_image_id() );
		$this->assertSame( array( $gallery_image_id ), $duplicated_product->get_gallery_image_ids() );
		$this->assertCount( 1, $duplicated_product->get_children() );

		$duplicated_reviews = get_comments(
			array(
				'post_id' => $duplicated_product->get_id(),
				'type'    => 'review',
				'status'  => 'approve',
			)
		);
		$this->assertCount( 1, $duplicated_reviews );
		$this->assertSame( '5', get_comment_meta( $duplicated_reviews[0]->comment_ID, 'rating', true ) );
	}

	public function test_duplicate_product_with_invalid_status(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'edit_products' );
		wp_set_current_user( $user_id );

		$product = new \WC_Product_Simple();
		$product->set_name( 'Invalid Status Product' );
		$product->set_status( 'publish' );
		$product_id = $product->save();
		update_post_meta( $product_id, '_test_post', 'true' );

		$result = DuplicateProduct::execute(
			array(
				'id'     => $product_id,
				'status' => 'invalid-status',
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Invalid product status.', $result['message'] );
	}
}
