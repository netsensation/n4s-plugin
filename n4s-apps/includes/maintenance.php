<?php
/**
 * Wartungsmodus (Funktion „Wartung“, ab 2.40.0).
 *
 * Schalter in der Admin-Leiste (Admin-Bereich und Website). Im Wartungsmodus sehen Besucher eine einzelne Seite im Theme der
 * Website: je Anlass eine eigene WordPress-Seite (Wartung, Update läuft, Bald verfügbar), im Seitenbaukasten pflegbar
 * (Titelzusatz „– Wartungsseite“). Dazu Zeitplan (ab/bis, Höchstdauer, Vorankündigung, Countdown), Ausnahmen (Rollen, IP,
 * URL, App-Schnittstellen), Freigabelinks für Kunden, Mail bei Start, Ende und Auto-Ende sowie Eintrag in den Aktivitäten.
 * Konzept: docs/n4s-apps-wartungsmodus-konzept.md.
 *
 * Status 503 mit Retry-After und noindex (Wartung, Update läuft), bei „Bald verfügbar“ Status 200 mit noindex.
 * Notausgang: define( 'N4S_MAINTENANCE_OFF', true ) in der wp-config.php.
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ Einstellungen */

/** Anlässe: Schlüssel => [ Name, HTTP-Status ]. */
function n4s_maint_modes() {
	return array(
		'wartung' => array( n4s_t( 'Wartung', 'Maintenance' ), 503 ),
		'update'  => array( n4s_t( 'Update läuft', 'Update in progress' ), 503 ),
		'bald'    => array( n4s_t( 'Bald verfügbar', 'Coming soon' ), 200 ),
	);
}

/** Bereiche, die auf der Wartungsseite ausgeblendet werden können. */
function n4s_maint_hide_items() {
	return array(
		'header'   => n4s_t( 'Kopfzeile', 'Header' ),
		'mainmenu' => n4s_t( 'Hauptmenü', 'Main menu' ),
		'hmenu'    => n4s_t( 'Headermenü (n4s)', 'Header menu (n4s)' ),
		'title'    => n4s_t( 'Seitentitel', 'Page title' ),
		'fmenu'    => n4s_t( 'Footermenü (n4s)', 'Footer menu (n4s)' ),
		'footer'   => n4s_t( 'Fußzeile', 'Footer' ),
	);
}

function n4s_maint_defaults() {
	return array(
		'on'        => 0,            // Handschalter
		'since'     => 0,            // seit wann der Handschalter an ist
		'mode'      => 'wartung',    // aktueller Anlass
		'from'      => 0,            // Wartung ab (Unix-Zeit), 0 = kein Zeitplan
		'until'     => 0,            // Wartung bis, 0 = offen
		'maxh'      => 24,           // Höchstdauer in Stunden, 0 = ohne
		'notice'    => 60,           // Vorankündigung in Minuten vor „ab“, 0 = ohne
		'pages'     => array( 'wartung' => 0, 'update' => 0, 'bald' => 0 ),
		'roles'     => array( 'editor' ), // Administratoren haben immer Zugang
		'ips'       => '',
		'urls'      => '',
		'rest_apps' => 1,            // öffentliche App-Schnittstellen (n4s-apps/v1) bleiben erreichbar
		'hide'      => array( 'header', 'mainmenu', 'hmenu', 'fmenu', 'footer' ),
		'countdown' => 1,            // Countdown unter dem Inhalt der Wartungsseite
		'mail'      => 1,
		'mails'     => '',
		'links'     => array(),      // Freigabelinks: id, label, hash, exp, created, by
		'last'      => 0,            // zuletzt bekannter Zustand (für Mail und Protokoll bei Wechsel)
		'by'        => '',
	);
}

function n4s_maint_opt( $reset = false ) {
	static $o = null;
	if ( null === $o || $reset ) {
		$s = get_option( 'n4s_maint', array() );
		$s = is_array( $s ) ? $s : array();
		$d = n4s_maint_defaults();
		$o = array_merge( $d, array_intersect_key( $s, $d ) );
		foreach ( array( 'on', 'since', 'from', 'until', 'maxh', 'notice', 'rest_apps', 'countdown', 'mail', 'last' ) as $k ) {
			$o[ $k ] = (int) $o[ $k ];
		}
		$o['mode']  = isset( n4s_maint_modes()[ $o['mode'] ] ) ? $o['mode'] : 'wartung';
		$o['pages'] = array_merge( $d['pages'], is_array( $o['pages'] ) ? array_map( 'intval', $o['pages'] ) : array() );
		foreach ( array( 'roles', 'hide', 'links' ) as $k ) {
			$o[ $k ] = is_array( $o[ $k ] ) ? array_values( $o[ $k ] ) : $d[ $k ];
		}
		$o['hide'] = array_values( array_intersect( $o['hide'], array_keys( n4s_maint_hide_items() ) ) );
	}
	return $o;
}

function n4s_maint_save( array $o ) {
	update_option( 'n4s_maint', $o, true ); // wird bei jedem Aufruf gelesen
	n4s_maint_opt( true );
}

/* ------------------------------------------------------------------ Zustand */

/**
 * Zustand zum Zeitpunkt $now: active (Wartung läuft), scheduled (Zeitplan wartet), expire (''|'until'|'max': Handschalter oder
 * Zeitplan ist abgelaufen und wird zurückgesetzt), ends (voraussichtliches Ende, 0 = offen), start.
 */
function n4s_maint_compute( array $o, $now ) {
	$r = array( 'active' => false, 'scheduled' => false, 'expire' => '', 'ends' => 0, 'start' => 0, 'off' => false );
	if ( defined( 'N4S_MAINTENANCE_OFF' ) && N4S_MAINTENANCE_OFF ) {
		$r['off'] = true;
		return $r;
	}
	$act   = false;
	$start = 0;
	if ( $o['on'] ) {
		$act   = true;
		$start = $o['since'] ? $o['since'] : $now;
	}
	if ( $o['from'] && $now >= $o['from'] && ( ! $o['until'] || $now < $o['until'] ) ) {
		$act   = true;
		$start = $start ? min( $start, $o['from'] ) : $o['from'];
	}
	if ( $o['on'] && $o['until'] && $now >= $o['until'] ) {
		$r['expire'] = 'until';
		$act         = false;
	}
	if ( $act && $o['maxh'] > 0 && $start && ( $now - $start ) >= $o['maxh'] * 3600 ) {
		$r['expire'] = 'max';
		$act         = false;
	}
	$r['active'] = $act;
	$r['start']  = $start;
	if ( $act ) {
		$ends = array();
		if ( $o['until'] > $now ) {
			$ends[] = $o['until'];
		}
		if ( $o['maxh'] > 0 && $start ) {
			$ends[] = $start + $o['maxh'] * 3600;
		}
		$r['ends'] = $ends ? min( $ends ) : 0;
	} elseif ( ! $r['expire'] && $o['from'] && $now < $o['from'] ) {
		$r['scheduled'] = true;
	}
	return $r;
}

function n4s_maint_state() {
	return n4s_maint_compute( n4s_maint_opt(), time() );
}

/** Zeitpunkt in der Zeitzone der Website, zum Beispiel „27.11.2026 22:00“. */
function n4s_maint_fmt( $ts ) {
	return $ts ? wp_date( 'd.m.Y H:i', (int) $ts ) : '–';
}

/** Eingabe „YYYY-MM-DDTHH:MM“ (Zeitzone der Website) in Unix-Zeit, leer oder ungültig = 0. */
function n4s_maint_parse_local( $s ) {
	$s = trim( (string) $s );
	if ( '' === $s ) {
		return 0;
	}
	try {
		$d = new DateTimeImmutable( str_replace( 'T', ' ', $s ), wp_timezone() );
	} catch ( Exception $e ) {
		return 0;
	}
	return max( 0, $d->getTimestamp() );
}

/** Zustandswechsel erkennen: Mail und Eintrag bei Start, Ende und Auto-Ende; abgelaufene Zeiten zurücksetzen. */
function n4s_maint_tick() {
	$o   = n4s_maint_opt();
	$now = time();
	$c   = n4s_maint_compute( $o, $now );
	if ( $c['off'] ) {
		return;
	}
	$expire  = $c['expire'];
	$changed = false;
	if ( $expire ) {
		$o['on'] = 0; $o['since'] = 0; $o['from'] = 0; $o['until'] = 0;
		$changed = true;
		$c       = n4s_maint_compute( $o, $now );
	}
	$active = $c['active'] ? 1 : 0;
	if ( (int) $o['last'] === $active ) {
		if ( $changed ) {
			n4s_maint_save( $o );
		}
		return;
	}
	if ( ! add_option( 'n4s_maint_lock', $now, '', 'no' ) ) { // nur ein Aufruf meldet den Wechsel
		if ( (int) get_option( 'n4s_maint_lock', 0 ) < $now - 120 ) {
			delete_option( 'n4s_maint_lock' );
		}
		return;
	}
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'n4s_maint', 'options' );
	$fresh = n4s_maint_opt( true );
	if ( (int) $fresh['last'] === $active ) {
		delete_option( 'n4s_maint_lock' );
		return;
	}
	$o         = $changed ? $o : $fresh;
	$by        = $o['by'] ? $o['by'] : n4s_t( 'Zeitplan', 'Schedule' );
	$event     = $active ? 'start' : ( 'max' === $expire ? 'auto' : 'ende' );
	$o['last'] = $active;
	$o['by']   = '';
	if ( 'auto' === $event ) {
		$by = n4s_t( 'Höchstdauer', 'Maximum duration' );
	} elseif ( 'ende' === $event && 'until' === $expire ) {
		$by = n4s_t( 'Zeitplan', 'Schedule' );
	}
	n4s_maint_save( $o );
	delete_option( 'n4s_maint_lock' );
	n4s_maint_event( $event, $o, $by );
}
add_action( 'init', 'n4s_maint_tick', 1 );
add_action( 'n4s_maint_tick', 'n4s_maint_tick' );

/** Cron-Termine für Beginn, Ende und Höchstdauer setzen (die Prüfung bei jedem Aufruf bleibt maßgeblich). */
function n4s_maint_reschedule( array $o ) {
	wp_clear_scheduled_hook( 'n4s_maint_tick' );
	$now = time();
	$ts  = array();
	foreach ( array( $o['from'], $o['until'] ) as $t ) {
		$ts[] = (int) $t;
	}
	if ( $o['maxh'] > 0 ) {
		$ts[] = (int) ( $o['since'] ? $o['since'] : 0 ) + $o['maxh'] * 3600;
		$ts[] = (int) $o['from'] + $o['maxh'] * 3600;
	}
	foreach ( array_unique( $ts ) as $t ) {
		if ( $t > $now ) {
			wp_schedule_single_event( $t + 1, 'n4s_maint_tick' );
		}
	}
}

/** Handschalter. Gibt den neuen Zustand zurück. $by: Name für Mail und Protokoll. */
function n4s_maint_set_active( $on, $by ) {
	$o   = n4s_maint_opt( true );
	$now = time();
	$c   = n4s_maint_compute( $o, $now );
	if ( $on ) {
		if ( $o['until'] && $o['until'] <= $now ) { // alter Zeitplan, sonst endet der Handschalter sofort
			$o['from'] = 0; $o['until'] = 0;
		}
		$o['on']    = 1;
		$o['since'] = $now;
	} else {
		$o['on']    = 0;
		$o['since'] = 0;
		if ( $c['active'] ) { // läuft über den Zeitplan: beenden heißt Zeitplan löschen
			$o['from'] = 0; $o['until'] = 0;
		}
	}
	$o['by'] = (string) $by;
	n4s_maint_save( $o );
	n4s_maint_reschedule( $o );
	n4s_maint_tick();
	return n4s_maint_state();
}

/** Empfänger der Mails: eingetragene Adressen, sonst die Administrator-Adresse der Website. */
function n4s_maint_recipients( array $o ) {
	$out = array();
	foreach ( preg_split( '/[\s,;]+/', (string) $o['mails'] ) as $m ) {
		$m = sanitize_email( $m );
		if ( '' !== $m && is_email( $m ) ) {
			$out[] = $m;
		}
	}
	if ( ! $out && is_email( get_option( 'admin_email' ) ) ) {
		$out[] = get_option( 'admin_email' );
	}
	return array_values( array_unique( $out ) );
}

/** Mail und Eintrag in den Aktivitäten bei Start, Ende oder Auto-Ende. */
function n4s_maint_event( $event, array $o, $by ) {
	$modes = n4s_maint_modes();
	$mode  = $modes[ $o['mode'] ][0];
	$texts = array(
		'start' => array( n4s_t( 'Wartungsmodus eingeschaltet', 'Maintenance mode switched on' ), n4s_t( 'Der Wartungsmodus wurde eingeschaltet.', 'Maintenance mode was switched on.' ) ),
		'ende'  => array( n4s_t( 'Wartungsmodus beendet', 'Maintenance mode ended' ), n4s_t( 'Der Wartungsmodus wurde beendet.', 'Maintenance mode ended.' ) ),
		'auto'  => array( n4s_t( 'Wartungsmodus automatisch beendet (Höchstdauer)', 'Maintenance mode ended automatically (maximum duration)' ), n4s_t( 'Der Wartungsmodus wurde nach Ablauf der Höchstdauer automatisch beendet.', 'Maintenance mode was ended automatically after the maximum duration.' ) ),
	);
	if ( ! isset( $texts[ $event ] ) ) {
		return;
	}
	if ( function_exists( 'n4s_apps_activity' ) ) {
		n4s_apps_activity( $texts[ $event ][0] . ' – ' . $mode . ' (' . $by . ')', (string) $by );
	}
	if ( empty( $o['mail'] ) ) {
		return;
	}
	$to = n4s_maint_recipients( $o );
	if ( ! $to ) {
		return;
	}
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$body = $texts[ $event ][1] . "\n\n"
		. n4s_t( 'Website: ', 'Website: ' ) . home_url( '/' ) . "\n"
		. n4s_t( 'Anlass: ', 'Purpose: ' ) . $mode . "\n"
		. n4s_t( 'Durch: ', 'By: ' ) . $by . "\n"
		. n4s_t( 'Zeit: ', 'Time: ' ) . n4s_maint_fmt( time() ) . "\n";
	if ( 'start' === $event ) {
		$c = n4s_maint_compute( $o, time() );
		if ( $c['ends'] ) {
			$body .= n4s_t( 'Voraussichtliches Ende: ', 'Expected end: ' ) . n4s_maint_fmt( $c['ends'] ) . "\n";
		}
	}
	$body .= "\n" . n4s_t( 'Einstellungen: ', 'Settings: ' ) . admin_url( 'admin.php?page=n4s-maintenance' ) . "\n";
	wp_mail( $to, '[' . $site . '] ' . $texts[ $event ][0], $body );
}

/* ------------------------------------------------------------------ Ausnahmen */

/** Passt die IP zur Regel (einzelne Adresse oder Bereich wie 192.168.0.0/24, auch IPv6)? */
function n4s_maint_ip_match( $ip, $rule ) {
	$rule = trim( (string) $rule );
	$a    = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( '' === $rule || false === $a ) {
		return false;
	}
	if ( false === strpos( $rule, '/' ) ) {
		$b = @inet_pton( $rule ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false !== $b && $a === $b;
	}
	list( $net, $bits ) = explode( '/', $rule, 2 );
	$b                  = @inet_pton( trim( $net ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	$bits               = (int) $bits;
	if ( false === $b || strlen( $a ) !== strlen( $b ) || $bits < 0 || $bits > strlen( $a ) * 8 ) {
		return false;
	}
	$bytes = intdiv( $bits, 8 );
	if ( substr( $a, 0, $bytes ) !== substr( $b, 0, $bytes ) ) {
		return false;
	}
	$rem = $bits % 8;
	if ( $rem ) {
		$mask = ( 0xFF << ( 8 - $rem ) ) & 0xFF;
		return ( ord( $a[ $bytes ] ) & $mask ) === ( ord( $b[ $bytes ] ) & $mask );
	}
	return true;
}

function n4s_maint_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/** Pfad der Anfrage relativ zur Website, ohne Parameter, mit führendem „/“. */
function n4s_maint_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nur zum Vergleich.
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	return '/' . ltrim( $path, '/' );
}

/** URL-Ausnahme: ein Pfad je Zeile; mit „*“ am Ende als Anfang, sonst genau (Schrägstrich am Ende egal). */
function n4s_maint_url_ok( array $o ) {
	$path = n4s_maint_request_path();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $o['urls'] ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$line = '/' . ltrim( $line, '/' );
		if ( '*' === substr( $line, -1 ) ) {
			if ( 0 === strpos( $path, substr( $line, 0, -1 ) ) ) {
				return true;
			}
		} elseif ( untrailingslashit( $path ) === untrailingslashit( $line ) ) {
			return true;
		}
	}
	return false;
}

function n4s_maint_ip_ok( array $o ) {
	$ip = n4s_maint_client_ip();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $o['ips'] ) as $line ) {
		if ( n4s_maint_ip_match( $ip, $line ) ) {
			return true;
		}
	}
	return false;
}

function n4s_maint_user_ok( array $o ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true; // Administratoren sperren sich nie aus
	}
	return (bool) array_intersect( (array) wp_get_current_user()->roles, (array) $o['roles'] );
}

/** Freigabelink: gültig, wenn das Cookie zu einem nicht abgelaufenen Link gehört. */
function n4s_maint_pass_ok( array $o ) {
	$t = isset( $_COOKIE['n4s_maint_pass'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['n4s_maint_pass'] ) ) : '';
	return '' !== $t && null !== n4s_maint_link_find( $o, $t );
}

function n4s_maint_link_find( array $o, $token ) {
	if ( ! preg_match( '/^[0-9a-f]{32}$/', (string) $token ) ) {
		return null;
	}
	$h = hash( 'sha256', $token );
	foreach ( $o['links'] as $l ) {
		if ( isset( $l['hash'], $l['exp'] ) && (int) $l['exp'] > time() && hash_equals( (string) $l['hash'], $h ) ) {
			return $l;
		}
	}
	return null;
}

/** Wer die Wartung umgeht: Rollen, Freigabelink, IP, URL. */
function n4s_maint_bypass( array $o ) {
	return n4s_maint_user_ok( $o ) || n4s_maint_pass_ok( $o ) || n4s_maint_ip_ok( $o ) || n4s_maint_url_ok( $o );
}

/* Freigabelink einlösen: Cookie setzen und die Adresse ohne Parameter öffnen. */
add_action(
	'init',
	function () {
		if ( empty( $_GET['n4s-freigabe'] ) || is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification -- geheimer Schlüssel im Link.
			return;
		}
		$t = sanitize_text_field( wp_unslash( $_GET['n4s-freigabe'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$l = n4s_maint_link_find( n4s_maint_opt(), $t );
		if ( $l ) {
			setcookie( 'n4s_maint_pass', $t, (int) $l['exp'], COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}
		wp_safe_redirect( remove_query_arg( 'n4s-freigabe' ) );
		exit;
	},
	2
);

/* ------------------------------------------------------------------ Ausspielen */

/** Fest eingebaute Seite, wenn keine Wartungsseite vorhanden ist. */
function n4s_maint_fallback( $mode, $status ) {
	$modes = n4s_maint_modes();
	$title = $modes[ $mode ][0];
	$texts = array(
		'wartung' => n4s_t( 'Wir führen gerade Wartungsarbeiten durch. Bitte versuchen Sie es in Kürze noch einmal.', 'We are currently performing maintenance. Please try again shortly.' ),
		'update'  => n4s_t( 'Wir spielen gerade ein Update ein. Die Website ist gleich wieder erreichbar.', 'We are installing an update. The website will be back in a moment.' ),
		'bald'    => n4s_t( 'Diese Website ist bald verfügbar.', 'This website will be available soon.' ),
	);
	status_header( $status );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html( $title . ' – ' . get_bloginfo( 'name' ) ) . '</title><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f4f5f7;color:#1d2433;font:16px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;text-align:center;padding:24px}main{max-width:560px}h1{font-size:1.8rem;margin:0 0 12px}p{margin:0;color:#4b5565}</style></head><body><main><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $texts[ $mode ] ) . '</p>' . do_shortcode( '[n4s_wartung_countdown]' ) . '</main></body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

/** Seite als Hauptabfrage setzen: das Theme der Website zeigt sie mit der Seitenvorlage der Seite (auch Entwürfe). */
function n4s_maint_serve_page( $mode, $status, array $o, $c ) {
	$id   = (int) $o['pages'][ $mode ];
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
		n4s_maint_fallback( $mode, $status );
	}
	global $wp_query, $wp_the_query;
	$wp_query->init();
	$wp_query->posts             = array( $post );
	$wp_query->post              = $post;
	$wp_query->post_count        = 1;
	$wp_query->found_posts       = 1;
	$wp_query->max_num_pages     = 1;
	$wp_query->queried_object    = $post;
	$wp_query->queried_object_id = $post->ID;
	$wp_query->is_page           = true;
	$wp_query->is_singular       = true;
	$wp_the_query                = $wp_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$GLOBALS['post']             = $post;
	setup_postdata( $post );
	$GLOBALS['n4s_maint_serving'] = array( 'mode' => $mode, 'hide' => $o['hide'], 'countdown' => (int) $o['countdown'] );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	remove_action( 'template_redirect', 'redirect_canonical' ); // keine Weiterleitung auf die Adresse der Wartungsseite
	remove_action( 'template_redirect', 'wp_old_slug_redirect' );
	status_header( $status );
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );
	if ( 503 === $status ) {
		$retry = ( ! empty( $c['ends'] ) && $c['ends'] > time() ) ? $c['ends'] - time() : 3600;
		header( 'Retry-After: ' . (int) $retry );
	}
}

add_action(
	'template_redirect',
	function () {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_robots() || is_favicon() ) {
			return;
		}
		$o     = n4s_maint_opt();
		$modes = n4s_maint_modes();
		// Vorschau für Administratoren (mit Nonce), auch bei ausgeschaltetem Modus.
		if ( isset( $_GET['n4s-wartung-vorschau'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$m = sanitize_key( wp_unslash( $_GET['n4s-wartung-vorschau'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $modes[ $m ] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'n4s_maint_preview' ) ) {
				n4s_maint_serve_page( $m, 200, $o, n4s_maint_state() );
				return;
			}
		}
		$c = n4s_maint_compute( $o, time() );
		if ( $c['active'] && ! n4s_maint_bypass( $o ) ) {
			n4s_maint_serve_page( $o['mode'], $modes[ $o['mode'] ][1], $o, $c );
			return;
		}
		// Normalbetrieb: die Wartungsseiten selbst sind von außen nicht erreichbar.
		if ( is_singular( 'page' ) && in_array( get_queried_object_id(), array_filter( $o['pages'] ), true ) && ! current_user_can( 'edit_page', get_queried_object_id() ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	},
	0
);

/** Schnittstellen: während der Wartung gesperrt, außer für Berechtigte, Ausnahmen und (einstellbar) die App-Schnittstellen. */
add_filter(
	'rest_authentication_errors',
	function ( $r ) {
		if ( ! empty( $r ) ) {
			return $r;
		}
		$o = n4s_maint_opt();
		$c = n4s_maint_compute( $o, time() );
		if ( ! $c['active'] || n4s_maint_bypass( $o ) ) {
			return $r;
		}
		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
		if ( '' === $route ) {
			$path  = n4s_maint_request_path();
			$pos   = strpos( $path, '/' . rest_get_url_prefix() . '/' );
			$route = false === $pos ? '' : substr( $path, $pos + strlen( rest_get_url_prefix() ) + 1 );
		}
		if ( $o['rest_apps'] && 0 === strpos( '/' . ltrim( $route, '/' ), '/n4s-apps/v1' ) ) {
			return $r;
		}
		return new WP_Error( 'n4s_maintenance', n4s_t( 'Die Website ist gerade in Wartung.', 'The website is currently under maintenance.' ), array( 'status' => 503 ) );
	},
	5
);

/* Anzeige auf der Wartungsseite: ausgeblendete Bereiche, Countdown, noindex. */
add_filter(
	'body_class',
	function ( $c ) {
		if ( ! empty( $GLOBALS['n4s_maint_serving'] ) ) {
			$c[] = 'n4s-maint';
			foreach ( $GLOBALS['n4s_maint_serving']['hide'] as $w ) {
				$c[] = 'n4s-no-' . $w;
			}
		}
		return array_values( array_unique( $c ) );
	},
	20
);

/* Die n4s-Leisten (Headermenü, Footermenü) und die Seitengestaltung der Theme-Funktion folgen der Auswahl. */
add_filter(
	'n4s_theme_hidden',
	function ( $h, $what ) {
		if ( ! empty( $GLOBALS['n4s_maint_serving'] ) && in_array( $what, $GLOBALS['n4s_maint_serving']['hide'], true ) ) {
			return true;
		}
		return $h;
	},
	10,
	2
);

add_action(
	'wp_head',
	function () {
		if ( empty( $GLOBALS['n4s_maint_serving'] ) ) {
			return;
		}
		$sel = array(
			'header'   => 'body.n4s-no-header header.header,body.n4s-no-header #masthead,body.n4s-no-header .site-header,body.n4s-no-header header[role="banner"],body.n4s-no-header #main-header,body.n4s-no-header #top-header,body.n4s-no-header .et-l--header,body.n4s-no-header .elementor-location-header',
			'footer'   => 'body.n4s-no-footer footer.site-footer,body.n4s-no-footer #colophon,body.n4s-no-footer .site-footer,body.n4s-no-footer footer[role="contentinfo"],body.n4s-no-footer #main-footer,body.n4s-no-footer .et-l--footer,body.n4s-no-footer .elementor-location-footer',
			'mainmenu' => 'body.n4s-no-mainmenu .menu-mobile-toggle,body.n4s-no-mainmenu header .wp-block-navigation,body.n4s-no-mainmenu .nv-navbar,body.n4s-no-mainmenu #nv-primary-navigation-main,body.n4s-no-mainmenu #top-menu-nav,body.n4s-no-mainmenu .et_mobile_nav_menu,body.n4s-no-mainmenu .main-navigation',
			'title'    => 'body.n4s-no-title .nv-page-title-wrap,body.n4s-no-title .page-header,body.n4s-no-title .entry-header .entry-title,body.n4s-no-title h1.page-title,body.n4s-no-title .entry-title,body.n4s-no-title .wp-block-post-title,body.n4s-no-title .post-title',
			'hmenu'    => 'body.n4s-no-hmenu .n4s-bar-header',
			'fmenu'    => 'body.n4s-no-fmenu .n4s-bar-footer',
		);
		$css = '';
		foreach ( $GLOBALS['n4s_maint_serving']['hide'] as $w ) {
			if ( isset( $sel[ $w ] ) ) {
				$css .= $sel[ $w ] . '{display:none!important}';
			}
		}
		if ( '' !== $css ) {
			echo '<style id="n4s-maint-css">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- feste Selektoren.
		}
	},
	99
);

add_filter(
	'wp_robots',
	function ( $r ) {
		$o = n4s_maint_opt();
		if ( ! empty( $GLOBALS['n4s_maint_serving'] ) || ( is_singular( 'page' ) && in_array( get_queried_object_id(), array_filter( $o['pages'] ), true ) ) ) {
			$r['noindex']  = true;
			$r['nofollow'] = true;
		}
		return $r;
	}
);

/* Wartungsseiten bleiben aus Sitemaps und Seitenlisten heraus. */
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args, $type ) {
		if ( 'page' === $type ) {
			$ids = array_filter( n4s_maint_opt()['pages'] );
			if ( $ids ) {
				$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $ids );
			}
		}
		return $args;
	},
	10,
	2
);
add_filter(
	'get_pages',
	function ( $pages ) {
		$ids = array_filter( n4s_maint_opt()['pages'] );
		if ( ! $ids || ! is_array( $pages ) || is_admin() ) {
			return $pages;
		}
		return array_values(
			array_filter(
				$pages,
				function ( $p ) use ( $ids ) {
					return ! in_array( (int) $p->ID, $ids, true );
				}
			)
		);
	}
);

/* Kennzeichnung in der Seitenliste. */
add_filter(
	'display_post_states',
	function ( $states, $post ) {
		$modes = n4s_maint_modes();
		foreach ( n4s_maint_opt()['pages'] as $m => $id ) {
			if ( (int) $id === (int) $post->ID && isset( $modes[ $m ] ) ) {
				$states['n4s_maint'] = n4s_t( 'Wartungsseite', 'Maintenance page' ) . ': ' . $modes[ $m ][0];
			}
		}
		return $states;
	},
	10,
	2
);

/* ------------------------------------------------------------------ Countdown und Vorankündigung */

/** Texte des Countdowns je Anlass: [ vor Beginn, während der Wartung, Ziel erreicht ]. */
function n4s_maint_cd_texts( $mode ) {
	$t = array(
		'wartung' => array( n4s_t( 'Wartung beginnt in', 'Maintenance starts in' ), n4s_t( 'Wir sind voraussichtlich zurück in', 'We expect to be back in' ), n4s_t( 'Gleich wieder da …', 'Back in a moment …' ) ),
		'update'  => array( n4s_t( 'Das Update startet in', 'The update starts in' ), n4s_t( 'Das Update ist voraussichtlich fertig in', 'The update should be finished in' ), n4s_t( 'Gleich fertig …', 'Almost done …' ) ),
		'bald'    => array( n4s_t( 'Die Seite ist vorübergehend nicht erreichbar in', 'The site becomes unavailable in' ), n4s_t( 'Wir starten in', 'We launch in' ), n4s_t( 'Es geht gleich los …', 'Starting any moment …' ) ),
	);
	return isset( $t[ $mode ] ) ? $t[ $mode ] : $t['wartung'];
}

/** Countdown: [n4s_wartung_countdown ziel="auto|bis|ab"]. Ohne Ziel bleibt die Ausgabe leer. */
function n4s_maint_countdown_html( $ziel = 'auto' ) {
	$o   = n4s_maint_opt();
	$now = time();
	$c   = n4s_maint_compute( $o, $now );
	$ts  = 0;
	$lab = '';
	$txt = n4s_maint_cd_texts( ! empty( $GLOBALS['n4s_maint_serving']['mode'] ) ? $GLOBALS['n4s_maint_serving']['mode'] : $o['mode'] );
	if ( 'ab' === $ziel || ( 'auto' === $ziel && $c['scheduled'] ) ) {
		$ts  = $o['from'] > $now ? $o['from'] : 0;
		$lab = $txt[0];
	}
	if ( ! $ts && ( 'bis' === $ziel || 'auto' === $ziel ) && $c['active'] && $c['ends'] > $now ) {
		$ts  = $c['ends'];
		$lab = $txt[1];
	}
	if ( ! $ts ) {
		return '';
	}
	static $js = false;
	$out = '<div class="n4s-maint-cd" style="text-align:center;margin:24px 0" data-ts="' . (int) $ts . '" data-now="' . (int) $now . '" data-d="' . esc_attr( n4s_t( 'Tage', 'days' ) ) . '" data-done="' . esc_attr( $txt[2] ) . '"><div style="font-size:.95rem;opacity:.8">' . esc_html( $lab ) . '</div><div class="n4s-maint-cd-v" style="font-size:2.2rem;font-weight:700;font-variant-numeric:tabular-nums;letter-spacing:.02em">–</div></div>';
	if ( ! $js ) {
		$js   = true;
		$out .= '<script>(function(){var off=0;function p(n){return(n<10?"0":"")+n}function t(){document.querySelectorAll(".n4s-maint-cd").forEach(function(e){var l=Number(e.dataset.ts)-(Date.now()/1000+off);var v=e.querySelector(".n4s-maint-cd-v");if(l<=0){v.textContent=e.dataset.done;return}var d=Math.floor(l/86400),h=Math.floor(l%86400/3600),m=Math.floor(l%3600/60),s=Math.floor(l%60);v.textContent=(d?d+" "+e.dataset.d+" ":"")+p(h)+":"+p(m)+":"+p(s)})}var f=document.querySelector(".n4s-maint-cd");if(f)off=Number(f.dataset.now)-Date.now()/1000;t();setInterval(t,1000)})();</script>';
	}
	return $out;
}
add_shortcode(
	'n4s_wartung_countdown',
	function ( $a ) {
		$a = shortcode_atts( array( 'ziel' => 'auto' ), $a, 'n4s_wartung_countdown' );
		return n4s_maint_countdown_html( in_array( $a['ziel'], array( 'bis', 'ab' ), true ) ? $a['ziel'] : 'auto' );
	}
);

/* Countdown automatisch unter dem Inhalt der Wartungsseite (Option). */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! empty( $GLOBALS['n4s_maint_serving']['countdown'] ) && in_the_loop() && is_main_query() && false === strpos( $content, 'n4s-maint-cd' ) ) {
			return $content . n4s_maint_countdown_html( 'bis' );
		}
		return $content;
	},
	20
);

/** Vorankündigung: Banner für Besucher, wenn der Beginn nahe ist. */
add_action(
	'wp_footer',
	function () {
		$o = n4s_maint_opt();
		if ( ! empty( $GLOBALS['n4s_maint_serving'] ) || $o['notice'] <= 0 || ! $o['from'] ) {
			return;
		}
		$c   = n4s_maint_compute( $o, time() );
		$in  = $o['from'] - time();
		if ( ! $c['scheduled'] || $in > $o['notice'] * 60 ) {
			return;
		}
		$when = wp_date( wp_date( 'Ymd', $o['from'] ) === wp_date( 'Ymd' ) ? 'H:i' : 'd.m. H:i', $o['from'] );
		$txt  = sprintf( n4s_t( 'Wartungsarbeiten ab %s Uhr', 'Maintenance from %s' ), $when );
		if ( $o['until'] ) {
			$txt .= ' ' . sprintf( n4s_t( 'bis ca. %s Uhr', 'until about %s' ), wp_date( wp_date( 'Ymd', $o['until'] ) === wp_date( 'Ymd', $o['from'] ) ? 'H:i' : 'd.m. H:i', $o['until'] ) );
		}
		$txt .= '. ' . n4s_t( 'Die Website ist dann kurz nicht erreichbar.', 'The website will be briefly unavailable.' );
		echo '<div class="n4s-maint-notice" role="status" style="position:fixed;left:0;right:0;bottom:0;z-index:99999;background:#1d2433;color:#fff;padding:10px 16px;text-align:center;font:14px/1.4 system-ui,sans-serif">' . esc_html( $txt ) . '</div>';
	},
	50
);

/* ------------------------------------------------------------------ Admin-Leiste */

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o     = n4s_maint_opt();
		$c     = n4s_maint_compute( $o, time() );
		$modes = n4s_maint_modes();
		if ( $c['active'] ) {
			$state = n4s_t( 'an', 'on' );
			$cls   = 'n4s-maint-on';
		} elseif ( $c['scheduled'] ) {
			$state = n4s_t( 'geplant', 'scheduled' );
			$cls   = 'n4s-maint-sched';
		} else {
			$state = n4s_t( 'aus', 'off' );
			$cls   = 'n4s-maint-off';
		}
		$toggle = wp_nonce_url( admin_url( 'admin-post.php?action=n4s_maint_toggle' ), 'n4s_maint_toggle' );
		$bar->add_node(
			array(
				'id'    => 'n4s-maint',
				'title' => '<span class="n4s-maint-dot"></span> ' . esc_html( n4s_t( 'Wartung', 'Maintenance' ) . ': ' . $state ),
				'href'  => $toggle,
				'meta'  => array( 'class' => $cls, 'title' => $c['active'] ? n4s_t( 'Klicken zum Beenden der Wartung', 'Click to end maintenance' ) : n4s_t( 'Klicken zum Einschalten der Wartung', 'Click to switch maintenance on' ) ),
			)
		);
		$page = (int) $o['pages'][ $o['mode'] ];
		if ( $page && get_post( $page ) ) {
			$bar->add_node( array( 'id' => 'n4s-maint-edit', 'parent' => 'n4s-maint', 'title' => esc_html( sprintf( n4s_t( 'Wartungsseite bearbeiten (%s)', 'Edit maintenance page (%s)' ), $modes[ $o['mode'] ][0] ) ), 'href' => get_edit_post_link( $page, 'raw' ) ) );
		}
		$bar->add_node( array( 'id' => 'n4s-maint-preview', 'parent' => 'n4s-maint', 'title' => esc_html( n4s_t( 'Vorschau', 'Preview' ) ), 'href' => wp_nonce_url( add_query_arg( 'n4s-wartung-vorschau', $o['mode'], home_url( '/' ) ), 'n4s_maint_preview' ), 'meta' => array( 'target' => '_blank' ) ) );
		if ( $c['active'] && $o['until'] ) {
			$bar->add_node( array( 'id' => 'n4s-maint-extend', 'parent' => 'n4s-maint', 'title' => esc_html( n4s_t( 'Verlängern um 1 Stunde', 'Extend by 1 hour' ) ), 'href' => wp_nonce_url( admin_url( 'admin-post.php?action=n4s_maint_extend' ), 'n4s_maint_extend' ) ) );
		}
		$bar->add_node( array( 'id' => 'n4s-maint-settings', 'parent' => 'n4s-maint', 'title' => esc_html( n4s_t( 'Einstellungen', 'Settings' ) ), 'href' => admin_url( 'admin.php?page=n4s-maintenance' ) ) );
	},
	101
);

add_action(
	'wp_head',
	function () {
		if ( is_admin_bar_showing() ) {
			echo '<style>' . n4s_maint_bar_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes CSS.
		}
	},
	100
);
add_action(
	'admin_head',
	function () {
		echo '<style>' . n4s_maint_bar_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- festes CSS.
	},
	100
);

function n4s_maint_bar_css() {
	return '#wpadminbar #wp-admin-bar-n4s-maint .n4s-maint-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:#8c8f94;margin-right:2px}'
		. '#wpadminbar #wp-admin-bar-n4s-maint.n4s-maint-on>.ab-item{background:#b32d2e;color:#fff}#wpadminbar #wp-admin-bar-n4s-maint.n4s-maint-on .n4s-maint-dot{background:#fff}'
		. '#wpadminbar #wp-admin-bar-n4s-maint.n4s-maint-sched .n4s-maint-dot{background:#dba617}';
}

/* ------------------------------------------------------------------ Verwaltung (nur Admin-Bereich) */

if ( is_admin() ) {
	add_filter(
		'n4s_apps_tabs',
		function ( $t ) {
			$t['maintenance'] = array( 'page' => 'n4s-maintenance', 'label' => n4s_t( 'Wartung', 'Maintenance' ), 'render' => 'n4s_maint_render' );
			return $t;
		}
	);

	/** Schalter in der Admin-Leiste. */
	add_action(
		'admin_post_n4s_maint_toggle',
		function () {
			if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'n4s_maint_toggle' ) ) {
				wp_die( esc_html( n4s_t( 'Keine Berechtigung.', 'Permission denied.' ) ), 403 );
			}
			$c = n4s_maint_state();
			n4s_maint_set_active( ! $c['active'], wp_get_current_user()->user_login );
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=n4s-maintenance' ) );
			exit;
		}
	);

	add_action(
		'admin_post_n4s_maint_extend',
		function () {
			if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'n4s_maint_extend' ) ) {
				wp_die( esc_html( n4s_t( 'Keine Berechtigung.', 'Permission denied.' ) ), 403 );
			}
			$o = n4s_maint_opt( true );
			if ( $o['until'] ) {
				$o['until'] = max( $o['until'], time() ) + 3600;
				n4s_maint_save( $o );
				n4s_maint_reschedule( $o );
				if ( function_exists( 'n4s_apps_activity' ) ) {
					n4s_apps_activity( n4s_t( 'Wartung verlängert bis ', 'Maintenance extended until ' ) . n4s_maint_fmt( $o['until'] ) );
				}
			}
			wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=n4s-maintenance' ) );
			exit;
		}
	);

	/** Wartungsseite anlegen (Entwurf mit Mustertext). */
	add_action(
		'admin_post_n4s_maint_newpage',
		function () {
			if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'n4s_maint_newpage' ) ) {
				wp_die( esc_html( n4s_t( 'Keine Berechtigung.', 'Permission denied.' ) ), 403 );
			}
			$m     = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
			$modes = n4s_maint_modes();
			if ( ! isset( $modes[ $m ] ) ) {
				wp_die( esc_html( n4s_t( 'Unbekannter Anlass.', 'Unknown purpose.' ) ), 400 );
			}
			$texts = array(
				'wartung' => array( n4s_t( 'Wir sind gleich wieder da', 'We will be right back' ), n4s_t( 'Wir führen gerade Wartungsarbeiten durch. Bitte versuchen Sie es in Kürze noch einmal.', 'We are currently performing maintenance. Please try again shortly.' ) ),
				'update'  => array( n4s_t( 'Update läuft', 'Update in progress' ), n4s_t( 'Wir spielen gerade ein Update ein. Die Website ist gleich wieder erreichbar.', 'We are installing an update. The website will be back in a moment.' ) ),
				'bald'    => array( n4s_t( 'Bald verfügbar', 'Coming soon' ), n4s_t( 'Diese Website ist bald verfügbar. Schauen Sie bald wieder vorbei.', 'This website will be available soon. Please check back shortly.' ) ),
			);
			$id    = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => $modes[ $m ][0] . ' – ' . n4s_t( 'Wartungsseite', 'Maintenance page' ),
					'post_content' => '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">' . esc_html( $texts[ $m ][0] ) . '</h2><!-- /wp:heading --><!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">' . esc_html( $texts[ $m ][1] ) . '</p><!-- /wp:paragraph -->',
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				wp_die( esc_html( $id->get_error_message() ) );
			}
			$o                   = n4s_maint_opt( true );
			$o['pages'][ $m ]    = (int) $id;
			n4s_maint_save( $o );
			if ( function_exists( 'n4s_apps_activity' ) ) {
				n4s_apps_activity( n4s_t( 'Wartungsseite angelegt: ', 'Maintenance page created: ' ) . $modes[ $m ][0] );
			}
			wp_safe_redirect( get_edit_post_link( $id, 'raw' ) );
			exit;
		}
	);

	/** Einstellungen speichern. */
	add_action(
		'admin_post_n4s_maint_save',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			n4s_apps_verify( 'n4s_maint_save' );
			$o     = n4s_maint_opt( true );
			$modes = n4s_maint_modes();
			$mode  = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : $o['mode']; // phpcs:ignore WordPress.Security.NonceVerification -- siehe n4s_apps_verify.
			$o['mode'] = isset( $modes[ $mode ] ) ? $mode : 'wartung';
			foreach ( array( 'from', 'until' ) as $k ) {
				$o[ $k ] = n4s_maint_parse_local( isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			if ( $o['from'] && $o['until'] && $o['until'] <= $o['from'] ) {
				$o['until'] = 0;
			}
			$o['maxh']   = max( 0, min( 720, isset( $_POST['maxh'] ) ? (int) $_POST['maxh'] : 24 ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$o['notice'] = max( 0, min( 1440, isset( $_POST['notice'] ) ? (int) $_POST['notice'] : 60 ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$valid_roles = array_diff( array_keys( wp_roles()->get_names() ), array( 'administrator' ) );
			$o['roles']  = array_values( array_intersect( isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['roles'] ) ) : array(), $valid_roles ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$o['hide']   = array_values( array_intersect( isset( $_POST['hide'] ) && is_array( $_POST['hide'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['hide'] ) ) : array(), array_keys( n4s_maint_hide_items() ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$clean       = function ( $key ) {
				$lines = array();
				foreach ( preg_split( '/\r\n|\r|\n/', isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : '' ) as $l ) { // phpcs:ignore WordPress.Security
					$l = trim( preg_replace( '/[^0-9a-zA-Z:.\/_*\-%]/', '', $l ) );
					if ( '' !== $l ) {
						$lines[] = $l;
					}
				}
				return implode( "\n", array_slice( $lines, 0, 200 ) );
			};
			$o['ips']       = $clean( 'ips' );
			$o['urls']      = $clean( 'urls' );
			$o['rest_apps'] = empty( $_POST['rest_apps'] ) ? 0 : 1; // phpcs:ignore WordPress.Security.NonceVerification
			$o['countdown'] = empty( $_POST['countdown'] ) ? 0 : 1; // phpcs:ignore WordPress.Security.NonceVerification
			$o['mail']      = empty( $_POST['mail'] ) ? 0 : 1; // phpcs:ignore WordPress.Security.NonceVerification
			$mails          = array();
			foreach ( preg_split( '/[\s,;]+/', isset( $_POST['mails'] ) ? (string) wp_unslash( $_POST['mails'] ) : '' ) as $m ) { // phpcs:ignore WordPress.Security
				$m = sanitize_email( $m );
				if ( '' !== $m && is_email( $m ) ) {
					$mails[] = $m;
				}
			}
			$o['mails'] = implode( ', ', array_slice( array_unique( $mails ), 0, 10 ) );
			foreach ( array_keys( $modes ) as $m ) { // gewählte Seiten (nur Seiten)
				$pid = isset( $_POST['page_id'][ $m ] ) ? (int) $_POST['page_id'][ $m ] : 0; // phpcs:ignore WordPress.Security.NonceVerification
				$o['pages'][ $m ] = ( $pid && 'page' === get_post_type( $pid ) ) ? $pid : 0;
			}
			n4s_maint_save( $o );
			n4s_maint_reschedule( $o );
			n4s_maint_tick();
			n4s_apps_notice( 'success', n4s_t( 'Wartungseinstellungen gespeichert.', 'Maintenance settings saved.' ), false );
			n4s_apps_back( false, 'n4s-maintenance' );
		}
	);

	/** Freigabelink anlegen. */
	add_action(
		'admin_post_n4s_maint_link',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			n4s_apps_verify( 'n4s_maint_link' );
			$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$days  = isset( $_POST['days'] ) ? (int) $_POST['days'] : 7; // phpcs:ignore WordPress.Security.NonceVerification
			$days  = in_array( $days, array( 1, 3, 7, 14, 30 ), true ) ? $days : 7;
			$token = bin2hex( random_bytes( 16 ) );
			$o     = n4s_maint_opt( true );
			$o['links'] = array_values( array_filter( $o['links'], function ( $l ) { return (int) $l['exp'] > time(); } ) );
			$o['links'][] = array(
				'id'      => substr( $token, 0, 8 ),
				'label'   => '' !== $label ? mb_substr( $label, 0, 60 ) : n4s_t( 'Freigabelink', 'Access link' ),
				'hash'    => hash( 'sha256', $token ),
				'exp'     => time() + $days * DAY_IN_SECONDS,
				'created' => time(),
				'by'      => wp_get_current_user()->user_login,
			);
			n4s_maint_save( $o );
			set_transient( 'n4s_maint_newlink_' . get_current_user_id(), add_query_arg( 'n4s-freigabe', $token, home_url( '/' ) ), 600 );
			if ( function_exists( 'n4s_apps_activity' ) ) {
				n4s_apps_activity( n4s_t( 'Freigabelink erstellt: ', 'Access link created: ' ) . ( '' !== $label ? $label : '–' ) . ' (' . $days . ' ' . n4s_t( 'Tage', 'days' ) . ')' );
			}
			n4s_apps_back( false, 'n4s-maintenance' );
		}
	);

	add_action(
		'admin_post_n4s_maint_revoke',
		function () {
			if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'n4s_maint_revoke' ) ) {
				wp_die( esc_html( n4s_t( 'Keine Berechtigung.', 'Permission denied.' ) ), 403 );
			}
			$id = isset( $_GET['id'] ) ? sanitize_key( wp_unslash( $_GET['id'] ) ) : '';
			$o  = n4s_maint_opt( true );
			$o['links'] = array_values( array_filter( $o['links'], function ( $l ) use ( $id ) { return $l['id'] !== $id; } ) );
			n4s_maint_save( $o );
			if ( function_exists( 'n4s_apps_activity' ) ) {
				n4s_apps_activity( n4s_t( 'Freigabelink widerrufen', 'Access link revoked' ) );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=n4s-maintenance' ) );
			exit;
		}
	);

	/** Tab „Wartung“. */
	function n4s_maint_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o     = n4s_maint_opt( true );
		$c     = n4s_maint_compute( $o, time() );
		$modes = n4s_maint_modes();
		$lf    = function ( $ts ) {
			return $ts ? wp_date( 'Y-m-d\TH:i', (int) $ts ) : '';
		};
		echo '<h2>' . esc_html( n4s_t( 'Wartung', 'Maintenance' ) ) . '</h2>';
		if ( $c['off'] ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( n4s_t( 'Der Notausgang N4S_MAINTENANCE_OFF ist in der wp-config.php gesetzt: Der Wartungsmodus ist abgeschaltet.', 'The emergency switch N4S_MAINTENANCE_OFF is set in wp-config.php: maintenance mode is disabled.' ) ) . '</p></div>';
		}
		// Status
		$state = $c['active'] ? n4s_t( 'Wartung läuft', 'Maintenance is on' ) : ( $c['scheduled'] ? n4s_t( 'Wartung geplant', 'Maintenance scheduled' ) : n4s_t( 'Wartung aus', 'Maintenance is off' ) );
		$info  = array();
		if ( $c['active'] ) {
			$info[] = n4s_t( 'Anlass: ', 'Purpose: ' ) . $modes[ $o['mode'] ][0];
			$info[] = $c['ends'] ? n4s_t( 'Voraussichtliches Ende: ', 'Expected end: ' ) . n4s_maint_fmt( $c['ends'] ) : n4s_t( 'Ende offen (Höchstdauer aus)', 'End open (no maximum duration)' );
		} elseif ( $c['scheduled'] ) {
			$info[] = n4s_t( 'Beginn: ', 'Start: ' ) . n4s_maint_fmt( $o['from'] );
		}
		echo '<div class="n4s-card"><p style="margin:0 0 6px;font-size:1.15rem"><b>' . esc_html( $state ) . '</b></p>';
		if ( $info ) {
			echo '<p style="margin:0 0 10px" class="description">' . esc_html( implode( ' · ', $info ) ) . '</p>';
		}
		echo '<p style="margin:0"><a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=n4s_maint_toggle' ), 'n4s_maint_toggle' ) ) . '">' . esc_html( $c['active'] ? n4s_t( 'Wartung beenden', 'End maintenance' ) : n4s_t( 'Wartung jetzt einschalten', 'Switch maintenance on now' ) ) . '</a> ';
		echo '<span class="description">' . esc_html( n4s_t( 'Auch über den Eintrag „Wartung“ in der Admin-Leiste oben.', 'Also via the "Maintenance" entry in the admin bar at the top.' ) ) . '</span></p></div>';

		n4s_apps_form_head( 'n4s_maint_save', false, 'display:block' );
		// Anlass und Seiten
		echo '<h3>' . esc_html( n4s_t( 'Anlass und Wartungsseiten', 'Purpose and maintenance pages' ) ) . '</h3><div class="n4s-card"><p class="description" style="margin-top:0">' . esc_html( n4s_t( 'Jeder Anlass hat eine eigene Seite, die im Seitenbaukasten gepflegt wird. Gezeigt wird die Seite des gewählten Anlasses. „Bald verfügbar“ antwortet mit Status 200, die anderen mit 503 (Suchmaschinen behalten die Seiten).', 'Each purpose has its own page, edited in the page builder. The page of the selected purpose is shown. "Coming soon" answers with status 200, the others with 503 (search engines keep the pages).' ) ) . '</p>';
		$pages = get_pages( array( 'post_status' => 'publish,draft,private,pending,future', 'sort_column' => 'post_title' ) );
		foreach ( $modes as $m => $mm ) {
			$pid  = (int) $o['pages'][ $m ];
			$ctl  = '<select name="page_id[' . esc_attr( $m ) . ']" style="max-width:320px"><option value="0">' . esc_html( n4s_t( '– keine (eingebaute Seite) –', '– none (built-in page) –' ) ) . '</option>';
			foreach ( $pages as $p ) {
				$ctl .= '<option value="' . (int) $p->ID . '"' . selected( $pid, (int) $p->ID, false ) . '>' . esc_html( $p->post_title . ( 'publish' === $p->post_status ? '' : ' (' . get_post_status_object( $p->post_status )->label . ')' ) ) . '</option>';
			}
			$ctl  .= '</select> ';
			$links = array();
			if ( $pid && get_post( $pid ) ) {
				$links[] = '<a href="' . esc_url( get_edit_post_link( $pid, 'raw' ) ) . '">' . esc_html( n4s_t( 'Bearbeiten', 'Edit' ) ) . '</a>';
			} else {
				$links[] = '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=n4s_maint_newpage&mode=' . $m ), 'n4s_maint_newpage' ) ) . '">' . esc_html( n4s_t( 'Seite anlegen', 'Create page' ) ) . '</a>';
			}
			$links[] = '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'n4s-wartung-vorschau', $m, home_url( '/' ) ), 'n4s_maint_preview' ) ) . '" target="_blank" rel="noopener">' . esc_html( n4s_t( 'Vorschau', 'Preview' ) ) . '</a>';
			echo '<div class="n4s-set"><div><label><input type="radio" name="mode" value="' . esc_attr( $m ) . '"' . checked( $o['mode'], $m, false ) . '> <b>' . esc_html( $mm[0] ) . '</b></label><small>' . esc_html( 200 === $mm[1] ? 'HTTP 200' : 'HTTP 503' ) . '</small></div><div class="n4s-ctl">' . $ctl . implode( ' · ', $links ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bausteine escaped.
		}
		echo '</div>';

		// Zeitplan
		echo '<h3>' . esc_html( n4s_t( 'Zeitplan', 'Schedule' ) ) . '</h3><div class="n4s-card">';
		n4s_apps_ui_row( n4s_t( 'Wartung ab', 'Maintenance from' ), n4s_t( 'Leer = kein Zeitplan. Zeitzone der Website: ', 'Empty = no schedule. Site time zone: ' ) . wp_timezone_string(), '<input type="datetime-local" name="from" value="' . esc_attr( $lf( $o['from'] ) ) . '">' );
		n4s_apps_ui_row( n4s_t( 'Wartung bis', 'Maintenance until' ), n4s_t( 'Gilt auch für den Handschalter: Danach endet die Wartung von selbst. Leer = offen.', 'Also applies to the manual switch: maintenance then ends by itself. Empty = open.' ), '<input type="datetime-local" name="until" value="' . esc_attr( $lf( $o['until'] ) ) . '">' );
		n4s_apps_ui_row( n4s_t( 'Höchstdauer', 'Maximum duration' ), n4s_t( 'Stunden. Danach endet die Wartung automatisch und es geht eine Mail an den Administrator. 0 = unbegrenzt.', 'Hours. Maintenance then ends automatically and an e-mail is sent. 0 = unlimited.' ), '<input type="number" min="0" max="720" name="maxh" value="' . (int) $o['maxh'] . '" style="width:90px">' );
		n4s_apps_ui_row( n4s_t( 'Vorankündigung', 'Advance notice' ), n4s_t( 'Minuten vor „Wartung ab“ zeigt die Website ein Banner. 0 = kein Banner.', 'Minutes before "maintenance from" the website shows a banner. 0 = no banner.' ), '<input type="number" min="0" max="1440" name="notice" value="' . (int) $o['notice'] . '" style="width:90px">' );
		n4s_apps_ui_toggle( 'countdown', ! empty( $o['countdown'] ), n4s_t( 'Countdown unter dem Inhalt der Wartungsseite', 'Countdown below the maintenance page content' ), n4s_t( 'Zeigt, wann es weitergeht (wenn ein Ende bekannt ist). Alternativ der Shortcode [n4s_wartung_countdown] an beliebiger Stelle.', 'Shows when it continues (if an end is known). Alternatively use the shortcode [n4s_wartung_countdown] anywhere.' ) );
		echo '</div>';

		// Anzeige
		echo '<h3>' . esc_html( n4s_t( 'Anzeige auf der Wartungsseite', 'Display on the maintenance page' ) ) . '</h3><div class="n4s-card"><p class="description" style="margin-top:0">' . esc_html( n4s_t( 'Diese Bereiche des Themes werden auf der Wartungsseite ausgeblendet (auch die Leisten von WP-Customize).', 'These theme areas are hidden on the maintenance page (including the WP-Customize bars).' ) ) . '</p>';
		foreach ( n4s_maint_hide_items() as $k => $label ) {
			echo '<label style="display:inline-block;margin:0 18px 6px 0"><input type="checkbox" name="hide[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, $o['hide'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';

		// Ausnahmen
		echo '<h3>' . esc_html( n4s_t( 'Wer die Wartung umgeht', 'Who bypasses maintenance' ) ) . '</h3><div class="n4s-card"><p class="description" style="margin-top:0">' . esc_html( n4s_t( 'Administratoren sehen immer die normale Website. Die Anmeldeseite, der Admin-Bereich, Cron und Hintergrundaufrufe bleiben erreichbar.', 'Administrators always see the normal website. The login page, the admin area, cron and background requests stay available.' ) ) . '</p><p><b>' . esc_html( n4s_t( 'Weitere Rollen', 'Further roles' ) ) . '</b><br>';
		foreach ( wp_roles()->get_names() as $r => $label ) {
			if ( 'administrator' === $r ) {
				continue;
			}
			echo '<label style="display:inline-block;margin:0 14px 4px 0"><input type="checkbox" name="roles[]" value="' . esc_attr( $r ) . '"' . checked( in_array( $r, $o['roles'], true ), true, false ) . '> ' . esc_html( translate_user_role( $label ) ) . '</label>';
		}
		echo '</p><div style="display:flex;gap:24px;flex-wrap:wrap"><div style="flex:1;min-width:260px"><label><b>' . esc_html( n4s_t( 'IP-Adressen', 'IP addresses' ) ) . '</b><br><textarea name="ips" rows="4" style="width:100%" placeholder="203.0.113.5&#10;198.51.100.0/24">' . esc_textarea( $o['ips'] ) . '</textarea></label><small class="description">' . esc_html( n4s_t( 'Eine Adresse oder ein Bereich (CIDR) je Zeile. Ihre IP: ', 'One address or range (CIDR) per line. Your IP: ' ) . n4s_maint_client_ip() ) . '</small></div>';
		echo '<div style="flex:1;min-width:260px"><label><b>' . esc_html( n4s_t( 'URL-Ausnahmen', 'URL exceptions' ) ) . '</b><br><textarea name="urls" rows="4" style="width:100%" placeholder="/zahlung/rueckruf&#10;/shop/*">' . esc_textarea( $o['urls'] ) . '</textarea></label><small class="description">' . esc_html( n4s_t( 'Ein Pfad je Zeile, mit „*“ am Ende als Anfang (zum Beispiel für Zahlungs-Rückrufe).', 'One path per line, with "*" at the end as a prefix (for example payment callbacks).' ) ) . '</small></div></div>';
		n4s_apps_ui_toggle( 'rest_apps', ! empty( $o['rest_apps'] ), n4s_t( 'App-Schnittstellen bleiben erreichbar', 'App interfaces stay available' ), n4s_t( 'Öffentliche Schnittstellen der n4s-Apps (n4s-apps/v1, zum Beispiel der Wärmepumpen-Rechner auf Kundenseiten und der Abgleich) werden nicht gesperrt. Alle anderen Schnittstellen antworten mit 503.', 'Public interfaces of the n4s apps (n4s-apps/v1, for example the heat pump calculator on customer sites and the sync) are not blocked. All other interfaces answer with 503.' ) );
		echo '</div>';

		// Mail
		echo '<h3>' . esc_html( n4s_t( 'Benachrichtigung', 'Notification' ) ) . '</h3><div class="n4s-card">';
		n4s_apps_ui_toggle( 'mail', ! empty( $o['mail'] ), n4s_t( 'Mail bei Start, Ende und Auto-Ende', 'E-mail on start, end and automatic end' ), n4s_t( 'Zusätzlich steht jeder Wechsel in den Aktivitäten (Übersicht).', 'Every change is also listed in the activities (overview).' ) );
		n4s_apps_ui_row( n4s_t( 'Empfänger', 'Recipients' ), n4s_t( 'Mailadressen, durch Komma getrennt. Leer = Administrator-Adresse der Website.', 'E-mail addresses, comma separated. Empty = the site administrator address.' ), '<input type="text" name="mails" value="' . esc_attr( $o['mails'] ) . '" style="width:320px" placeholder="' . esc_attr( (string) get_option( 'admin_email' ) ) . '">' );
		echo '</div>';
		submit_button( n4s_t( 'Speichern', 'Save' ), 'primary', 'submit', true );
		echo '</form>';

		// Freigabelinks
		echo '<h3>' . esc_html( n4s_t( 'Freigabelinks für Kunden', 'Access links for customers' ) ) . '</h3><div class="n4s-card"><p class="description" style="margin-top:0">' . esc_html( n4s_t( 'Wer den Link öffnet, sieht während der Wartung die normale Website (Cookie bis zum Ablauf). Zum Beispiel für die Abnahme ohne Anmeldung. Ein Link wird nur direkt nach dem Erstellen angezeigt und lässt sich widerrufen.', 'Whoever opens the link sees the normal website during maintenance (cookie until expiry). For example for acceptance without login. A link is shown only right after creation and can be revoked.' ) ) . '</p>';
		$new = get_transient( 'n4s_maint_newlink_' . get_current_user_id() );
		if ( $new ) {
			delete_transient( 'n4s_maint_newlink_' . get_current_user_id() );
			echo '<div class="notice notice-success inline"><p><b>' . esc_html( n4s_t( 'Neuer Link (jetzt kopieren):', 'New link (copy now):' ) ) . '</b><br><input type="text" readonly class="large-text code" value="' . esc_attr( $new ) . '" onclick="this.select()"></p></div>';
		}
		n4s_apps_form_head( 'n4s_maint_link', false, 'display:block' );
		echo '<p><input type="text" name="label" maxlength="60" placeholder="' . esc_attr( n4s_t( 'Bezeichnung, zum Beispiel Kunde Müller', 'Label, for example customer Smith' ) ) . '" style="width:280px"> <select name="days">';
		foreach ( array( 1 => n4s_t( '1 Tag', '1 day' ), 3 => n4s_t( '3 Tage', '3 days' ), 7 => n4s_t( '7 Tage', '7 days' ), 14 => n4s_t( '14 Tage', '14 days' ), 30 => n4s_t( '30 Tage', '30 days' ) ) as $d => $l ) {
			echo '<option value="' . (int) $d . '"' . selected( 7, $d, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <button type="submit" class="button">' . esc_html( n4s_t( 'Link erstellen', 'Create link' ) ) . '</button></p></form>';
		$links = array_filter( $o['links'], function ( $l ) { return (int) $l['exp'] > time(); } );
		if ( $links ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html( n4s_t( 'Bezeichnung', 'Label' ) ) . '</th><th>' . esc_html( n4s_t( 'Gültig bis', 'Valid until' ) ) . '</th><th>' . esc_html( n4s_t( 'Erstellt', 'Created' ) ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $links as $l ) {
				echo '<tr><td>' . esc_html( $l['label'] ) . '</td><td>' . esc_html( n4s_maint_fmt( $l['exp'] ) ) . '</td><td>' . esc_html( n4s_maint_fmt( $l['created'] ) . ' · ' . $l['by'] ) . '</td><td><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=n4s_maint_revoke&id=' . rawurlencode( $l['id'] ) ), 'n4s_maint_revoke' ) ) . '">' . esc_html( n4s_t( 'Widerrufen', 'Revoke' ) ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p class="description">' . esc_html( n4s_t( 'Keine gültigen Links.', 'No valid links.' ) ) . '</p>';
		}
		echo '</div>';

		echo '<h3>' . esc_html( n4s_t( 'Hinweise', 'Notes' ) ) . '</h3><div class="n4s-card"><ul style="margin:0 0 0 18px;list-style:disc"><li>' . esc_html( n4s_t( 'Notausgang: Wer ausgesperrt ist, setzt in der wp-config.php define( \'N4S_MAINTENANCE_OFF\', true );. Der Wartungsmodus ist dann aus.', 'Emergency switch: if someone is locked out, add define( \'N4S_MAINTENANCE_OFF\', true ); to wp-config.php. Maintenance mode is then off.' ) ) . '</li><li>' . esc_html( n4s_t( 'Cache: Die Wartungsseite wird nicht gecacht. Sitzt ein Seiten-Cache oder CDN davor, nach dem Umschalten den Cache leeren.', 'Cache: the maintenance page is not cached. If a page cache or CDN sits in front, clear the cache after switching.' ) ) . '</li><li>' . esc_html( n4s_t( 'Die Wartungsseiten sind im Normalbetrieb von außen nicht erreichbar (404), stehen nicht in der Sitemap und tragen noindex.', 'In normal operation the maintenance pages are not reachable from outside (404), are not in the sitemap and carry noindex.' ) ) . '</li></ul></div>';
	}
}
