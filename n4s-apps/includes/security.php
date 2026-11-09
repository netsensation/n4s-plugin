<?php
/**
 * n4s Sicherheit / Security: Teil des Plugins n4s Apps (Tab und Menü „Sicherheit“), zweisprachig DE/EN über n4s_t().
 * Anmeldeschutz (Nachbau der freien Funktionen von Limit Login Attempts) + Härtung.
 * Wird nur geladen, wenn die Funktion unter n4s → Funktionen eingeschaltet ist.
 * Speicher: Optionen n4s_lla_settings und n4s_lla_state (übernimmt die der früheren App „security“), keine eigene Tabelle.
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Sprache ---------- */

function n4s_sec_de() {
	if ( function_exists( 'n4s_t' ) ) {
		return 'de' === n4s_t( 'de', 'en' );
	}
	return 0 === strpos( determine_locale(), 'de' );
}

function n4s_sec_t( $de ) {
	static $en = null;
	if ( null === $en ) {
		$en = array(
			'Sicherheit'                          => 'Security',
			'Aktive Sperren'                      => 'Active lockouts',
			'Fehlversuche (24 Std.)'              => 'Failed attempts (24 h)',
			'Fehlversuche (30 Tage)'              => 'Failed attempts (30 days)',
			'Fehlversuche pro Tag (max. %d)'      => 'Failed attempts per day (max. %d)',
			'Fehlversuche pro Tag, letzte 30 Tage' => 'Failed attempts per day, last 30 days',
			'Ihre erkannte IP-Adresse:'           => 'Your detected IP address:',
			'Keine.'                              => 'None.',
			'Benutzername'                        => 'Username',
			'Bis'                                 => 'Until',
			'Aufheben'                            => 'Unlock',
			'Protokoll (letzte 30 Tage, max. 200)' => 'Log (last 30 days, max. 200)',
			'Leer.'                               => 'Empty.',
			'Zeit'                                => 'Time',
			'Ereignis'                            => 'Event',
			'Protokoll leeren'                    => 'Clear log',
			'Fehlversuch'                         => 'Failed attempt',
			'Sperre (%d Min.)'                    => 'Lockout (%d min.)',
			'Einstellungen'                       => 'Settings',
			'Anmeldeschutz'                       => 'Login protection',
			'Härtung'                             => 'Hardening',
			'Erlaubte Fehlversuche'               => 'Allowed failed attempts',
			'bis zur Sperre'                      => 'before lockout',
			'Sperrdauer'                          => 'Lockout duration',
			'Minuten'                             => 'minutes',
			'Sperren bis zur langen Sperre'       => 'Lockouts before long lockout',
			'Dauer der langen Sperre'             => 'Long lockout duration',
			'Stunden'                             => 'hours',
			'Fehlversuche verfallen nach'         => 'Failed attempts expire after',
			'Verbleibende Versuche anzeigen'      => 'Show remaining attempts',
			'E-Mail-Benachrichtigung'             => 'Email notification',
			'ab'                                  => 'from',
			'abgelehnten Anmeldeversuchen in 24 Stunden an' => 'rejected login attempts in 24 hours, to',
			'Höchstens eine Mail pro 24 Stunden. Zählt alle abgelehnten Versuche, auch von gesperrten IPs.' => 'At most one email per 24 hours. Counts all rejected attempts, including from locked IPs.',
			'Quelle der IP-Adresse'               => 'IP address source',
			'REMOTE_ADDR (Standard, sicher)'      => 'REMOTE_ADDR (default, safe)',
			'X-Forwarded-For (hinter Proxy)'      => 'X-Forwarded-For (behind proxy)',
			'Nur ändern, wenn die Seite hinter einem Proxy liegt; sonst lassen sich IP-Sperren umgehen.' => 'Only change if the site is behind a proxy; otherwise IP lockouts can be bypassed.',
			'Blacklist (immer gesperrt)'          => 'Blacklist (always blocked)',
			'Eine IP pro Zeile. Geräte, auf denen sich jemand erfolgreich angemeldet hat, werden 30 Tage lang automatisch nie gesperrt (Cookie).' => 'One IP per line. Devices with a successful login are never locked out for 30 days (cookie).',
			'Speichern'                           => 'Save',
			'Benutzernamen verbergen'             => 'Hide usernames',
			'Sperrt ?author=, Autorenseiten, die REST-Benutzerliste und Autorenangaben in oEmbed für Besucher ohne Anmeldung.' => 'Blocks ?author=, author archives, the REST user list and oEmbed author data for visitors who are not logged in.',
			'Neutrale Login-Fehlermeldung'        => 'Neutral login error message',
			'Verrät nicht, ob Benutzername oder Passwort falsch war.' => 'Does not reveal whether the username or the password was wrong.',
			'XML-RPC abschalten'                  => 'Disable XML-RPC',
			'Schaltet xmlrpc.php und Pingbacks ab. Deaktivieren, falls eine App oder ein Dienst XML-RPC benötigt.' => 'Turns off xmlrpc.php and pingbacks. Disable this option if an app or service needs XML-RPC.',
			'Sicherheits-Header senden'           => 'Send security headers',
			'X-Content-Type-Options, X-Frame-Options (SAMEORIGIN), Referrer-Policy, Permissions-Policy. Die Seite lässt sich dann nicht mehr in fremden iframes einbetten.' => 'X-Content-Type-Options, X-Frame-Options (SAMEORIGIN), Referrer-Policy, Permissions-Policy. The site can then no longer be embedded in foreign iframes.',
			'WordPress-Version verbergen'         => 'Hide WordPress version',
			'Entfernt den Generator-Tag und die Versionsnummer an Skript- und Style-Adressen.' => 'Removes the generator tag and the version number from script and style URLs.',
			'Einstellungen gespeichert.'          => 'Settings saved.',
			'Sperre aufgehoben.'                  => 'Lockout removed.',
			'Protokoll geleert.'                  => 'Log cleared.',
			'Der Zugang von Ihrer IP-Adresse ist gesperrt.' => 'Access from your IP address is blocked.',
			'Zu viele fehlgeschlagene Anmeldeversuche. Bitte in %d Minute(n) erneut versuchen.' => 'Too many failed login attempts. Please try again in %d minute(s).',
			'Zu viele Fehlversuche: Sperre für %d Minute(n).' => 'Too many failed attempts: locked for %d minute(s).',
			'Noch %d Versuch(e) übrig.'           => '%d attempt(s) left.',
			'<strong>Fehler:</strong> Benutzername oder Passwort ist falsch.' => '<strong>Error:</strong> Username or password is incorrect.',
			'Nicht erlaubt.'                      => 'Not allowed.',
			'[%s] %d abgelehnte Anmeldeversuche in 24 Stunden' => '[%s] %d rejected login attempts in 24 hours',
			"In den letzten 24 Stunden wurden %d Anmeldeversuche abgelehnt (Schwelle: %d).\nLetzte IP: %s\nZeit: %s" => "In the last 24 hours %d login attempts were rejected (threshold: %d).\nLast IP: %s\nTime: %s",
			'Zu den Einstellungen'                => 'Go to settings',
			'Details: n4s → Sicherheit'           => 'Details: n4s → Security',
			'Aktuell aktive Sperren: %d'          => 'Currently active lockouts: %d',
			'Gesperrte IPs (24 Std.): %d'         => 'Locked IPs (24 h): %d',
			'%d× gesperrt'                        => '%d× locked',
			'Häufigste Anmeldenamen bei Sperren:' => 'Most frequent usernames in lockouts:',
			'Keine Sperren in den letzten 24 Stunden.' => 'No lockouts in the last 24 hours.',
			'Funktionen'                          => 'Features',
			'Beiträge deaktivieren'               => 'Disable posts',
			'Blendet „Beiträge" im Admin aus (Menü, Admin-Leiste, Dashboard, Kategorien, Schlagwörter) und liefert Beiträge, Blog-Seite, Archive, Feeds und die REST-Beiträge für Besucher als 404. Vorhandene Beiträge bleiben in der Datenbank. Voraussetzung: Die Startseite ist eine statische Seite.' => 'Hides "Posts" in the admin (menu, admin bar, dashboard, categories, tags) and returns 404 to visitors for posts, the blog page, archives, feeds and the REST posts routes. Existing posts stay in the database. Requires a static front page.',
		);
	}
	$e = isset( $en[ $de ] ) ? $en[ $de ] : $de;
	return function_exists( 'n4s_t' ) ? n4s_t( $de, $e ) : ( n4s_sec_de() ? $de : $e );
}

function n4s_sec_dt( $ts ) {
	return wp_date( n4s_sec_de() ? 'd.m.Y H:i' : 'Y-m-d H:i', $ts );
}

/* ---------- Einstellungen und Zustand ---------- */

function n4s_sec_defaults() {
	return array(
		'retries'       => 4,
		'lockout_min'   => 20,
		'lockouts_long' => 4,
		'long_hours'    => 24,
		'reset_hours'   => 12,
		'notify'        => 1,
		'notify_fails'  => 200,
		'email'         => get_option( 'admin_email' ),
		'blacklist'     => '',
		'header'        => 'REMOTE_ADDR',
		'show_left'     => 1,
		'enum'          => 1,
		'generic'       => 1,
		'xmlrpc'        => 1,
		'headers'       => 1,
		'hidever'       => 1,
		'noposts'       => 0,
	);
}

function n4s_sec_opt( $reset = false ) {
	static $o = null;
	if ( null === $o || $reset ) {
		$v = get_option( 'n4s_lla_settings', array() );
		$o = wp_parse_args( is_array( $v ) ? $v : array(), n4s_sec_defaults() );
	}
	return $o;
}

function n4s_sec_state() {
	$s = get_option( 'n4s_lla_state', array() );
	$s = wp_parse_args( is_array( $s ) ? $s : array(), array( 'retries' => array(), 'locks' => array(), 'counts' => array(), 'log' => array(), 'fails' => array(), 'notified' => 0, 'lockusers' => array(), 'days' => array(), 'lockev' => array() ) );
	$o   = n4s_sec_opt();
	$now = time();
	foreach ( $s['fails'] as $b => $c ) {
		if ( (int) $b <= $now - DAY_IN_SECONDS ) {
			unset( $s['fails'][ $b ] );
		}
	}
	foreach ( $s['retries'] as $ip => $e ) {
		if ( $e[1] < $now - $o['reset_hours'] * HOUR_IN_SECONDS ) {
			unset( $s['retries'][ $ip ] );
		}
	}
	foreach ( $s['locks'] as $ip => $until ) {
		if ( $until <= $now ) {
			unset( $s['locks'][ $ip ] );
		}
	}
	$oldest = wp_date( 'Y-m-d', $now - 31 * DAY_IN_SECONDS );
	foreach ( $s['days'] as $k => $c ) {
		if ( $k < $oldest ) {
			unset( $s['days'][ $k ] );
		}
	}
	foreach ( $s['lockusers'] as $ip => $u ) {
		if ( ! isset( $s['locks'][ $ip ] ) ) {
			unset( $s['lockusers'][ $ip ] );
		}
	}
	foreach ( $s['lockev'] as $k => $ev ) {
		if ( $ev[0] <= $now - DAY_IN_SECONDS ) {
			unset( $s['lockev'][ $k ] );
		}
	}
	$s['lockev'] = array_slice( array_values( $s['lockev'] ), -200 );
	foreach ( $s['counts'] as $ip => $e ) {
		if ( $e[1] < $now - 7 * DAY_IN_SECONDS ) {
			unset( $s['counts'][ $ip ] );
		}
	}
	return $s;
}

function n4s_sec_save( $s ) {
	update_option( 'n4s_lla_state', $s, false );
}

function n4s_sec_log( &$s, $event, $ip, $user ) {
	array_unshift( $s['log'], array( time(), $event, $ip, mb_substr( sanitize_text_field( (string) $user ), 0, 60 ) ) );
	$cut      = time() - 30 * DAY_IN_SECONDS;
	$s['log'] = array_slice( array_filter( $s['log'], function ( $l ) use ( $cut ) {
		return $l[0] >= $cut;
	} ), 0, 200 );
}

/** Ereignis-Code (neu: fail, lock:N; alt: deutscher Text) in die aktuelle Sprache übersetzen */
function n4s_sec_event( $e ) {
	if ( 'fail' === $e || 'Fehlversuch' === $e ) {
		return n4s_sec_t( 'Fehlversuch' );
	}
	if ( preg_match( '/^(?:lock:|Sperre \()(\d+)/', $e, $m ) ) {
		return sprintf( n4s_sec_t( 'Sperre (%d Min.)' ), (int) $m[1] );
	}
	return $e;
}

/** Kurze Zusammenfassung für die Mail: gesperrte IPs und häufigste Anmeldenamen der Sperren (letzte 24 Stunden). */
function n4s_sec_mail_summary( $s, $now ) {
	$ips   = array();
	$users = array();
	foreach ( $s['lockev'] as $ev ) {
		if ( $ev[0] <= $now - DAY_IN_SECONDS ) {
			continue;
		}
		$ips[ $ev[1] ] = ( isset( $ips[ $ev[1] ] ) ? $ips[ $ev[1] ] : 0 ) + 1;
		foreach ( (array) $ev[2] as $u => $n ) {
			$u           = (string) $u;
			$users[ $u ] = ( isset( $users[ $u ] ) ? $users[ $u ] : 0 ) + (int) $n;
		}
	}
	$out = sprintf( n4s_sec_t( 'Aktuell aktive Sperren: %d' ), count( $s['locks'] ) );
	if ( ! $ips ) {
		return $out . "\n" . n4s_sec_t( 'Keine Sperren in den letzten 24 Stunden.' );
	}
	arsort( $ips );
	arsort( $users );
	$out .= "\n" . sprintf( n4s_sec_t( 'Gesperrte IPs (24 Std.): %d' ), count( $ips ) );
	foreach ( array_slice( $ips, 0, 5, true ) as $ip => $n ) {
		$out .= "\n- " . $ip . ' (' . sprintf( n4s_sec_t( '%d× gesperrt' ), $n ) . ')';
	}
	if ( $users ) {
		$out .= "\n" . n4s_sec_t( 'Häufigste Anmeldenamen bei Sperren:' );
		foreach ( array_slice( $users, 0, 5, true ) as $u => $n ) {
			$out .= "\n- " . $u . ' (' . (int) $n . '×)';
		}
	}
	return $out;
}

/* ---------- Anmeldeschutz ---------- */

function n4s_sec_ip() {
	$o       = n4s_sec_opt();
	$h       = $o['header'];
	$allowed = array( 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP' );
	if ( ! in_array( $h, $allowed, true ) ) {
		$h = 'REMOTE_ADDR';
	}
	$v = isset( $_SERVER[ $h ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) : '';
	if ( 'HTTP_X_FORWARDED_FOR' === $h ) {
		$p = explode( ',', $v );
		$v = trim( $p[0] );
	}
	if ( ! filter_var( $v, FILTER_VALIDATE_IP ) ) {
		$v = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
	return filter_var( $v, FILTER_VALIDATE_IP ) ? $v : '0.0.0.0';
}

function n4s_sec_list( $text ) {
	$out = array();
	foreach ( preg_split( '/[\s,;]+/', (string) $text ) as $p ) {
		if ( '' !== $p && filter_var( $p, FILTER_VALIDATE_IP ) ) {
			$out[] = $p;
		}
	}
	return array_values( array_unique( $out ) );
}

/** Vertrauenswürdiges Gerät: signiertes Cookie nach erfolgreichem Login (30 Tage), unabhängig von der IP */
function n4s_sec_trusted() {
	if ( empty( $_COOKIE['n4s_lla_t'] ) ) {
		return false;
	}
	$p = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['n4s_lla_t'] ) ) );
	if ( 3 !== count( $p ) || (int) $p[1] < time() ) {
		return false;
	}
	return hash_equals( hash_hmac( 'sha256', (int) $p[0] . '|' . (int) $p[1], wp_salt( 'auth' ) ), $p[2] );
}

function n4s_sec_set_trusted( $user_login, $user ) {
	if ( headers_sent() || ! ( $user instanceof WP_User ) ) {
		return;
	}
	$exp = time() + 30 * DAY_IN_SECONDS;
	$val = $user->ID . '|' . $exp . '|' . hash_hmac( 'sha256', $user->ID . '|' . $exp, wp_salt( 'auth' ) );
	setcookie( 'n4s_lla_t', $val, array( 'expires' => $exp, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
}
add_action( 'wp_login', 'n4s_sec_set_trusted', 10, 2 );

/** 0 = frei, -1 = dauerhaft gesperrt (Blacklist), >0 = Restsekunden der Sperre */
function n4s_sec_locked( $ip ) {
	$o = n4s_sec_opt();
	if ( in_array( $ip, n4s_sec_list( $o['blacklist'] ), true ) ) {
		return -1;
	}
	if ( n4s_sec_trusted() ) {
		return 0;
	}
	$s = n4s_sec_state();
	return isset( $s['locks'][ $ip ] ) ? max( 1, $s['locks'][ $ip ] - time() ) : 0;
}

function n4s_sec_generic( $user ) {
	if ( is_wp_error( $user ) && n4s_sec_opt()['generic'] && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'incorrect_password', 'invalid_email' ) ) ) {
		return new WP_Error( 'n4s_sec_generic', n4s_sec_t( '<strong>Fehler:</strong> Benutzername oder Passwort ist falsch.' ) );
	}
	return $user;
}
add_filter( 'authenticate', 'n4s_sec_generic', 98, 1 );

function n4s_sec_authenticate( $user, $username, $password ) {
	$r = n4s_sec_locked( n4s_sec_ip() );
	if ( 0 === $r ) {
		return $user;
	}
	$msg = ( $r < 0 )
		? n4s_sec_t( 'Der Zugang von Ihrer IP-Adresse ist gesperrt.' )
		: sprintf( n4s_sec_t( 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte in %d Minute(n) erneut versuchen.' ), (int) ceil( $r / 60 ) );
	return new WP_Error( 'n4s_sec_locked', $msg );
}
add_filter( 'authenticate', 'n4s_sec_authenticate', 99, 3 );

function n4s_sec_failed( $username ) {
	global $n4s_sec_note;
	$ip  = n4s_sec_ip();
	$o   = n4s_sec_opt();
	$s   = n4s_sec_state();
	$now = time();
	$b   = (string) ( $now - $now % HOUR_IN_SECONDS );
	$s['fails'][ $b ] = ( isset( $s['fails'][ $b ] ) ? $s['fails'][ $b ] : 0 ) + 1;
	$dk  = wp_date( 'Y-m-d', $now );
	$s['days'][ $dk ] = ( isset( $s['days'][ $dk ] ) ? $s['days'][ $dk ] : 0 ) + 1;
	$sum = array_sum( $s['fails'] );
	if ( $o['notify'] && is_email( $o['email'] ) && $sum >= (int) $o['notify_fails'] && $s['notified'] < $now - DAY_IN_SECONDS ) {
		$s['notified'] = $now;
		wp_mail(
			$o['email'],
			sprintf( n4s_sec_t( '[%s] %d abgelehnte Anmeldeversuche in 24 Stunden' ), get_bloginfo( 'name' ), $sum ),
			sprintf( n4s_sec_t( "In den letzten 24 Stunden wurden %d Anmeldeversuche abgelehnt (Schwelle: %d).\nLetzte IP: %s\nZeit: %s" ), $sum, (int) $o['notify_fails'], $ip, n4s_sec_dt( $now ) )
			. "\n\n" . n4s_sec_mail_summary( $s, $now )
			. "\n\n" . n4s_sec_t( 'Details: n4s → Sicherheit' )
		);
	}
	if ( n4s_sec_trusted() || 0 !== n4s_sec_locked( $ip ) ) {
		n4s_sec_save( $s );
		return;
	}
	$e = isset( $s['retries'][ $ip ] ) ? $s['retries'][ $ip ] : array( 0, $now, array() );
	$e[0]++;
	$e[1] = $now;
	$un   = mb_substr( sanitize_text_field( (string) $username ), 0, 60 );
	$e[2] = ( isset( $e[2] ) && is_array( $e[2] ) ) ? $e[2] : array();
	if ( '' !== $un ) {
		$e[2][ $un ] = ( isset( $e[2][ $un ] ) ? $e[2][ $un ] : 0 ) + 1;
		$e[2]        = array_slice( $e[2], -10, null, true );
	}
	$s['retries'][ $ip ] = $e;
	n4s_sec_log( $s, 'fail', $ip, $username );

	if ( $e[0] >= max( 1, (int) $o['retries'] ) ) {
		$c   = ( isset( $s['counts'][ $ip ] ) ? $s['counts'][ $ip ][0] : 0 ) + 1;
		$dur = ( $c >= max( 1, (int) $o['lockouts_long'] ) ) ? $o['long_hours'] * HOUR_IN_SECONDS : $o['lockout_min'] * MINUTE_IN_SECONDS;
		$s['counts'][ $ip ]    = array( $c, $now );
		$s['locks'][ $ip ]     = $now + $dur;
		$s['lockusers'][ $ip ] = mb_substr( sanitize_text_field( (string) $username ), 0, 60 );
		$s['lockev'][] = array( $now, $ip, $e[2] );
		unset( $s['retries'][ $ip ] );
		n4s_sec_log( $s, 'lock:' . (int) round( $dur / 60 ), $ip, $username );
		$n4s_sec_note = sprintf( n4s_sec_t( 'Zu viele Fehlversuche: Sperre für %d Minute(n).' ), (int) round( $dur / 60 ) );
	} else {
		$n4s_sec_note = $o['show_left'] ? sprintf( n4s_sec_t( 'Noch %d Versuch(e) übrig.' ), $o['retries'] - $e[0] ) : '';
	}
	n4s_sec_save( $s );
}
add_action( 'wp_login_failed', 'n4s_sec_failed', 10, 1 );

function n4s_sec_errors( $errors ) {
	global $n4s_sec_note;
	if ( ! empty( $n4s_sec_note ) ) {
		$errors .= '<br><br>' . esc_html( $n4s_sec_note );
	}
	return $errors;
}
add_filter( 'login_errors', 'n4s_sec_errors', 99 );

/* ---------- Härtung ---------- */

/** 1. Benutzernamen verbergen (nur für nicht angemeldete Besucher) */
function n4s_sec_enum_redirect() {
	if ( ! n4s_sec_opt()['enum'] || is_user_logged_in() ) {
		return;
	}
	if ( isset( $_GET['author'] ) || isset( $_GET['author_name'] ) || is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'n4s_sec_enum_redirect', 1 );

function n4s_sec_rest( $result, $server, $request ) {
	if ( n4s_sec_opt()['enum'] && ! is_user_logged_in() && preg_match( '#^/wp/v2/users(/|$)#', $request->get_route() ) ) {
		return new WP_Error( 'rest_forbidden', n4s_sec_t( 'Nicht erlaubt.' ), array( 'status' => 401 ) );
	}
	return $result;
}
add_filter( 'rest_pre_dispatch', 'n4s_sec_rest', 10, 3 );

function n4s_sec_oembed( $data ) {
	if ( n4s_sec_opt()['enum'] && ! is_user_logged_in() ) {
		unset( $data['author_name'], $data['author_url'] );
	}
	return $data;
}
add_filter( 'oembed_response_data', 'n4s_sec_oembed' );

function n4s_sec_sitemap( $provider, $name ) {
	return ( 'users' === $name && n4s_sec_opt()['enum'] ) ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'n4s_sec_sitemap', 10, 2 );

/** 3. XML-RPC abschalten */
function n4s_sec_xmlrpc( $on ) {
	return n4s_sec_opt()['xmlrpc'] ? false : $on;
}
add_filter( 'xmlrpc_enabled', 'n4s_sec_xmlrpc' );

function n4s_sec_xmlrpc_methods( $m ) {
	if ( n4s_sec_opt()['xmlrpc'] ) {
		unset( $m['pingback.ping'], $m['pingback.extensions.getPingbacks'] );
	}
	return $m;
}
add_filter( 'xmlrpc_methods', 'n4s_sec_xmlrpc_methods' );

function n4s_sec_wp_headers( $h ) {
	if ( n4s_sec_opt()['xmlrpc'] ) {
		unset( $h['X-Pingback'] );
	}
	return $h;
}
add_filter( 'wp_headers', 'n4s_sec_wp_headers' );

/** 4. Sicherheits-Header */
function n4s_sec_send_headers() {
	if ( ! n4s_sec_opt()['headers'] || headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
}
add_action( 'send_headers', 'n4s_sec_send_headers' );

/** 5. WordPress-Version verbergen */
function n4s_sec_generator( $g ) {
	return n4s_sec_opt()['hidever'] ? '' : $g;
}
add_filter( 'the_generator', 'n4s_sec_generator', 99 );

function n4s_sec_strip_ver( $src ) {
	global $wp_version;
	if ( n4s_sec_opt()['hidever'] && ! is_admin() && false !== strpos( $src, 'ver=' . $wp_version ) ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'n4s_sec_strip_ver', 99 );
add_filter( 'script_loader_src', 'n4s_sec_strip_ver', 99 );

/** 6. Beiträge komplett deaktivieren (Option, standardmäßig aus) */
function n4s_sec_np_admin() {
	global $pagenow;
	if ( ! n4s_sec_opt()['noposts'] || wp_doing_ajax() ) {
		return;
	}
	$pt  = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
	$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : 'post_tag';
	$hit = ( in_array( $pagenow, array( 'edit.php', 'post-new.php' ), true ) && 'post' === $pt )
		|| ( in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) && in_array( $tax, array( 'category', 'post_tag' ), true ) );
	if ( $hit ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'n4s_sec_np_admin' );

function n4s_sec_np_menu() {
	if ( n4s_sec_opt()['noposts'] ) {
		remove_menu_page( 'edit.php' );
	}
}
add_action( 'admin_menu', 'n4s_sec_np_menu', 999 );

function n4s_sec_np_bar( $bar ) {
	if ( n4s_sec_opt()['noposts'] ) {
		$bar->remove_node( 'new-post' );
	}
}
add_action( 'admin_bar_menu', 'n4s_sec_np_bar', 999 );

function n4s_sec_np_dash() {
	if ( n4s_sec_opt()['noposts'] ) {
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	}
}
add_action( 'wp_dashboard_setup', 'n4s_sec_np_dash', 999 );

function n4s_sec_np_front() {
	if ( ! n4s_sec_opt()['noposts'] ) {
		return;
	}
	global $wp_query;
	$home = is_home() && 'posts' !== get_option( 'show_on_front' );
	if ( $home || is_singular( 'post' ) || is_category() || is_tag() || is_date() || is_author() || is_feed() ) {
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'n4s_sec_np_front', 2 );

function n4s_sec_np_search( $q ) {
	if ( n4s_sec_opt()['noposts'] && ! is_admin() && $q->is_main_query() && $q->is_search() ) {
		$q->set( 'post_type', array( 'page' ) );
	}
}
add_action( 'pre_get_posts', 'n4s_sec_np_search' );

function n4s_sec_np_rest( $result, $server, $request ) {
	if ( n4s_sec_opt()['noposts'] && ! is_user_logged_in() && preg_match( '#^/wp/v2/(posts|categories|tags)(/|$)#', $request->get_route() ) ) {
		return new WP_Error( 'rest_no_route', n4s_sec_t( 'Nicht erlaubt.' ), array( 'status' => 404 ) );
	}
	return $result;
}
add_filter( 'rest_pre_dispatch', 'n4s_sec_np_rest', 10, 3 );

function n4s_sec_np_sitemap( $types ) {
	if ( n4s_sec_opt()['noposts'] ) {
		unset( $types['post'] );
	}
	return $types;
}
add_filter( 'wp_sitemaps_post_types', 'n4s_sec_np_sitemap' );

function n4s_sec_np_head() {
	if ( n4s_sec_opt()['noposts'] ) {
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}
}
add_action( 'wp_head', 'n4s_sec_np_head', 0 );

/* ---------- Verwaltung ---------- */

function n4s_sec_summary( $s, $compact = false ) {
	$now  = time();
	$days = array();
	$sum  = 0;
	for ( $i = 29; $i >= 0; $i-- ) {
		$k          = wp_date( 'Y-m-d', $now - $i * DAY_IN_SECONDS );
		$days[ $k ] = isset( $s['days'][ $k ] ) ? (int) $s['days'][ $k ] : 0;
		$sum       += $days[ $k ];
	}
	$max  = max( 1, max( $days ) );
	$tile = function ( $n, $label, $color ) use ( $compact ) {
		echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:' . ( $compact ? '8px 12px;flex:1;min-width:80px' : '10px 16px;min-width:150px' ) . '"><div style="font-size:' . ( $compact ? '22' : '26' ) . 'px;font-weight:600;color:' . esc_attr( $color ) . '">' . esc_html( number_format_i18n( $n ) ) . '</div><div style="color:#646970;font-size:12px">' . esc_html( $label ) . '</div></div>';
	};
	echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:12px 0">';
	$tile( count( $s['locks'] ), n4s_sec_t( 'Aktive Sperren' ), count( $s['locks'] ) ? '#d63638' : '#1d2327' );
	$tile( array_sum( $s['fails'] ), n4s_sec_t( 'Fehlversuche (24 Std.)' ), '#1d2327' );
	$tile( $sum, n4s_sec_t( 'Fehlversuche (30 Tage)' ), '#1d2327' );
	echo '</div>';
	$bw = 700 / 30;
	$ch = 100;
	echo '<svg viewBox="0 0 700 135" width="100%" style="max-width:700px;background:#fff;border:1px solid #dcdcde;border-radius:4px" role="img" aria-label="' . esc_attr( n4s_sec_t( 'Fehlversuche pro Tag, letzte 30 Tage' ) ) . '">';
	echo '<text x="6" y="12" font-size="10" fill="#646970">' . esc_html( sprintf( n4s_sec_t( 'Fehlversuche pro Tag (max. %d)' ), $max ) ) . '</text>';
	$i = 0;
	foreach ( $days as $k => $v ) {
		$bh = $v ? max( 2, $v / $max * $ch ) : 0;
		$lb = n4s_sec_de() ? substr( $k, 8, 2 ) . '.' . substr( $k, 5, 2 ) . '.' : substr( $k, 5, 2 ) . '/' . substr( $k, 8, 2 );
		echo '<rect x="' . esc_attr( round( $i * $bw + 2, 1 ) ) . '" y="' . esc_attr( round( 112 - $bh, 1 ) ) . '" width="' . esc_attr( round( $bw - 4, 1 ) ) . '" height="' . esc_attr( round( $bh, 1 ) ) . '" fill="#2271b1"><title>' . esc_html( $lb . ': ' . $v ) . '</title></rect>';
		if ( 0 === $i || 14 === $i || 29 === $i ) {
			echo '<text x="' . esc_attr( round( $i * $bw + $bw / 2, 1 ) ) . '" y="128" font-size="10" text-anchor="' . ( 0 === $i ? 'start' : ( 29 === $i ? 'end' : 'middle' ) ) . '" fill="#646970">' . esc_html( $lb ) . '</text>';
		}
		$i++;
	}
	echo '<line x1="0" x2="700" y1="112.5" y2="112.5" stroke="#dcdcde"/></svg>';
}

/* ---------- Dashboard-Widget ---------- */

function n4s_sec_dashboard_setup() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'n4s_sec_widget', n4s_sec_t( 'Sicherheit' ) . ' – by n4s', 'n4s_sec_widget' );
	}
}
add_action( 'wp_dashboard_setup', 'n4s_sec_dashboard_setup' );

function n4s_sec_widget() {
	n4s_sec_summary( n4s_sec_state(), true );
	echo '<p style="margin:10px 0 0"><a href="' . esc_url( admin_url( 'admin.php?page=n4s-security' ) ) . '">' . esc_html( n4s_sec_t( 'Zu den Einstellungen' ) ) . ' →</a></p>';
}

/** Inhalt des Tabs „Sicherheit“ (Rahmen, Titel und Tabs liefert das Plugin). */
function n4s_sec_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$msg = '';
	if ( isset( $_POST['n4s_sec_action'] ) ) {
		check_admin_referer( 'n4s_sec' );
		$act = sanitize_key( wp_unslash( $_POST['n4s_sec_action'] ) );
		$s   = n4s_sec_state();
		if ( 'save' === $act ) {
			$d  = n4s_sec_defaults();
			$in = function ( $k, $min = 1 ) use ( $d ) {
				return isset( $_POST[ $k ] ) ? max( $min, absint( wp_unslash( $_POST[ $k ] ) ) ) : $d[ $k ];
			};
			$cb  = function ( $k ) {
				return empty( $_POST[ $k ] ) ? 0 : 1;
			};
			$hd  = isset( $_POST['header'] ) ? sanitize_text_field( wp_unslash( $_POST['header'] ) ) : 'REMOTE_ADDR';
			$new = array(
				'retries'       => $in( 'retries' ),
				'lockout_min'   => $in( 'lockout_min' ),
				'lockouts_long' => $in( 'lockouts_long' ),
				'long_hours'    => $in( 'long_hours' ),
				'reset_hours'   => $in( 'reset_hours' ),
				'notify'        => $cb( 'notify' ),
				'notify_fails'  => $in( 'notify_fails' ),
				'email'         => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
				'blacklist'     => implode( "\n", n4s_sec_list( isset( $_POST['blacklist'] ) ? sanitize_textarea_field( wp_unslash( $_POST['blacklist'] ) ) : '' ) ),
				'header'        => in_array( $hd, array( 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP' ), true ) ? $hd : 'REMOTE_ADDR',
				'show_left'     => $cb( 'show_left' ),
				'enum'          => $cb( 'enum' ),
				'generic'       => $cb( 'generic' ),
				'xmlrpc'        => $cb( 'xmlrpc' ),
				'headers'       => $cb( 'headers' ),
				'hidever'       => $cb( 'hidever' ),
				'noposts'       => $cb( 'noposts' ),
			);
			update_option( 'n4s_lla_settings', $new, false );
			n4s_sec_opt( true );
			$msg = n4s_sec_t( 'Einstellungen gespeichert.' );
		} elseif ( 'unlock' === $act && isset( $_POST['ip'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_POST['ip'] ) );
			unset( $s['locks'][ $ip ], $s['retries'][ $ip ], $s['counts'][ $ip ] );
			n4s_sec_save( $s );
			$msg = n4s_sec_t( 'Sperre aufgehoben.' );
		} elseif ( 'clearlog' === $act ) {
			$s['log'] = array();
			n4s_sec_save( $s );
			$msg = n4s_sec_t( 'Protokoll geleert.' );
		}
	}
	$o = n4s_sec_opt();
	$s = n4s_sec_state();
	$f = function ( $k, $label, $suffix = '' ) use ( $o ) {
		echo '<tr><th><label for="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td><input type="number" min="1" id="' . esc_attr( $k ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $o[ $k ] ) . '" class="small-text"> ' . esc_html( $suffix ) . '</td></tr>';
	};
	$c = function ( $k, $label, $desc ) use ( $o ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( $o[ $k ], 1, false ) . '></label>' . ( $desc ? '<p class="description">' . esc_html( $desc ) . '</p>' : '' ) . '</td></tr>';
	};
	if ( $msg ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
	}
	n4s_sec_summary( $s );
	echo '<p>' . esc_html( n4s_sec_t( 'Ihre erkannte IP-Adresse:' ) ) . ' <code>' . esc_html( n4s_sec_ip() ) . '</code></p>';

	echo '<h2>' . esc_html( n4s_sec_t( 'Aktive Sperren' ) ) . '</h2>';
	if ( ! $s['locks'] ) {
		echo '<p>' . esc_html( n4s_sec_t( 'Keine.' ) ) . '</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:700px"><thead><tr><th>IP</th><th>' . esc_html( n4s_sec_t( 'Benutzername' ) ) . '</th><th>' . esc_html( n4s_sec_t( 'Bis' ) ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $s['locks'] as $ip => $until ) {
			echo '<tr><td>' . esc_html( $ip ) . '</td><td>' . esc_html( isset( $s['lockusers'][ $ip ] ) ? $s['lockusers'][ $ip ] : '–' ) . '</td><td>' . esc_html( n4s_sec_dt( $until ) ) . '</td><td><form method="post">';
			wp_nonce_field( 'n4s_sec' );
			echo '<input type="hidden" name="n4s_sec_action" value="unlock"><input type="hidden" name="ip" value="' . esc_attr( $ip ) . '"><button class="button">' . esc_html( n4s_sec_t( 'Aufheben' ) ) . '</button></form></td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<h2>' . esc_html( n4s_sec_t( 'Protokoll (letzte 30 Tage, max. 200)' ) ) . '</h2>';
	if ( ! $s['log'] ) {
		echo '<p>' . esc_html( n4s_sec_t( 'Leer.' ) ) . '</p>';
	} else {
		$th = ' style="position:sticky;top:0;background:#fff"';
		echo '<div style="max-width:700px;max-height:380px;overflow-y:auto;border:1px solid #c3c4c7"><table class="widefat striped" style="border:0"><thead><tr><th' . $th . '>' . esc_html( n4s_sec_t( 'Zeit' ) ) . '</th><th' . $th . '>' . esc_html( n4s_sec_t( 'Ereignis' ) ) . '</th><th' . $th . '>IP</th><th' . $th . '>' . esc_html( n4s_sec_t( 'Benutzername' ) ) . '</th></tr></thead><tbody>';
		foreach ( $s['log'] as $l ) {
			echo '<tr><td>' . esc_html( n4s_sec_dt( $l[0] ) ) . '</td><td>' . esc_html( n4s_sec_event( $l[1] ) ) . '</td><td>' . esc_html( $l[2] ) . '</td><td>' . esc_html( $l[3] ) . '</td></tr>';
		}
		echo '</tbody></table></div><form method="post" style="margin-top:8px">';
		wp_nonce_field( 'n4s_sec' );
		echo '<input type="hidden" name="n4s_sec_action" value="clearlog"><button class="button">' . esc_html( n4s_sec_t( 'Protokoll leeren' ) ) . '</button></form>';
	}

	echo '<hr style="margin:28px 0 8px"><h2>' . esc_html( n4s_sec_t( 'Einstellungen' ) ) . '</h2>';
	echo '<form method="post">';
	wp_nonce_field( 'n4s_sec' );
	echo '<input type="hidden" name="n4s_sec_action" value="save">';
	echo '<h3>' . esc_html( n4s_sec_t( 'Anmeldeschutz' ) ) . '</h3><table class="form-table">';
	$f( 'retries', n4s_sec_t( 'Erlaubte Fehlversuche' ), n4s_sec_t( 'bis zur Sperre' ) );
	$f( 'lockout_min', n4s_sec_t( 'Sperrdauer' ), n4s_sec_t( 'Minuten' ) );
	$f( 'lockouts_long', n4s_sec_t( 'Sperren bis zur langen Sperre' ), '' );
	$f( 'long_hours', n4s_sec_t( 'Dauer der langen Sperre' ), n4s_sec_t( 'Stunden' ) );
	$f( 'reset_hours', n4s_sec_t( 'Fehlversuche verfallen nach' ), n4s_sec_t( 'Stunden' ) );
	$c( 'show_left', n4s_sec_t( 'Verbleibende Versuche anzeigen' ), '' );
	echo '<tr><th>' . esc_html( n4s_sec_t( 'E-Mail-Benachrichtigung' ) ) . '</th><td><input type="checkbox" name="notify" value="1"' . checked( $o['notify'], 1, false ) . '> ' . esc_html( n4s_sec_t( 'ab' ) ) . ' ';
	echo '<input type="number" min="1" name="notify_fails" value="' . esc_attr( $o['notify_fails'] ) . '" class="small-text"> ' . esc_html( n4s_sec_t( 'abgelehnten Anmeldeversuchen in 24 Stunden an' ) ) . ' <input type="email" name="email" value="' . esc_attr( $o['email'] ) . '" class="regular-text"><p class="description">' . esc_html( n4s_sec_t( 'Höchstens eine Mail pro 24 Stunden. Zählt alle abgelehnten Versuche, auch von gesperrten IPs.' ) ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html( n4s_sec_t( 'Quelle der IP-Adresse' ) ) . '</th><td><select name="header">';
	foreach ( array( 'REMOTE_ADDR' => n4s_sec_t( 'REMOTE_ADDR (Standard, sicher)' ), 'HTTP_X_FORWARDED_FOR' => n4s_sec_t( 'X-Forwarded-For (hinter Proxy)' ), 'HTTP_CF_CONNECTING_IP' => 'Cloudflare', 'HTTP_X_REAL_IP' => 'X-Real-IP' ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $o['header'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select><p class="description">' . esc_html( n4s_sec_t( 'Nur ändern, wenn die Seite hinter einem Proxy liegt; sonst lassen sich IP-Sperren umgehen.' ) ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html( n4s_sec_t( 'Blacklist (immer gesperrt)' ) ) . '</th><td><textarea name="blacklist" rows="4" cols="40">' . esc_textarea( $o['blacklist'] ) . '</textarea><p class="description">' . esc_html( n4s_sec_t( 'Eine IP pro Zeile. Geräte, auf denen sich jemand erfolgreich angemeldet hat, werden 30 Tage lang automatisch nie gesperrt (Cookie).' ) ) . '</p></td></tr>';
	echo '</table>';
	echo '<h3>' . esc_html( n4s_sec_t( 'Härtung' ) ) . '</h3><table class="form-table">';
	$c( 'enum', n4s_sec_t( 'Benutzernamen verbergen' ), n4s_sec_t( 'Sperrt ?author=, Autorenseiten, die REST-Benutzerliste und Autorenangaben in oEmbed für Besucher ohne Anmeldung.' ) );
	$c( 'generic', n4s_sec_t( 'Neutrale Login-Fehlermeldung' ), n4s_sec_t( 'Verrät nicht, ob Benutzername oder Passwort falsch war.' ) );
	$c( 'xmlrpc', n4s_sec_t( 'XML-RPC abschalten' ), n4s_sec_t( 'Schaltet xmlrpc.php und Pingbacks ab. Deaktivieren, falls eine App oder ein Dienst XML-RPC benötigt.' ) );
	$c( 'headers', n4s_sec_t( 'Sicherheits-Header senden' ), n4s_sec_t( 'X-Content-Type-Options, X-Frame-Options (SAMEORIGIN), Referrer-Policy, Permissions-Policy. Die Seite lässt sich dann nicht mehr in fremden iframes einbetten.' ) );
	$c( 'hidever', n4s_sec_t( 'WordPress-Version verbergen' ), n4s_sec_t( 'Entfernt den Generator-Tag und die Versionsnummer an Skript- und Style-Adressen.' ) );
	echo '</table><h3>' . esc_html( n4s_sec_t( 'Funktionen' ) ) . '</h3><table class="form-table">';
	$c( 'noposts', n4s_sec_t( 'Beiträge deaktivieren' ), n4s_sec_t( 'Blendet „Beiträge" im Admin aus (Menü, Admin-Leiste, Dashboard, Kategorien, Schlagwörter) und liefert Beiträge, Blog-Seite, Archive, Feeds und die REST-Beiträge für Besucher als 404. Vorhandene Beiträge bleiben in der Datenbank. Voraussetzung: Die Startseite ist eine statische Seite.' ) );
	echo '</table><p><button class="button button-primary">' . esc_html( n4s_sec_t( 'Speichern' ) ) . '</button></p></form>';
}
