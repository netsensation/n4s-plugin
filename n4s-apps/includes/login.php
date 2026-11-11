<?php
/**
 * Eigenes Login-Design: ersetzt das Aussehen der WordPress-Anmeldung (wp-login.php)
 * durch eine Anmeldung mit dem Logo der Website und den Farben des aktiven Themes.
 * Anmeldung, Passwort vergessen und Registrierung laufen weiter über WordPress
 * (Sicherheit, Zwei-Faktor-Plugins und Weiterleitungen funktionieren unverändert).
 * Wird nur geladen, wenn unter n4s → Funktionen eingeschaltet.
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ Farben */

/** Farbwert als #rrggbb normalisieren (#rgb, #rrggbb, #rrggbbaa, rgb()/rgba()), sonst ''. */
function n4s_login_hex( $v ) {
	$v = strtolower( trim( (string) $v ) );
	if ( preg_match( '/^#?([0-9a-f]{3})$/', $v, $m ) ) {
		return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
	}
	if ( preg_match( '/^#?([0-9a-f]{6})([0-9a-f]{2})?$/', $v, $m ) ) {
		return '#' . $m[1];
	}
	if ( preg_match( '/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/', $v, $m ) ) {
		return sprintf( '#%02x%02x%02x', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
	}
	return '';
}

/** Farbe auflösen: Hex/RGB direkt, Theme-Verweise (var:preset|color|slug, var(--wp--preset--color--slug)) über die Palette. */
function n4s_login_resolve( $v, $palette ) {
	if ( ! is_string( $v ) || '' === $v ) {
		return '';
	}
	if ( preg_match( '/^var:preset\|color\|([a-z0-9_-]+)$/i', $v, $m ) || preg_match( '/var\(\s*--wp--preset--color--([a-z0-9_-]+)/i', $v, $m ) ) {
		$slug = strtolower( $m[1] );
		return isset( $palette[ $slug ] ) ? n4s_login_hex( $palette[ $slug ] ) : '';
	}
	return n4s_login_hex( $v );
}

/** Relative Helligkeit (0 = schwarz, 1 = weiß). */
function n4s_login_lum( $hex ) {
	$c = array();
	foreach ( array( 1, 3, 5 ) as $i ) {
		$x   = hexdec( substr( $hex, $i, 2 ) ) / 255;
		$c[] = $x <= 0.03928 ? $x / 12.92 : pow( ( $x + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/** Kontrastverhältnis zweier Farben (1 bis 21). */
function n4s_login_contrast( $a, $b ) {
	$la = n4s_login_lum( $a );
	$lb = n4s_login_lum( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/** Zwei Farben mischen ($t = Anteil von $b, 0 bis 1). */
function n4s_login_mix( $a, $b, $t ) {
	$o = '#';
	foreach ( array( 1, 3, 5 ) as $i ) {
		$x  = hexdec( substr( $a, $i, 2 ) );
		$y  = hexdec( substr( $b, $i, 2 ) );
		$o .= sprintf( '%02x', (int) round( $x + ( $y - $x ) * $t ) );
	}
	return $o;
}

/** Schriftfarbe mit gutem Kontrast auf einer Fläche. */
function n4s_login_on( $bg ) {
	return n4s_login_contrast( $bg, '#ffffff' ) >= n4s_login_contrast( $bg, '#111111' ) ? '#ffffff' : '#111111';
}

/**
 * Farben des aktiven Themes: Hintergrund, Text und Hauptfarbe (Buttons, Links).
 * Quellen: Neve, theme.json/Website-Editor, Divi, Elementor-Kit, Astra, GeneratePress, Customizer.
 * Fehlende Werte: WordPress-Standard. Filter n4s_login_colors zum Überschreiben.
 */
function n4s_login_colors() {
	$c = array( 'bg' => '', 'text' => '', 'primary' => '' );

	// Neve (globale Farbpalette im Customizer, aktive Palette, Button-Farbe).
	$nv = get_theme_mod( 'neve_global_colors' );
	if ( is_array( $nv ) && ! empty( $nv['palettes'] ) && is_array( $nv['palettes'] ) ) {
		$act  = ( ! empty( $nv['activePalette'] ) && isset( $nv['palettes'][ $nv['activePalette'] ] ) ) ? $nv['activePalette'] : 'base';
		$npal = isset( $nv['palettes'][ $act ]['colors'] ) && is_array( $nv['palettes'][ $act ]['colors'] ) ? $nv['palettes'][ $act ]['colors'] : array();
		$nres = function ( $v ) use ( $npal ) {
			if ( is_string( $v ) && preg_match( '/var\(\s*--([a-z0-9_-]+)/i', $v, $m ) ) {
				$v = isset( $npal[ $m[1] ] ) ? $npal[ $m[1] ] : '';
			}
			return n4s_login_hex( $v );
		};
		$btn          = get_theme_mod( 'neve_button_appearance' );
		$c['primary'] = is_array( $btn ) && ! empty( $btn['background'] ) ? $nres( $btn['background'] ) : '';
		if ( ! $c['primary'] ) {
			$c['primary'] = $nres( 'var(--nv-primary-accent)' );
		}
		$c['bg']   = $nres( 'var(--nv-site-bg)' );
		$c['text'] = $nres( 'var(--nv-text-color)' );
	}

	// Block-Themes und Themes mit theme.json (Website-Editor, globale Stile).
	$has_json = function_exists( 'wp_theme_has_theme_json' ) ? wp_theme_has_theme_json() : ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() );
	if ( $has_json && function_exists( 'wp_get_global_styles' ) && function_exists( 'wp_get_global_settings' ) ) {
		$palette = array();
		$pal     = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'default', 'theme', 'custom' ) as $origin ) {
			if ( ! empty( $pal[ $origin ] ) && is_array( $pal[ $origin ] ) ) {
				foreach ( $pal[ $origin ] as $p ) {
					if ( isset( $p['slug'], $p['color'] ) ) {
						$palette[ strtolower( $p['slug'] ) ] = $p['color'];
					}
				}
			}
		}
		$s           = wp_get_global_styles();
		if ( ! $c['bg'] ) {
			$c['bg'] = n4s_login_resolve( isset( $s['color']['background'] ) ? $s['color']['background'] : '', $palette );
		}
		if ( ! $c['text'] ) {
			$c['text'] = n4s_login_resolve( isset( $s['color']['text'] ) ? $s['color']['text'] : '', $palette );
		}
		$candidates  = array(
			isset( $s['elements']['button']['color']['background'] ) ? $s['elements']['button']['color']['background'] : '',
			isset( $s['elements']['link']['color']['text'] ) ? $s['elements']['link']['color']['text'] : '',
		);
		foreach ( array( 'primary', 'accent', 'accent-1', 'vivid-primary', 'secondary', 'accent-2' ) as $slug ) {
			$candidates[] = isset( $palette[ $slug ] ) ? $palette[ $slug ] : '';
		}
		foreach ( $c['primary'] ? array() : $candidates as $cand ) {
			$hex = n4s_login_resolve( $cand, $palette );
			// Hauptfarbe soll sich vom Hintergrund abheben (sonst nächste Quelle); #32373c ist der WordPress-Standard-Button, keine Theme-Farbe.
			if ( $hex && '#32373c' !== $hex && ( ! $c['bg'] || n4s_login_contrast( $hex, $c['bg'] ) >= 1.6 ) ) {
				$c['primary'] = $hex;
				break;
			}
		}
	}

	// Divi.
	$divi = get_option( 'et_divi' );
	if ( is_array( $divi ) ) {
		if ( ! $c['primary'] && ! empty( $divi['accent_color'] ) ) {
			$c['primary'] = n4s_login_hex( $divi['accent_color'] );
		}
		if ( ! $c['text'] && ! empty( $divi['font_color'] ) ) {
			$c['text'] = n4s_login_hex( $divi['font_color'] );
		}
	}

	// Elementor (globale Farben des aktiven Kits).
	$kit = (int) get_option( 'elementor_active_kit' );
	if ( $kit ) {
		$ks = get_post_meta( $kit, '_elementor_page_settings', true );
		if ( is_array( $ks ) && ! empty( $ks['system_colors'] ) && is_array( $ks['system_colors'] ) ) {
			$sys = array();
			foreach ( $ks['system_colors'] as $sc ) {
				if ( isset( $sc['_id'], $sc['color'] ) ) {
					$sys[ $sc['_id'] ] = n4s_login_hex( $sc['color'] );
				}
			}
			if ( ! $c['primary'] ) {
				$c['primary'] = ! empty( $sys['accent'] ) ? $sys['accent'] : ( ! empty( $sys['primary'] ) ? $sys['primary'] : '' );
			}
			if ( ! $c['text'] && ! empty( $sys['text'] ) ) {
				$c['text'] = $sys['text'];
			}
		}
		if ( is_array( $ks ) && ! $c['bg'] && ! empty( $ks['body_background_color'] ) ) {
			$c['bg'] = n4s_login_hex( $ks['body_background_color'] );
		}
	}

	// Astra.
	$astra = get_option( 'astra-settings' );
	if ( is_array( $astra ) ) {
		if ( ! $c['primary'] && ! empty( $astra['theme-color'] ) && is_string( $astra['theme-color'] ) ) {
			$c['primary'] = n4s_login_hex( $astra['theme-color'] );
		}
		if ( ! $c['text'] && ! empty( $astra['text-color'] ) && is_string( $astra['text-color'] ) ) {
			$c['text'] = n4s_login_hex( $astra['text-color'] );
		}
	}

	// GeneratePress.
	$gp = get_option( 'generate_settings' );
	if ( is_array( $gp ) ) {
		foreach ( array( 'primary' => 'link_color', 'text' => 'text_color', 'bg' => 'background_color' ) as $k => $o ) {
			if ( ! $c[ $k ] && ! empty( $gp[ $o ] ) && is_string( $gp[ $o ] ) ) {
				$c[ $k ] = n4s_login_hex( $gp[ $o ] );
			}
		}
	}

	// Customizer (klassische Themes).
	foreach ( array( 'accent_color', 'primary_color', 'link_color', 'theme_color' ) as $mod ) {
		if ( $c['primary'] ) {
			break;
		}
		$v = get_theme_mod( $mod );
		if ( is_string( $v ) && $v ) {
			$c['primary'] = n4s_login_hex( $v );
		}
	}
	if ( ! $c['bg'] ) {
		$v = get_theme_mod( 'background_color' );
		if ( is_string( $v ) && $v ) {
			$c['bg'] = n4s_login_hex( $v );
		}
	}

	$c = apply_filters( 'n4s_login_colors', $c );

	// Standardwerte und Lesbarkeit sicherstellen.
	$bg      = n4s_login_hex( isset( $c['bg'] ) ? $c['bg'] : '' );
	$bg      = $bg ? $bg : '#f0f0f1';
	$dark    = n4s_login_lum( $bg ) < 0.18;
	$text    = n4s_login_hex( isset( $c['text'] ) ? $c['text'] : '' );
	$primary = n4s_login_hex( isset( $c['primary'] ) ? $c['primary'] : '' );
	$primary = $primary ? $primary : ( $dark ? '#72aee6' : '#2271b1' );
	$card    = $dark ? n4s_login_mix( $bg, '#ffffff', 0.07 ) : '#ffffff';
	if ( ! $text || n4s_login_contrast( $text, $card ) < 4.5 ) {
		$text = $dark ? '#f0f0f1' : '#1e1e1e';
	}
	// Links in der Hauptfarbe nur, wenn sie auf der Fläche lesbar ist.
	$link = n4s_login_contrast( $primary, $card ) >= 3 ? $primary : $text;

	return array(
		'bg'       => $bg,
		'card'     => $card,
		'text'     => $text,
		'muted'    => n4s_login_mix( $text, $card, 0.35 ),
		'border'   => n4s_login_mix( $text, $card, 0.78 ),
		'field'    => $dark ? n4s_login_mix( $card, '#ffffff', 0.05 ) : '#ffffff',
		'primary'  => $primary,
		'on'       => n4s_login_on( $primary ),
		'link'     => $link,
		'outside'  => n4s_login_contrast( $primary, $bg ) >= 3 ? $primary : n4s_login_on( $bg ),
		'dark'     => $dark,
	);
}

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
