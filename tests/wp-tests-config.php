<?php
/**
 * WordPress test configuration for wp-env.
 * 
 * This file is used by PHPUnit when running WordPress integration tests
 * inside the wp-env Docker containers.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests
 */

// WordPress test environment configuration for wp-env
define( 'ABSPATH', '/var/www/html/' );
define( 'WP_DEFAULT_THEME', 'twentytwentyfour' );

// Debug settings
define( 'WP_DEBUG', true );
define( 'SCRIPT_DEBUG', true );
define( 'WP_TESTS_MULTISITE', false );
define( 'WP_TESTS_FORCE_KNOWN_BUGS', false );

// Database settings for wp-env test environment
define( 'DB_NAME', 'tests-wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'password' );
define( 'DB_HOST', 'tests-mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// Authentication keys and salts
define( 'AUTH_KEY',         'test-auth-key-12345' );
define( 'SECURE_AUTH_KEY',  'test-secure-auth-key-12345' );
define( 'LOGGED_IN_KEY',    'test-logged-in-key-12345' );
define( 'NONCE_KEY',        'test-nonce-key-12345' );
define( 'AUTH_SALT',        'test-auth-salt-12345' );
define( 'SECURE_AUTH_SALT', 'test-secure-auth-salt-12345' );
define( 'LOGGED_IN_SALT',   'test-logged-in-salt-12345' );
define( 'NONCE_SALT',       'test-nonce-salt-12345' );

// Table prefix
$table_prefix = 'wptests_';

// Required WordPress test constants
define( 'WP_TESTS_DOMAIN', 'localhost:8891' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

// Language
define( 'WPLANG', '' );
