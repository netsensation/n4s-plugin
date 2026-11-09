<?php
/**
 * Plugin Name: n4s Apps
 * Description: Rahmen für eigene PHP-Apps mit Verwaltung im WordPress-Menü / Framework for custom PHP apps with admin management. Shortcode [n4s_app name="..."].
 * Version: 2.6.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: n4s
 * Author URI: https://n4s.one
 * Update URI: https://github.com/netsensation/n4s-plugin
 * Text Domain: n4s-apps
 */

defined( 'ABSPATH' ) || exit;

define( 'N4S_APPS_VERSION', '2.6.0' );
define( 'N4S_APPS_FILE', __FILE__ );
define( 'N4S_APPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'N4S_APPS_URL', plugin_dir_url( __FILE__ ) );
/** Eigene Apps liegen außerhalb des Plugin-Ordners und überstehen Plugin-Updates. */
define( 'N4S_APPS_USER_DIR', trailingslashit( WP_CONTENT_DIR ) . 'n4s-apps-data/apps/' );

/**
 * Zweisprachiger Text: Deutsch bei deutscher Sprache (Benutzer- bzw. Website-Sprache), sonst Englisch.
 * Auch in eigenen Apps nutzbar: n4s_t( 'Hallo', 'Hello' ).
 */
function n4s_t( $de, $en ) {
	return ( 0 === strpos( determine_locale(), 'de' ) ) ? $de : $en;
}

/** Versionszeile: Version: 1.2.0 by n4s (n4s unsichtbar verlinkt auf n4s.one). */
function n4s_apps_byline( $version ) {
	return esc_html( 'Version: ' . $version ) . ' by <a href="https://n4s.one" target="_blank" rel="noopener" style="color:inherit;text-decoration:none">n4s</a>';
}

/** Version einer App aus dem Dateikopf (Version:), ohne Ausführung. */
function n4s_apps_file_version( $slug ) {
	if ( empty( $GLOBALS['n4s_app_files'][ $slug ] ) ) {
		return '';
	}
	list( $source, $name ) = explode( ':', $GLOBALS['n4s_app_files'][ $slug ], 2 );
	$file = ( 'bundled' === $source ? N4S_APPS_DIR . 'apps/' : N4S_APPS_USER_DIR ) . $name;
	$head = is_readable( $file ) ? (string) file_get_contents( $file, false, null, 0, 8192 ) : '';
	return preg_match( '/^[ \t\/*#@]*Version:[ \t]*([^\r\n]+)/mi', $head, $m ) ? trim( $m[1], " \t*/" ) : '';
}

/** Registry: slug => [ 'render' => callable, 'login' => bool ] */
$GLOBALS['n4s_apps']      = array();
/** slug => Dateischlüssel ("bundled:name.php" oder "user:name.php") */
$GLOBALS['n4s_app_files'] = array();

/**
 * App registrieren (wird aus den Dateien in apps/ aufgerufen).
 *
 * @param string   $slug   Eindeutiger Name, genutzt im Shortcode.
 * @param callable $render Gibt das HTML der App zurück, Parameter: $atts.
 * @param bool     $login  true = nur für eingeloggte Benutzer.
 */
function n4s_register_app( $slug, $render, $login = false ) {
	$GLOBALS['n4s_apps'][ sanitize_key( $slug ) ] = array(
		'render' => $render,
		'login'  => (bool) $login,
	);
}

/** Alle App-Dateien: mitgelieferte (Plugin) und eigene (wp-content/n4s-apps-data/apps). */
function n4s_apps_files() {
	$out = array();
	$dirs = array(
		'bundled' => N4S_APPS_DIR . 'apps/',
		'user'    => N4S_APPS_USER_DIR,
	);
	foreach ( $dirs as $source => $dir ) {
		foreach ( glob( $dir . '*.php' ) ?: array() as $p ) {
			$name = basename( $p );
			// index.php ist nur der Verzeichnisschutz, keine App.
			if ( 'index.php' !== $name && preg_match( '/^[a-z0-9_-]+\.php$/', $name ) ) {
				$out[] = array( 'file' => $p, 'source' => $source );
			}
		}
	}
	return $out;
}

/** Datei in die Liste der deaktivierten Apps eintragen. */
function n4s_apps_disable_key( $key ) {
	$list   = (array) get_option( 'n4s_apps_disabled', array() );
	$list[] = $key;
	update_option( 'n4s_apps_disabled', array_values( array_unique( $list ) ) );
}

/** Apps laden. Eine Datei mit Fehler wird automatisch deaktiviert, damit die Seite erreichbar bleibt. */
add_action(
	'plugins_loaded',
	function () {
		register_shutdown_function(
			function () {
				$cur = isset( $GLOBALS['n4s_loading'] ) ? $GLOBALS['n4s_loading'] : null;
				$e   = error_get_last();
				if ( $cur && $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
					n4s_apps_disable_key( $cur );
				}
			}
		);

		$disabled = (array) get_option( 'n4s_apps_disabled', array() );
		foreach ( n4s_apps_files() as $f ) {
			$key = $f['source'] . ':' . basename( $f['file'] );
			if ( in_array( $key, $disabled, true ) ) {
				continue;
			}
			$before                = array_keys( $GLOBALS['n4s_apps'] );
			$GLOBALS['n4s_loading'] = $key;
			try {
				require_once $f['file'];
			} catch ( \Throwable $e ) {
				n4s_apps_disable_key( $key );
				error_log( 'n4s Apps: ' . $key . ' deaktiviert: ' . $e->getMessage() );
			}
			$GLOBALS['n4s_loading'] = null;
			foreach ( array_diff( array_keys( $GLOBALS['n4s_apps'] ), $before ) as $slug ) {
				$GLOBALS['n4s_app_files'][ $slug ] = $key;
			}
		}
	}
);

/** Shortcode: [n4s_app name="hello"] */
add_shortcode(
	'n4s_app',
	function ( $atts ) {
		$atts = shortcode_atts( array( 'name' => '' ), $atts, 'n4s_app' );
		$slug = sanitize_key( $atts['name'] );
		$apps = $GLOBALS['n4s_apps'];

		if ( ! isset( $apps[ $slug ] ) ) {
			return current_user_can( 'manage_options' )
				? '<p>n4s Apps: ' . esc_html( n4s_t( 'App "' . $slug . '" nicht gefunden oder deaktiviert.', 'App "' . $slug . '" not found or disabled.' ) ) . '</p>'
				: '';
		}
		if ( $apps[ $slug ]['login'] && ! is_user_logged_in() ) {
			return '<p>' . esc_html( n4s_t( 'Bitte melde dich an, um diese App zu nutzen.', 'Please log in to use this app.' ) ) . '</p>';
		}
		$out = (string) call_user_func( $apps[ $slug ]['render'], $atts );
		$ver = n4s_apps_file_version( $slug );
		// Versionsanzeige automatisch anhängen (nicht doppelt, wenn die App sie selbst liefert).
		if ( $ver && false === strpos( $out, 'n4s-app-ver' ) ) {
			$out .= '<div class="n4s-app-ver" style="text-align:right;font-size:11px;opacity:.6;margin-top:6px">' . n4s_apps_byline( $ver ) . '</div>';
		}
		return $out;
	}
);

/** Update über WordPress (Quelle GitHub); immer laden, damit Auto-Updates per Cron laufen. */
require_once N4S_APPS_DIR . 'includes/updater.php';

if ( is_admin() ) {
	require_once N4S_APPS_DIR . 'includes/admin.php';
}
