<?php
/**
 * Ability contract.
 */
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Contracts;

interface RegistersAbility {

	public static function register(): void;
}
