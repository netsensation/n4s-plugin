<?php
/**
 * Farben des aktiven Themes (Hintergrund, Text, Hauptfarbe) für Login-Design und Snippets.
 * Quellen: Neve, theme.json/Website-Editor, Divi, Elementor-Kit, Astra, GeneratePress, Customizer.
 * Immer ladbar (nur Funktionen, keine Hooks).
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
