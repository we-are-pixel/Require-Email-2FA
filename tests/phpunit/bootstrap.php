<?php
/**
 * Load the real WordPress test site built by tests/bin/setup-wp.sh.
 *
 * @package Force_2FA
 */

$force_2fa_root = getenv( 'FORCE_2FA_TESTS_ROOT' ) ?: sys_get_temp_dir() . '/require-email-2fa';
$force_2fa_mode = getenv( 'WP_TESTS_MULTISITE' ) ? 'multisite' : 'single';

// Multisite resolves the network from the host.
$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

define( 'WP_USE_THEMES', false );

require $force_2fa_root . '/' . $force_2fa_mode . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require __DIR__ . '/TestCase.php';
