<?php
/**
 * Point the GitHub mock at a release. Run with: wp eval-file release.php <version> <dir> <zip> <asset:1|0>.
 *
 * @package Force_2FA
 */

file_put_contents(
	ABSPATH . 'test-release.json',
	wp_json_encode(
		array(
			'version' => $args[0],
			'dir'     => $args[1],
			'zip'     => $args[2],
			'asset'   => '1' === $args[3],
		)
	)
);

// Forget earlier checks so the next one asks "GitHub" again.
delete_site_option( 'external_updates-Require-Email-2FA-main' );
delete_site_transient( 'update_plugins' );
