<?php
/**
 * Snippets: kleine Funktionen für WordPress-Seiten, die per Shortcode eingebunden werden.
 * Im Tab „Snippets“ einzeln aktivierbar; erst dann wird der Shortcode angezeigt, sonst nur die Beschreibung.
 * Die Snippets nutzen die Darstellung des aktiven WordPress-Themes: Farben (Hauptfarbe, Text, Felder) und
 * Button-Form kommen aus dem Theme (Neve-Variablen, theme.json, Divi, Elementor, Astra, GeneratePress, Customizer).
 * Ausgeschaltet bleibt ein Shortcode leer.
 *
 * Snippets: [n4s_login], [n4s_link], [n4s_button], [n4s_menue]
 */

defined( 'ABSPATH' ) || exit;

require_once N4S_APPS_DIR . 'includes/colors.php';

/** Beschreibung der Snippets: Schlüssel => Titel, Beschreibung, Hinweis, Shortcode, Attribute. */
function n4s_snippets_defs() {
	return array(
		'login'  => array(
			'title'     => n4s_t( 'Anmeldung', 'Login' ),
			'desc'      => n4s_t(
				'Ein Button „Anmelden“; erst nach dem Klick klappt die schmale, zentrierte Anmeldemaske (Benutzername, Passwort, „Angemeldet bleiben“) auf. Optional ist die Maske sofort sichtbar. Nach der Anmeldung geht es auf derselben Seite weiter, bei falschen Daten erscheint ein Hinweis (die Maske bleibt offen). Ist der Besucher schon angemeldet, steht dort „Angemeldet als …“ mit Abmelden-Link. Die Darstellung übernimmt das Theme.',
				'A "Log in" button; only after clicking it the narrow, centered login form (username, password, "Remember me") opens. Optionally the form is visible right away. After logging in the visitor stays on the same page, wrong credentials show a notice (the form stays open). If the visitor is already logged in, "Logged in as …" with a log-out link is shown. The theme provides the design.'
			),
			'hint'      => '',
			'shortcode' => '[n4s_login]',
			'attrs'     => array(
				'anzeige="formular"'      => n4s_t( 'Maske sofort anzeigen statt nur den Button (Standard: Button)', 'Show the form right away instead of only the button (default: button)' ),
				'text="Anmelden"'         => n4s_t( 'Beschriftung des Buttons', 'Button label' ),
				'titel="Anmelden"'        => n4s_t( 'Überschrift über der Maske (nur bei anzeige="formular")', 'Heading above the form (only with anzeige="formular")' ),
				'weiterleitung="/seite/"' => n4s_t( 'Seite nach der Anmeldung (Standard: dieselbe Seite)', 'Page after login (default: same page)' ),
				'angemeldet="ausblenden"' => n4s_t( 'Nichts anzeigen, wenn schon angemeldet', 'Show nothing if already logged in' ),
			),
		),
		'link'   => array(
			'title'     => n4s_t( 'Link (nur angemeldet)', 'Link (logged-in only)' ),
			'desc'      => n4s_t(
				'Ein konfigurierbarer Link wie in WordPress (Text, Adresse, im neuen Tab öffnen), der nur angezeigt wird, wenn der Besucher angemeldet ist. Für nicht angemeldete Besucher bleibt die Stelle leer.',
				'A configurable link as in WordPress (text, address, open in new tab) that is only shown to logged-in visitors. Logged-out visitors see nothing.'
			),
			'hint'      => n4s_t( 'Beliebig oft nutzbar: einfach weitere Shortcodes einfügen. Mehrere Einträge als Reihe: Menü.', 'Can be used any number of times: just add more shortcodes. Several entries as a row: menu.' ),
			'shortcode' => '[n4s_link url="/mein-bereich/" text="Mein Bereich"]',
			'attrs'     => array(),
		),
		'button' => array(
			'title'     => n4s_t( 'Button (nur angemeldet)', 'Button (logged-in only)' ),
			'desc'      => n4s_t(
				'Ein konfigurierbarer Button (Text, Adresse, im neuen Tab öffnen, gefüllt oder umrandet), der nur angezeigt wird, wenn der Besucher angemeldet ist. Farben und Rundung übernimmt das Theme. Für nicht angemeldete Besucher bleibt die Stelle leer.',
				'A configurable button (text, address, open in new tab, filled or outlined) that is only shown to logged-in visitors. The theme provides colors and corner radius. Logged-out visitors see nothing.'
			),
			'hint'      => n4s_t( 'Beliebig oft nutzbar. Mehrere Buttons in einer Zeile stehen nebeneinander; für eine feste Reihe das Menü nutzen.', 'Can be used any number of times. Several buttons in one line sit side by side; for a fixed row use the menu.' ),
			'shortcode' => '[n4s_button url="/mein-bereich/" text="Mein Bereich"]',
			'attrs'     => array(),
		),
		'menu'   => array(
			'title'     => n4s_t( 'Menü (nur angemeldet)', 'Menu (logged-in only)' ),
			'desc'      => n4s_t(
				'Eine Reihe aus mehreren Links oder Buttons nebeneinander (umbrechend auf kleinen Bildschirmen), die nur angezeigt wird, wenn der Besucher angemeldet ist. Einträge mit Text, Adresse und optional neuem Tab; Stil Links, Buttons gefüllt oder Buttons umrandet; Ausrichtung links, mittig oder rechts.',
				'A row of several links or buttons side by side (wrapping on small screens), only shown to logged-in visitors. Entries with text, address and optionally a new tab; style links, filled buttons or outlined buttons; alignment left, center or right.'
			),
			'hint'      => n4s_t( 'Beliebig viele Einträge je Menü, mehrere Menüs je Seite möglich.', 'Any number of entries per menu, several menus per page possible.' ),
			'shortcode' => '[n4s_menue stil="buttons" eintraege="Mein Bereich|/mein-bereich/; Profil|/profil/"]',
			'attrs'     => array(),
		),
	);
}

/** Ist das Snippet eingeschaltet? Standard: aus. */
function n4s_snippet_on( $key ) {
	$o = get_option( 'n4s_snippets', array() );
	return is_array( $o ) && ! empty( $o[ $key ] );
}

/* ------------------------------------------------------------ Hilfsfunktionen */

/** Adresse der aktuellen Seite (ohne unsere Meldungsparameter). */
function n4s_snippets_current_url() {
	$u = get_permalink();
	if ( ! $u ) {
		$u = home_url( add_query_arg( array(), isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wird mit esc_url_raw bereinigt.
	}
	return esc_url_raw( remove_query_arg( 'n4s_login', $u ) );
}

/** Adresse aus dem Shortcode: absolut (http, https, mailto, tel), Pfad (/…, relativ zur Website) oder #Anker. */
function n4s_snippets_url( $u ) {
	$u = trim( (string) $u );
	if ( '' === $u ) {
		return '';
	}
	if ( preg_match( '#^(https?:|mailto:|tel:)#i', $u ) ) {
		return esc_url_raw( $u );
	}
	if ( '#' === $u[0] ) {
		return '#' . sanitize_html_class( substr( $u, 1 ) );
	}
	return esc_url_raw( home_url( '/' . ltrim( $u, '/' ) ) );
}

function n4s_snippets_flag( $v ) {
	return in_array( strtolower( trim( (string) $v ) ), array( '1', 'ja', 'yes', 'true', 'neu', 'on' ), true );
}

/** Zusätzliche CSS-Klassen aus dem Shortcode (nur gültige Klassennamen). */
function n4s_snippets_classes( $v ) {
	$out = array();
	foreach ( preg_split( '/\s+/', trim( (string) $v ) ) as $c ) {
		$c = sanitize_html_class( $c );
		if ( '' !== $c ) {
			$out[] = $c;
		}
	}
	return implode( ' ', $out );
}

/**
 * Gemeinsames Design der Snippets (einmal je Seite): Farben aus dem Theme. Neve-Variablen (--primarybtnbg,
 * --primarybtncolor, --primarybtnborderradius) haben Vorrang, sonst die erkannte Hauptfarbe des Themes.
 */
function n4s_snippets_style() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	$c    = n4s_login_colors();
	$ol   = $c['outside'];
	$css  = '.n4s-btn{--n4s-bg:var(--primarybtnbg,' . $c['primary'] . ');--n4s-fg:var(--primarybtncolor,' . $c['on'] . ');--n4s-r:var(--primarybtnborderradius,.4em);display:inline-block;box-sizing:border-box;margin:0 .6em .6em 0;padding:.65em 1.4em;border:2px solid var(--n4s-bg);border-radius:var(--n4s-r);background:var(--n4s-bg);color:var(--n4s-fg);font:inherit;font-weight:600;line-height:1.25;text-align:center;text-decoration:none;cursor:pointer;vertical-align:top;transition:filter .15s}'
		. '.n4s-btn:hover,.n4s-btn:focus-visible{filter:brightness(.92);color:var(--n4s-fg);text-decoration:none}'
		. '.n4s-btn-outline{--n4s-bg:var(--primarybtnbg,' . $ol . ');--n4s-fg:var(--primarybtncolor,' . n4s_login_on( $ol ) . ');background:transparent;color:var(--n4s-bg)}'
		. '.n4s-btn-outline:hover,.n4s-btn-outline:focus-visible{background:var(--n4s-bg);color:var(--n4s-fg);filter:none}'
		. '.n4s-menu{display:flex;flex-wrap:wrap;align-items:center;gap:.4em 1.2em;margin-top:0;margin-bottom:.6em}.n4s-menu .n4s-btn{margin:0}.n4s-menu-buttons,.n4s-menu-umrandet{gap:.6em}'
		. '.n4s-login{--n4s-r:var(--primarybtnborderradius,.4em);max-width:22em;margin-left:auto;margin-right:auto;text-align:center}.n4s-login-title{margin:0 0 .6em}'
		. '.n4s-login summary.n4s-btn{display:inline-block;margin:0;list-style:none}.n4s-login summary.n4s-btn::-webkit-details-marker{display:none}.n4s-login summary.n4s-btn::marker{content:""}.n4s-login-box{margin-top:1em}'
		. '.n4s-login p{margin:0 0 12px}.n4s-login .login-username label,.n4s-login .login-password label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
		. '.n4s-login input.input{width:100%;box-sizing:border-box;padding:.75em 1.2em;border:1px solid ' . $c['border'] . ';border-radius:var(--n4s-r);background:' . $c['field'] . ';color:' . $c['text'] . ';font:inherit;text-align:center}'
		. '.n4s-login .login-remember{font-size:.9em;opacity:.8;text-align:center}.n4s-login .login-remember label{display:inline-flex;align-items:center;justify-content:center;gap:.5em;margin:0;cursor:pointer}.n4s-login .login-submit .button{display:block;width:100%;margin:0;padding:.85em 1.4em}.n4s-login-error{color:#b32d2e}';
	return '<style>' . $css . '</style>';
}

/* ------------------------------------------------------------------ Shortcodes */

/** Anmeldung. */
function n4s_snippets_sc_login( $atts ) {
	if ( ! n4s_snippet_on( 'login' ) ) {
		return '';
	}
	$a   = shortcode_atts( array( 'titel' => '', 'weiterleitung' => '', 'angemeldet' => 'hinweis', 'anzeige' => 'button', 'text' => '' ), $atts, 'n4s_login' );
	$cur = n4s_snippets_current_url();
	if ( is_user_logged_in() ) {
		if ( 'ausblenden' === strtolower( $a['angemeldet'] ) ) {
			return '';
		}
		$u = wp_get_current_user();
		return n4s_snippets_style() . '<p class="n4s-snippet n4s-login n4s-login-info">' . esc_html( sprintf( n4s_t( 'Angemeldet als %s.', 'Logged in as %s.' ), $u->display_name ) ) . ' <a href="' . esc_url( wp_logout_url( $cur ) ) . '">' . esc_html( n4s_t( 'Abmelden', 'Log out' ) ) . '</a></p>';
	}
	static $n = 0;
	++$n;
	$redirect = '' !== trim( $a['weiterleitung'] ) ? n4s_snippets_url( $a['weiterleitung'] ) : $cur;
	$form     = wp_login_form(
		array(
			'echo'           => false,
			'redirect'       => $redirect,
			'remember'       => true,
			'form_id'        => 'n4s-loginform-' . $n,
			'id_username'    => 'n4s-user-' . $n,
			'id_password'    => 'n4s-pass-' . $n,
			'id_remember'    => 'n4s-rem-' . $n,
			'id_submit'      => 'n4s-submit-' . $n,
			'label_username' => n4s_t( 'Benutzername oder E-Mail-Adresse', 'Username or email address' ),
			'label_password' => n4s_t( 'Passwort', 'Password' ),
			'label_remember' => n4s_t( 'Angemeldet bleiben', 'Remember me' ),
			'label_log_in'   => n4s_t( 'Anmelden', 'Log in' ),
		)
	);
	// Pflichtfelder, Button im Design der Snippets und Rückkehradresse für Fehlermeldungen.
	$form = str_replace( 'type="text" name="log"', 'type="text" name="log" placeholder="' . esc_attr( n4s_t( 'Benutzername oder E-Mail-Adresse', 'Username or email address' ) ) . '"', $form );
	$form = str_replace( 'type="password" name="pwd"', 'type="password" name="pwd" placeholder="' . esc_attr( n4s_t( 'Passwort', 'Password' ) ) . '"', $form );
	$form = preg_replace( '/<input type="(text|password)" name="(log|pwd)"/', '<input type="$1" name="$2" required', $form );
	$form = str_replace( 'class="button button-primary"', 'class="button button-primary n4s-btn"', $form );
	$form = str_replace( '</form>', '<input type="hidden" name="n4s_login_ref" value="' . esc_attr( $cur ) . '"></form>', $form );
	$fail = isset( $_GET['n4s_login'] ) && 'fehler' === sanitize_key( wp_unslash( $_GET['n4s_login'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige einer Meldung.
	$err  = $fail ? '<p class="n4s-login-error" role="alert">' . esc_html( n4s_t( 'Anmeldung fehlgeschlagen. Bitte Benutzername und Passwort prüfen.', 'Login failed. Please check your username and password.' ) ) . '</p>' : '';
	$out  = n4s_snippets_style() . '<div class="n4s-snippet n4s-login">';
	if ( 'formular' === strtolower( trim( $a['anzeige'] ) ) ) {
		// Formular sofort sichtbar.
		if ( '' !== trim( $a['titel'] ) ) {
			$out .= '<h3 class="n4s-login-title">' . esc_html( $a['titel'] ) . '</h3>';
		}
		return $out . $err . $form . '</div>';
	}
	// Standard: nur ein Button, die Anmeldemaske klappt nach dem Klick auf (ohne JavaScript, bei Fehler offen).
	$label = '' !== trim( $a['text'] ) ? $a['text'] : n4s_t( 'Anmelden', 'Log in' );
	return $out . '<details' . ( $fail ? ' open' : '' ) . '><summary class="n4s-btn n4s-btn-outline">' . esc_html( $label ) . '</summary><div class="n4s-login-box">' . $err . $form . '</div></details></div>';
}

/** Fehlgeschlagene Anmeldung aus dem Snippet: zurück auf die Seite mit Hinweis statt auf die Anmeldeseite. */
add_action(
	'wp_login_failed',
	function () {
		if ( empty( $_POST['n4s_login_ref'] ) || ! n4s_snippet_on( 'login' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Anmeldung hat keinen Nonce.
			return;
		}
		$ref  = esc_url_raw( wp_unslash( $_POST['n4s_login_ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$host = wp_parse_url( $ref, PHP_URL_HOST );
		if ( ! $ref || ! $host || strtolower( $host ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'n4s_login', 'fehler', remove_query_arg( 'n4s_login', $ref ) ) );
		exit;
	}
);

/** Gemeinsame Daten für Link und Button; null, wenn nichts angezeigt werden soll. */
function n4s_snippets_link_data( $atts, $tag ) {
	if ( ! is_user_logged_in() ) {
		return null;
	}
	$a   = shortcode_atts( array( 'url' => '', 'text' => '', 'neu' => '', 'titel' => '', 'klasse' => '', 'stil' => '' ), $atts, $tag );
	$url = n4s_snippets_url( $a['url'] );
	if ( '' === $url ) {
		return null;
	}
	$text = '' !== trim( $a['text'] ) ? $a['text'] : $a['url'];
	$new  = n4s_snippets_flag( $a['neu'] );
	return array(
		'url'    => $url,
		'text'   => $text,
		'attrs'  => ( $new ? ' target="_blank" rel="noopener noreferrer"' : '' ) . ( '' !== trim( $a['titel'] ) ? ' title="' . esc_attr( $a['titel'] ) . '"' : '' ),
		'klasse' => n4s_snippets_classes( $a['klasse'] ),
		'stil'   => strtolower( trim( $a['stil'] ) ),
	);
}

/** Link nur für angemeldete Besucher. */
function n4s_snippets_sc_link( $atts ) {
	if ( ! n4s_snippet_on( 'link' ) ) {
		return '';
	}
	$d = n4s_snippets_link_data( $atts, 'n4s_link' );
	if ( ! $d ) {
		return '';
	}
	return '<a class="n4s-snippet n4s-link' . ( $d['klasse'] ? ' ' . esc_attr( $d['klasse'] ) : '' ) . '" href="' . esc_url( $d['url'] ) . '"' . $d['attrs'] . '>' . esc_html( $d['text'] ) . '</a>';
}

/** Ein Button im Design der Snippets (Farben und Rundung aus dem Theme). */
function n4s_snippets_btn( $url, $text, $attrs, $klasse, $outline ) {
	return '<a class="n4s-snippet n4s-btn' . ( $outline ? ' n4s-btn-outline' : '' ) . ( $klasse ? ' ' . esc_attr( $klasse ) : '' ) . '" href="' . esc_url( $url ) . '"' . $attrs . '>' . esc_html( $text ) . '</a>';
}

/** Button nur für angemeldete Besucher. */
function n4s_snippets_sc_button( $atts ) {
	if ( ! n4s_snippet_on( 'button' ) ) {
		return '';
	}
	$d = n4s_snippets_link_data( $atts, 'n4s_button' );
	if ( ! $d ) {
		return '';
	}
	return n4s_snippets_style() . n4s_snippets_btn( $d['url'], $d['text'], $d['attrs'], $d['klasse'], in_array( $d['stil'], array( 'umrandet', 'outline' ), true ) );
}

/** Menü: mehrere Links oder Buttons nebeneinander, nur für angemeldete Besucher. */
function n4s_snippets_sc_menu( $atts ) {
	if ( ! n4s_snippet_on( 'menu' ) || ! is_user_logged_in() ) {
		return '';
	}
	$a    = shortcode_atts( array( 'eintraege' => '', 'stil' => 'buttons', 'ausrichtung' => 'links', 'klasse' => '' ), $atts, 'n4s_menue' );
	$stil = strtolower( trim( $a['stil'] ) );
	$stil = in_array( $stil, array( 'links', 'umrandet' ), true ) ? $stil : 'buttons';
	$just = array( 'links' => 'flex-start', 'mitte' => 'center', 'rechts' => 'flex-end' );
	$al   = strtolower( trim( $a['ausrichtung'] ) );
	$al   = isset( $just[ $al ] ) ? $al : 'links';
	$out  = '';
	$n    = 0;
	foreach ( explode( ';', (string) $a['eintraege'] ) as $e ) {
		$p    = array_map( 'trim', explode( '|', $e ) );
		$url  = isset( $p[1] ) ? n4s_snippets_url( $p[1] ) : '';
		$text = '' !== $p[0] ? $p[0] : ( isset( $p[1] ) ? $p[1] : '' );
		if ( '' === $url || '' === $text || ++$n > 30 ) {
			continue;
		}
		$attrs = ( isset( $p[2] ) && n4s_snippets_flag( $p[2] ) ) ? ' target="_blank" rel="noopener noreferrer"' : '';
		if ( 'links' === $stil ) {
			$out .= '<a class="n4s-link" href="' . esc_url( $url ) . '"' . $attrs . '>' . esc_html( $text ) . '</a>';
		} else {
			$out .= n4s_snippets_btn( $url, $text, $attrs, '', 'umrandet' === $stil );
		}
	}
	if ( '' === $out ) {
		return '';
	}
	$k = n4s_snippets_classes( $a['klasse'] );
	return n4s_snippets_style() . '<div role="navigation" class="n4s-snippet n4s-menu n4s-menu-' . esc_attr( $stil ) . ( $k ? ' ' . esc_attr( $k ) : '' ) . '" style="justify-content:' . esc_attr( $just[ $al ] ) . '">' . $out . '</div>';
}

add_action(
	'init',
	function () {
		add_shortcode( 'n4s_login', 'n4s_snippets_sc_login' );
		add_shortcode( 'n4s_link', 'n4s_snippets_sc_link' );
		add_shortcode( 'n4s_button', 'n4s_snippets_sc_button' );
		add_shortcode( 'n4s_menue', 'n4s_snippets_sc_menu' );
	}
);

/* ------------------------------------------------------------------ Admin: Tab Snippets */

if ( is_admin() ) {

	/** Snippets ein-/ausschalten. */
	add_action(
		'admin_post_n4s_snippets_save',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			n4s_apps_verify( 'n4s_snippets_save' );
			$sel = isset( $_POST['snip'] ) && is_array( $_POST['snip'] ) ? array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['snip'] ) ) ) : array();
			$new = array();
			foreach ( array_keys( n4s_snippets_defs() ) as $k ) {
				$new[ $k ] = in_array( $k, $sel, true ) ? 1 : 0;
			}
			update_option( 'n4s_snippets', $new, false );
			n4s_apps_notice( 'success', n4s_t( 'Snippets gespeichert.', 'Snippets saved.' ) );
			n4s_apps_back( false, 'n4s-snippets' );
		}
	);

	/** Tab Snippets. */
	function n4s_snippets_tab() {
		$defs = n4s_snippets_defs();
		echo '<h2>' . esc_html( n4s_t( 'Snippets', 'Snippets' ) ) . '</h2>';
		echo '<p>' . esc_html( n4s_t(
			'Kleine Funktionen, die per Shortcode in jede Seite eingebunden werden können. Ein Snippet muss zuerst aktiviert werden; dann erscheint der Shortcode zum Kopieren. Ausgeschaltet bleiben die Shortcodes leer. Die Darstellung übernimmt immer das aktive Theme.',
			'Small functions that can be embedded in any page with a shortcode. A snippet must be activated first; then its shortcode is shown for copying. When switched off, the shortcodes stay empty. The active theme always provides the design.'
		) ) . '</p>';
		if ( n4s_snippet_on( 'login' ) || n4s_snippet_on( 'link' ) || n4s_snippet_on( 'button' ) || n4s_snippet_on( 'menu' ) ) {
			n4s_snippets_tab_import();
		}
		n4s_apps_form_head( 'n4s_snippets_save', false, 'display:block' );
		echo '<table class="widefat striped" style="width:100%"><thead><tr><th style="width:90px">' . esc_html( n4s_t( 'Aktiv', 'Active' ) ) . '</th><th>Snippet</th></tr></thead><tbody>';
		foreach ( $defs as $k => $d ) {
			$on = n4s_snippet_on( $k );
			echo '<tr><td><label><input type="checkbox" name="snip[' . esc_attr( $k ) . ']" value="1"' . ( $on ? ' checked' : '' ) . '> ' . esc_html( $on ? n4s_t( 'an', 'on' ) : n4s_t( 'aus', 'off' ) ) . '</label></td><td>';
			echo '<strong>' . esc_html( $d['title'] ) . '</strong><p style="margin:4px 0 8px;max-width:none">' . esc_html( $d['desc'] ) . '</p>';
			if ( $on ) {
				if ( '' !== $d['hint'] ) {
					echo '<p class="description" style="margin:0 0 8px;max-width:none">' . esc_html( $d['hint'] ) . '</p>';
				}
				n4s_snippets_tab_usage( $k, $d );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p>';
		submit_button( n4s_t( 'Speichern', 'Save' ), 'primary', 'submit', false );
		echo '</p></form>';
		// Kopieren-Schaltflächen aller Snippets.
		$js = <<<'N4SJS'
document.addEventListener("click",function(e){var b=e.target.closest("[data-n4s-copy]");if(!b)return;e.preventDefault();var i=document.getElementById(b.getAttribute("data-n4s-copy"));if(!i)return;
var done=function(){var t=b.textContent;b.textContent=b.getAttribute("data-done");setTimeout(function(){b.textContent=t},1600)};
i.select();if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(i.value).then(done,function(){document.execCommand("copy");done()})}else{document.execCommand("copy");done()}});
N4SJS;
		echo '<script>' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- feste Skriptvorlage.
	}

	/** Vorhandenen Shortcode (Link, Button, Menü) einlesen: füllt die Einstellhilfe des passenden Snippets zum Bearbeiten. */
	function n4s_snippets_tab_import() {
		echo '<div style="margin:0 0 14px;padding:10px 12px;background:#fff;border:1px solid #c3c4c7;width:100%;box-sizing:border-box"><strong>' . esc_html( n4s_t( 'Shortcode einlesen und bearbeiten', 'Load and edit a shortcode' ) ) . '</strong>';
		echo '<p class="description" style="margin:2px 0 6px;max-width:none">' . esc_html( n4s_t( 'Einen vorhandenen Shortcode (Anmeldung, Link, Button oder Menü) hier einfügen und „Einlesen“ wählen. Die Einstellhilfe des passenden Snippets wird damit gefüllt; dort lässt er sich ändern und wieder kopieren.', 'Paste an existing shortcode (login, link, button or menu) here and choose "Load". The helper of the matching snippet is filled with it; change it there and copy it again.' ) ) . '</p>';
		echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input type="text" id="n4s-imp-in" class="code" style="flex:1;min-width:240px" placeholder="[n4s_button url=&quot;/mein-bereich/&quot; text=&quot;Mein Bereich&quot;]">';
		echo '<button type="button" class="button" id="n4s-imp-go">' . esc_html( n4s_t( 'Einlesen', 'Load' ) ) . '</button> <span id="n4s-imp-msg" class="description"></span></div></div>';
		$js = <<<'N4SJS'
(function(){var i=document.getElementById("n4s-imp-in"),b=document.getElementById("n4s-imp-go"),m=document.getElementById("n4s-imp-msg");if(!i||!b)return;
var T={login:"login",link:"link",button:"button",menue:"menu"},M={bad:"%BAD%",off:"%OFF%",ok:"%OK%"};
function go(){var v=i.value.trim(),r=/^\[\s*n4s_(login|link|button|menue)\b([^\]]*)\]?/i.exec(v);if(!r){m.textContent=M.bad;return}
var k=T[r[1].toLowerCase()],a={},re=/([a-z_]+)\s*=\s*(?:"([^"]*)"|'([^']*)')/gi,x;while((x=re.exec(r[2]))){a[x[1].toLowerCase()]=x[2]!==undefined?x[2]:x[3]}
var g=document.getElementById("n4s-gen-"+k);if(!g){m.textContent=M.off;return}
g.dispatchEvent(new CustomEvent("n4s-load",{detail:a}));g.scrollIntoView({behavior:"smooth",block:"center"});m.textContent=M.ok}
b.addEventListener("click",go);i.addEventListener("keydown",function(e){if(e.key==="Enter"){e.preventDefault();go()}})})();
N4SJS;
		echo '<script>' . str_replace( array( '%BAD%', '%OFF%', '%OK%' ), array( esc_js( n4s_t( 'Kein Shortcode für Anmeldung, Link, Button oder Menü erkannt.', 'No login, link, button or menu shortcode recognised.' ) ), esc_js( n4s_t( 'Dieses Snippet ist nicht aktiv: zuerst aktivieren und speichern.', 'This snippet is not active: activate and save it first.' ) ), esc_js( n4s_t( 'Eingelesen: unten in der Einstellhilfe bearbeiten.', 'Loaded: edit it in the helper below.' ) ) ), $js ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- feste Skriptvorlage, Werte mit esc_js.
	}

	/** Shortcode (mit Kopieren-Schaltfläche) und Einstellhilfe eines aktiven Snippets. */
	function n4s_snippets_tab_usage( $key, $d ) {
		$oid = 'n4s-out-' . $key;
		echo '<div style="display:flex;gap:8px;align-items:center;width:100%;margin:0 0 6px"><strong>' . esc_html( n4s_t( 'Shortcode:', 'Shortcode:' ) ) . '</strong>';
		echo '<input type="text" readonly id="' . esc_attr( $oid ) . '" class="code" style="flex:1;min-width:0" value="' . esc_attr( $d['shortcode'] ) . '" onclick="this.select()" data-n4s-out="' . esc_attr( $key ) . '">';
		echo '<button type="button" class="button" data-n4s-copy="' . esc_attr( $oid ) . '" data-done="' . esc_attr( n4s_t( 'Kopiert', 'Copied' ) ) . '">' . esc_html( n4s_t( 'Kopieren', 'Copy' ) ) . '</button></div>';
		$id = 'n4s-gen-' . $key;
		if ( 'login' === $key ) {
			n4s_snippets_tab_login_helper( $id, $d );
			return;
		}
		if ( 'menu' === $key ) {
			n4s_snippets_tab_menu_helper( $id );
			return;
		}
		// Link und Button: Einstellhilfe, die den Shortcode live zusammensetzt.
		echo '<div id="' . esc_attr( $id ) . '" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;width:100%;margin-top:6px">';
		echo '<label>' . esc_html( n4s_t( 'Text', 'Text' ) ) . '<br><input type="text" data-f="text" value="Mein Bereich" style="width:100%"></label>';
		echo '<label>' . esc_html( n4s_t( 'Adresse (URL oder Pfad)', 'Address (URL or path)' ) ) . '<br><input type="text" data-f="url" value="/mein-bereich/" style="width:100%"></label>';
		echo '<label>' . esc_html( n4s_t( 'Tooltip (optional)', 'Tooltip (optional)' ) ) . '<br><input type="text" data-f="titel" value="" style="width:100%"></label>';
		echo '<label>' . esc_html( n4s_t( 'Zusätzliche CSS-Klasse (optional)', 'Extra CSS class (optional)' ) ) . '<br><input type="text" data-f="klasse" value="" style="width:100%"></label>';
		if ( 'button' === $key ) {
			echo '<label>' . esc_html( n4s_t( 'Stil', 'Style' ) ) . '<br><select data-f="stil" style="width:100%"><option value="">' . esc_html( n4s_t( 'Gefüllt', 'Filled' ) ) . '</option><option value="umrandet">' . esc_html( n4s_t( 'Umrandet', 'Outline' ) ) . '</option></select></label>';
		}
		echo '<label style="align-self:end"><input type="checkbox" data-f="neu" value="1"> ' . esc_html( n4s_t( 'In neuem Tab öffnen', 'Open in new tab' ) ) . '</label>';
		echo '</div>';
		$js = <<<'N4SJS'
(function(){var g=document.getElementById("%ID%"),o=document.querySelector('[data-n4s-out="%KEY%"]');if(!g||!o)return;
function up(){var s="[n4s_%KEY%";["url","text","stil","neu","titel","klasse"].forEach(function(n){var el=g.querySelector('[data-f="'+n+'"]');if(!el)return;var v=el.type==="checkbox"?(el.checked?"1":""):el.value.trim();if(v!=="")s+=" "+n+'="'+v.replace(/"/g,"'")+'"'});o.value=s+"]"}
g.addEventListener("n4s-load",function(ev){var a=ev.detail||{};["url","text","stil","neu","titel","klasse"].forEach(function(n){var el=g.querySelector('[data-f="'+n+'"]');if(!el)return;var v=a[n]||"";if(el.type==="checkbox"){el.checked=["1","ja","yes","true","neu","on"].indexOf(v.toLowerCase())>-1}else{el.value=v}});up()});
g.addEventListener("input",up);g.addEventListener("change",up);up()})();
N4SJS;
		echo '<script>' . str_replace( array( '%ID%', '%KEY%' ), array( esc_js( $id ), esc_js( $key ) ), $js ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- feste Skriptvorlage, Werte mit esc_js.
	}

	/** Einstellhilfe für die Anmeldung: optionale Angaben als Checkboxen (bei Textangaben mit Eingabefeld), Shortcode live. */
	function n4s_snippets_tab_login_helper( $id, $d ) {
		$rows = array(
			array( 'anzeige', 'formular', '', n4s_t( 'Maske sofort anzeigen statt nur den Button', 'Show the form right away instead of only the button' ) ),
			array( 'text', '', 'Anmelden', n4s_t( 'Beschriftung des Buttons ändern', 'Change the button label' ) ),
			array( 'titel', '', 'Anmelden', n4s_t( 'Überschrift über der Maske (nur bei sofort angezeigter Maske)', 'Heading above the form (only when the form is shown right away)' ) ),
			array( 'weiterleitung', '', '/mein-bereich/', n4s_t( 'Seite nach der Anmeldung festlegen (Standard: dieselbe Seite)', 'Set the page after login (default: same page)' ) ),
			array( 'angemeldet', 'ausblenden', '', n4s_t( 'Nichts anzeigen, wenn schon angemeldet', 'Show nothing if already logged in' ) ),
		);
		echo '<div id="' . esc_attr( $id ) . '" style="width:100%;margin-top:6px"><p class="description" style="margin:0 0 6px">' . esc_html( n4s_t( 'Optionale Angaben (Häkchen setzen, der Shortcode wird oben angepasst):', 'Optional settings (tick a box, the shortcode above is updated):' ) ) . '</p>';
		foreach ( $rows as $r ) {
			echo '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 6px"><label style="min-width:340px"><input type="checkbox" data-a="' . esc_attr( $r[0] ) . '"' . ( '' !== $r[1] ? ' data-fix="' . esc_attr( $r[1] ) . '"' : '' ) . '> ' . esc_html( $r[3] ) . ' <code>' . esc_html( $r[0] ) . '</code></label>';
			if ( '' !== $r[2] ) {
				echo '<input type="text" data-av="' . esc_attr( $r[0] ) . '" value="' . esc_attr( $r[2] ) . '" disabled style="min-width:220px">';
			}
			echo '</div>';
		}
		echo '</div>';
		$js = <<<'N4SJS'
(function(){var g=document.getElementById("%ID%"),o=document.querySelector('[data-n4s-out="login"]');if(!g||!o)return;
function up(){var s="[n4s_login";g.querySelectorAll("[data-a]").forEach(function(c){var n=c.getAttribute("data-a"),t=g.querySelector('[data-av="'+n+'"]'),v;if(t)t.disabled=!c.checked;if(!c.checked)return;v=c.hasAttribute("data-fix")?c.getAttribute("data-fix"):(t?t.value.trim():"");if(v==="")return;s+=" "+n+'="'+v.replace(/"/g,"'")+'"'});o.value=s+"]"}
g.addEventListener("n4s-load",function(ev){var a=ev.detail||{};g.querySelectorAll("[data-a]").forEach(function(c){var n=c.getAttribute("data-a"),t=g.querySelector('[data-av="'+n+'"]'),has=Object.prototype.hasOwnProperty.call(a,n);if(c.hasAttribute("data-fix")){c.checked=has&&String(a[n]).toLowerCase()===c.getAttribute("data-fix")}else{c.checked=has;if(has&&t)t.value=a[n]}});up()});
g.addEventListener("input",up);g.addEventListener("change",up);up()})();
N4SJS;
		echo '<script>' . str_replace( '%ID%', esc_js( $id ), $js ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- feste Skriptvorlage, ID mit esc_js.
	}

	/** Einstellhilfe für das Menü: Einträge hinzufügen/entfernen, Stil und Ausrichtung. */
	function n4s_snippets_tab_menu_helper( $id ) {
		echo '<div id="' . esc_attr( $id ) . '" style="width:100%;margin-top:6px">';
		echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px">';
		echo '<label>' . esc_html( n4s_t( 'Stil', 'Style' ) ) . '<br><select data-m="stil"><option value="buttons">' . esc_html( n4s_t( 'Buttons gefüllt', 'Filled buttons' ) ) . '</option><option value="umrandet">' . esc_html( n4s_t( 'Buttons umrandet', 'Outlined buttons' ) ) . '</option><option value="links">' . esc_html( n4s_t( 'Links', 'Links' ) ) . '</option></select></label>';
		echo '<label>' . esc_html( n4s_t( 'Ausrichtung', 'Alignment' ) ) . '<br><select data-m="ausrichtung"><option value="links">' . esc_html( n4s_t( 'Links', 'Left' ) ) . '</option><option value="mitte">' . esc_html( n4s_t( 'Mitte', 'Center' ) ) . '</option><option value="rechts">' . esc_html( n4s_t( 'Rechts', 'Right' ) ) . '</option></select></label>';
		echo '</div><div data-rows></div>';
		echo '<p style="margin:6px 0 0"><button type="button" class="button" data-add>' . esc_html( n4s_t( '+ Eintrag hinzufügen', '+ Add entry' ) ) . '</button> <span class="description">' . esc_html( n4s_t( 'Im Text sind die Zeichen ; und | nicht möglich.', 'The characters ; and | are not allowed in the text.' ) ) . '</span></p></div>';
		$js = <<<'N4SJS'
(function(){var g=document.getElementById("%ID%"),o=document.querySelector('[data-n4s-out="menu"]');if(!g||!o)return;var rows=g.querySelector("[data-rows]");
var L={t:"%T%",u:"%U%",n:"%N%",x:"%X%"};
function row(t,u,n){var d=document.createElement("div");d.style.cssText="display:grid;grid-template-columns:2fr 3fr auto auto;gap:8px;align-items:center;margin-bottom:6px";
d.innerHTML='<input type="text" data-r="t" placeholder="'+L.t+'"><input type="text" data-r="u" placeholder="'+L.u+'"><label style="white-space:nowrap"><input type="checkbox" data-r="n"> '+L.n+'</label><button type="button" class="button" data-del title="'+L.x+'">×</button>';
d.querySelector('[data-r="t"]').value=t||"";d.querySelector('[data-r="u"]').value=u||"";d.querySelector('[data-r="n"]').checked=!!n;rows.appendChild(d)}
function up(){var e=[];Array.prototype.forEach.call(rows.children,function(r){var t=r.querySelector('[data-r="t"]').value.replace(/[;|"]/g," ").trim(),u=r.querySelector('[data-r="u"]').value.replace(/[;|"]/g,"").trim(),n=r.querySelector('[data-r="n"]').checked;if(t===""&&u==="")return;e.push(t+"|"+u+(n?"|neu":""))});
var s="[n4s_menue",st=g.querySelector('[data-m="stil"]').value,al=g.querySelector('[data-m="ausrichtung"]').value;if(st!=="buttons")s+=' stil="'+st+'"';if(al!=="links")s+=' ausrichtung="'+al+'"';o.value=s+' eintraege="'+e.join("; ")+'"]'}
g.addEventListener("input",up);g.addEventListener("change",up);g.addEventListener("click",function(ev){if(ev.target.hasAttribute("data-add")){row();up()}else if(ev.target.hasAttribute("data-del")){ev.target.parentNode.remove();up()}});
g.addEventListener("n4s-load",function(ev){var a=ev.detail||{};var st=g.querySelector('[data-m="stil"]'),al=g.querySelector('[data-m="ausrichtung"]');if(a.stil&&st.querySelector('option[value="'+a.stil+'"]'))st.value=a.stil;else st.value="buttons";if(a.ausrichtung&&al.querySelector('option[value="'+a.ausrichtung+'"]'))al.value=a.ausrichtung;else al.value="links";rows.innerHTML="";(a.eintraege||"").split(";").forEach(function(x){x=x.trim();if(!x)return;var p=x.split("|");row((p[0]||"").trim(),(p[1]||"").trim(),(p[2]||"").trim().toLowerCase()==="neu")});if(!rows.children.length)row();up()});
row("Mein Bereich","/mein-bereich/");row("Profil","/profil/");up()})();
N4SJS;
		echo '<script>' . str_replace( array( '%ID%', '%T%', '%U%', '%N%', '%X%' ), array( esc_js( $id ), esc_js( n4s_t( 'Text', 'Text' ) ), esc_js( n4s_t( 'Adresse (URL oder Pfad)', 'Address (URL or path)' ) ), esc_js( n4s_t( 'Neuer Tab', 'New tab' ) ), esc_js( n4s_t( 'Entfernen', 'Remove' ) ) ), $js ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- feste Skriptvorlage, Werte mit esc_js.
	}
}
