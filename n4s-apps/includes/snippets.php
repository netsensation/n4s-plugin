<?php
/**
 * Snippets: kleine Funktionen für WordPress-Seiten, die per Shortcode eingebunden werden.
 * Im Tab „Snippets“ einzeln aktivierbar; erst dann wird der Shortcode angezeigt, sonst nur die Beschreibung.
 * Die Snippets bringen kein eigenes Design mit, sondern nutzen die Darstellung des aktiven WordPress-Themes
 * (Formularfelder, Links, Block-Buttons). Ausgeschaltet bleibt ein Shortcode leer.
 *
 * Snippets: [n4s_login], [n4s_link], [n4s_button]
 */

defined( 'ABSPATH' ) || exit;

/** Beschreibung der Snippets: Schlüssel => Titel, Beschreibung, Shortcode, Attribute. */
function n4s_snippets_defs() {
	return array(
		'login'  => array(
			'title'     => n4s_t( 'Anmeldung', 'Login' ),
			'desc'      => n4s_t(
				'Anmeldeformular (Benutzername, Passwort, „Angemeldet bleiben“) direkt in der Seite. Nach der Anmeldung geht es auf derselben Seite weiter, bei falschen Daten erscheint ein Hinweis über dem Formular. Ist der Besucher schon angemeldet, steht dort „Angemeldet als …“ mit Abmelden-Link. Die Darstellung übernimmt das Theme.',
				'Login form (username, password, "Remember me") right in the page. After logging in the visitor stays on the same page, wrong credentials show a notice above the form. If the visitor is already logged in, "Logged in as …" with a log-out link is shown. The theme provides the design.'
			),
			'shortcode' => '[n4s_login]',
			'attrs'     => array(
				'titel="Anmelden"'       => n4s_t( 'Überschrift über dem Formular (optional)', 'Heading above the form (optional)' ),
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
			'shortcode' => '[n4s_link url="/mein-bereich/" text="Mein Bereich"]',
			'attrs'     => array(),
		),
		'button' => array(
			'title'     => n4s_t( 'Button (nur angemeldet)', 'Button (logged-in only)' ),
			'desc'      => n4s_t(
				'Ein konfigurierbarer Button (wie der Button-Block in WordPress: Text, Adresse, im neuen Tab öffnen, gefüllt oder umrandet), der nur angezeigt wird, wenn der Besucher angemeldet ist. Für nicht angemeldete Besucher bleibt die Stelle leer.',
				'A configurable button (like the WordPress button block: text, address, open in new tab, filled or outlined) that is only shown to logged-in visitors. Logged-out visitors see nothing.'
			),
			'shortcode' => '[n4s_button url="/mein-bereich/" text="Mein Bereich"]',
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

/* ------------------------------------------------------------------ Shortcodes */

/** Anmeldung. */
function n4s_snippets_sc_login( $atts ) {
	if ( ! n4s_snippet_on( 'login' ) ) {
		return '';
	}
	$a   = shortcode_atts( array( 'titel' => '', 'weiterleitung' => '', 'angemeldet' => 'hinweis' ), $atts, 'n4s_login' );
	$cur = n4s_snippets_current_url();
	if ( is_user_logged_in() ) {
		if ( 'ausblenden' === strtolower( $a['angemeldet'] ) ) {
			return '';
		}
		$u = wp_get_current_user();
		return '<p class="n4s-snippet n4s-login-info">' . esc_html( sprintf( n4s_t( 'Angemeldet als %s.', 'Logged in as %s.' ), $u->display_name ) ) . ' <a href="' . esc_url( wp_logout_url( $cur ) ) . '">' . esc_html( n4s_t( 'Abmelden', 'Log out' ) ) . '</a></p>';
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
	// Pflichtfelder und Rückkehradresse für Fehlermeldungen.
	$form = preg_replace( '/<input type="(text|password)" name="(log|pwd)"/', '<input type="$1" name="$2" required', $form );
	$form = str_replace( '</form>', '<input type="hidden" name="n4s_login_ref" value="' . esc_attr( $cur ) . '"></form>', $form );
	$out  = '<style>.n4s-login p{margin:0 0 12px}.n4s-login label{display:block;margin-bottom:4px}.n4s-login input.input{width:100%;box-sizing:border-box}.n4s-login .login-remember label{display:inline}.n4s-login-error{color:#b32d2e}</style>';
	$out .= '<div class="n4s-snippet n4s-login">';
	if ( '' !== trim( $a['titel'] ) ) {
		$out .= '<h3 class="n4s-login-title">' . esc_html( $a['titel'] ) . '</h3>';
	}
	if ( isset( $_GET['n4s_login'] ) && 'fehler' === sanitize_key( wp_unslash( $_GET['n4s_login'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige einer Meldung.
		$out .= '<p class="n4s-login-error" role="alert">' . esc_html( n4s_t( 'Anmeldung fehlgeschlagen. Bitte Benutzername und Passwort prüfen.', 'Login failed. Please check your username and password.' ) ) . '</p>';
	}
	return $out . $form . '</div>';
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

/** Button nur für angemeldete Besucher (Block-Button-Markup, Darstellung durch das Theme). */
function n4s_snippets_sc_button( $atts ) {
	if ( ! n4s_snippet_on( 'button' ) ) {
		return '';
	}
	$d = n4s_snippets_link_data( $atts, 'n4s_button' );
	if ( ! $d ) {
		return '';
	}
	if ( wp_style_is( 'wp-block-library', 'registered' ) ) {
		wp_enqueue_style( 'wp-block-library' );
	}
	$outline = in_array( $d['stil'], array( 'umrandet', 'outline' ), true );
	return '<div class="n4s-snippet n4s-button wp-block-buttons"><div class="wp-block-button' . ( $outline ? ' is-style-outline' : '' ) . '"><a class="wp-block-button__link wp-element-button button' . ( $d['klasse'] ? ' ' . esc_attr( $d['klasse'] ) : '' ) . '" href="' . esc_url( $d['url'] ) . '"' . $d['attrs'] . '>' . esc_html( $d['text'] ) . '</a></div></div>';
}

add_action(
	'init',
	function () {
		add_shortcode( 'n4s_login', 'n4s_snippets_sc_login' );
		add_shortcode( 'n4s_link', 'n4s_snippets_sc_link' );
		add_shortcode( 'n4s_button', 'n4s_snippets_sc_button' );
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
		n4s_apps_form_head( 'n4s_snippets_save', false, 'display:block' );
		echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th style="width:90px">' . esc_html( n4s_t( 'Aktiv', 'Active' ) ) . '</th><th>Snippet</th></tr></thead><tbody>';
		foreach ( $defs as $k => $d ) {
			$on = n4s_snippet_on( $k );
			echo '<tr><td><label><input type="checkbox" name="snip[' . esc_attr( $k ) . ']" value="1"' . ( $on ? ' checked' : '' ) . '> ' . esc_html( $on ? n4s_t( 'an', 'on' ) : n4s_t( 'aus', 'off' ) ) . '</label></td><td>';
			echo '<strong>' . esc_html( $d['title'] ) . '</strong><p style="margin:4px 0 8px">' . esc_html( $d['desc'] ) . '</p>';
			if ( $on ) {
				n4s_snippets_tab_usage( $k, $d );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p>';
		submit_button( n4s_t( 'Speichern', 'Save' ), 'primary', 'submit', false );
		echo '</p></form>';
	}

	/** Shortcode und Einstellhilfe eines aktiven Snippets. */
	function n4s_snippets_tab_usage( $key, $d ) {
		echo '<p style="margin:0 0 6px"><strong>' . esc_html( n4s_t( 'Shortcode:', 'Shortcode:' ) ) . '</strong> <input type="text" readonly class="large-text code" style="max-width:520px" value="' . esc_attr( $d['shortcode'] ) . '" onclick="this.select()"' . ( in_array( $key, array( 'link', 'button' ), true ) ? ' data-n4s-out="' . esc_attr( $key ) . '"' : '' ) . '></p>';
		if ( 'login' === $key ) {
			echo '<p class="description" style="margin:0 0 4px">' . esc_html( n4s_t( 'Optionale Angaben im Shortcode:', 'Optional attributes:' ) ) . '</p><ul style="margin:0 0 0 18px;list-style:disc">';
			foreach ( $d['attrs'] as $a => $t ) {
				echo '<li><code>' . esc_html( $a ) . '</code> – ' . esc_html( $t ) . '</li>';
			}
			echo '</ul>';
			return;
		}
		// Link und Button: Einstellhilfe, die den Shortcode live zusammensetzt.
		$id = 'n4s-gen-' . $key;
		echo '<div id="' . esc_attr( $id ) . '" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px;max-width:760px;margin-top:6px">';
		echo '<label>' . esc_html( n4s_t( 'Text', 'Text' ) ) . '<br><input type="text" data-f="text" value="Mein Bereich" class="regular-text" style="width:100%"></label>';
		echo '<label>' . esc_html( n4s_t( 'Adresse (URL oder Pfad)', 'Address (URL or path)' ) ) . '<br><input type="text" data-f="url" value="/mein-bereich/" class="regular-text" style="width:100%"></label>';
		echo '<label>' . esc_html( n4s_t( 'Tooltip (optional)', 'Tooltip (optional)' ) ) . '<br><input type="text" data-f="titel" value="" class="regular-text" style="width:100%"></label>';
		echo '<label>' . esc_html( n4s_t( 'Zusätzliche CSS-Klasse (optional)', 'Extra CSS class (optional)' ) ) . '<br><input type="text" data-f="klasse" value="" class="regular-text" style="width:100%"></label>';
		if ( 'button' === $key ) {
			echo '<label>' . esc_html( n4s_t( 'Stil', 'Style' ) ) . '<br><select data-f="stil" style="width:100%"><option value="">' . esc_html( n4s_t( 'Gefüllt', 'Filled' ) ) . '</option><option value="umrandet">' . esc_html( n4s_t( 'Umrandet', 'Outline' ) ) . '</option></select></label>';
		}
		echo '<label style="align-self:end"><input type="checkbox" data-f="neu" value="1"> ' . esc_html( n4s_t( 'In neuem Tab öffnen', 'Open in new tab' ) ) . '</label>';
		echo '</div>';
		$js = <<<'N4SJS'
(function(){var g=document.getElementById("%ID%"),o=document.querySelector('[data-n4s-out="%KEY%"]');if(!g||!o)return;
function up(){var s="[n4s_%KEY%";["url","text","stil","neu","titel","klasse"].forEach(function(n){var el=g.querySelector('[data-f="'+n+'"]');if(!el)return;var v=el.type==="checkbox"?(el.checked?"1":""):el.value.trim();if(v!=="")s+=" "+n+'="'+v.replace(/"/g,"'")+'"'});o.value=s+"]"}
g.addEventListener("input",up);g.addEventListener("change",up);up()})();
N4SJS;
		echo '<script>' . str_replace( array( '%ID%', '%KEY%' ), array( esc_js( $id ), esc_js( $key ) ), $js ) . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- feste Skriptvorlage, Werte mit esc_js.
	}
}
