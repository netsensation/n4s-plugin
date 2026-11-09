<?php
/**
 * n4s Apps / Update über den normalen WordPress-Update-Prozess, Quelle: GitHub-Releases.
 *
 * - Neueste Release von N4S_APPS_GITHUB_REPO (Asset n4s-apps-plugin.zip) wird abgefragt (6 h Cache).
 * - WordPress zeigt das Update unter Dashboard → Aktualisierungen und Plugins (auch Auto-Updates).
 * - Öffentliches Repository: kein Token nötig. Optional (privates Repository oder höheres API-Limit):
 *   Token per Konstante N4S_APPS_GITHUB_TOKEN (wp-config.php) oder im Menü Apps.
 * - Das heruntergeladene ZIP wird vor der Installation wie beim manuellen Update geprüft.
 * Wird immer geladen (nicht nur im Admin), damit Auto-Updates per Cron funktionieren.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'N4S_APPS_GITHUB_REPO' ) ) {
	define( 'N4S_APPS_GITHUB_REPO', 'netsensation/n4s-plugin' );
}
define( 'N4S_APPS_ASSET', 'n4s-apps-plugin.zip' );

/** Prüft ein Plugin-ZIP. Gibt array( 'version' => ... ) oder eine Fehlermeldung zurück. */
function n4s_apps_inspect_zip( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return n4s_t( 'ZipArchive fehlt auf dem Server. Bitte über Plugins → Installieren → Hochladen aktualisieren.', 'ZipArchive is missing on the server. Please update via Plugins → Add New → Upload.' );
	}
	$z = new ZipArchive();
	if ( true !== $z->open( $path ) ) {
		return n4s_t( 'Die ZIP-Datei ist nicht lesbar.', 'The ZIP file is not readable.' );
	}
	$dir   = basename( untrailingslashit( N4S_APPS_DIR ) );
	$total = 0;
	for ( $i = 0; $i < $z->numFiles; $i++ ) {
		$s = $z->statIndex( $i );
		if ( 0 !== strpos( $s['name'], $dir . '/' ) || false !== strpos( $s['name'], '..' ) ) {
			$z->close();
			return n4s_t( 'Das ZIP enthält Dateien außerhalb von ' . $dir . '/ und wird nicht installiert.', 'The ZIP contains files outside ' . $dir . '/ and will not be installed.' );
		}
		$total += (int) $s['size'];
	}
	if ( $total > 20971520 ) {
		$z->close();
		return n4s_t( 'Das ZIP ist entpackt größer als 20 MB.', 'The ZIP is larger than 20 MB unpacked.' );
	}
	$main = $z->getFromName( $dir . '/n4s-apps.php' );
	$z->close();
	if ( false === $main ) {
		return n4s_t( 'Im ZIP fehlt ' . $dir . '/n4s-apps.php.', $dir . '/n4s-apps.php is missing in the ZIP.' );
	}
	if ( ! preg_match( '/^[ \t\/*#@]*Plugin Name:\s*n4s Apps\s*$/mi', $main ) ) {
		return n4s_t( 'Das ZIP ist kein n4s-Apps-Plugin.', 'The ZIP is not an n4s Apps plugin.' );
	}
	if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*(\S+)/mi', $main, $m ) ) {
		return n4s_t( 'Im ZIP wurde keine Version gefunden.', 'No version found in the ZIP.' );
	}
	return array( 'version' => $m[1] );
}

/** GitHub-Token: Konstante hat Vorrang vor der Option aus dem Menü Apps. */
function n4s_apps_gh_token() {
	if ( defined( 'N4S_APPS_GITHUB_TOKEN' ) && N4S_APPS_GITHUB_TOKEN ) {
		return (string) N4S_APPS_GITHUB_TOKEN;
	}
	return (string) get_option( 'n4s_apps_github_token', '' );
}

function n4s_apps_gh_headers( $accept = 'application/vnd.github+json' ) {
	$h = array(
		'Accept'               => $accept,
		'User-Agent'           => 'n4s-apps/' . N4S_APPS_VERSION,
		'X-GitHub-Api-Version' => '2022-11-28',
	);
	$t = n4s_apps_gh_token();
	if ( '' !== $t ) {
		$h['Authorization'] = 'Bearer ' . $t;
	}
	return $h;
}

/** API-Präfix der Release-Assets; nur solche Pakete lädt der Updater selbst herunter. */
function n4s_apps_gh_asset_prefix() {
	return 'https://api.github.com/repos/' . N4S_APPS_GITHUB_REPO . '/releases/assets/';
}

/**
 * Neueste Release von GitHub (gecacht). Ergebnis: version, package, url, body, date, size
 * oder array( 'error' => Meldung ). Fehler werden 30 Minuten gecacht.
 */
function n4s_apps_gh_release( $force = false ) {
	$cached = get_site_transient( 'n4s_apps_gh_release' );
	if ( ! $force && is_array( $cached ) ) {
		return $cached;
	}
	$res  = wp_remote_get(
		'https://api.github.com/repos/' . N4S_APPS_GITHUB_REPO . '/releases/latest',
		array( 'timeout' => 10, 'headers' => n4s_apps_gh_headers() )
	);
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	$data = array( 'checked' => time() );

	if ( is_wp_error( $res ) ) {
		$data['error'] = $res->get_error_message();
	} elseif ( 200 !== $code ) {
		$data['error'] = 'GitHub HTTP ' . $code . ( in_array( $code, array( 401, 403, 404 ), true )
			? n4s_t( ' (noch keine Release vorhanden, API-Limit erreicht oder Token ungültig)', ' (no release yet, API limit reached or invalid token)' )
			: '' );
	} else {
		$j       = json_decode( wp_remote_retrieve_body( $res ), true );
		$version = is_array( $j ) && isset( $j['tag_name'] ) ? ltrim( (string) $j['tag_name'], 'vV' ) : '';
		$asset   = null;
		foreach ( ( is_array( $j ) && ! empty( $j['assets'] ) ? $j['assets'] : array() ) as $a ) {
			if ( isset( $a['name'], $a['url'] ) && N4S_APPS_ASSET === $a['name'] ) {
				$asset = $a;
				break;
			}
		}
		if ( ! preg_match( '/^\d+(\.\d+){0,3}$/', $version ) ) {
			$data['error'] = n4s_t( 'Release ohne gültige Versionsnummer.', 'Release without a valid version number.' );
		} elseif ( ! $asset || 0 !== strpos( $asset['url'], n4s_apps_gh_asset_prefix() ) ) {
			$data['error'] = n4s_t( 'In der Release fehlt ' . N4S_APPS_ASSET . '.', N4S_APPS_ASSET . ' is missing in the release.' );
		} else {
			$data += array(
				'version' => $version,
				'package' => $asset['url'],
				'size'    => isset( $asset['size'] ) ? (int) $asset['size'] : 0,
				'url'     => isset( $j['html_url'] ) ? (string) $j['html_url'] : 'https://github.com/' . N4S_APPS_GITHUB_REPO,
				'body'    => isset( $j['body'] ) ? (string) $j['body'] : '',
				'date'    => isset( $j['published_at'] ) ? (string) $j['published_at'] : '',
			);
		}
	}
	set_site_transient( 'n4s_apps_gh_release', $data, isset( $data['error'] ) ? 30 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS );
	return $data;
}

/** Update-Angabe für WordPress (Plugin-Header „Update URI: https://github.com/..."; ab WordPress 5.8). */
add_filter(
	'update_plugins_github.com',
	function ( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( N4S_APPS_FILE ) !== $plugin_file ) {
			return $update;
		}
		$r = n4s_apps_gh_release();
		if ( empty( $r['version'] ) ) {
			return $update;
		}
		$out = array(
			'id'           => 'github.com/' . N4S_APPS_GITHUB_REPO,
			'slug'         => 'n4s-apps',
			'plugin'       => $plugin_file,
			'version'      => $r['version'],
			'url'          => $r['url'],
			'package'      => $r['package'],
			'requires_php' => '7.4',
		);
		if ( function_exists( 'n4s_apps_logo' ) ) {
			$out['icons'] = array( 'svg' => 'data:image/svg+xml;base64,' . base64_encode( n4s_apps_logo( false ) ) );
		}
		return $out;
	},
	10,
	3
);

/** Fenster „Details ansehen" mit Änderungen aus der Release-Beschreibung. */
add_filter(
	'plugins_api',
	function ( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'n4s-apps' !== $args->slug ) {
			return $res;
		}
		$r = n4s_apps_gh_release();
		if ( empty( $r['version'] ) ) {
			return $res;
		}
		return (object) array(
			'name'          => 'n4s Apps',
			'slug'          => 'n4s-apps',
			'version'       => $r['version'],
			'author'        => '<a href="https://n4s.one">n4s</a>',
			'homepage'      => 'https://n4s.one',
			'requires_php'  => '7.4',
			'last_updated'  => $r['date'],
			'download_link' => $r['package'],
			'sections'      => array(
				'changelog' => '<p><strong>' . esc_html( $r['version'] ) . '</strong></p>' . wpautop( esc_html( '' !== $r['body'] ? $r['body'] : '–' ) )
					. '<p><a href="' . esc_url( $r['url'] ) . '" target="_blank" rel="noopener">GitHub</a></p>',
			),
		);
	},
	10,
	3
);

/**
 * Download des Release-Assets selbst übernehmen: mit Token und Accept octet-stream bei GitHub anfragen,
 * der Weiterleitung (signierte URL) ohne Token folgen, ZIP prüfen.
 */
add_filter(
	'upgrader_pre_download',
	function ( $reply, $package ) {
		if ( false !== $reply || ! is_string( $package ) || 0 !== strpos( $package, n4s_apps_gh_asset_prefix() ) ) {
			return $reply;
		}
		$fail = function ( $msg ) {
			return new WP_Error( 'n4s_apps_download', 'n4s Apps: ' . $msg );
		};
		$res = wp_remote_get( $package, array( 'timeout' => 30, 'redirection' => 0, 'headers' => n4s_apps_gh_headers( 'application/octet-stream' ) ) );
		if ( is_wp_error( $res ) ) {
			return $fail( $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$loc = (string) wp_remote_retrieve_header( $res, 'location' );
			if ( 0 !== strpos( $loc, 'https://' ) ) {
				return $fail( n4s_t( 'Ungültige Weiterleitung von GitHub.', 'Invalid redirect from GitHub.' ) );
			}
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$tmp = download_url( $loc, 300 );
			if ( is_wp_error( $tmp ) ) {
				return $fail( $tmp->get_error_message() );
			}
		} elseif ( 200 === $code ) {
			$tmp = wp_tempnam( N4S_APPS_ASSET );
			if ( ! $tmp || false === file_put_contents( $tmp, wp_remote_retrieve_body( $res ) ) ) {
				return $fail( n4s_t( 'Temporäre Datei konnte nicht angelegt werden.', 'Temporary file could not be created.' ) );
			}
		} else {
			return $fail( 'GitHub HTTP ' . $code );
		}

		$info = filesize( $tmp ) > 5242880 ? n4s_t( 'Das ZIP ist größer als 5 MB.', 'The ZIP is larger than 5 MB.' ) : n4s_apps_inspect_zip( $tmp );
		if ( ! is_array( $info ) ) {
			unlink( $tmp );
			return $fail( $info );
		}
		return $tmp;
	},
	10,
	2
);

/** Nach einem Update des Plugins den Release-Cache leeren. */
add_action(
	'upgrader_process_complete',
	function ( $upgrader, $extra ) {
		if ( isset( $extra['type'] ) && 'plugin' === $extra['type'] ) {
			delete_site_transient( 'n4s_apps_gh_release' );
		}
	},
	10,
	2
);
