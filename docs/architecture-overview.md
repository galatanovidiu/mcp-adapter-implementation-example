# Architecture Overview

- Abilities are registered on `abilities_api_init`.
- Each ability is a class implementing `WPMCP\Abilities\Contracts\RegistersAbility` with `public static function register(): void`.
- `WPMCP\Abilities\Bootstrap::init()` hooks `abilities_api_init` and registers all abilities.
- The MCP adapter converts abilities to tools (replacing `/` with `-`).

## Layout
- Posts: `WPMCP\\Abilities\\Posts\\*`
- Post Terms: `WPMCP\\Abilities\\Posts\\Terms\\*`
- Post Meta: `WPMCP\\Abilities\\Posts\\Meta\\*`
- Taxonomies: `WPMCP\\Abilities\\Taxonomies\\*`
- Blocks: `WPMCP\\Abilities\\Blocks\\*`
- Support helpers: `WPMCP\\Abilities\\Support\\*`

## Adding a new ability
1. Create a class in the appropriate namespace implementing `RegistersAbility`.
2. Implement `register()` and call `wp_register_ability()` with label, description, schemas, permission and execute callbacks.
3. Add the class call to `Bootstrap::init()`.
4. If needed as a tool, include the ability name in the server creation tools list in `wpmcp.php`.
