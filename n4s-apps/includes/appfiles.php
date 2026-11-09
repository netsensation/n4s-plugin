<?php
/**
 * n4s Apps / Hilfsfunktionen für App-Dateien (Name prüfen, Code prüfen, Dateikopf lesen, Abgleich).
 * Immer geladen (auch bei Cron-Läufen für automatische App-Updates).
 */

defined( 'ABSPATH' ) || exit;

/** Gültiger App-Dateiname (index.php ist für den Verzeichnisschutz reserviert). */
function n4s_apps_valid_name( $name ) {
	return (bool) preg_match( '/^[a-z0-9_-]+\.php$/', $name ) && 'index.php' !== $name;
}

/** Ordner für eigene Apps anlegen und vor direktem Aufruf schützen. */
function n4s_apps_prepare_dir() {
	if ( ! wp_mkdir_p( N4S_APPS_USER_DIR ) ) {
		return false;
	}
	$root = dirname( rtrim( N4S_APPS_USER_DIR, '/' ) );
	if ( ! file_exists( $root . '/.htaccess' ) ) {
		file_put_contents( $root . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
	}
	foreach ( array( $root, rtrim( N4S_APPS_USER_DIR, '/' ) ) as $d ) {
		if ( ! file_exists( $d . '/index.php' ) ) {
			file_put_contents( $d . '/index.php', "<?php\n// Silence is golden.\n" );
		}
	}
	return true;
}

/** Prüft den Code einer App, ohne ihn auszuführen. Gibt true oder eine Fehlermeldung zurück. */
function n4s_apps_validate_code( $code ) {
	if ( strlen( $code ) > 1048576 ) {
		return n4s_t( 'Die Datei ist größer als 1024 KB.', 'The file is larger than 1024 KB.' );
	}
	if ( false === strpos( $code, '<?php' ) ) {
		return n4s_t( 'Das ist keine PHP-Datei (<?php fehlt).', 'This is not a PHP file (<?php missing).' );
	}
	if ( false === strpos( $code, 'n4s_register_app' ) ) {
		return n4s_t( 'Die Datei registriert keine App (n4s_register_app fehlt).', 'The file does not register an app (n4s_register_app missing).' );
	}
	if ( ! preg_match( '/defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)/', $code ) ) {
		return n4s_t( 'Aus Sicherheitsgründen muss die Datei mit defined( \'ABSPATH\' ) || exit; geschützt sein, damit sie nicht direkt aufrufbar ist.', 'For security, the file must be protected with defined( \'ABSPATH\' ) || exit; so it cannot be called directly.' );
	}
	if ( ! n4s_apps_meta( $code )['slugs'] ) {
		return n4s_t( 'Der App-Name (Slug) muss in n4s_register_app( \'name\', … ) als feste Zeichenkette stehen.', 'The app slug must be a literal string in n4s_register_app( \'name\', … ).' );
	}
	try {
		token_get_all( $code, TOKEN_PARSE );
	} catch ( ParseError $e ) {
		return n4s_t( 'PHP-Syntaxfehler in Zeile ', 'PHP syntax error on line ' ) . $e->getLine() . ': ' . $e->getMessage();
	}
	return true;
}

/** Liest Slugs, Anzeigename und Version einer App aus dem Quelltext (ohne Ausführung). */
function n4s_apps_meta( $code ) {
	$head = substr( $code, 0, 8192 );
	$out  = array( 'slugs' => array(), 'name' => '', 'version' => '', 'update_uri' => '' );
	if ( preg_match( '/^[ \t\/*#@]*App Name:[ \t]*([^\r\n]+)/mi', $head, $m ) ) {
		$out['name'] = trim( $m[1], " \t*/" );
	}
	if ( preg_match( '/^[ \t\/*#@]*Version:[ \t]*([^\r\n]+)/mi', $head, $m ) ) {
		$out['version'] = trim( $m[1], " \t*/" );
	}
	if ( preg_match( '/^[ \t\/*#@]*Update URI:[ \t]*([^\r\n]+)/mi', $head, $m ) ) {
		$out['update_uri'] = trim( $m[1], " \t*/" );
	}
	if ( preg_match_all( '/n4s_register_app\s*\(\s*([\'"])([A-Za-z0-9_-]+)\1/', $code, $m ) ) {
		$out['slugs'] = array_values( array_unique( array_map( 'sanitize_key', $m[2] ) ) );
		sort( $out['slugs'] );
	}
	return $out;
}

/** Alle installierten Slugs mit Dateischlüssel (statisch gelesen). */
function n4s_apps_installed_slugs() {
	$map = array();
	foreach ( n4s_apps_files() as $f ) {
		$meta = n4s_apps_meta( (string) file_get_contents( $f['file'] ) );
		foreach ( $meta['slugs'] as $s ) {
			$map[ $s ] = $f['source'] . ':' . basename( $f['file'] );
		}
	}
	return $map;
}

/**
 * Abgleich installierte App gegen Update.
 * Rückgabe: array( 'error' => string|'', 'warnings' => string[], 'old' => meta, 'new' => meta, 'cmp' => int )
 */
function n4s_apps_compare( $old_code, $new_code, $target, $upload_name ) {
	$old = n4s_apps_meta( $old_code );
	$new = n4s_apps_meta( $new_code );
	$res = array( 'error' => '', 'warnings' => array(), 'notes' => array(), 'old' => $old, 'new' => $new, 'cmp' => 0 );

	if ( $old['slugs'] !== $new['slugs'] ) {
		$res['error'] = n4s_t(
			'Das Update gehört nicht zu dieser App. Installiert: ' . implode( ', ', $old['slugs'] ) . ' · Hochgeladen: ' . implode( ', ', $new['slugs'] ) . '. Es wurde nichts verändert.',
			'This update does not belong to this app. Installed: ' . implode( ', ', $old['slugs'] ) . ' · Uploaded: ' . implode( ', ', $new['slugs'] ) . '. Nothing was changed.'
		);
		return $res;
	}
	if ( $old['name'] && $new['name'] && 0 !== strcasecmp( $old['name'], $new['name'] ) ) {
		$res['warnings'][] = n4s_t( 'Der App-Name weicht ab: „' . $old['name'] . '" → „' . $new['name'] . '".', 'The app name differs: "' . $old['name'] . '" → "' . $new['name'] . '".' );
	}
	if ( $upload_name && $upload_name !== $target ) {
		$res['warnings'][] = n4s_t( 'Der Dateiname weicht ab: ' . $target . ' → ' . $upload_name . '. Gespeichert wird als ' . $target . '.', 'The file name differs: ' . $target . ' → ' . $upload_name . '. It will be saved as ' . $target . '.' );
	}
	if ( '' === $new['version'] && '' === $old['version'] ) {
		$res['notes'][] = n4s_t( 'Weder die installierte App noch das Update haben eine Versionsangabe (ältere App). Verglichen wurde nur der App-Name. Tipp: „Version:" und „App Name:" im Dateikopf ergänzen, dann ist künftig ein Versionsabgleich möglich.', 'Neither the installed app nor the update has a version (older app). Only the app name was compared. Tip: add "Version:" and "App Name:" to the file header to enable version comparison in future.' );
	} elseif ( '' === $new['version'] ) {
		$res['warnings'][] = n4s_t( 'Das Update enthält keine Versionsangabe („Version:" im Dateikopf), die installierte App hat Version ' . $old['version'] . '.', 'The update has no version ("Version:" in the file header), the installed app has version ' . $old['version'] . '.' );
	} elseif ( '' === $old['version'] ) {
		$res['notes'][] = n4s_t( 'Die installierte App hat keine Versionsangabe, ein Versionsvergleich ist nicht möglich.', 'The installed app has no version, so versions cannot be compared.' );
	} else {
		$res['cmp'] = version_compare( $new['version'], $old['version'] );
		if ( 0 === $res['cmp'] ) {
			$res['warnings'][] = n4s_t( 'Gleiche Version wie installiert (' . $old['version'] . ').', 'Same version as installed (' . $old['version'] . ').' );
		} elseif ( $res['cmp'] < 0 ) {
			$res['warnings'][] = n4s_t( 'ACHTUNG: Das Update ist ÄLTER als die installierte Version (' . $new['version'] . ' < ' . $old['version'] . ').', 'WARNING: The update is OLDER than the installed version (' . $new['version'] . ' < ' . $old['version'] . ').' );
		}
	}
	return $res;
}
