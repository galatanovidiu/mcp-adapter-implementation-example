<?php declare(strict_types=1);

/**
 * Abilities API Loader for WPMCP Plugin
 * 
 * Loads the bundled Abilities API if not already available.
 */

namespace OvidiuGalatan\McpAdapterExample\ThirdParty\AbilitiesApi;

// Only load if the functions don't already exist (e.g., from core or another plugin)
if (!function_exists('wp_register_ability')) {
    require_once __DIR__ . '/class-wp-ability.php';
    require_once __DIR__ . '/class-wp-abilities-registry.php';
    require_once __DIR__ . '/abilities-api.php';
}
