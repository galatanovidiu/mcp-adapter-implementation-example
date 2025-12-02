<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Categories;

final class EcommerceCategory implements RegistersCategory {

	public static function register(): void {
		\wp_register_ability_category(
			'ecommerce',
			array(
				'label'       => 'E-commerce',
				'description' => 'Abilities related to WooCommerce products, orders, and store management.',
			)
		);
	}
}
