<?php
/**
 * Plugin Name: n4s Apps
 * Description: n4s: Rahmen für eigene PHP-Apps und Sicherheit (Anmeldeschutz, Härtung), Funktionen einzeln schaltbar / Framework for custom PHP apps and security (login protection, hardening), features switchable. Shortcode [n4s_app name="..."].
 * Version: 2.38.1
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: n4s
 * Author URI: https://n4s.one
 * Update URI: https://github.com/netsensation/n4s-plugin
 * Text Domain: n4s-apps
 */

defined( 'ABSPATH' ) || exit;

define( 'N4S_APPS_VERSION', '2.38.1' );
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

/**
 * Funktionen des Plugins (Tab Funktionen): 'apps', 'security', 'dup' (Seite duplizieren) und 'mediareplace' (Mediendatei ersetzen) und 'login' (eigenes Login-Design) und 'theme' (Theme-Engine) einzeln ein-/ausschaltbar.
 * Standard: Apps an. Sicherheit an, wenn die Einstellungen der früheren Security-App schon da sind
 * (Datei security.php aktiv oder Option n4s_lla_settings), sonst aus, damit ein Update nichts Neues aktiviert.
 */
function n4s_features() {
	$v = get_option( 'n4s_features', null );
	if ( ! is_array( $v ) ) {
		$old = ( file_exists( N4S_APPS_USER_DIR . 'security.php' ) && ! in_array( 'user:security.php', (array) get_option( 'n4s_apps_disabled', array() ), true ) )
			|| false !== get_option( 'n4s_lla_settings', false );
		$v   = array( 'apps' => 1, 'security' => $old ? 1 : 0 );
	}
	// Theme-Engine: war sie schon mit Modulen eingerichtet (Version 2.21.0), bleibt sie eingeschaltet.
	$theme_old = 0;
	if ( ! isset( $v['theme'] ) ) {
		$t = get_option( 'n4s_theme', array() );
		if ( is_array( $t ) && ! empty( $t ) ) { // schon eingerichtet (auch wenn alle Module aus sind)
			$theme_old = 1;
		}
	}
	return wp_parse_args( $v, array( 'apps' => 1, 'security' => 0, 'dup' => 0, 'mediareplace' => 0, 'login' => 0, 'theme' => $theme_old, 'mcp' => 0, 'barmenu' => 1, 'pageaccess' => 0, 'snippets' => 1 ) );
}

function n4s_feature_on( $key ) {
	$f = n4s_features();
	return ! empty( $f[ $key ] );
}

/** Die frühere App-Datei security.php wird nicht mehr geladen (Funktion steckt jetzt im Plugin). */
function n4s_apps_replaced( $key ) {
	return 'user:security.php' === $key;
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

/**
 * Aktivitätsprotokoll (Übersicht → Zuletzt): Zeit, Text, Benutzer ('' = automatisch). Höchstens 100 Einträge, neueste zuerst.
 * Protokolliert werden Apps (Update automatisch/manuell, installiert, gelöscht, aktiviert/deaktiviert), Plugin-Update, Funktionen und Lizenz.
 */
function n4s_apps_activity( $text, $by = null ) {
	if ( null === $by ) {
		$u  = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		$by = ( $u && $u->ID ) ? $u->user_login : '';
	}
	$log = get_option( 'n4s_apps_activity', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array( time(), mb_substr( wp_strip_all_tags( (string) $text ), 0, 300 ), (string) $by ) );
	update_option( 'n4s_apps_activity', array_slice( $log, 0, 100 ), false );
}

/** Seiten des Untermenüs „n4s“ in der Admin-Leiste: im Admin-Bereich alle Tabs, auf der Website die festen Tabs (Admin-Seiten laden dort nicht). */
function n4s_apps_bar_tabs() {
	if ( function_exists( 'n4s_apps_tabs_list' ) ) {
		return n4s_apps_tabs_list( current_user_can( 'manage_options' ), true );
	}
	$t = array(
		'overview' => array( 'n4s', n4s_t( 'Übersicht', 'Overview' ), 'edit_pages' ),
		'features' => array( 'n4s-features', n4s_t( 'Funktionen', 'Features' ), 'edit_pages' ),
	);
	if ( n4s_feature_on( 'apps' ) ) {
		$t['apps'] = array( 'n4s-apps', 'Apps', 'edit_pages' );
	}
	if ( n4s_feature_on( 'security' ) ) {
		$t['security'] = array( 'n4s-security', n4s_t( 'Sicherheit', 'Security' ), 'edit_pages' );
	}
	if ( n4s_feature_on( 'snippets' ) ) {
		$t['snippets'] = array( 'n4s-snippets', 'Snippets', 'edit_pages' );
	}
	$t['theme'] = array( 'n4s-theme', 'Customize', 'manage_options' );
	return $t;
}

/** Admin-Leiste: Untermenü „n4s“ im Dropdown des Seitennamens (Admin-Bereich und Website). */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! n4s_feature_on( 'barmenu' ) || ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'     => 'n4s-menu',
				'parent' => 'site-name',
				'title'  => 'n4s',
				'href'   => admin_url( 'admin.php?page=n4s' ),
			)
		);
		foreach ( n4s_apps_bar_tabs() as $k => $t ) {
			if ( ! current_user_can( $t[2] ) ) {
				continue;
			}
			$bar->add_node(
				array(
					'id'     => 'n4s-menu-' . sanitize_key( $k ),
					'parent' => 'n4s-menu',
					'title'  => esc_html( $t[1] ),
					'href'   => admin_url( 'admin.php?page=' . $t[0] ),
				)
			);
		}
	},
	80
);

/** Nach einem Update über WordPress: Link zur n4s-Übersicht (und Sprung dorthin, wenn das Update aus dem n4s-Menü kam). */
add_filter(
	'update_plugin_complete_actions',
	function ( $actions, $plugin ) {
		if ( plugin_basename( N4S_APPS_FILE ) !== $plugin || ! current_user_can( 'edit_pages' ) ) {
			return $actions;
		}
		$url = admin_url( 'admin.php?page=n4s' );
		$new = array( 'n4s_overview' => '<a href="' . esc_url( $url ) . '" target="_parent">' . esc_html( n4s_t( 'Zur n4s-Übersicht', 'Go to the n4s overview' ) ) . '</a>' );
		if ( ! empty( $_GET['n4s_ret'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- reiner Sprung nach dem Update.
			$new['n4s_jump'] = '<script>setTimeout(function(){window.location.href=' . wp_json_encode( $url ) . '},2500)</script>';
		}
		return $new + (array) $actions;
	},
	10,
	2
);

/** Hinweis mit Link zur Übersicht nach einem Plugin-Update, solange die n4s-Seiten noch nicht geöffnet wurden. */
add_action(
	'admin_notices',
	function () {
		if ( ! get_option( 'n4s_apps_updated_notice' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 === strpos( $page, 'n4s' ) ) {
			delete_option( 'n4s_apps_updated_notice' );
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( n4s_t( 'n4s Apps wurde aktualisiert auf Version ' . N4S_APPS_VERSION . '. ', 'n4s Apps was updated to version ' . N4S_APPS_VERSION . '. ' ) ) . '<a href="' . esc_url( admin_url( 'admin.php?page=n4s' ) ) . '">' . esc_html( n4s_t( 'Zur n4s-Übersicht', 'Go to the n4s overview' ) ) . '</a></p></div>';
	}
);

/** Plugin-Update erkennen (egal auf welchem Weg): Version mit der zuletzt gesehenen vergleichen. */
add_action(
	'init',
	function () {
		$seen = get_option( 'n4s_apps_seen_version', '' );
		if ( N4S_APPS_VERSION !== $seen ) {
			update_option( 'n4s_apps_seen_version', N4S_APPS_VERSION, false );
			if ( '' !== $seen ) {
				update_option( 'n4s_apps_updated_notice', 1, false );
				n4s_apps_activity( n4s_t( 'Plugin n4s Apps aktualisiert: ', 'Plugin n4s Apps updated: ' ) . $seen . ' → ' . N4S_APPS_VERSION, '' );
			}
		}
	},
	5
);

/** Datei in die Liste der deaktivierten Apps eintragen. */
function n4s_apps_disable_key( $key ) {
	$list   = (array) get_option( 'n4s_apps_disabled', array() );
	$list[] = $key;
	update_option( 'n4s_apps_disabled', array_values( array_unique( $list ) ) );
}

/**
 * Test vor der Freigabe (App n4s Admin auf n4s.one): Die App legt in der Option n4s_apps_test je Benutzer
 * { slug, until } ab. Solange der Test läuft, lädt die Seite die installierte Version dieser App nicht, wenn der
 * Testende selbst eine Test-, REST-, Ajax- oder Formular-Anfrage stellt; so kann die Testversion dieselben
 * Funktionen nutzen. Alle anderen Besucher sehen unverändert die installierte Version.
 */
function n4s_apps_test_skip_slug() {
	static $r = false;
	if ( false !== $r ) {
		return $r;
	}
	$r = '';
	$o = get_option( 'n4s_apps_test', array() );
	if ( ! is_array( $o ) || ! $o || ! function_exists( 'wp_validate_logged_in_cookie' ) ) {
		return $r;
	}
	$uid = (int) wp_validate_logged_in_cookie( false );
	if ( $uid < 1 || empty( $o[ $uid ]['slug'] ) || (int) $o[ $uid ]['until'] < time() ) {
		return $r;
	}
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$rest = false !== strpos( $uri, 'n4s-apps/v1/' ) || ( isset( $_GET['rest_route'] ) && false !== strpos( (string) wp_unslash( $_GET['rest_route'] ), 'n4s-apps/v1/' ) ); // phpcs:ignore WordPress.Security
	$page = isset( $_REQUEST['n4s_adm_test'] ) && ! defined( 'WP_ADMIN' ); // phpcs:ignore WordPress.Security.NonceVerification
	$ajax = ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || 'admin-post.php' === basename( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	if ( $rest || $page || $ajax ) {
		$r = sanitize_key( $o[ $uid ]['slug'] );
	}
	return $r;
}

/** Apps laden. Eine Datei mit Fehler wird automatisch deaktiviert, damit die Seite erreichbar bleibt. */
add_action(
	'plugins_loaded',
	function () {
		if ( ! n4s_feature_on( 'apps' ) ) {
			return;
		}
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
		$lic      = n4s_apps_license_all(); // gesperrte oder abgelaufene Lizenz eines Katalogs (meist leer)
		foreach ( n4s_apps_files() as $f ) {
			$key = $f['source'] . ':' . basename( $f['file'] );
			if ( in_array( $key, $disabled, true ) || n4s_apps_replaced( $key ) ) {
				continue;
			}
			if ( $lic && 'user' === $f['source'] ) { // Lizenz gesperrt oder abgelaufen: private Katalog-Apps nicht laden
				$lmeta = n4s_apps_meta( (string) file_get_contents( $f['file'] ) );
				$lst   = n4s_apps_license_state_for( $lmeta, $lic );
				if ( '' !== $lst ) {
					foreach ( $lmeta['slugs'] as $ls ) {
						$GLOBALS['n4s_apps_blocked'][ $ls ] = $lst;
					}
					continue;
				}
			}
			$test_slug = n4s_apps_test_skip_slug();
			if ( '' !== $test_slug && 'n4s-admin' !== $test_slug && in_array( $test_slug, n4s_apps_meta( (string) file_get_contents( $f['file'] ) )['slugs'], true ) ) {
				continue; // Testversion läuft statt der installierten Version (siehe n4s_apps_test_skip_slug).
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

		if ( ! n4s_feature_on( 'apps' ) ) {
			return current_user_can( 'manage_options' )
				? '<p>n4s: ' . esc_html( n4s_t( 'Apps sind unter n4s → Funktionen ausgeschaltet.', 'Apps are switched off under n4s → Features.' ) ) . '</p>'
				: '';
		}
		if ( isset( $GLOBALS['n4s_apps_blocked'][ $slug ] ) ) { // Lizenz gesperrt oder abgelaufen
			$why = n4s_t( 'Der Administrator muss auf eine neue Version aktualisieren oder upgraden.', 'The administrator must update or upgrade to a new version.' );
			return '<div class="n4s-license" style="padding:14px 16px;border:1px solid currentColor;border-radius:8px;opacity:.85"><strong>' . esc_html( n4s_t( 'Version abgelaufen', 'Version expired' ) ) . '</strong>'
				. ( current_user_can( 'manage_options' ) ? '<br><span style="font-size:.9em">' . esc_html( $why ) . '</span>' : '' )
				. '</div>';
		}
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

/** Hilfsfunktionen für App-Dateien und App-Katalog (Updates, Installation); immer geladen, damit Cron-Läufe funktionieren. */
require_once N4S_APPS_DIR . 'includes/appfiles.php';
require_once N4S_APPS_DIR . 'includes/catalog.php';

/** Sicherheit (Anmeldeschutz, Härtung): nur, wenn unter n4s → Funktionen eingeschaltet. */
if ( n4s_feature_on( 'security' ) ) {
	require_once N4S_APPS_DIR . 'includes/security.php';
}

/** Eigenes Login-Design (Logo und Farben des Themes auf der Anmeldeseite): nur, wenn eingeschaltet. */
if ( n4s_feature_on( 'login' ) ) {
	require_once N4S_APPS_DIR . 'includes/login.php';
}

/** Snippets (Shortcodes für Anmeldung, Link und Button nur für Angemeldete): immer geladen, einzeln im Tab Snippets aktivierbar. */
require_once N4S_APPS_DIR . 'includes/snippets.php';
/** Farben aus dem Theme lesen und Vorschläge: immer geladen, auch für Apps nutzbar. */
require_once N4S_APPS_DIR . 'includes/themecolors.php';
if ( n4s_feature_on( 'theme' ) ) {
	require_once N4S_APPS_DIR . 'includes/theme.php';
}
/** Seitenzugriff je Rolle: nur, wenn eingeschaltet. */
if ( n4s_feature_on( 'pageaccess' ) ) {
	require_once N4S_APPS_DIR . 'includes/access.php';
}
/** KI-Anbindung (Werkzeuge für Royal MCP): nur, wenn eingeschaltet. */
if ( n4s_feature_on( 'mcp' ) ) {
	require_once N4S_APPS_DIR . 'includes/mcp.php';
}

if ( is_admin() ) {
	require_once N4S_APPS_DIR . 'includes/admin.php';
	if ( n4s_feature_on( 'dup' ) || n4s_feature_on( 'mediareplace' ) ) {
		require_once N4S_APPS_DIR . 'includes/tools.php';
	}
}
