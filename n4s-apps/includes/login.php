<?php
/**
 * Eigenes Login-Design: ersetzt das Aussehen der WordPress-Anmeldung (wp-login.php)
 * durch eine Anmeldung mit dem Logo der Website und den Farben des aktiven Themes.
 * Anmeldung, Passwort vergessen und Registrierung laufen weiter über WordPress
 * (Sicherheit, Zwei-Faktor-Plugins und Weiterleitungen funktionieren unverändert).
 * Wird nur geladen, wenn unter n4s → Funktionen eingeschaltet.
 */

defined( 'ABSPATH' ) || exit;

/** Farben des aktiven Themes (gemeinsam mit den Snippets genutzt). */
require_once N4S_APPS_DIR . 'includes/colors.php';

/* ------------------------------------------------------------------- Logo */

/** Bildadresse aus Anhang-ID oder Adresse ('' wenn unbekannt). */
function n4s_login_img( $v ) {
	if ( is_numeric( $v ) && (int) $v > 0 ) {
		$src = wp_get_attachment_image_src( (int) $v, 'full' );
		return $src ? (string) $src[0] : '';
	}
	if ( is_array( $v ) ) {
		foreach ( array( 'url', 'id', 'desktop' ) as $k ) {
			if ( ! empty( $v[ $k ] ) ) {
				$u = n4s_login_img( 'id' === $k || 'desktop' === $k ? $v[ $k ] : (string) $v[ $k ] );
				if ( $u ) {
					return $u;
				}
			}
		}
		return '';
	}
	return ( is_string( $v ) && preg_match( '#^(https?:)?//|^/#', $v ) ) ? $v : '';
}

/**
 * Logo der Website. Zuerst das Logo, das das aktive Theme im Header zeigt (Neve, Divi, Avada, Enfold, Blocksy),
 * dann das WordPress-Logo (Customizer bzw. Block-Theme), Elementor-Kit, Website-Icon; '' = Name als Text.
 */
function n4s_login_logo() {
	$cands = array();

	// Neve: Logo-Komponente im Header (JSON {"light":ID,"dark":ID,"same":bool} oder ID).
	$nv = get_theme_mod( 'logo_logo' );
	if ( $nv ) {
		$d = is_string( $nv ) ? json_decode( $nv, true ) : $nv;
		if ( is_array( $d ) ) {
			$dark    = ! empty( $d['dark'] ) && empty( $d['same'] ) && n4s_login_colors()['dark'];
			$cands[] = $dark ? $d['dark'] : ( isset( $d['light'] ) ? $d['light'] : '' );
		} else {
			$cands[] = $nv;
		}
	}

	// Divi.
	$divi = get_option( 'et_divi' );
	if ( is_array( $divi ) && ! empty( $divi['divi_logo'] ) ) {
		$cands[] = $divi['divi_logo'];
	}

	// Avada.
	$fu = get_option( 'fusion_options' );
	if ( is_array( $fu ) && ! empty( $fu['logo'] ) ) {
		$cands[] = $fu['logo'];
	}

	// Enfold.
	$en = get_option( 'avia_options_enfold' );
	if ( is_array( $en ) && ! empty( $en['avia']['logo'] ) ) {
		$cands[] = $en['avia']['logo'];
	}

	// WordPress-Logo (Customizer; Blocksy speichert es je Gerät als Array) und Logo der Block-Themes.
	$cands[] = get_theme_mod( 'custom_logo' );
	$cands[] = get_option( 'site_logo' );

	// Elementor-Kit.
	$kit = (int) get_option( 'elementor_active_kit' );
	$ks  = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : array();
	if ( is_array( $ks ) && ! empty( $ks['site_logo'] ) ) {
		$cands[] = $ks['site_logo'];
	}

	$url = '';
	foreach ( $cands as $c ) {
		$url = n4s_login_img( $c );
		if ( $url ) {
			break;
		}
	}
	if ( ! $url && function_exists( 'get_site_icon_url' ) ) {
		$url = get_site_icon_url( 192 );
	}
	return (string) apply_filters( 'n4s_login_logo', $url );
}

/* ------------------------------------------------------------------ Ausgabe */

/** CSS der Anmeldeseite (inline, keine externen Schriften). */
function n4s_login_css() {
	$c    = n4s_login_colors();
	$logo = n4s_login_logo();
	$h    = 'body.login';

	$css  = ':root{--n4s-bg:' . $c['bg'] . ';--n4s-card:' . $c['card'] . ';--n4s-text:' . $c['text'] . ';--n4s-muted:' . $c['muted'] . ';--n4s-border:' . $c['border'] . ';--n4s-field:' . $c['field'] . ';--n4s-primary:' . $c['primary'] . ';--n4s-on:' . $c['on'] . ';--n4s-link:' . $c['link'] . ';--n4s-outside:' . $c['outside'] . '}';
	$css .= $h . '{background:var(--n4s-bg);color:var(--n4s-text);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;display:flex;flex-direction:column;justify-content:center;min-height:100vh;height:auto}';
	$css .= $h . ' #login{width:360px;max-width:calc(100% - 32px);padding:24px 0;margin:0 auto}';
	$css .= $h . ' h1{margin-bottom:24px}';
	if ( $logo ) {
		$css .= $h . ' h1 a{background-image:url("' . esc_url( $logo ) . '");background-size:contain;background-position:center;background-repeat:no-repeat;width:100%;max-width:280px;height:96px;margin:0 auto}';
	} else {
		$css .= $h . ' h1 a{background:none;text-indent:0;width:auto;height:auto;font-size:26px;line-height:1.3;font-weight:600;color:var(--n4s-outside);overflow:visible}';
	}
	$css .= $h . ' h1 a:focus{box-shadow:0 0 0 2px var(--n4s-primary);border-radius:4px;outline:0}';
	$css .= $h . ' #login form{background:var(--n4s-card);color:var(--n4s-text);border:1px solid var(--n4s-border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,' . ( $c['dark'] ? '.35' : '.08' ) . ');padding:28px 28px 32px;margin-top:0}';
	$css .= $h . ' label,' . $h . ' #login form p,' . $h . ' .login-remember label{color:var(--n4s-text);font-size:14px}';
	$css .= $h . ' input[type=text],' . $h . ' input[type=email],' . $h . ' input[type=password]{background:var(--n4s-field);color:var(--n4s-text);border:1px solid var(--n4s-border);border-radius:8px;font-size:16px;padding:8px 12px;min-height:44px;box-shadow:none}';
	$css .= $h . ' input[type=text]:focus,' . $h . ' input[type=email]:focus,' . $h . ' input[type=password]:focus,' . $h . ' input[type=checkbox]:focus{border-color:var(--n4s-primary);box-shadow:0 0 0 1px var(--n4s-primary);outline:0}';
	$css .= $h . ' input[type=checkbox]{background:var(--n4s-field);border-color:var(--n4s-border);border-radius:4px}';
	$css .= $h . ' .button.wp-hide-pw{color:var(--n4s-muted);min-height:44px;height:44px}';
	$css .= $h . ' .button.wp-hide-pw:focus{border-color:var(--n4s-primary);box-shadow:0 0 0 1px var(--n4s-primary)}';
	$css .= $h . ' .button-primary,' . $h . ' .button-primary:focus,' . $h . ' .button-primary:hover,' . $h . ' .button-primary:active{background:var(--n4s-primary);border-color:var(--n4s-primary);color:var(--n4s-on);text-shadow:none;box-shadow:none}';
	$css .= $h . ' .button-primary{border-radius:8px;min-height:40px;padding:0 20px;font-size:14px;font-weight:600}';
	$css .= $h . ' .button-primary:hover{filter:brightness(1.08)}';
	$css .= $h . ' .button-primary:focus{box-shadow:0 0 0 2px var(--n4s-card),0 0 0 4px var(--n4s-primary)}';
	$css .= $h . ' .button-secondary,' . $h . ' .button:not(.button-primary):not(.wp-hide-pw){color:var(--n4s-link);border-color:var(--n4s-link);background:transparent;border-radius:8px}';
	$css .= $h . ' #login form a,' . $h . ' .message a,' . $h . ' .notice a{color:var(--n4s-link)}';
	$css .= $h . ' #nav,' . $h . ' #backtoblog{text-align:center;padding:0;margin:16px 0 0}';
	$css .= $h . ' #nav a,' . $h . ' #backtoblog a,' . $h . ' .privacy-policy-link{color:var(--n4s-outside)}';
	$css .= $h . ' #nav a:hover,' . $h . ' #backtoblog a:hover,' . $h . ' .privacy-policy-link:hover{color:var(--n4s-outside);text-decoration:underline}';
	$css .= $h . ' .message,' . $h . ' .notice,' . $h . ' .success,' . $h . ' #login_error{background:var(--n4s-card);color:var(--n4s-text);border-radius:8px;border-left-color:var(--n4s-primary);box-shadow:0 1px 3px rgba(0,0,0,.08)}';
	$css .= $h . ' #login_error,' . $h . ' .notice-error{border-left-color:#d63638}';
	$css .= $h . ' .language-switcher{padding-bottom:16px}' . $h . ' .language-switcher form{background:none;border:0;box-shadow:none;padding:0;margin:0}';
	$css .= $h . ' .language-switcher label .dashicons{color:var(--n4s-outside)}';
	$css .= $h . ' .language-switcher select{background-color:var(--n4s-card);color:var(--n4s-text);border-color:var(--n4s-border);border-radius:8px}';
	$css .= $h . ' .language-switcher .button{background:var(--n4s-card);color:var(--n4s-text);border-color:var(--n4s-border);border-radius:8px}';
	$css .= $h . ' .privacy-policy-page-link{margin:16px 0 0}';
	return $css;
}

function n4s_login_enqueue() {
	wp_register_style( 'n4s-login', false, array( 'login' ), N4S_APPS_VERSION );
	wp_enqueue_style( 'n4s-login' );
	wp_add_inline_style( 'n4s-login', n4s_login_css() );
}

add_action( 'login_enqueue_scripts', 'n4s_login_enqueue', 20 );
add_filter(
	'login_headerurl',
	function () {
		return home_url( '/' );
	}
);
add_filter(
	'login_headertext',
	function () {
		return get_bloginfo( 'name', 'display' );
	}
);
add_filter(
	'login_body_class',
	function ( $classes ) {
		$classes[] = 'n4s-login';
		return $classes;
	}
);
