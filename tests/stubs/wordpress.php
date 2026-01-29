<?php
declare( strict_types=1 );

namespace WP\MCP\Core {
	if ( false ) {
		class McpAdapter {
			public static function is_available(): bool {
				return false;
			}

			public static function instance() {
				return null;
			}

			public function get_server( string $server_id ) {
				return null;
			}
		}

		class McpServer {
			public function get_tool( string $tool_name ) {
				return null;
			}

			public function get_server_route_namespace() {
				return '';
			}

			public function get_server_route() {
				return '';
			}

			public function get_tools(): array {
				return array();
			}
		}
	}
}

namespace {
	if ( false ) {
		function do_action( string $tag, ...$args ) {}
		function remove_all_actions( string $tag ) {}
		function wp_get_abilities() {
			return array();
		}
		function wp_get_ability( string $name ) {
			return null;
		}
		function get_user_by( string $field, $value ) {
			return null;
		}
		function update_user_meta( int $user_id, string $meta_key, $meta_value ) {
			return null;
		}
		function get_user_meta( int $user_id, string $key = '', $single = false ) {
			return null;
		}
		function wp_unregister_ability( string $name ) {
			return null;
		}
		function get_posts( array $args = array() ) {
			return array();
		}
		function wp_delete_post( $post_id, $force_delete = false ) {
			return null;
		}
		function get_taxonomies( array $args = array(), $output = 'names', $operator = 'and' ) {
			return array();
		}
		function get_terms( array $args = array() ) {
			return array();
		}
		function is_wp_error( $thing ): bool {
			return false;
		}
		function wp_delete_term( $term_id, $taxonomy, $args = array() ) {
			return null;
		}
		function wp_insert_post( array $args, $wp_error = false ) {
			return 0;
		}
		function wp_insert_comment( array $args = array() ) {
			return 0;
		}
		function wp_insert_term( $term, $taxonomy, $args = array() ) {
			return array();
		}
		function add_term_meta( $term_id, $meta_key, $meta_value, $unique = false ) {
			return null;
		}
		function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
			return null;
		}
		function update_comment_meta( $comment_id, $meta_key, $meta_value, $prev_value = '' ) {
			return null;
		}
		function get_current_user_id(): int {
			return 0;
		}
		function wp_set_current_user( int $user_id ) {
			return null;
		}
		function wp_create_nonce( string $action = '' ): string {
			return '';
		}
		function wp_json_encode( $data, $options = 0, $depth = 512 ) {
			return '';
		}
		function rest_get_server() {
			return null;
		}
		function get_stylesheet(): string {
			return '';
		}

		class WP_REST_Request {
			public function __construct( string $method = 'GET', string $route = '' ) {}
			public function set_header( string $header, $value ) {
				return null;
			}
			public function set_body( $data ) {
				return null;
			}
		}

		class WP_REST_Response {}

		class WP_User {
			public function __construct( int $user_id = 0 ) {}
			public function add_cap( string $cap, $grant = true ) {}
		}

		class WC_Product {}
		class WC_Product_Simple extends WC_Product {
			public function set_name( string $name ) {}
			public function set_status( string $status ) {}
			public function set_sku( string $sku ) {}
			public function set_category_ids( array $ids ) {}
			public function save() {
				return 0;
			}
		}
		class WC_Product_Variable extends WC_Product_Simple {
			public function set_attributes( array $attributes ) {}
		}
		class WC_Product_Variation extends WC_Product_Simple {
			public function set_parent_id( int $product_id ) {}
			public function set_regular_price( string $price ) {}
			public function set_attributes( array $attributes ) {}
		}
		class WC_Product_Attribute {
			public function set_name( string $name ) {}
			public function set_options( array $options ) {}
			public function set_position( int $position ) {}
			public function set_visible( bool $visible ) {}
			public function set_variation( bool $variation ) {}
		}
	}
}
