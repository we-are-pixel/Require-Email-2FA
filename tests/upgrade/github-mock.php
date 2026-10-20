<?php
/**
 * Test fixture (mu-plugin): answer Plugin Update Checker's GitHub calls from a local release.
 *
 * The release is described by test-release.json in the site root:
 * {"version": "2.0.0", "dir": "<unzipped plugin>", "zip": "<zip>", "asset": true}
 *
 * @package Force_2FA
 */

add_filter(
	'pre_http_request',
	function ( $result, $args, $url ) {
		$respond = function ( $body, $code = 200 ) {
			return array(
				'headers'  => array(),
				'body'     => $body,
				'response' => array(
					'code'    => $code,
					'message' => 200 === $code ? 'OK' : 'Not Found',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};

		// Keep the tests offline: WordPress.org reports "no updates".
		if ( false !== strpos( $url, 'api.wordpress.org' ) ) {
			return $respond( '{"plugins":[],"themes":[],"translations":[],"no_update":[]}' );
		}

		$release = json_decode( (string) @file_get_contents( ABSPATH . 'test-release.json' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $release || ! preg_match( '#^https://(?:api\.)?github\.com/(?:repos/)?we-are-pixel/Require-Email-2FA/([^?]*)#', $url, $match ) ) {
			return $result;
		}
		$tag     = 'v' . $release['version'];
		$path    = $match[1];

		if ( 'releases/latest' === $path ) {
			$asset = array(
				'name'                 => 'Require-Email-2FA-main.zip',
				'browser_download_url' => "https://github.com/we-are-pixel/Require-Email-2FA/releases/download/$tag/Require-Email-2FA-main.zip",
				'url'                  => 'https://api.github.com/repos/we-are-pixel/Require-Email-2FA/releases/assets/1',
				'download_count'       => 0,
			);

			return $respond(
				wp_json_encode(
					array(
						'tag_name'     => $tag,
						'name'         => $tag,
						'body'         => 'Test release',
						'draft'        => false,
						'prerelease'   => false,
						'created_at'   => gmdate( 'c' ),
						'published_at' => gmdate( 'c' ),
						'zipball_url'  => "https://api.github.com/repos/we-are-pixel/Require-Email-2FA/zipball/$tag",
						'assets'       => $release['asset'] ? array( $asset ) : array(),
					)
				)
			);
		}

		// Like GitHub, every release also has a tag (old updaters fall back to it).
		if ( 'tags' === $path ) {
			return $respond(
				wp_json_encode(
					array(
						array(
							'name'        => $tag,
							'zipball_url' => "https://api.github.com/repos/we-are-pixel/Require-Email-2FA/zipball/$tag",
							'commit'      => array( 'sha' => sha1( $tag ) ),
						),
					)
				)
			);
		}

		if ( 0 === strpos( $path, 'contents/' ) && is_readable( $release['dir'] . '/' . substr( $path, 9 ) ) ) {
			return $respond( wp_json_encode( array( 'encoding' => 'base64', 'content' => base64_encode( file_get_contents( $release['dir'] . '/' . substr( $path, 9 ) ) ) ) ) );
		}

		if ( false !== strpos( $path, 'releases/download/' ) && ! empty( $args['filename'] ) ) {
			copy( $release['zip'], $args['filename'] );
			return $respond( '' );
		}

		return $respond( '[]', 404 );
	},
	10,
	3
);
