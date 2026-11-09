<?php
/**
 * n4s Apps / App-Katalog (Client): Updates und Installation von Apps aus einem n4s-Katalog (z. B. https://n4s.one).
 *
 * - Opt-in je App: nur Apps mit Dateikopf "Update URI: https://..." werden geprüft.
 * - Protokoll und Signaturformat: docs/n4s-katalog-protokoll.md
 * - Jede Datei wird vor dem Schreiben geprüft: SHA-256, Ed25519-Signatur (vertrauenswürdige Schlüssel in includes/trust.php),
 *   Slug und Version, danach dieselbe statische Prüfung wie beim manuellen Upload. Die vorherige Version bleibt als .bak.
 * - Der Zugangsschlüssel für private Apps wird nur an den Host der eingestellten Quelle gesendet, nie an andere Hosts, nie über Weiterleitungen.
 * Immer geladen, damit automatische Updates per Cron laufen.
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------ Einstellungen */

/** Normalisierte Basis-Adresse (https://host[:port][/pfad]) oder '' bei ungültiger oder unsicherer Adresse. */
function n4s_apps_base( $url ) {
	$p = wp_parse_url( trim( (string) $url ) );
	if ( empty( $p['scheme'] ) || empty( $p['host'] ) || isset( $p['user'] ) || isset( $p['pass'] ) ) {
		return '';
	}
	$scheme = strtolower( $p['scheme'] );
	if ( 'https' !== $scheme && ! ( 'http' === $scheme && apply_filters( 'n4s_apps_allow_http', false ) ) ) {
		return '';
	}
	$path = isset( $p['path'] ) ? untrailingslashit( preg_replace( '#[^A-Za-z0-9/_.-]#', '', $p['path'] ) ) : '';
	return $scheme . '://' . strtolower( $p['host'] ) . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' ) . $path;
}

/** Host (mit Port) einer Adresse, kleingeschrieben. */
function n4s_apps_host( $url ) {
	$p = wp_parse_url( (string) $url );
	return empty( $p['host'] ) ? '' : strtolower( $p['host'] ) . ( isset( $p['port'] ) ? ':' . (int) $p['port'] : '' );
}

/** Katalog-Quelle und Zugangsschlüssel. Konstanten haben Vorrang vor den gespeicherten Werten. */
function n4s_apps_catalog_cfg() {
	$o = get_option( 'n4s_apps_catalog', array() );
	$o = is_array( $o ) ? $o : array();
	$s = ( defined( 'N4S_APPS_CATALOG_SOURCE' ) && N4S_APPS_CATALOG_SOURCE ) ? N4S_APPS_CATALOG_SOURCE : ( isset( $o['source'] ) ? $o['source'] : '' );
	$k = ( defined( 'N4S_APPS_CATALOG_KEY' ) && N4S_APPS_CATALOG_KEY ) ? N4S_APPS_CATALOG_KEY : ( isset( $o['key'] ) ? $o['key'] : '' );
	$b = n4s_apps_base( $s );
	return array(
		'source' => '' !== $b ? $b : 'https://n4s.one',
		'key'    => preg_replace( '/[^A-Za-z0-9_.-]/', '', (string) $k ),
	);
}

/** Vertrauenswürdige Ed25519-Schlüssel (Base64, 32 Byte): includes/trust.php, Konstante N4S_APPS_TRUSTED_KEYS, Filter n4s_apps_trusted_keys. */
function n4s_apps_trusted_keys() {
	static $file = null;
	if ( null === $file ) {
		$file = include N4S_APPS_DIR . 'includes/trust.php';
		$file = is_array( $file ) ? $file : array();
	}
	$keys = $file;
	if ( defined( 'N4S_APPS_TRUSTED_KEYS' ) ) {
		$c    = N4S_APPS_TRUSTED_KEYS;
		$keys = array_merge( $keys, is_array( $c ) ? $c : preg_split( '/[\s,]+/', (string) $c ) );
	}
	$keys = (array) apply_filters( 'n4s_apps_trusted_keys', $keys );
	return array_values( array_filter( array_unique( array_map( 'trim', array_map( 'strval', $keys ) ) ) ) );
}

/* ------------------------------------------------------------------ Signatur */

/** Nachricht, die signiert wird: bindet Slug, Version und Dateiinhalt (Hash). */
function n4s_apps_sign_message( $slug, $version, $sha256 ) {
	return "n4s-app-v1\n" . $slug . "\n" . $version . "\n" . $sha256;
}

/** true oder eine Fehlermeldung. */
function n4s_apps_check_signature( $slug, $version, $sha256, $sig ) {
	$keys = n4s_apps_trusted_keys();
	if ( ! $keys ) {
		return n4s_t( 'Es ist kein Signaturschlüssel hinterlegt, daher sind Katalog-Updates deaktiviert.', 'No signing key is configured, so catalog updates are disabled.' );
	}
	if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
		return n4s_t( 'Auf dem Server fehlt libsodium, die Signatur kann nicht geprüft werden.', 'libsodium is missing on the server, the signature cannot be verified.' );
	}
	$raw = base64_decode( (string) $sig, true );
	if ( false === $raw || 64 !== strlen( $raw ) ) {
		return n4s_t( 'Die Signatur der Datei fehlt oder ist ungültig.', 'The file signature is missing or invalid.' );
	}
	$msg = n4s_apps_sign_message( $slug, $version, $sha256 );
	foreach ( $keys as $k ) {
		$pk = base64_decode( $k, true );
		if ( false !== $pk && 32 === strlen( $pk ) && sodium_crypto_sign_verify_detached( $raw, $msg, $pk ) ) {
			return true;
		}
	}
	return n4s_t( 'Die Signatur der Datei stimmt nicht mit einem vertrauenswürdigen Schlüssel überein. Die Datei wurde nicht installiert.', 'The file signature does not match a trusted key. The file was not installed.' );
}

/* ---------------------------------------------------------------------- HTTP */

/**
 * GET auf eine Katalog-Adresse. Nur gleicher Host wie die Basis, keine Weiterleitungen,
 * Zugangsschlüssel nur an den Host der eingestellten Quelle. Gibt array( 'body' => ... ) oder WP_Error zurück.
 */
function n4s_apps_request( $url, $base, $max ) {
	$u = wp_parse_url( $url );
	$b = wp_parse_url( $base );
	if ( empty( $u['scheme'] ) || empty( $b['scheme'] ) || $u['scheme'] !== $b['scheme'] || n4s_apps_host( $url ) !== n4s_apps_host( $base ) ) {
		return new WP_Error( 'n4s_catalog', n4s_t( 'Ungültige Adresse im Katalog (anderer Host).', 'Invalid address in the catalog (different host).' ) );
	}
	$cfg     = n4s_apps_catalog_cfg();
	$headers = array(
		'Accept'     => 'application/json, text/plain, */*',
		'User-Agent' => 'n4s-apps/' . N4S_APPS_VERSION,
	);
	if ( '' !== $cfg['key'] && n4s_apps_host( $base ) === n4s_apps_host( $cfg['source'] ) ) {
		$headers['Authorization'] = 'Bearer ' . $cfg['key'];
		$headers['X-N4S-Key']     = $cfg['key']; // Fallback: manche Server entfernen den Authorization-Header
	}
	$res = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 15,
			'redirection'         => 0,
			'headers'             => $headers,
			'limit_response_size' => $max,
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'n4s_catalog', $res->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( in_array( $code, array( 401, 403 ), true ) ) {
		return new WP_Error( 'n4s_catalog', n4s_t( 'Zugriff verweigert (Zugangsschlüssel prüfen).', 'Access denied (check the access key).' ) );
	}
	if ( 200 !== $code ) {
		return new WP_Error( 'n4s_catalog', 'HTTP ' . $code );
	}
	return array( 'body' => (string) wp_remote_retrieve_body( $res ) );
}

/** JSON-Schnittstelle des Katalogs (REST-Namespace n4s-admin/v1, auch ohne schöne Permalinks erreichbar). */
function n4s_apps_api( $base, $path, $query = array() ) {
	$url = add_query_arg( array_merge( array( 'rest_route' => '/n4s-admin/v1/' . $path ), $query ), $base . '/' );
	$r   = n4s_apps_request( $url, $base, 262144 );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$j = json_decode( $r['body'], true );
	return is_array( $j ) ? $j : new WP_Error( 'n4s_catalog', n4s_t( 'Ungültige Antwort des Katalogs.', 'Invalid response from the catalog.' ) );
}

/** Prüft und bereinigt einen Katalog-Eintrag. Gibt array oder null zurück. */
function n4s_apps_clean_entry( $slug, $e, $base ) {
	if ( ! is_array( $e ) ) {
		return null;
	}
	$slug = (string) $slug;
	$v    = isset( $e['version'] ) ? (string) $e['version'] : '';
	$sha  = isset( $e['sha256'] ) ? strtolower( (string) $e['sha256'] ) : '';
	$sig  = isset( $e['signature'] ) ? (string) $e['signature'] : '';
	$dl   = isset( $e['download'] ) ? (string) $e['download'] : '';
	if ( ! preg_match( '/^[a-z0-9_-]{1,60}$/', $slug ) || ! preg_match( '/^[0-9][0-9A-Za-z.+-]{0,30}$/', $v ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha ) || ! preg_match( '#^[A-Za-z0-9+/=]{80,100}$#', $sig ) ) {
		return null;
	}
	if ( 0 !== strpos( $dl, 'https://' ) && 0 !== strpos( $dl, 'http://' ) ) {
		return null;
	}
	if ( n4s_apps_host( $dl ) !== n4s_apps_host( $base ) ) {
		return null;
	}
	$desc = isset( $e['description'] ) ? $e['description'] : '';
	if ( is_array( $desc ) ) {
		$desc = n4s_t( isset( $desc['de'] ) ? $desc['de'] : '', isset( $desc['en'] ) ? $desc['en'] : ( isset( $desc['de'] ) ? $desc['de'] : '' ) );
	}
	return array(
		'slug'        => $slug,
		'version'     => $v,
		'name'        => isset( $e['name'] ) ? mb_substr( sanitize_text_field( (string) $e['name'] ), 0, 80 ) : $slug,
		'sha256'      => $sha,
		'signature'   => $sig,
		'download'    => $dl,
		'size'        => isset( $e['size'] ) ? (int) $e['size'] : 0,
		'access'      => ( isset( $e['access'] ) && 'private' === $e['access'] ) ? 'private' : 'public',
		'changelog'   => isset( $e['changelog'] ) ? mb_substr( sanitize_textarea_field( (string) $e['changelog'] ), 0, 1000 ) : '',
		'description' => mb_substr( sanitize_textarea_field( (string) $desc ), 0, 400 ),
		'license'     => isset( $e['license'] ) ? mb_substr( sanitize_text_field( (string) $e['license'] ), 0, 60 ) : '',
		'base'        => $base,
	);
}

/* ------------------------------------------------------------------- Updates */

/** Installierte eigene Apps mit Update-Quelle (Dateikopf "Update URI"): slug => array( file, name, version, base ). */
function n4s_apps_update_sources() {
	$out = array();
	foreach ( n4s_apps_files() as $f ) {
		if ( 'user' !== $f['source'] ) {
			continue;
		}
		$meta = n4s_apps_meta( (string) file_get_contents( $f['file'] ) );
		$base = $meta['update_uri'] ? n4s_apps_base( $meta['update_uri'] ) : '';
		if ( '' !== $base && 1 === count( $meta['slugs'] ) ) {
			$out[ $meta['slugs'][0] ] = array(
				'file'    => basename( $f['file'] ),
				'name'    => $meta['name'],
				'version' => $meta['version'],
				'base'    => $base,
			);
		}
	}
	return $out;
}

/**
 * Update-Prüfung (einen Tag gecacht, Cron erneuert alle 12 Stunden). Ergebnis: array( checked, updates => slug => Eintrag, errors => base => Meldung ).
 * $cached_only: nie eine Verbindung aufbauen (für die Admin-Seite), ohne Cache leeres Ergebnis.
 */
function n4s_apps_updates( $force = false, $cached_only = false ) {
	$c = get_transient( 'n4s_apps_updates' );
	if ( ! $force && is_array( $c ) ) {
		return $c;
	}
	if ( $cached_only ) {
		return array( 'checked' => 0, 'updates' => array(), 'errors' => array() );
	}
	$res = array( 'checked' => time(), 'updates' => array(), 'errors' => array() );
	$by  = array();
	foreach ( n4s_apps_update_sources() as $slug => $i ) {
		$by[ $i['base'] ][ $slug ] = $i['version'];
	}
	foreach ( $by as $base => $apps ) {
		$q = array();
		foreach ( $apps as $s => $v ) {
			$q[] = $s . ':' . $v;
		}
		$r = n4s_apps_api( $base, 'check', array( 'apps' => implode( ',', $q ) ) );
		if ( is_wp_error( $r ) ) {
			$res['errors'][ $base ] = $r->get_error_message();
			continue;
		}
		foreach ( (array) ( isset( $r['apps'] ) ? $r['apps'] : array() ) as $slug => $e ) {
			$e = isset( $apps[ $slug ] ) ? n4s_apps_clean_entry( $slug, $e, $base ) : null;
			if ( $e ) {
				$res['updates'][ $slug ] = $e;
			}
		}
	}
	set_transient( 'n4s_apps_updates', $res, DAY_IN_SECONDS );
	return $res;
}

/** Nur die Updates, die neuer sind als die installierte Version: slug => Eintrag. */
function n4s_apps_pending_updates( $force = false, $cached_only = false ) {
	$u   = n4s_apps_updates( $force, $cached_only );
	$out = array();
	foreach ( n4s_apps_update_sources() as $slug => $i ) {
		if ( isset( $u['updates'][ $slug ] ) && version_compare( $u['updates'][ $slug ]['version'], $i['version'] ? $i['version'] : '0', '>' ) ) {
			$out[ $slug ] = $u['updates'][ $slug ];
		}
	}
	return $out;
}

/** Katalog-Liste (alle sichtbaren Apps) der eingestellten Quelle: Liste von Einträgen oder WP_Error. */
function n4s_apps_catalog_list() {
	$base = n4s_apps_catalog_cfg()['source'];
	$r    = n4s_apps_api( $base, 'catalog' );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$out = array();
	foreach ( (array) ( isset( $r['apps'] ) ? $r['apps'] : array() ) as $e ) {
		$e = is_array( $e ) && isset( $e['slug'] ) ? n4s_apps_clean_entry( $e['slug'], $e, $base ) : null;
		if ( $e ) {
			$out[] = $e;
		}
	}
	return $out;
}

/* --------------------------------------------------------------- Installation */

/** Lädt die Datei eines Katalog-Eintrags und prüft Größe, Hash, Signatur, Slug und Version. Gibt den Code oder WP_Error zurück. */
function n4s_apps_catalog_fetch( $e ) {
	$r = n4s_apps_request( $e['download'], $e['base'], 1048577 );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = $r['body'];
	if ( strlen( $code ) > 1048576 ) {
		return new WP_Error( 'n4s_catalog', n4s_t( 'Die Datei ist größer als 1024 KB.', 'The file is larger than 1024 KB.' ) );
	}
	if ( ! hash_equals( $e['sha256'], hash( 'sha256', $code ) ) ) {
		return new WP_Error( 'n4s_catalog', n4s_t( 'Die Prüfsumme der Datei stimmt nicht (SHA-256). Die Datei wurde nicht installiert.', 'The file checksum does not match (SHA-256). The file was not installed.' ) );
	}
	$sig = n4s_apps_check_signature( $e['slug'], $e['version'], $e['sha256'], $e['signature'] );
	if ( true !== $sig ) {
		return new WP_Error( 'n4s_catalog', $sig );
	}
	$meta = n4s_apps_meta( $code );
	if ( $meta['slugs'] !== array( $e['slug'] ) || $meta['version'] !== $e['version'] ) {
		return new WP_Error( 'n4s_catalog', n4s_t( 'App-Name oder Version in der Datei passen nicht zum Katalog-Eintrag.', 'App name or version in the file do not match the catalog entry.' ) );
	}
	return $code;
}

/**
 * Installiert oder aktualisiert eine App aus dem Katalog. $mode: 'update' oder 'new'.
 * Gibt array( name, version ) oder WP_Error zurück.
 */
function n4s_apps_catalog_apply( $e, $mode ) {
	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
		return new WP_Error( 'n4s_catalog', n4s_t( 'Dateiänderungen sind gesperrt (DISALLOW_FILE_MODS).', 'File changes are disabled (DISALLOW_FILE_MODS).' ) );
	}
	$slug = $e['slug'];
	$have = n4s_apps_installed_slugs();
	$old  = '';
	if ( 'update' === $mode ) {
		if ( ! isset( $have[ $slug ] ) || 0 !== strpos( $have[ $slug ], 'user:' ) ) {
			return new WP_Error( 'n4s_catalog', n4s_t( 'Die App ist nicht installiert.', 'The app is not installed.' ) );
		}
		$name = substr( $have[ $slug ], 5 );
		$path = N4S_APPS_USER_DIR . $name;
		$old  = (string) file_get_contents( $path );
	} else {
		$name = $slug . '.php';
		$path = N4S_APPS_USER_DIR . $name;
		if ( isset( $have[ $slug ] ) || 'security' === $slug || ! n4s_apps_valid_name( $name ) || file_exists( $path ) || file_exists( N4S_APPS_DIR . 'apps/' . $name ) ) {
			return new WP_Error( 'n4s_catalog', n4s_t( 'Der App-Name ist bereits vergeben oder reserviert.', 'The app name is already taken or reserved.' ) );
		}
	}
	$code = n4s_apps_catalog_fetch( $e );
	if ( is_wp_error( $code ) ) {
		return $code;
	}
	$ok = n4s_apps_validate_code( $code );
	if ( true !== $ok ) {
		return new WP_Error( 'n4s_catalog', $ok );
	}
	if ( 'update' === $mode ) {
		$cmp = n4s_apps_compare( $old, $code, $name, '' );
		if ( $cmp['error'] ) {
			return new WP_Error( 'n4s_catalog', $cmp['error'] );
		}
		if ( '' !== $cmp['old']['version'] && $cmp['cmp'] <= 0 ) {
			return new WP_Error( 'n4s_catalog', n4s_t( 'Die Version im Katalog ist nicht neuer als die installierte.', 'The catalog version is not newer than the installed one.' ) );
		}
		copy( $path, $path . '.bak' ); // Vorherige Version bleibt als .bak erhalten.
	} elseif ( ! n4s_apps_prepare_dir() ) {
		return new WP_Error( 'n4s_catalog', n4s_t( 'Der Ordner für eigene Apps konnte nicht angelegt werden.', 'The folder for custom apps could not be created.' ) );
	}
	if ( false === file_put_contents( $path, $code, LOCK_EX ) ) {
		return new WP_Error( 'n4s_catalog', n4s_t( 'Die Datei konnte nicht gespeichert werden.', 'The file could not be saved.' ) );
	}
	delete_transient( 'n4s_apps_updates' );
	return array( 'name' => $name, 'version' => $e['version'] );
}

/* ---------------------------------------------------------- Automatik (Cron) */

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'n4s_apps_cron' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'n4s_apps_cron' );
		}
	}
);

register_deactivation_hook(
	N4S_APPS_FILE,
	function () {
		wp_clear_scheduled_hook( 'n4s_apps_cron' );
	}
);

/** Zweimal täglich: Updates prüfen und Apps mit eingeschalteter Automatik aktualisieren. Ohne Apps mit Update URI passiert nichts. */
function n4s_apps_cron_run() {
	if ( ! n4s_feature_on( 'apps' ) || ! n4s_apps_update_sources() ) {
		return;
	}
	$auto = (array) get_option( 'n4s_apps_autoupdate', array() );
	$log  = array();
	foreach ( n4s_apps_pending_updates( true ) as $slug => $e ) {
		if ( in_array( $slug, $auto, true ) ) {
			$r           = n4s_apps_catalog_apply( $e, 'update' );
			$log[ $slug ] = is_wp_error( $r ) ? $r->get_error_message() : 'OK ' . $r['version'];
		}
	}
	if ( $log ) {
		update_option( 'n4s_apps_autolog', array( 'time' => time(), 'log' => $log ), false );
	}
}
add_action( 'n4s_apps_cron', 'n4s_apps_cron_run' );
