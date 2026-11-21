<?php
/**
 * KI-Anbindung (Royal MCP): n4s-Werkzeuge für KI-Assistenten.
 * Hängt sich über die offiziellen Filter des Plugins Royal MCP ein (royal_mcp_tools, royal_mcp_execute_tool_external);
 * ohne Royal MCP bleibt diese Funktion ohne Wirkung. Anmeldung, Zugriffsschlüssel, Protokoll und die Schalter
 * „Nur lesen“ / „Nur Entwürfe“ bleiben bei Royal MCP.
 *
 * Lesende Werkzeuge tragen annotations.readOnlyHint, schreibende nicht (Royal MCP sperrt sie dann im Lesemodus).
 * Schreibende Werkzeuge sind nur wirksam, wenn im Tab KI der Hauptschalter und die jeweilige Gruppe eingeschaltet sind.
 * Jedes Werkzeug prüft selbst die Berechtigung manage_options. Nicht angeboten: Code hochladen, Apps löschen,
 * Zugangsschlüssel, Plugin-Update.
 */

defined( 'ABSPATH' ) || exit;

/** Einstellungen: on = Schreibzugriff erlaubt (Hauptschalter), groups = Gruppen. Standard: alles aus. */
function n4s_mcp_opt() {
	$o = get_option( 'n4s_mcp', array() );
	$o = is_array( $o ) ? $o : array();
	$g = array_fill_keys( array_keys( n4s_mcp_groups() ), 0 );
	return array(
		'on'     => empty( $o['on'] ) ? 0 : 1,
		'groups' => array_merge( $g, array_map( 'intval', array_intersect_key( isset( $o['groups'] ) && is_array( $o['groups'] ) ? $o['groups'] : array(), $g ) ) ),
	);
}

function n4s_mcp_groups() {
	return array(
		'theme'    => n4s_t( 'Theme-Engine (Module, Farbschemata, Menüzuordnung)', 'Theme engine (modules, color schemes, menu assignment)' ),
		'snippets' => n4s_t( 'Snippets ein-/ausschalten', 'Switch snippets on/off' ),
		'security' => n4s_t( 'Sicherheit (Schutzeinstellungen, ohne Mail und Sperrliste)', 'Security (protection settings, without e-mail and blocklist)' ),
		'features' => n4s_t( 'Funktionen ein-/ausschalten', 'Switch features on/off' ),
		'apps'     => n4s_t( 'Apps aktivieren/deaktivieren', 'Activate/deactivate apps' ),
	);
}

function n4s_mcp_write_allowed( $group ) {
	$o = n4s_mcp_opt();
	return ! empty( $o['on'] ) && ! empty( $o['groups'][ $group ] );
}

/** Ist Royal MCP aktiv? */
function n4s_mcp_royal_active() {
	return class_exists( '\Royal_MCP\MCP\Server' ) || defined( 'ROYAL_MCP_VERSION' );
}

/** Eigenschaften eines Werkzeugs: [ Beschreibung, Eingaben, Gruppe oder '' für lesend, Handler ]. */
function n4s_mcp_defs() {
	$none = new stdClass();
	$bool = array( 'type' => 'boolean' );
	$defs = array(
		'n4s_get_status'            => array( 'Status von n4s Apps: Version, Funktionen (an/aus), KI-Schalter, Anzahl Apps und aktive Theme-Module. Zuerst aufrufen.', array( 'type' => 'object', 'properties' => $none ), '', 'n4s_mcp_t_status' ),
		'n4s_list_apps'             => array( 'Liste der App-Dateien (mitgelieferte und eigene) mit App-Kennung (app), Name, Version und Status aktiv/deaktiviert.', array( 'type' => 'object', 'properties' => $none ), '', 'n4s_mcp_t_list_apps' ),
		'n4s_get_theme_settings'    => array( 'Theme-Engine: Module an/aus, Menüzuordnung je Stelle, Farbschemata, Leisten über Kopf und Fuß. Dazu die verfügbaren Menüs (ID, Name), Stellen und Rollen.', array( 'type' => 'object', 'properties' => $none ), '', 'n4s_mcp_t_get_theme' ),
		'n4s_get_security_settings' => array( 'Schutzeinstellungen (Anmeldeschutz, Härtung). Mailadresse und Sperrliste werden nicht ausgegeben (Sperrliste nur als Anzahl).', array( 'type' => 'object', 'properties' => $none ), '', 'n4s_mcp_t_get_security' ),
		'n4s_list_snippets'         => array( 'Snippets (Shortcodes) mit Titel, Shortcode und Status an/aus.', array( 'type' => 'object', 'properties' => $none ), '', 'n4s_mcp_t_list_snippets' ),
		'n4s_suggest_colors'        => array( 'Farbvorschläge für ein Farbschema (Hell, Dunkel, Komplementär, nur Berechnung, es wird nichts gespeichert). Ohne Angabe aus dem aktiven Theme gelesen, mit accent aus einer Hauptfarbe.', array( 'type' => 'object', 'properties' => array( 'accent' => array( 'type' => 'string', 'description' => 'Hauptfarbe als #rrggbb (optional).' ) ) ), '', 'n4s_mcp_t_suggest' ),
		'n4s_set_features'          => array( 'Funktionen von n4s Apps ein-/ausschalten (apps, security, dup, mediareplace, login, theme). Nur die genannten ändern sich.', array( 'type' => 'object', 'properties' => array( 'apps' => $bool, 'security' => $bool, 'dup' => $bool, 'mediareplace' => $bool, 'login' => $bool, 'theme' => $bool ) ), 'features', 'n4s_mcp_t_set_features' ),
		'n4s_set_theme_modules'     => array( 'Module der Theme-Engine ein-/ausschalten (menus, items, loginout, widget, above, abovefooter, page, schemes). Nur die genannten ändern sich.', array( 'type' => 'object', 'properties' => array( 'menus' => $bool, 'items' => $bool, 'loginout' => $bool, 'widget' => $bool, 'above' => $bool, 'abovefooter' => $bool, 'page' => $bool, 'schemes' => $bool ) ), 'theme', 'n4s_mcp_t_set_modules' ),
		'n4s_set_color_scheme'      => array( 'Farbschema 1 bis 3 ändern: Name und Farben (bg, text, accent, hover, light, dark, darktext, btntext, heading und weitere aus n4s_get_theme_settings, jeweils #rrggbb, leer = Theme-Wert). Optional als Standard setzen (default 1-3, 0 = keins).', array( 'type' => 'object', 'properties' => array( 'scheme' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 3 ), 'name' => array( 'type' => 'string' ), 'colors' => array( 'type' => 'object' ), 'default' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 3 ) ), 'required' => array( 'scheme' ) ), 'theme', 'n4s_mcp_t_set_scheme' ),
		'n4s_set_menu_assignment'   => array( 'Menü einer Theme-Stelle je Anmeldestatus und Rolle zuordnen. location = Stelle, gast / user = Menü-ID (-1 = kein Menü, 0 = Theme-Standard), roles = Objekt Rolle => Menü-ID.', array( 'type' => 'object', 'properties' => array( 'location' => array( 'type' => 'string' ), 'gast' => array( 'type' => 'integer' ), 'user' => array( 'type' => 'integer' ), 'roles' => array( 'type' => 'object' ) ), 'required' => array( 'location' ) ), 'theme', 'n4s_mcp_t_set_menu' ),
		'n4s_set_snippets'          => array( 'Snippets ein-/ausschalten (Namen aus n4s_list_snippets). Nur die genannten ändern sich.', array( 'type' => 'object', 'properties' => array( 'login' => $bool, 'link' => $bool, 'button' => $bool, 'menu' => $bool ) ), 'snippets', 'n4s_mcp_t_set_snippets' ),
		'n4s_set_security'          => array( 'Schutzeinstellungen ändern (Zahlen und Schalter aus n4s_get_security_settings; Mailadresse, Sperrliste und IP-Quelle sind ausgenommen). Nur die genannten ändern sich.', array( 'type' => 'object', 'properties' => new stdClass() ), 'security', 'n4s_mcp_t_set_security' ),
		'n4s_toggle_app'            => array( 'App aktivieren oder deaktivieren (app aus n4s_list_apps). Löschen, Hochladen und Ändern von Code sind nicht möglich.', array( 'type' => 'object', 'properties' => array( 'app' => array( 'type' => 'string' ), 'active' => $bool ), 'required' => array( 'app', 'active' ) ), 'apps', 'n4s_mcp_t_toggle_app' ),
	);
	return $defs;
}

/** Werkzeuge in tools/list: lesende immer, schreibende nur bei eingeschaltetem Hauptschalter und Gruppe. */
add_filter(
	'royal_mcp_tools',
	function ( $tools ) {
		if ( ! is_array( $tools ) ) {
			$tools = array();
		}
		foreach ( n4s_mcp_defs() as $name => $d ) {
			if ( '' !== $d[2] && ! n4s_mcp_write_allowed( $d[2] ) ) {
				continue;
			}
			$tool = array(
				'name'        => $name,
				'description' => 'n4s Apps: ' . $d[0],
				'inputSchema' => $d[1],
			);
			$tool['annotations'] = '' === $d[2]
				? array( 'readOnlyHint' => true, 'destructiveHint' => false )
				: array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true );
			$tools[] = $tool;
		}
		return $tools;
	}
);

/** Ausführung. */
add_filter(
	'royal_mcp_execute_tool_external',
	function ( $result, $name, $args ) {
		if ( null !== $result || 0 !== strpos( (string) $name, 'n4s_' ) ) {
			return $result;
		}
		$defs = n4s_mcp_defs();
		if ( ! isset( $defs[ $name ] ) ) {
			return $result;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'n4s_forbidden', 'Permission denied: manage_options required.' );
		}
		$d = $defs[ $name ];
		if ( '' !== $d[2] && ! n4s_mcp_write_allowed( $d[2] ) ) {
			return new WP_Error( 'n4s_write_off', 'Write access is switched off in n4s Apps (tab KI).' );
		}
		return call_user_func( $d[3], is_array( $args ) ? $args : array() );
	},
	10,
	3
);

/* ------------------------------------------------------------ lesende Werkzeuge */

function n4s_mcp_apps() {
	$out      = array();
	$disabled = (array) get_option( 'n4s_apps_disabled', array() );
	foreach ( n4s_apps_files() as $f ) {
		$name = basename( $f['file'] );
		$key  = $f['source'] . ':' . $name;
		$head = (string) file_get_contents( $f['file'], false, null, 0, 8192 );
		$out[] = array(
			'app'     => $key,
			'name'    => preg_match( '/^[ \t\/*#@]*App Name:[ \t]*([^\r\n]+)/mi', $head, $m ) ? trim( $m[1], " \t*/" ) : $name,
			'version' => preg_match( '/^[ \t\/*#@]*Version:[ \t]*([^\r\n]+)/mi', $head, $m ) ? trim( $m[1], " \t*/" ) : '',
			'source'  => $f['source'],
			'active'  => ! in_array( $key, $disabled, true ) && ! n4s_apps_replaced( $key ),
		);
	}
	return $out;
}

function n4s_mcp_t_status() {
	$o = n4s_mcp_opt();
	return array(
		'plugin_version' => N4S_APPS_VERSION,
		'features'       => array_map( 'boolval', n4s_features() ),
		'ki_write'       => array( 'main' => (bool) $o['on'], 'groups' => array_map( 'boolval', $o['groups'] ) ),
		'apps'           => count( n4s_mcp_apps() ),
		'theme_modules'  => function_exists( 'n4s_theme_opt' ) ? array_map( 'boolval', n4s_theme_opt()['on'] ) : null,
	);
}

function n4s_mcp_t_list_apps() {
	return array( 'apps' => n4s_mcp_apps() );
}

function n4s_mcp_theme_context() {
	$menus = array();
	foreach ( wp_get_nav_menus() as $m ) {
		$menus[] = array( 'id' => (int) $m->term_id, 'name' => $m->name );
	}
	return array(
		'menus'     => $menus,
		'locations' => get_registered_nav_menus(),
		'roles'     => array_keys( wp_roles()->get_names() ),
	);
}

function n4s_mcp_t_get_theme() {
	if ( ! function_exists( 'n4s_theme_opt' ) ) {
		return new WP_Error( 'n4s_off', 'Theme engine is switched off.' );
	}
	return array(
		'settings'     => n4s_theme_opt(),
		'color_fields' => array_map( function ( $f ) {
			return $f[0];
		}, n4s_theme_color_fields() ),
		'context'      => n4s_mcp_theme_context(),
	);
}

function n4s_mcp_sec_safe() {
	if ( ! function_exists( 'n4s_sec_opt' ) ) {
		return null;
	}
	$o = n4s_sec_opt();
	$o['blacklist_count'] = count( array_filter( preg_split( '/\R/', (string) $o['blacklist'] ) ) );
	unset( $o['email'], $o['blacklist'] );
	return $o;
}

function n4s_mcp_t_get_security() {
	$o = n4s_mcp_sec_safe();
	return null === $o ? new WP_Error( 'n4s_off', 'Security is switched off.' ) : array( 'settings' => $o );
}

function n4s_mcp_t_list_snippets() {
	$out = array();
	foreach ( n4s_snippets_defs() as $k => $d ) {
		$out[] = array( 'snippet' => $k, 'title' => $d['title'], 'shortcode' => $d['shortcode'], 'active' => (bool) n4s_snippet_on( $k ) );
	}
	return array( 'snippets' => $out );
}

function n4s_mcp_t_suggest( $a ) {
	if ( ! function_exists( 'n4s_theme_proposals' ) ) {
		return new WP_Error( 'n4s_off', 'Theme engine is switched off.' );
	}
	$accent = isset( $a['accent'] ) ? sanitize_hex_color( (string) $a['accent'] ) : '';
	if ( $accent ) {
		return array( 'proposals' => n4s_theme_proposals( $accent ) );
	}
	$read = n4s_theme_read_colors();
	if ( empty( $read['colors'] ) ) {
		return array( 'proposals' => array(), 'note' => 'No colors could be read from the active theme.' );
	}
	$label = isset( $read['source'] ) ? (string) $read['source'] : '';
	return array( 'theme' => $label, 'read_colors' => $read['colors'], 'proposals' => n4s_theme_proposals( $read['colors'], $label ) );
}

/* ------------------------------------------------------------ schreibende Werkzeuge */

/** Antwort mit Vorher/Nachher. */
function n4s_mcp_done( $before, $after ) {
	return array( 'changed' => $before !== $after, 'before' => $before, 'after' => $after );
}

function n4s_mcp_t_set_features( $a ) {
	$cur    = n4s_features();
	$before = $cur;
	foreach ( array( 'apps', 'security', 'dup', 'mediareplace', 'login', 'theme' ) as $k ) {
		if ( isset( $a[ $k ] ) ) {
			$cur[ $k ] = filter_var( $a[ $k ], FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
		}
	}
	$cur['mcp'] = 1; // die KI-Anbindung selbst bleibt unverändert an
	update_option( 'n4s_features', $cur );
	return n4s_mcp_done( array_map( 'boolval', $before ), array_map( 'boolval', n4s_features() ) );
}

function n4s_mcp_t_set_modules( $a ) {
	if ( ! function_exists( 'n4s_theme_opt' ) ) {
		return new WP_Error( 'n4s_off', 'Theme engine is switched off.' );
	}
	$o      = get_option( 'n4s_theme', array() );
	$o      = is_array( $o ) ? $o : array();
	$cur    = n4s_theme_opt();
	$before = $cur['on'];
	$on     = $before;
	foreach ( array_keys( $before ) as $k ) {
		if ( isset( $a[ $k ] ) ) {
			$on[ $k ] = filter_var( $a[ $k ], FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
		}
	}
	$o['on'] = $on;
	update_option( 'n4s_theme', array_merge( $cur, $o ), false );
	n4s_theme_opt( true );
	return n4s_mcp_done( array_map( 'boolval', $before ), array_map( 'boolval', n4s_theme_opt()['on'] ) );
}

function n4s_mcp_t_set_scheme( $a ) {
	if ( ! function_exists( 'n4s_theme_opt' ) ) {
		return new WP_Error( 'n4s_off', 'Theme engine is switched off.' );
	}
	$n = isset( $a['scheme'] ) ? (int) $a['scheme'] : 0;
	if ( $n < 1 || $n > 3 ) {
		return new WP_Error( 'n4s_bad_scheme', 'scheme must be 1, 2 or 3.' );
	}
	$cur    = n4s_theme_opt();
	$before = array( 'scheme' => $cur['schemes'][ $n ], 'default' => (int) $cur['default'] );
	if ( isset( $a['name'] ) && '' !== trim( (string) $a['name'] ) ) {
		$cur['schemes'][ $n ]['name'] = mb_substr( sanitize_text_field( (string) $a['name'] ), 0, 30 );
	}
	if ( isset( $a['colors'] ) && is_array( $a['colors'] ) ) {
		$fields = n4s_theme_color_fields();
		foreach ( $a['colors'] as $k => $v ) {
			if ( ! isset( $fields[ $k ] ) ) {
				return new WP_Error( 'n4s_bad_color_key', 'Unknown color key: ' . sanitize_key( $k ) );
			}
			$v = '' === trim( (string) $v ) ? '' : sanitize_hex_color( trim( (string) $v ) );
			if ( null === $v ) {
				return new WP_Error( 'n4s_bad_color', 'Color must be #rrggbb: ' . sanitize_key( $k ) );
			}
			$cur['schemes'][ $n ]['colors'][ $k ] = $v;
		}
	}
	if ( isset( $a['default'] ) ) {
		$dn             = (int) $a['default'];
		$cur['default'] = ( $dn >= 1 && $dn <= 3 ) ? $dn : 0;
	}
	update_option( 'n4s_theme', $cur, false );
	n4s_theme_opt( true );
	$new = n4s_theme_opt();
	return n4s_mcp_done( $before, array( 'scheme' => $new['schemes'][ $n ], 'default' => (int) $new['default'] ) );
}

function n4s_mcp_t_set_menu( $a ) {
	if ( ! function_exists( 'n4s_theme_opt' ) ) {
		return new WP_Error( 'n4s_off', 'Theme engine is switched off.' );
	}
	$loc = isset( $a['location'] ) ? sanitize_key( $a['location'] ) : '';
	if ( ! isset( get_registered_nav_menus()[ $loc ] ) ) {
		return new WP_Error( 'n4s_bad_location', 'Unknown location. See n4s_get_theme_settings (context.locations).' );
	}
	$valid = array_map( function ( $m ) {
		return (int) $m->term_id;
	}, wp_get_nav_menus() );
	$ok    = function ( $v ) use ( $valid ) {
		$v = (int) $v;
		return ( -1 === $v || in_array( $v, $valid, true ) ) ? $v : 0;
	};
	$cur    = n4s_theme_opt();
	$before = isset( $cur['menus'][ $loc ] ) ? $cur['menus'][ $loc ] : array( 'gast' => 0, 'user' => 0, 'roles' => array() );
	$c      = array(
		'gast'  => isset( $a['gast'] ) ? $ok( $a['gast'] ) : (int) $before['gast'],
		'user'  => isset( $a['user'] ) ? $ok( $a['user'] ) : (int) $before['user'],
		'roles' => (array) $before['roles'],
	);
	if ( isset( $a['roles'] ) && is_array( $a['roles'] ) ) {
		$roles = array_keys( wp_roles()->get_names() );
		foreach ( $a['roles'] as $r => $v ) {
			if ( in_array( $r, $roles, true ) ) {
				$v = $ok( $v );
				if ( $v ) {
					$c['roles'][ $r ] = $v;
				} else {
					unset( $c['roles'][ $r ] );
				}
			}
		}
	}
	if ( $c['gast'] || $c['user'] || $c['roles'] ) {
		$cur['menus'][ $loc ] = $c;
	} else {
		unset( $cur['menus'][ $loc ] );
	}
	update_option( 'n4s_theme', $cur, false );
	n4s_theme_opt( true );
	return n4s_mcp_done( $before, isset( $cur['menus'][ $loc ] ) ? $cur['menus'][ $loc ] : array() );
}

function n4s_mcp_t_set_snippets( $a ) {
	$o      = get_option( 'n4s_snippets', array() );
	$o      = is_array( $o ) ? $o : array();
	$before = array();
	$after  = array();
	foreach ( array_keys( n4s_snippets_defs() ) as $k ) {
		$before[ $k ] = ! empty( $o[ $k ] );
		if ( isset( $a[ $k ] ) ) {
			$o[ $k ] = filter_var( $a[ $k ], FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
		}
		$after[ $k ] = ! empty( $o[ $k ] );
	}
	update_option( 'n4s_snippets', $o, false );
	return n4s_mcp_done( $before, $after );
}

function n4s_mcp_t_set_security( $a ) {
	if ( ! function_exists( 'n4s_sec_opt' ) ) {
		return new WP_Error( 'n4s_off', 'Security is switched off.' );
	}
	$int    = array(
		'retries' => array( 1, 20 ), 'lockout_min' => array( 1, 1440 ), 'lockouts_long' => array( 1, 20 ), 'long_hours' => array( 1, 720 ),
		'reset_hours' => array( 1, 720 ), 'notify_fails' => array( 1, 100000 ),
	);
	$flags  = array( 'notify', 'show_left', 'enum', 'generic', 'xmlrpc', 'headers', 'hidever', 'noposts', 'nonotices', 'homeredirect', 'logouthome', 'svg' );
	$cur    = n4s_sec_opt();
	$before = n4s_mcp_sec_safe();
	$new    = $cur;
	foreach ( (array) $a as $k => $v ) {
		if ( isset( $int[ $k ] ) ) {
			$new[ $k ] = max( $int[ $k ][0], min( $int[ $k ][1], (int) $v ) );
		} elseif ( in_array( $k, $flags, true ) ) {
			$new[ $k ] = filter_var( $v, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
		} else {
			return new WP_Error( 'n4s_bad_key', 'Setting not available: ' . sanitize_key( $k ) );
		}
	}
	update_option( 'n4s_lla_settings', $new );
	n4s_sec_opt( true );
	return n4s_mcp_done( $before, n4s_mcp_sec_safe() );
}

function n4s_mcp_t_toggle_app( $a ) {
	$key = isset( $a['app'] ) ? (string) $a['app'] : '';
	$app = null;
	foreach ( n4s_mcp_apps() as $x ) {
		if ( $x['app'] === $key ) {
			$app = $x;
		}
	}
	if ( ! $app ) {
		return new WP_Error( 'n4s_bad_app', 'Unknown app. See n4s_list_apps.' );
	}
	if ( n4s_apps_replaced( $key ) ) {
		return new WP_Error( 'n4s_replaced', 'This app file is replaced by a built-in function.' );
	}
	$list = (array) get_option( 'n4s_apps_disabled', array() );
	if ( ! empty( $a['active'] ) && filter_var( $a['active'], FILTER_VALIDATE_BOOLEAN ) ) {
		$list = array_values( array_diff( $list, array( $key ) ) );
	} else {
		$list[] = $key;
	}
	update_option( 'n4s_apps_disabled', array_values( array_unique( $list ) ) );
	return n4s_mcp_done( array( 'app' => $key, 'active' => $app['active'] ), array( 'app' => $key, 'active' => ! in_array( $key, $list, true ) ) );
}

/* ------------------------------------------------------------ Tab KI (Verwaltung) */

if ( is_admin() ) {
	add_filter(
		'n4s_apps_tabs',
		function ( $t ) {
			$t['ki'] = array( 'page' => 'n4s-ki', 'label' => 'KI', 'render' => 'n4s_mcp_render' );
			return $t;
		}
	);

	function n4s_mcp_render() {
		$o = n4s_mcp_opt();
		echo '<h2>' . esc_html( n4s_t( 'KI-Anbindung (Royal MCP)', 'AI connection (Royal MCP)' ) ) . '</h2>';
		echo '<p style="max-width:900px">' . esc_html( n4s_t( 'n4s Apps stellt KI-Assistenten eigene Werkzeuge zur Verfügung, die über das Plugin Royal MCP erreichbar sind. Anmeldung, Zugriffsschlüssel, Protokoll und die Schalter „Nur lesen“ / „Nur Entwürfe“ stellst du in Royal MCP ein; sie gelten auch für diese Werkzeuge.', 'n4s Apps offers AI assistants its own tools, reachable through the Royal MCP plugin. Sign-in, access keys, log and the "read only" / "drafts only" switches are set in Royal MCP and apply to these tools as well.' ) ) . '</p>';
		if ( n4s_mcp_royal_active() ) {
			echo '<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span> ' . esc_html( n4s_t( 'Royal MCP ist aktiv.', 'Royal MCP is active.' ) ) . '</p>';
		} else {
			echo '<p><span class="dashicons dashicons-warning" style="color:#b32d2e"></span> ' . esc_html( n4s_t( 'Royal MCP ist nicht aktiv. Ohne dieses Plugin sind die Werkzeuge nicht erreichbar.', 'Royal MCP is not active. Without it the tools cannot be reached.' ) ) . '</p>';
		}
		n4s_mcp_guide();
		echo '<h3>' . esc_html( n4s_t( 'Lesende Werkzeuge (immer verfügbar)', 'Read tools (always available)' ) ) . '</h3><ul style="list-style:disc;margin-left:20px">';
		foreach ( n4s_mcp_defs() as $name => $d ) {
			if ( '' === $d[2] ) {
				echo '<li><code>' . esc_html( $name ) . '</code></li>';
			}
		}
		echo '</ul>';
		n4s_apps_form_head( 'n4s_mcp_save', false, 'display:block' );
		echo '<h3>' . esc_html( n4s_t( 'Schreibzugriff', 'Write access' ) ) . '</h3>';
		echo '<p class="description" style="max-width:900px">' . esc_html( n4s_t( 'Standard: aus. Schreibende Werkzeuge werden erst angeboten, wenn der Hauptschalter und die jeweilige Gruppe eingeschaltet sind. Jede Änderung meldet den Zustand davor und danach.', 'Default: off. Write tools are only offered when the main switch and the group are on. Each change reports the state before and after.' ) ) . '</p>';
		echo '<p><label><input type="checkbox" name="on" value="1"' . checked( ! empty( $o['on'] ), true, false ) . '> <strong>' . esc_html( n4s_t( 'Schreibzugriff erlauben (Hauptschalter)', 'Allow write access (main switch)' ) ) . '</strong></label></p>';
		foreach ( n4s_mcp_groups() as $g => $label ) {
			$tools = array();
			foreach ( n4s_mcp_defs() as $name => $d ) {
				if ( $d[2] === $g ) {
					$tools[] = $name;
				}
			}
			echo '<p style="margin-left:22px"><label><input type="checkbox" name="groups[' . esc_attr( $g ) . ']" value="1"' . checked( ! empty( $o['groups'][ $g ] ), true, false ) . '> ' . esc_html( $label ) . '</label><br><span class="description"><code>' . esc_html( implode( '</code>, <code>', $tools ) ) . '</code></span></p>';
		}
		echo '<p class="description" style="max-width:900px">' . esc_html( n4s_t( 'Nicht möglich, auch nicht mit Schreibzugriff: Code hochladen oder ändern, Apps löschen, Zugangsschlüssel, Plugin-Update.', 'Not possible, even with write access: uploading or changing code, deleting apps, access keys, plugin update.' ) ) . '</p>';
		submit_button( n4s_t( 'Speichern', 'Save' ), 'primary', 'submit', true );
		echo '</form>';
	}

	/** Anleitung zur Einrichtung in Royal MCP. */
	function n4s_mcp_guide() {
		$url   = rest_url( 'royal-mcp/v1/mcp' );
		$steps = array(
			n4s_t( 'Royal MCP installieren und aktivieren (Plugins → Installieren, Suche „Royal MCP“). Im Menü Royal MCP die Verbindung einschalten.', 'Install and activate Royal MCP (Plugins → Add new, search "Royal MCP"). In the Royal MCP menu switch the connection on.' ),
			n4s_t( 'In Royal MCP einen Zugriffsschlüssel (API-Key) erzeugen und sofort kopieren, er wird nur einmal angezeigt. Alternativ meldet sich ein KI-Dienst per Anmeldung (OAuth) an; dann gibt es keinen Schlüssel.', 'In Royal MCP create an access key (API key) and copy it right away, it is shown only once. Alternatively an AI service signs in via OAuth; then there is no key.' ),
			n4s_t( 'Beim KI-Dienst (z. B. Claude oder ChatGPT) einen eigenen MCP-Connector mit der Adresse unten anlegen; als Anmeldung den Schlüssel als Bearer-Token angeben (Header Authorization: Bearer …).', 'In the AI service (e.g. Claude or ChatGPT) add a custom MCP connector with the address below; as sign-in use the key as Bearer token (header Authorization: Bearer …).' ),
			n4s_t( 'In Royal MCP unter Einstellungen die Sicherheit festlegen: „Nur lesen“ sperrt alle Werkzeuge, die etwas ändern (auch die schreibenden von n4s). „Nur Entwürfe“ erlaubt nur Entwürfe, n4s-Schreibwerkzeuge bleiben dann gesperrt. Zugriffsstufen je Verbindung gelten ebenso.', 'In Royal MCP under Settings set the security: "Read only" blocks every tool that changes something (including the n4s write tools). "Drafts only" allows drafts only, n4s write tools stay blocked then. Access levels per connection apply as well.' ),
			n4s_t( 'Zum Testen den KI-Dienst fragen: „Rufe n4s_get_status auf“. Es kommen Version, Funktionen und Schalterzustand zurück.', 'To test ask the AI service: "Call n4s_get_status". It returns version, features and switch state.' ),
			n4s_t( 'Änderungen erst erlauben, wenn alles läuft: unten Hauptschalter und nur die Gruppen einschalten, die gebraucht werden, und Royals „Nur lesen“ ausschalten. Danach wieder zurückschalten, wenn die Arbeit erledigt ist.', 'Only allow changes once everything works: below switch on the main switch and only the groups you need, and switch off Royal\'s "Read only". Switch them back when the work is done.' ),
		);
		echo '<details style="max-width:900px;margin:12px 0;border:1px solid #c3c4c7;background:#fff;padding:8px 12px"><summary style="cursor:pointer;font-weight:600">' . esc_html( n4s_t( 'Anleitung: Einrichtung in Royal MCP', 'Guide: setting up Royal MCP' ) ) . '</summary><ol style="margin:10px 0 6px 20px">';
		foreach ( $steps as $st ) {
			echo '<li style="margin-bottom:6px">' . esc_html( $st ) . '</li>';
		}
		echo '</ol><p style="margin:6px 0"><strong>' . esc_html( n4s_t( 'Adresse des Connectors:', 'Connector address:' ) ) . '</strong> <code>' . esc_html( $url ) . '</code></p>';
		echo '<p class="description" style="margin:4px 0">' . esc_html( n4s_t( 'Die Verbindung braucht https und einen Server, der den Authorization-Header durchreicht; Royal MCP zeigt einen Hinweis, falls nicht. Die Werkzeuge von n4s verlangen zusätzlich die Berechtigung „Einstellungen verwalten“ (Administrator).', 'The connection needs https and a server that passes the Authorization header; Royal MCP shows a notice if not. The n4s tools additionally require the "manage options" capability (administrator).' ) ) . '</p></details>';
	}

	add_action(
		'admin_post_n4s_mcp_save',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			n4s_apps_verify( 'n4s_mcp_save' );
			$groups = array();
			$posted = isset( $_POST['groups'] ) && is_array( $_POST['groups'] ) ? $_POST['groups'] : array(); // phpcs:ignore WordPress.Security -- nur Schlüssel geprüft.
			foreach ( array_keys( n4s_mcp_groups() ) as $g ) {
				$groups[ $g ] = empty( $posted[ $g ] ) ? 0 : 1;
			}
			update_option( 'n4s_mcp', array( 'on' => empty( $_POST['on'] ) ? 0 : 1, 'groups' => $groups ), false ); // phpcs:ignore WordPress.Security.NonceVerification -- siehe n4s_apps_verify.
			n4s_apps_notice( 'success', n4s_t( 'KI-Einstellungen gespeichert.', 'AI settings saved.' ) );
			n4s_apps_back( false, 'n4s-ki' );
		}
	);
}
