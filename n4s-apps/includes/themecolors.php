<?php
/**
 * Farben aus dem aktiven Theme oder einem Theme-ZIP lesen und daraus Farbschemen vorschlagen.
 * Immer geladen (auch bei ausgeschalteter Theme-Engine), damit auch Apps (z. B. Wärmepumpen) die Funktionen nutzen können:
 * n4s_theme_read_colors(), n4s_theme_proposals(), n4s_theme_zip_colors(), n4s_theme_hex(), n4s_theme_hsl(), n4s_theme_from_hsl().
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ Farben auslesen und Vorschläge */

/** Hex-Farbe normalisieren (#rgb oder #rrggbb, klein), sonst leer. */
function n4s_theme_hex( $c ) {
	$c = strtolower( trim( (string) $c ) );
	if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $c, $m ) ) {
		return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
	}
	return preg_match( '/^#[0-9a-f]{6}$/', $c ) ? $c : '';
}

function n4s_theme_hsl( $hex ) {
	$r = hexdec( substr( $hex, 1, 2 ) ) / 255;
	$g = hexdec( substr( $hex, 3, 2 ) ) / 255;
	$b = hexdec( substr( $hex, 5, 2 ) ) / 255;
	$mx = max( $r, $g, $b );
	$mn = min( $r, $g, $b );
	$l  = ( $mx + $mn ) / 2;
	if ( $mx === $mn ) {
		return array( 0.0, 0.0, $l );
	}
	$d = $mx - $mn;
	$s = $l > 0.5 ? $d / ( 2 - $mx - $mn ) : $d / ( $mx + $mn );
	if ( $mx === $r ) {
		$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
	} elseif ( $mx === $g ) {
		$h = ( $b - $r ) / $d + 2;
	} else {
		$h = ( $r - $g ) / $d + 4;
	}
	return array( $h * 60, $s, $l );
}

function n4s_theme_from_hsl( $h, $s, $l ) {
	$h = fmod( fmod( $h, 360 ) + 360, 360 ) / 360;
	$s = max( 0, min( 1, $s ) );
	$l = max( 0, min( 1, $l ) );
	if ( 0.0 === (float) $s ) {
		$v = (int) round( $l * 255 );
		return sprintf( '#%02x%02x%02x', $v, $v, $v );
	}
	$q  = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
	$p  = 2 * $l - $q;
	$f  = function ( $t ) use ( $p, $q ) {
		$t = fmod( $t + 1, 1 );
		if ( $t < 1 / 6 ) {
			return $p + ( $q - $p ) * 6 * $t;
		}
		if ( $t < 1 / 2 ) {
			return $q;
		}
		if ( $t < 2 / 3 ) {
			return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		}
		return $p;
	};
	return sprintf( '#%02x%02x%02x', (int) round( $f( $h + 1 / 3 ) * 255 ), (int) round( $f( $h ) * 255 ), (int) round( $f( $h - 1 / 3 ) * 255 ) );
}

function n4s_theme_lum( $hex ) {
	$ch = array();
	foreach ( array( 1, 3, 5 ) as $i ) {
		$v    = hexdec( substr( $hex, $i, 2 ) ) / 255;
		$ch[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $ch[0] + 0.7152 * $ch[1] + 0.0722 * $ch[2];
}

function n4s_theme_contrast( $a, $b ) {
	$la = n4s_theme_lum( $a );
	$lb = n4s_theme_lum( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/** Lesbare Textfarbe (weiß oder fast schwarz) auf einem Hintergrund. */
function n4s_theme_on_color( $bg ) {
	return n4s_theme_contrast( $bg, '#ffffff' ) >= n4s_theme_contrast( $bg, '#111111' ) ? '#ffffff' : '#111111';
}

/** Farbe in Richtung heller (dir 1) oder dunkler (dir -1) verschieben, bis der Kontrast zum Hintergrund reicht. */
function n4s_theme_fit( $hex, $bg, $min, $dir ) {
	list( $h, $s, $l ) = n4s_theme_hsl( $hex );
	for ( $i = 0; $i < 40 && n4s_theme_contrast( n4s_theme_from_hsl( $h, $s, $l ), $bg ) < $min; $i++ ) {
		$l += 0.02 * $dir;
		if ( $l > 1 || $l < 0 ) {
			break;
		}
	}
	return n4s_theme_from_hsl( $h, $s, max( 0, min( 1, $l ) ) );
}

/**
 * Drei Vorschläge aus einem Theme. $base: Hauptfarbe (Text) oder Farben des Themes (accent, bg, text, hover, light, dark, darktext).
 * Mit $label (Theme-Name): 1. die eingelesenen Farben des Themes, 2. das Gegenstück (Dunkel bei hellem, Hell bei dunklem Theme), 3. Komplementär.
 * Ohne $label: Hell, Dunkel, Komplementär aus der Hauptfarbe.
 */
function n4s_theme_proposals( $base, $label = '' ) {
	$own    = is_array( $base ) ? $base : array( 'accent' => $base );
	$accent = n4s_theme_hex( isset( $own['accent'] ) ? $own['accent'] : '' );
	if ( '' === $accent ) {
		return array();
	}
	list( $h, $s, $l ) = n4s_theme_hsl( $accent );
	$s_acc = max( 0.35, min( 0.9, $s ) );

	// Hell: weißer Grund, Hauptfarbe so abgedunkelt, dass sie auf Weiß lesbar ist.
	$a1  = n4s_theme_fit( $accent, '#ffffff', 4.5, -1 );
	$h1  = n4s_theme_fit( n4s_theme_from_hsl( $h, $s_acc, max( 0.1, min( 0.9, n4s_theme_hsl( $a1 )[2] - 0.08 ) ) ), '#ffffff', 7, -1 );
	$light = array(
		'bg' => '#ffffff', 'text' => '#1f2328', 'accent' => $a1, 'hover' => $h1,
		'light' => n4s_theme_from_hsl( $h, min( 0.3, $s_acc ), 0.96 ), 'dark' => n4s_theme_from_hsl( $h, min( 0.35, $s_acc ), 0.1 ),
		'darktext' => '#ffffff', 'btntext' => n4s_theme_on_color( $a1 ), 'heading' => '',
	);

	// Dunkel: dunkler Grund im Farbton der Hauptfarbe, Hauptfarbe aufgehellt.
	$bg2 = n4s_theme_from_hsl( $h, min( 0.3, $s_acc ), 0.09 );
	$a2  = n4s_theme_fit( n4s_theme_from_hsl( $h, $s_acc, max( 0.55, $l ) ), $bg2, 4.5, 1 );
	$dark = array(
		'bg' => $bg2, 'text' => '#e6e9ee', 'accent' => $a2, 'hover' => n4s_theme_fit( n4s_theme_from_hsl( $h, $s_acc, min( 0.85, n4s_theme_hsl( $a2 )[2] + 0.1 ) ), $bg2, 7, 1 ),
		'light' => n4s_theme_from_hsl( $h, min( 0.28, $s_acc ), 0.15 ), 'dark' => n4s_theme_from_hsl( $h, min( 0.3, $s_acc ), 0.04 ),
		'darktext' => '#e6e9ee', 'btntext' => n4s_theme_on_color( $a2 ), 'heading' => '',
	);

	// Komplementär: Hauptfarbe als Grund (abgedunkelt für weißen Text), Gegenfarbe als Akzent.
	$bg3 = n4s_theme_fit( n4s_theme_from_hsl( $h, $s_acc, min( $l, 0.45 ) ), '#ffffff', 4.5, -1 );
	$ch  = $h + 180;
	$a3  = n4s_theme_fit( n4s_theme_from_hsl( $ch, max( 0.6, $s_acc ), 0.62 ), $bg3, 3.5, 1 );
	$comp = array(
		'bg' => $bg3, 'text' => '#ffffff', 'accent' => $a3, 'hover' => n4s_theme_fit( n4s_theme_from_hsl( $ch, max( 0.6, $s_acc ), min( 0.85, n4s_theme_hsl( $a3 )[2] + 0.1 ) ), $bg3, 4.5, 1 ),
		'light' => n4s_theme_from_hsl( $h, $s_acc, min( 0.6, n4s_theme_hsl( $bg3 )[2] + 0.08 ) ), 'dark' => n4s_theme_from_hsl( $h, $s_acc, max( 0.05, n4s_theme_hsl( $bg3 )[2] - 0.14 ) ),
		'darktext' => '#ffffff', 'btntext' => n4s_theme_on_color( $a3 ), 'heading' => '#ffffff',
	);
	$blank = array( 'hdrbg' => '', 'hdrtext' => '', 'ftrbg' => '', 'ftrtext' => '' ); // Header und Footer folgen dem Schema (oder dem Theme)
	$light = array_merge( $light, $blank );
	$dark  = array_merge( $dark, $blank );
	$comp  = array_merge( $comp, $blank );
	$set   = array( 'bg', 'text', 'hover', 'light', 'dark', 'darktext' );
	$have  = false;
	foreach ( $set as $k ) {
		if ( ! empty( $own[ $k ] ) && n4s_theme_hex( $own[ $k ] ) ) {
			$have = true;
		}
	}
	if ( '' === $label || ! $have ) {
		return array(
			array( 'name' => n4s_t( 'Hell', 'Light' ), 'colors' => $light ),
			array( 'name' => n4s_t( 'Dunkel', 'Dark' ), 'colors' => $dark ),
			array( 'name' => n4s_t( 'Komplementär', 'Complementary' ), 'colors' => $comp ),
		);
	}
	// Eigene Farben des Themes; Fehlendes wird passend zur Helligkeit ergänzt.
	$bg    = isset( $own['bg'] ) ? n4s_theme_hex( $own['bg'] ) : '';
	$tx    = isset( $own['text'] ) ? n4s_theme_hex( $own['text'] ) : '';
	$is_light = $bg ? n4s_theme_lum( $bg ) > 0.35 : ( $tx ? n4s_theme_lum( $tx ) < 0.5 : true );
	$mine  = $is_light ? $light : $dark;
	foreach ( $set as $k ) {
		if ( ! empty( $own[ $k ] ) && n4s_theme_hex( $own[ $k ] ) ) {
			$mine[ $k ] = n4s_theme_hex( $own[ $k ] );
		}
	}
	$mine['accent']  = $accent;
	$mine['btntext'] = n4s_theme_on_color( $accent );
	$name = mb_strlen( $label ) > 20 ? mb_substr( $label, 0, 19 ) . '…' : $label;
	return array(
		array( 'name' => $name, 'colors' => $mine ),
		$is_light ? array( 'name' => n4s_t( 'Dunkel', 'Dark' ), 'colors' => $dark ) : array( 'name' => n4s_t( 'Hell', 'Light' ), 'colors' => $light ),
		array( 'name' => n4s_t( 'Komplementär', 'Complementary' ), 'colors' => $comp ),
	);
}

/** Farben des aktiven Themes auslesen (ohne Code auszuführen): Neve-Palette, theme.json/Block-Palette oder Theme-Support. */
function n4s_theme_read_colors() {
	$th   = wp_get_theme();
	$name = $th->get( 'Name' );
	$pal  = array();
	if ( 'neve' === strtolower( (string) get_template() ) ) {
		$g = get_theme_mod( 'neve_global_colors' );
		$g = is_string( $g ) ? json_decode( $g, true ) : $g;
		$c = array();
		if ( is_array( $g ) && ! empty( $g['palettes'] ) ) {
			$act = isset( $g['activePalette'] ) && isset( $g['palettes'][ $g['activePalette'] ] ) ? $g['activePalette'] : key( $g['palettes'] );
			$c   = isset( $g['palettes'][ $act ]['colors'] ) ? (array) $g['palettes'][ $act ]['colors'] : array();
		}
		$def = array( 'nv-primary-accent' => '#2f5aae', 'nv-secondary-accent' => '#24478c', 'nv-site-bg' => '#ffffff', 'nv-light-bg' => '#f4f5f7', 'nv-dark-bg' => '#121212', 'nv-text-color' => '#272626', 'nv-text-dark-bg' => '#ffffff' );
		$c   = array_merge( $def, array_filter( $c, 'is_string' ) );
		$map = array( 'nv-site-bg' => 'bg', 'nv-text-color' => 'text', 'nv-primary-accent' => 'accent', 'nv-secondary-accent' => 'hover', 'nv-light-bg' => 'light', 'nv-dark-bg' => 'dark', 'nv-text-dark-bg' => 'darktext' );
		$out = array();
		foreach ( $map as $k => $key ) {
			$out[ $key ] = n4s_theme_hex( isset( $c[ $k ] ) ? $c[ $k ] : '' );
		}
		return array( 'source' => $name, 'colors' => array_filter( $out ) );
	}
	// Block-Themes und Themes mit theme.json: Palette der globalen Einstellungen.
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$p = wp_get_global_settings( array( 'color', 'palette' ) );
		if ( is_array( $p ) ) {
			$list = isset( $p['theme'] ) ? $p['theme'] : ( isset( $p['default'] ) ? array() : $p );
			if ( ! $list && isset( $p['custom'] ) ) {
				$list = $p['custom'];
			}
			foreach ( (array) $list as $e ) {
				if ( is_array( $e ) && isset( $e['slug'], $e['color'] ) ) {
					$pal[ $e['slug'] ] = n4s_theme_hex( $e['color'] );
				}
			}
		}
	}
	if ( ! $pal ) { // Klassische Themes: Editor-Palette per add_theme_support.
		$sup = get_theme_support( 'editor-color-palette' );
		if ( is_array( $sup ) && isset( $sup[0] ) && is_array( $sup[0] ) ) {
			foreach ( $sup[0] as $e ) {
				if ( is_array( $e ) && isset( $e['slug'], $e['color'] ) ) {
					$pal[ $e['slug'] ] = n4s_theme_hex( $e['color'] );
				}
			}
		}
	}
	$pal = array_filter( $pal );
	$pick = function ( $slugs ) use ( $pal ) {
		foreach ( $slugs as $sl ) {
			if ( ! empty( $pal[ $sl ] ) ) {
				return $pal[ $sl ];
			}
		}
		return '';
	};
	$out = array(
		'bg'       => $pick( array( 'base', 'background', 'white', 'site-bg' ) ),
		'text'     => $pick( array( 'contrast', 'foreground', 'text', 'black' ) ),
		'accent'   => $pick( array( 'primary', 'accent', 'accent-1', 'brand', 'main' ) ),
		'hover'    => $pick( array( 'secondary', 'accent-2' ) ),
		'light'    => $pick( array( 'tertiary', 'accent-5', 'light', 'light-gray' ) ),
	);
	if ( '' === $out['accent'] ) { // erste deutlich farbige Palettenfarbe
		foreach ( $pal as $hx ) {
			if ( n4s_theme_hsl( $hx )[1] > 0.25 && n4s_theme_hsl( $hx )[2] > 0.15 && n4s_theme_hsl( $hx )[2] < 0.85 ) {
				$out['accent'] = $hx;
				break;
			}
		}
	}
	if ( '' === $out['bg'] ) {
		$bgc = get_theme_mod( 'background_color' );
		$out['bg'] = $bgc ? n4s_theme_hex( '#' . ltrim( (string) $bgc, '#' ) ) : '';
	}
	return array( 'source' => $name, 'colors' => array_filter( $out ) );
}

/** Farben aus einem Theme-ZIP lesen, ohne Dateien zu entpacken oder Code auszuführen (theme.json und CSS). */
function n4s_theme_zip_colors( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'n4s', n4s_t( 'Auf diesem Server fehlt die ZIP-Unterstützung (PHP-Erweiterung zip).', 'This server lacks ZIP support (PHP extension zip).' ) );
	}
	$z = new ZipArchive();
	if ( true !== $z->open( $path ) ) {
		return new WP_Error( 'n4s', n4s_t( 'Die Datei ist kein gültiges ZIP.', 'The file is not a valid ZIP.' ) );
	}
	$name  = '';
	$pal   = array();
	$count = array();
	$files = 0;
	for ( $i = 0; $i < $z->numFiles && $i < 4000; $i++ ) {
		$st = $z->statIndex( $i );
		$n  = isset( $st['name'] ) ? (string) $st['name'] : '';
		if ( '' === $n || '/' === substr( $n, -1 ) || false !== strpos( $n, '..' ) || $st['size'] > 1500000 ) {
			continue;
		}
		$ext = strtolower( pathinfo( $n, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'json', 'css' ), true ) || substr_count( $n, '/' ) > 3 || $files >= 14 ) {
			continue;
		}
		$body = $z->getFromIndex( $i );
		if ( ! is_string( $body ) ) {
			continue;
		}
		$files++;
		$base = strtolower( basename( $n ) );
		if ( 'style.css' === $base && '' === $name && preg_match( '/^[ \t\/*#@]*Theme Name:\s*(.+)$/mi', substr( $body, 0, 8192 ), $m ) ) {
			$name = trim( $m[1], " \t*/" );
		}
		if ( 'theme.json' === $base ) {
			$j = json_decode( $body, true );
			if ( isset( $j['settings']['color']['palette'] ) && is_array( $j['settings']['color']['palette'] ) ) {
				foreach ( $j['settings']['color']['palette'] as $e ) {
					if ( is_array( $e ) && isset( $e['slug'], $e['color'] ) && n4s_theme_hex( $e['color'] ) ) {
						$pal[ $e['slug'] ] = n4s_theme_hex( $e['color'] );
					}
				}
			}
			continue;
		}
		if ( 'css' === $ext ) {
			// Variablen mit sprechendem Namen
			if ( preg_match_all( '/--([a-z0-9_-]*(?:primary|accent|brand|main)[a-z0-9_-]*)\s*:\s*(#[0-9a-fA-F]{3,6})\b/', $body, $mm, PREG_SET_ORDER ) ) {
				foreach ( $mm as $v ) {
					if ( n4s_theme_hex( $v[2] ) && ! isset( $pal[ $v[1] ] ) ) {
						$pal[ $v[1] ] = n4s_theme_hex( $v[2] );
					}
				}
			}
			if ( preg_match_all( '/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $body, $hx ) ) {
				foreach ( $hx[0] as $c ) {
					$c = n4s_theme_hex( $c );
					if ( $c ) {
						$count[ $c ] = ( isset( $count[ $c ] ) ? $count[ $c ] : 0 ) + 1;
					}
				}
			}
		}
	}
	$z->close();
	$accent = '';
	foreach ( array( 'primary', 'accent', 'accent-1', 'brand', 'main' ) as $sl ) {
		if ( ! empty( $pal[ $sl ] ) ) {
			$accent = $pal[ $sl ];
			break;
		}
	}
	if ( '' === $accent ) { // benannte Variable, sonst die häufigste deutlich farbige Farbe
		foreach ( $pal as $hx ) {
			list( , $s, $l ) = n4s_theme_hsl( $hx );
			if ( $s > 0.25 && $l > 0.15 && $l < 0.85 ) {
				$accent = $hx;
				break;
			}
		}
	}
	if ( '' === $accent ) {
		arsort( $count );
		foreach ( array_keys( $count ) as $hx ) {
			list( , $s, $l ) = n4s_theme_hsl( $hx );
			if ( $s > 0.3 && $l > 0.15 && $l < 0.85 ) {
				$accent = $hx;
				break;
			}
		}
	}
	if ( '' === $accent ) {
		return new WP_Error( 'n4s', n4s_t( 'Im ZIP wurde keine Hauptfarbe gefunden (weder theme.json noch Farben in CSS-Dateien).', 'No main color was found in the ZIP (neither theme.json nor colors in CSS files).' ) );
	}
	$pick = function ( $slugs ) use ( $pal ) {
		foreach ( $slugs as $sl ) {
			if ( ! empty( $pal[ $sl ] ) ) {
				return $pal[ $sl ];
			}
		}
		return '';
	};
	$cols = array_filter( array(
		'bg'    => $pick( array( 'base', 'background', 'white', 'site-bg' ) ),
		'text'  => $pick( array( 'contrast', 'foreground', 'text', 'black' ) ),
		'hover' => $pick( array( 'secondary', 'accent-2' ) ),
		'light' => $pick( array( 'tertiary', 'accent-5', 'light', 'light-gray' ) ),
	) );
	return array( 'name' => '' !== $name ? $name : 'ZIP', 'accent' => $accent, 'colors' => $cols );
}
