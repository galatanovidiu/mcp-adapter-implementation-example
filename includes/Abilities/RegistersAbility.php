<?php
/**
 * Ability contract.
 */
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities;

interface RegistersAbility {
	public static function register(): void;
}
