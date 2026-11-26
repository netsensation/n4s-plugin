<?php
/**
 * n4s Theme-Engine: Menüs je Anmeldestatus und Rolle, Menüpunkte nach Sichtbarkeit, Anmelden/Abmelden als Menüpunkt,
 * Seitengestaltung (Kopfzeile, Fußzeile, Titel ausblenden) und bis zu drei Farbschemen je Seite.
 *
 * Alles läuft über WordPress-Hooks und braucht keine Änderung am Theme. Die Menü-Funktionen sind theme-unabhängig.
 * Die Farbschemen setzen die Farbvariablen von Neve (--nv-…); bei anderen Themes färben sie Hintergrund und Text der Seite.
 * Jedes Modul ist im Tab „Customize“ einzeln schaltbar (Standard: aus).
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------ Einstellungen */

/** Farbfelder eines Farbschemas: Schlüssel => [ Beschriftung de, en, CSS-Variablen ]. */
function n4s_theme_color_fields() {
	return array(
		'bg'       => array( n4s_t( 'Seitenhintergrund', 'Page background' ), array( '--nv-site-bg' ) ),
		'text'     => array( n4s_t( 'Text', 'Text' ), array( '--nv-text-color' ) ),
		'accent'   => array( n4s_t( 'Hauptfarbe (Links, Buttons)', 'Main color (links, buttons)' ), array( '--nv-primary-accent' ) ),
		'hover'    => array( n4s_t( 'Hover-Farbe', 'Hover color' ), array( '--nv-secondary-accent' ) ),
		'light'    => array( n4s_t( 'Helle Fläche (Karten, Linien)', 'Light surface (cards, lines)' ), array( '--nv-light-bg' ) ),
		'dark'     => array( n4s_t( 'Dunkle Fläche (z. B. untere Fußzeile)', 'Dark surface (e.g. bottom footer)' ), array( '--nv-dark-bg' ) ),
		'darktext' => array( n4s_t( 'Text auf dunkler Fläche', 'Text on dark surface' ), array( '--nv-text-dark-bg' ) ),
		'btntext'  => array( n4s_t( 'Text auf Buttons', 'Button text' ), array( '--primarybtncolor', '--primarybtnhovercolor' ) ),
		'heading'  => array( n4s_t( 'Überschriften (leer = Textfarbe)', 'Headings (empty = text color)' ), array( '--headingcolor' ) ),
		'hdrbg'    => array( n4s_t( 'Header: Hintergrund (leer = wie Seite)', 'Header: background (empty = like page)' ), array() ),
		'hdrtext'  => array( n4s_t( 'Header: Text und Menü (leer = wie Seite)', 'Header: text and menu (empty = like page)' ), array() ),
		'ftrbg'    => array( n4s_t( 'Footer: Hintergrund (leer = Theme)', 'Footer: background (empty = theme)' ), array() ),
		'ftrtext'  => array( n4s_t( 'Footer: Text (leer = Theme)', 'Footer: text (empty = theme)' ), array() ),
	);
}

function n4s_theme_defaults() {
	return array(
		'on'          => array( 'menus' => 0, 'items' => 0, 'loginout' => 0, 'newtab' => 0, 'adminbar' => 0, 'widget' => 0, 'above' => 0, 'abovefooter' => 0, 'page' => 0, 'schemes' => 0 ),
		'above'       => array( 'align' => 'right', 'bg' => '', 'text' => '' ),
		'abovefooter' => array( 'align' => 'right', 'bg' => '', 'text' => '' ),
		'adminbar'    => array( 'parent' => 'site-name', 'hide' => array() ),
		'menus'   => array(),
		'default' => 0,
		'schemes' => array(
			1 => array(
				'name'   => n4s_t( 'Hell', 'Light' ),
				'colors' => array( 'bg' => '#ffffff', 'text' => '#272626', 'accent' => '#2f5aae', 'hover' => '#24478c', 'light' => '#f4f5f7', 'dark' => '#121212', 'darktext' => '#ffffff', 'btntext' => '#ffffff', 'heading' => '', 'hdrbg' => '', 'hdrtext' => '', 'ftrbg' => '', 'ftrtext' => '' ),
			),
			2 => array(
				'name'   => n4s_t( 'Dunkel', 'Dark' ),
				'colors' => array( 'bg' => '#0f1419', 'text' => '#e6e9ee', 'accent' => '#4098e0', 'hover' => '#6ec1f5', 'light' => '#1a212a', 'dark' => '#06090c', 'darktext' => '#e6e9ee', 'btntext' => '#0f1419', 'heading' => '', 'hdrbg' => '', 'hdrtext' => '', 'ftrbg' => '', 'ftrtext' => '' ),
			),
			3 => array(
				'name'   => n4s_t( 'Akzent', 'Accent' ),
				'colors' => array( 'bg' => '#2f5aae', 'text' => '#ffffff', 'accent' => '#ffd54a', 'hover' => '#ffe27d', 'light' => '#3a68c2', 'dark' => '#1d3a75', 'darktext' => '#ffffff', 'btntext' => '#1d1d1d', 'heading' => '#ffffff', 'hdrbg' => '', 'hdrtext' => '', 'ftrbg' => '', 'ftrtext' => '' ),
			),
		),
	);
}

function n4s_theme_opt( $reset = false ) {
	static $o = null;
	if ( null === $o || $reset ) {
		$d = n4s_theme_defaults();
		$s = get_option( 'n4s_theme', array() );
		$s = is_array( $s ) ? $s : array();
		$o = $d;
		foreach ( array( 'on', 'menus', 'default', 'above', 'abovefooter', 'adminbar' ) as $k ) {
			if ( isset( $s[ $k ] ) ) {
				$o[ $k ] = is_array( $d[ $k ] ) ? array_merge( $d[ $k ], (array) $s[ $k ] ) : $s[ $k ];
			}
		}
		for ( $n = 1; $n <= 3; $n++ ) {
			if ( isset( $s['schemes'][ $n ] ) && is_array( $s['schemes'][ $n ] ) ) {
				$o['schemes'][ $n ]['name']   = isset( $s['schemes'][ $n ]['name'] ) ? (string) $s['schemes'][ $n ]['name'] : $d['schemes'][ $n ]['name'];
				$o['schemes'][ $n ]['colors'] = array_merge( $d['schemes'][ $n ]['colors'], isset( $s['schemes'][ $n ]['colors'] ) ? (array) $s['schemes'][ $n ]['colors'] : array() );
			}
		}
	}
	return $o;
}

function n4s_theme_on( $key ) {
	return ! empty( n4s_theme_opt()['on'][ $key ] );
}

/* ------------------------------------------------------------------ Menü je Anmeldestatus und Rolle */

/** Menü-ID für den aktuellen Besucher nach der Einstellung einer Position, 0 = Standard. */
function n4s_theme_menu_for( $cfg ) {
	if ( ! is_user_logged_in() ) {
		return empty( $cfg['gast'] ) ? 0 : (int) $cfg['gast'];
	}
	$user  = wp_get_current_user();
	$roles = isset( $cfg['roles'] ) ? (array) $cfg['roles'] : array();
	foreach ( array_keys( wp_roles()->get_names() ) as $r ) { // Reihenfolge der Rollen: Administrator zuerst.
		if ( in_array( $r, (array) $user->roles, true ) && ! empty( $roles[ $r ] ) ) {
			return (int) $roles[ $r ];
		}
	}
	return empty( $cfg['user'] ) ? 0 : (int) $cfg['user'];
}

/** Wahl für eine Menüposition: 0 = Standard, -1 = kein Menü, sonst Menü-ID. */
function n4s_theme_menu_choice( $location ) {
	$cfg = n4s_theme_opt()['menus'];
	return empty( $cfg[ $location ] ) ? 0 : (int) n4s_theme_menu_for( $cfg[ $location ] );
}

/** „Kein Menü“: die Position liefert nichts. */
add_filter(
	'pre_wp_nav_menu',
	function ( $out, $args ) {
		if ( n4s_theme_on( 'menus' ) && ! empty( $args->theme_location ) && n4s_theme_menu_choice( $args->theme_location ) < 0 ) {
			return '';
		}
		return $out;
	},
	9,
	2
);

add_filter(
	'wp_nav_menu_args',
	function ( $args ) {
		if ( ! n4s_theme_on( 'menus' ) || empty( $args['theme_location'] ) ) {
			return $args;
		}
		$cfg = n4s_theme_opt()['menus'];
		if ( empty( $cfg[ $args['theme_location'] ] ) ) {
			return $args;
		}
		$id = n4s_theme_menu_for( $cfg[ $args['theme_location'] ] );
		if ( $id && wp_get_nav_menu_object( $id ) ) {
			$args['menu'] = $id;
		}
		return $args;
	},
	1000
);

/* ------------------------------------------------------------------ Sichtbarkeit je Menüpunkt, Anmelden/Abmelden */

/** Sichtbarkeit eines Menüpunkts: array( mode => all|guest|user|roles, roles => [...] ). */
function n4s_theme_item_vis( $item_id ) {
	$v = get_post_meta( (int) $item_id, '_n4s_vis', true );
	return is_array( $v ) && ! empty( $v['mode'] ) ? $v : array( 'mode' => 'all', 'roles' => array() );
}

function n4s_theme_vis_ok( $v ) {
	$mode = isset( $v['mode'] ) ? $v['mode'] : 'all';
	if ( 'guest' === $mode ) {
		return ! is_user_logged_in();
	}
	if ( 'user' === $mode ) {
		return is_user_logged_in();
	}
	if ( 'roles' === $mode ) {
		$u = wp_get_current_user();
		return is_user_logged_in() && (bool) array_intersect( (array) $u->roles, (array) ( isset( $v['roles'] ) ? $v['roles'] : array() ) );
	}
	return true;
}

add_filter(
	'wp_nav_menu_objects',
	function ( $items ) {
		$loginout = n4s_theme_on( 'loginout' );
		$vis      = n4s_theme_on( 'items' );
		if ( ! $loginout && ! $vis ) {
			return $items;
		}
		$drop = array();
		foreach ( $items as $k => $it ) {
			$remove = false;
			if ( $loginout && 'custom' === $it->type && 0 === strpos( (string) $it->url, '#n4s-' ) ) {
				$in = is_user_logged_in();
				$ac = substr( (string) $it->url, 5 );
				if ( 'login' === $ac ) {
					$remove = $in;
					$it->url = wp_login_url();
				} elseif ( 'logout' === $ac ) {
					$remove = ! $in;
					$it->url = wp_logout_url();
				} elseif ( 'loginout' === $ac ) {
					$parts = array_map( 'trim', explode( '|', (string) $it->title ) );
					if ( $in ) {
						$it->title = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : n4s_t( 'Abmelden', 'Log out' );
						$it->url   = wp_logout_url();
					} else {
						$it->title = '' !== $parts[0] ? $parts[0] : n4s_t( 'Anmelden', 'Log in' );
						$it->url   = wp_login_url();
					}
				}
				$it->classes[] = 'n4s-' . $ac;
			}
			if ( ! $remove && $vis && ! n4s_theme_vis_ok( n4s_theme_item_vis( $it->ID ) ) ) {
				$remove = true;
			}
			if ( $remove ) {
				$drop[ $it->ID ] = true;
			}
		}
		if ( ! $drop ) {
			return $items;
		}
		// Untermenüs entfernter Punkte entfernen (wiederholen, bis nichts mehr dazukommt).
		do {
			$added = false;
			foreach ( $items as $it ) {
				if ( ! isset( $drop[ $it->ID ] ) && isset( $drop[ (int) $it->menu_item_parent ] ) ) {
					$drop[ $it->ID ] = true;
					$added           = true;
				}
			}
		} while ( $added );
		foreach ( $items as $k => $it ) {
			if ( isset( $drop[ $it->ID ] ) ) {
				unset( $items[ $k ] );
			}
		}
		return array_values( $items );
	},
	5
);

/** Link eines Menüpunkts in neuem Tab/Fenster öffnen (Feld je Menüpunkt, gilt in allen Menüs). */
add_filter(
	'nav_menu_link_attributes',
	function ( $atts, $item ) {
		if ( ! n4s_theme_on( 'newtab' ) || ! is_object( $item ) || empty( $item->ID ) || ! get_post_meta( (int) $item->ID, '_n4s_newtab', true ) ) {
			return $atts;
		}
		$atts['target'] = '_blank';
		$rel            = isset( $atts['rel'] ) ? (string) $atts['rel'] : '';
		foreach ( array( 'noopener', 'noreferrer' ) as $r ) {
			if ( false === strpos( $rel, $r ) ) {
				$rel = trim( $rel . ' ' . $r );
			}
		}
		$atts['rel'] = $rel;
		return $atts;
	},
	20,
	2
);

/* ------------------------------------------------------------------ Admin-Leiste oben (Dropdown pflegen) */

/** Standard-Einträge der Admin-Leiste, die sich ausblenden lassen: Knoten-ID => Beschriftung. */
function n4s_theme_adminbar_nodes() {
	return array(
		'wp-logo'     => n4s_t( 'WordPress-Logo und Menü', 'WordPress logo and menu' ),
		'dashboard'   => n4s_t( 'Dashboard (im Dropdown)', 'Dashboard (in the dropdown)' ),
		'themes'      => n4s_t( 'Themes (im Dropdown)', 'Themes (in the dropdown)' ),
		'widgets'     => n4s_t( 'Widgets (im Dropdown)', 'Widgets (in the dropdown)' ),
		'menus'       => n4s_t( 'Menüs (im Dropdown)', 'Menus (in the dropdown)' ),
		'customize'   => n4s_t( 'Customizer', 'Customizer' ),
		'updates'     => n4s_t( 'Updates', 'Updates' ),
		'comments'    => n4s_t( 'Kommentare', 'Comments' ),
		'new-content' => n4s_t( 'Neu', 'New' ),
	);
}

/** Einträge des Menüs „Admin-Leiste“ als Knoten, Standard-Einträge ausblenden. */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! n4s_theme_on( 'adminbar' ) ) {
			return;
		}
		$o = n4s_theme_opt()['adminbar'];
		foreach ( (array) $o['hide'] as $id ) {
			if ( isset( n4s_theme_adminbar_nodes()[ $id ] ) ) {
				$bar->remove_node( $id );
			}
		}
		$locs = get_nav_menu_locations();
		if ( empty( $locs['n4s-adminbar'] ) ) {
			return;
		}
		$items = wp_get_nav_menu_items( (int) $locs['n4s-adminbar'] );
		if ( ! $items ) {
			return;
		}
		$top    = ( 'top' === $o['parent'] ) ? '' : ( $bar->get_node( 'site-name' ) ? 'site-name' : '' );
		$vis    = n4s_theme_on( 'items' );
		$newtab = n4s_theme_on( 'newtab' );
		$drop   = array();
		foreach ( $items as $it ) {
			$hidden = isset( $drop[ (int) $it->menu_item_parent ] ) || ( $vis && ! n4s_theme_vis_ok( n4s_theme_item_vis( $it->ID ) ) ) || 0 === strpos( (string) $it->url, '#n4s-' );
			if ( $hidden ) {
				$drop[ (int) $it->ID ] = true;
				continue;
			}
			$blank = ( $newtab && get_post_meta( (int) $it->ID, '_n4s_newtab', true ) ) || '_blank' === $it->target;
			$meta  = array( 'class' => implode( ' ', array_filter( (array) $it->classes ) ) );
			if ( $blank ) {
				$meta['target'] = '_blank';
				$meta['rel']    = 'noopener noreferrer';
			}
			$bar->add_node(
				array(
					'id'     => 'n4s-ab-' . (int) $it->ID,
					'parent' => (int) $it->menu_item_parent ? 'n4s-ab-' . (int) $it->menu_item_parent : $top,
					'title'  => esc_html( $it->title ),
					'href'   => ( '' === (string) $it->url || '#' === $it->url ) ? false : $it->url,
					'meta'   => $meta,
				)
			);
		}
	},
	90
);

/* ------------------------------------------------------------------ Headermenü (über dem Header) und Footermenü (über dem Footer) */

add_action(
	'after_setup_theme',
	function () {
		$l = array();
		if ( n4s_theme_on( 'above' ) ) {
			$l['n4s-above'] = n4s_t( 'Headermenü (über dem Header)', 'Header menu (above the header)' );
		}
		if ( n4s_theme_on( 'abovefooter' ) ) {
			$l['n4s-above-footer'] = n4s_t( 'Footermenü (über dem Footer)', 'Footer menu (above the footer)' );
		}
		if ( n4s_theme_on( 'adminbar' ) ) {
			$l['n4s-adminbar'] = n4s_t( 'Admin-Leiste (Dropdown oben)', 'Admin bar (dropdown at the top)' );
		}
		if ( $l ) {
			register_nav_menus( $l );
		}
	},
	30
);

/** HTML einer Menüleiste: which = above (Headermenü) oder abovefooter (Footermenü). Leer, wenn kein Menü gilt. */
function n4s_theme_bar_html( $which ) {
	$hdr  = 'above' === $which;
	$menu = wp_nav_menu(
		array(
			'theme_location' => $hdr ? 'n4s-above' : 'n4s-above-footer',
			'container'      => false,
			'menu_class'     => 'n4s-bar-list',
			'menu_id'        => '',
			'depth'          => 1,
			'fallback_cb'    => '__return_empty_string',
			'echo'           => false,
		)
	);
	if ( ! $menu || '' === trim( wp_strip_all_tags( $menu ) ) ) {
		return '';
	}
	$o     = n4s_theme_opt()[ $which ];
	$bg    = sanitize_hex_color( (string) $o['bg'] );
	$tx    = sanitize_hex_color( (string) $o['text'] );
	$just  = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
	$al    = isset( $just[ $o['align'] ] ) ? $o['align'] : 'right';
	$style = ( $bg ? '--n4s-bar-bg:' . $bg . ';' : '' ) . ( $tx ? '--n4s-bar-tx:' . $tx . ';' : '' );
	static $css = false;
	$out = '';
	if ( ! $css ) {
		$css = true;
		// Farben: eigene Wahl, sonst automatisch die Farben von Header bzw. Footer (per Skript gelesen), sonst Neve-Variablen.
		$out = '<style id="n4s-bar-css">.n4s-bar{background:var(--n4s-bar-bg,var(--n4s-bar-bg-auto,var(--nv-site-bg,#fff)));color:var(--n4s-bar-tx,var(--n4s-bar-tx-auto,var(--nv-text-color,inherit)));font-size:.85rem;line-height:1.4;border-bottom:1px solid rgba(127,127,127,.25)}'
			. '.n4s-bar-footer{border-bottom:0;border-top:1px solid rgba(127,127,127,.25)}'
			. '.n4s-bar .n4s-bar-inner{max-width:var(--container,1170px);margin:0 auto;padding:6px 15px;display:flex}'
			. '.n4s-bar .n4s-bar-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:2px 18px}'
			. '.n4s-bar a{color:inherit;text-decoration:none}.n4s-bar a:hover{text-decoration:underline}'
			. 'body.n4s-no-header .n4s-bar-header,body.n4s-no-hmenu .n4s-bar-header,body.n4s-no-footer .n4s-bar-footer,body.n4s-no-fmenu .n4s-bar-footer{display:none}</style>'
			. '<script id="n4s-bar-js">document.addEventListener("DOMContentLoaded",function(){function bg(e){while(e){var c=getComputedStyle(e).backgroundColor;if(c&&c!=="rgba(0, 0, 0, 0)"&&c!=="transparent")return c;e=e.parentElement}return""}'
			. 'function sync(b,sels){if(!b)return;for(var i=0;i<sels.length;i++){var el=document.querySelector(sels[i]);if(el){b.style.setProperty("--n4s-bar-bg-auto",bg(el));b.style.setProperty("--n4s-bar-tx-auto",getComputedStyle(el).color);return}}}'
			. 'sync(document.querySelector(".n4s-bar-header"),["header.header .header--row-inner","header.header","#masthead","header[role=banner]","header.wp-block-template-part","header"]);'
			. 'var f=document.querySelector(".n4s-bar-footer");if(f){var fs=["footer.site-footer","#colophon","footer.wp-block-template-part","footer[role=contentinfo]","footer"],t=null;for(var k=0;k<fs.length&&!t;k++){t=document.querySelector(fs[k])}'
			. 'if(t&&f.hasAttribute("data-n4s-move")&&t.parentNode){t.parentNode.insertBefore(f,t)}'
			. 'var r=t&&(t.querySelector(".footer--row-inner")||t);if(r){f.style.setProperty("--n4s-bar-bg-auto",bg(r));f.style.setProperty("--n4s-bar-tx-auto",getComputedStyle(r).color)}}});</script>';
	}
	return $out . '<div class="n4s-bar ' . ( $hdr ? 'n4s-bar-header' : 'n4s-bar-footer' ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . ( $hdr ? '' : ' data-n4s-move' ) . '><div class="n4s-bar-inner" style="justify-content:' . $just[ $al ] . '">' . $menu . '</div></div>';
}

add_action(
	'wp_body_open',
	function () {
		static $done = false;
		if ( ! $done && n4s_theme_on( 'above' ) ) {
			$h = n4s_theme_bar_html( 'above' );
			if ( '' !== $h ) {
				$done = true;
				echo $h; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Menü von wp_nav_menu, Farben als Hex geprüft.
			}
		}
	},
	1
);

/** Footermenü: bei Neve direkt vor dem Footer, sonst am Seitenende und per Skript vor den Footer gesetzt. */
function n4s_theme_footer_bar() {
	static $done = false;
	if ( ! $done && n4s_theme_on( 'abovefooter' ) ) {
		$h = n4s_theme_bar_html( 'abovefooter' );
		if ( '' !== $h ) {
			$done = true;
			echo $h; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- siehe oben.
		}
	}
}
add_action( 'neve_before_footer_hook', 'n4s_theme_footer_bar', 1 );
add_action( 'wp_footer', 'n4s_theme_footer_bar', 1 );

/** Hauptmenü je Seite ausblenden: Menüposition des Hauptmenüs liefert dann nichts. */
add_filter(
	'pre_wp_nav_menu',
	function ( $out, $args ) {
		if ( n4s_theme_hidden( 'mainmenu' ) && ! empty( $args->theme_location ) && in_array( $args->theme_location, (array) apply_filters( 'n4s_theme_main_locations', array( 'primary', 'main', 'main-menu', 'primary-menu', 'menu-1', 'header', 'top' ) ), true ) ) {
			return '';
		}
		return $out;
	},
	10,
	2
);

/* ------------------------------------------------------------------ Zusatzmenü: Widget und Shortcode */

/** Menü-Objekt aus Name, Slug oder ID. */
function n4s_theme_menu_id( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v || '0' === $v ) {
		return 0;
	}
	if ( in_array( strtolower( $v ), array( '-1', 'kein', 'none' ), true ) ) {
		return -1; // kein Menü
	}
	$m = wp_get_nav_menu_object( ctype_digit( $v ) ? (int) $v : $v );
	return $m ? (int) $m->term_id : 0;
}

/** Zusatzmenü als HTML. cfg: menu, gast, user, roles (Rolle => Menü), stil (liste|zeile|button|umrandet), tiefe, klasse. */
function n4s_theme_nav_html( $cfg ) {
	$def  = n4s_theme_menu_id( isset( $cfg['menu'] ) ? $cfg['menu'] : 0 );
	if ( ! is_user_logged_in() ) {
		$id = n4s_theme_menu_id( isset( $cfg['gast'] ) ? $cfg['gast'] : 0 );
	} else {
		$id = n4s_theme_menu_for( array( 'user' => n4s_theme_menu_id( isset( $cfg['user'] ) ? $cfg['user'] : 0 ), 'roles' => array_map( 'n4s_theme_menu_id', (array) ( isset( $cfg['roles'] ) ? $cfg['roles'] : array() ) ) ) );
	}
	$id = $id ? $id : $def;
	if ( $id <= 0 ) {
		return ''; // kein Menü gewählt oder keines vorhanden
	}
	$stil = ( isset( $cfg['stil'] ) && in_array( $cfg['stil'], array( 'zeile', 'button', 'umrandet' ), true ) ) ? $cfg['stil'] : 'liste'; // liste, zeile, Button gefüllt, Button umrandet
	$btn  = in_array( $stil, array( 'button', 'umrandet' ), true );
	$tief = ( ! $btn && isset( $cfg['tiefe'] ) && (int) $cfg['tiefe'] > 1 ) ? 2 : 1;
	$al   = ( isset( $cfg['ausrichtung'] ) && in_array( $cfg['ausrichtung'], array( 'mitte', 'rechts' ), true ) ) ? $cfg['ausrichtung'] : 'links';
	$kl   = isset( $cfg['klasse'] ) ? implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $cfg['klasse'] ) ) ) ) : '';
	static $css = false;
	$out = '';
	if ( ! $css ) {
		$css = true;
		$out = '<style>.n4s-nav-al-mitte{text-align:center}.n4s-nav-al-rechts{text-align:right}.n4s-nav-al-mitte .n4s-nav-list{justify-content:center}.n4s-nav-al-rechts .n4s-nav-list{justify-content:flex-end}.n4s-nav-al-rechts .n4s-nav-list ul{padding-left:0;padding-right:1em}.n4s-nav .n4s-nav-list,.n4s-nav .n4s-nav-list ul{list-style:none;margin:0;padding:0}.n4s-nav .n4s-nav-list ul{padding-left:1em}.n4s-nav a{text-decoration:none}.n4s-nav-liste .n4s-nav-list>li{margin:0 0 .4em}.n4s-nav-zeile .n4s-nav-list{display:flex;flex-wrap:wrap;gap:.4em 1.2em}.n4s-nav-zeile .n4s-nav-list ul{display:none}.n4s-nav-btn .n4s-nav-list{gap:.6em}.n4s-nav-btn .n4s-btn{margin:0}</style>';
	}
	$btn_class = function ( $a ) use ( $stil ) {
		$a['class'] = trim( ( isset( $a['class'] ) ? $a['class'] . ' ' : '' ) . 'n4s-btn' . ( 'umrandet' === $stil ? ' n4s-btn-outline' : '' ) );
		return $a;
	};
	if ( $btn ) { // Links als Buttons im Design der Snippets (Farben und Rundung aus dem Theme)
		$out = ( function_exists( 'n4s_snippets_style' ) ? n4s_snippets_style() : '' ) . $out;
		add_filter( 'nav_menu_link_attributes', $btn_class );
	}
	$html = wp_nav_menu(
		array(
			'menu'            => $id,
			'container'       => 'nav',
			'container_class' => 'n4s-nav n4s-nav-' . ( $btn ? 'zeile n4s-nav-btn' : $stil ) . ' n4s-nav-al-' . $al . ( '' !== $kl ? ' ' . $kl : '' ),
			'menu_class'      => 'n4s-nav-list',
			'menu_id'         => '',
			'depth'           => $tief,
			'fallback_cb'     => '__return_empty_string',
			'echo'            => false,
		)
	);
	if ( $btn ) {
		remove_filter( 'nav_menu_link_attributes', $btn_class );
	}
	return $out . $html;
}

/** Zusatzmenü aus Angaben wie im Shortcode (menu, gast, angemeldet, rollen, stil, tiefe, klasse). */
function n4s_theme_nav_from_atts( $a ) {
	$a     = wp_parse_args( (array) $a, array( 'menu' => '', 'gast' => '', 'angemeldet' => '', 'rollen' => '', 'stil' => 'liste', 'tiefe' => '1', 'klasse' => '', 'ausrichtung' => 'links' ) );
	$roles = array();
	foreach ( explode( ';', (string) $a['rollen'] ) as $pair ) { // rollen="editor:Redaktion; author:Autorenmenü"
		$p = array_map( 'trim', explode( ':', $pair, 2 ) );
		if ( 2 === count( $p ) && '' !== $p[0] ) {
			$roles[ sanitize_key( $p[0] ) ] = $p[1];
		}
	}
	return n4s_theme_nav_html( array( 'menu' => $a['menu'], 'gast' => $a['gast'], 'user' => $a['angemeldet'], 'roles' => $roles, 'stil' => $a['stil'], 'tiefe' => $a['tiefe'], 'klasse' => $a['klasse'], 'ausrichtung' => $a['ausrichtung'] ) );
}

add_shortcode(
	'n4s_nav',
	function ( $atts ) {
		if ( ! n4s_theme_on( 'widget' ) ) {
			return '';
		}
		return n4s_theme_nav_from_atts( shortcode_atts( array( 'menu' => '', 'gast' => '', 'angemeldet' => '', 'rollen' => '', 'stil' => 'liste', 'tiefe' => '1', 'klasse' => '', 'ausrichtung' => 'links' ), $atts, 'n4s_nav' ) );
	}
);

/** Block „n4s Menü“ (Seiten, Beiträge, Block-Widgets, Site-Editor): dynamisch, gleiche Auswahl wie Widget und Shortcode. */
add_action(
	'init',
	function () {
		if ( ! n4s_theme_on( 'widget' ) || ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script( 'n4s-nav-block', false, array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ), N4S_APPS_VERSION, true );
		$menus = array();
		foreach ( wp_get_nav_menus() as $m ) {
			$menus[] = array( 'value' => (string) $m->term_id, 'label' => $m->name );
		}
		$data = array(
			'menus' => $menus,
			't'     => array(
				'title'   => 'n4s – ' . n4s_t( 'Menü', 'Menu' ),
				'desc'    => n4s_t( 'Zusatzmenü mit eigener Auswahl für Besucher, Angemeldete und Rollen.', 'Additional menu with its own choice for visitors, logged-in users and roles.' ),
				'panel'   => n4s_t( 'Menü', 'Menu' ),
				'def'     => n4s_t( 'Menü (Standard)', 'Menu (default)' ),
				'gast'    => n4s_t( 'Menü für Besucher ohne Anmeldung', 'Menu for logged-out visitors' ),
				'user'    => n4s_t( 'Menü für alle Angemeldeten', 'Menu for all logged-in users' ),
				'roles'   => n4s_t( 'Menü je Rolle (z. B. editor:Redaktion; author:Autoren)', 'Menu per role (e.g. editor:Editors; author:Authors)' ),
				'style'   => n4s_t( 'Darstellung', 'Appearance' ),
				'list'    => n4s_t( 'Liste (untereinander)', 'List (vertical)' ),
				'row'     => n4s_t( 'Zeile (nebeneinander)', 'Row (side by side)' ),
				'btn'     => n4s_t( 'Button gefüllt', 'Filled button' ),
				'btnout'  => n4s_t( 'Button umrandet', 'Outlined button' ),
				'depth'   => n4s_t( 'Untermenüs anzeigen (Liste)', 'Show sub-menus (list)' ),
				'align'   => n4s_t( 'Ausrichtung', 'Alignment' ),
				'left'    => n4s_t( 'Links', 'Left' ),
				'center'  => n4s_t( 'Mitte', 'Center' ),
				'right'   => n4s_t( 'Rechts', 'Right' ),
				'standard' => n4s_t( 'Standard', 'Default' ),
				'none'    => n4s_t( 'Kein Menü', 'No menu' ),
			),
		);
		wp_add_inline_script( 'n4s-nav-block', 'window.n4sNav=' . wp_json_encode( $data ) . ';(function(wp){var el=wp.element.createElement,be=wp.blockEditor||wp.editor,C=wp.components,SSR=wp.serverSideRender,N=window.n4sNav,T=N.t;'
			. 'function sel(a,set,label,key,first){return el(C.SelectControl,{label:label,value:a[key],options:first.concat(N.menus),onChange:function(v){var o={};o[key]=v;set(o)}})}'
			. 'wp.blocks.registerBlockType("n4s/nav",{title:T.title,icon:"menu",category:"widgets",description:T.desc,'
			. 'attributes:{menu:{type:"string",default:""},gast:{type:"string",default:""},angemeldet:{type:"string",default:""},rollen:{type:"string",default:""},stil:{type:"string",default:"liste"},tiefe:{type:"string",default:"1"},ausrichtung:{type:"string",default:"links"}},'
			. 'edit:function(p){var a=p.attributes,set=p.setAttributes,d=[{value:"",label:T.standard},{value:"-1",label:T.none}];'
			. 'return el(wp.element.Fragment,null,el(be.InspectorControls,null,el(C.PanelBody,{title:T.panel,initialOpen:true},'
			. 'el(C.SelectControl,{label:T.def,value:a.menu,options:[{value:"",label:"–"}].concat(N.menus),onChange:function(v){set({menu:v})}}),'
			. 'sel(a,set,T.gast,"gast",d),sel(a,set,T.user,"angemeldet",d),'
			. 'el(C.TextControl,{label:T.roles,value:a.rollen,onChange:function(v){set({rollen:v})}}),'
			. 'el(C.SelectControl,{label:T.style,value:a.stil,options:[{value:"liste",label:T.list},{value:"zeile",label:T.row},{value:"button",label:T.btn},{value:"umrandet",label:T.btnout}],onChange:function(v){set({stil:v})}}),'
			. 'el(C.SelectControl,{label:T.align,value:a.ausrichtung,options:[{value:"links",label:T.left},{value:"mitte",label:T.center},{value:"rechts",label:T.right}],onChange:function(v){set({ausrichtung:v})}}),'
			. 'el(C.ToggleControl,{label:T.depth,checked:a.tiefe==="2",onChange:function(v){set({tiefe:v?"2":"1"})}}))),'
			. 'el(SSR,{block:"n4s/nav",attributes:a}))},save:function(){return null}})})(window.wp);' );
		register_block_type(
			'n4s/nav',
			array(
				'editor_script'   => 'n4s-nav-block',
				'attributes'      => array(
					'menu'       => array( 'type' => 'string', 'default' => '' ),
					'gast'       => array( 'type' => 'string', 'default' => '' ),
					'angemeldet' => array( 'type' => 'string', 'default' => '' ),
					'rollen'     => array( 'type' => 'string', 'default' => '' ),
					'stil'       => array( 'type' => 'string', 'default' => 'liste' ),
					'tiefe'      => array( 'type' => 'string', 'default' => '1' ),
					'ausrichtung' => array( 'type' => 'string', 'default' => 'links' ),
				),
				'render_callback' => function ( $attrs ) {
					$h = n4s_theme_nav_from_atts( (array) $attrs );
					return '' === trim( $h ) && is_admin() ? '<p style="opacity:.6">' . esc_html( n4s_t( 'Zusatzmenü: kein Menü gewählt.', 'Additional menu: no menu chosen.' ) ) . '</p>' : $h;
				},
			)
		);
	}
);

class N4S_Theme_Nav_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'n4s_theme_nav', 'n4s – ' . n4s_t( 'Menü', 'Menu' ), array( 'description' => n4s_t( 'Zusatzmenü, optional mit eigener Auswahl für Besucher, Angemeldete und Rollen.', 'Additional menu, optionally with its own choice for visitors, logged-in users and roles.' ) ) );
	}

	public function widget( $args, $inst ) {
		$html = n4s_theme_nav_html( (array) $inst );
		if ( '' === trim( $html ) ) {
			return;
		}
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- von WordPress bereitgestellt.
		if ( ! empty( $inst['title'] ) ) {
			echo $args['before_title'] . esc_html( $inst['title'] ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- von wp_nav_menu erzeugt.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function menu_select( $field, $cur, $label ) {
		echo '<p><label for="' . esc_attr( $this->get_field_id( $field ) ) . '">' . esc_html( $label ) . '</label><select class="widefat" id="' . esc_attr( $this->get_field_id( $field ) ) . '" name="' . esc_attr( $this->get_field_name( $field ) ) . '"><option value="0">–</option><option value="-1"' . selected( (int) $cur, -1, false ) . '>' . esc_html( n4s_t( 'Kein Menü', 'No menu' ) ) . '</option>';
		foreach ( wp_get_nav_menus() as $m ) {
			echo '<option value="' . (int) $m->term_id . '"' . selected( (int) $cur, (int) $m->term_id, false ) . '>' . esc_html( $m->name ) . '</option>';
		}
		echo '</select></p>';
	}

	public function form( $inst ) {
		$inst = wp_parse_args( (array) $inst, array( 'title' => '', 'menu' => 0, 'gast' => 0, 'user' => 0, 'roles' => array(), 'stil' => 'liste', 'tiefe' => 1, 'ausrichtung' => 'links' ) );
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'title' ) ) . '">' . esc_html( n4s_t( 'Titel', 'Title' ) ) . '</label><input class="widefat" type="text" id="' . esc_attr( $this->get_field_id( 'title' ) ) . '" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" value="' . esc_attr( $inst['title'] ) . '"></p>';
		$this->menu_select( 'menu', $inst['menu'], n4s_t( 'Menü (Standard)', 'Menu (default)' ) );
		$this->menu_select( 'gast', $inst['gast'], n4s_t( 'Menü für Besucher ohne Anmeldung', 'Menu for logged-out visitors' ) );
		$this->menu_select( 'user', $inst['user'], n4s_t( 'Menü für alle Angemeldeten', 'Menu for all logged-in users' ) );
		echo '<details><summary>' . esc_html( n4s_t( 'Menü je Rolle', 'Menu per role' ) ) . '</summary>';
		foreach ( wp_roles()->get_names() as $r => $label ) {
			$this->menu_select( 'roles_' . $r, isset( $inst['roles'][ $r ] ) ? $inst['roles'][ $r ] : 0, translate_user_role( $label ) );
		}
		echo '</details><p><label for="' . esc_attr( $this->get_field_id( 'stil' ) ) . '">' . esc_html( n4s_t( 'Darstellung', 'Appearance' ) ) . '</label><select class="widefat" id="' . esc_attr( $this->get_field_id( 'stil' ) ) . '" name="' . esc_attr( $this->get_field_name( 'stil' ) ) . '"><option value="liste"' . selected( $inst['stil'], 'liste', false ) . '>' . esc_html( n4s_t( 'Liste (untereinander)', 'List (vertical)' ) ) . '</option><option value="zeile"' . selected( $inst['stil'], 'zeile', false ) . '>' . esc_html( n4s_t( 'Zeile (nebeneinander)', 'Row (side by side)' ) ) . '</option><option value="button"' . selected( $inst['stil'], 'button', false ) . '>' . esc_html( n4s_t( 'Button gefüllt', 'Filled button' ) ) . '</option><option value="umrandet"' . selected( $inst['stil'], 'umrandet', false ) . '>' . esc_html( n4s_t( 'Button umrandet', 'Outlined button' ) ) . '</option></select></p>';
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'ausrichtung' ) ) . '">' . esc_html( n4s_t( 'Ausrichtung', 'Alignment' ) ) . '</label><select class="widefat" id="' . esc_attr( $this->get_field_id( 'ausrichtung' ) ) . '" name="' . esc_attr( $this->get_field_name( 'ausrichtung' ) ) . '">';
		foreach ( array( 'links' => n4s_t( 'Links', 'Left' ), 'mitte' => n4s_t( 'Mitte', 'Center' ), 'rechts' => n4s_t( 'Rechts', 'Right' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $inst['ausrichtung'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label><input type="checkbox" name="' . esc_attr( $this->get_field_name( 'tiefe' ) ) . '" value="2"' . checked( (int) $inst['tiefe'], 2, false ) . '> ' . esc_html( n4s_t( 'Untermenüs anzeigen (Liste)', 'Show sub-menus (list)' ) ) . '</label></p>';
	}

	public function update( $new, $old ) {
		$valid = array_map( function ( $m ) {
			return (int) $m->term_id;
		}, wp_get_nav_menus() );
		$ok    = function ( $v ) use ( $valid ) {
			$v = (int) $v;
			return ( -1 === $v || in_array( $v, $valid, true ) ) ? $v : 0; // -1 = kein Menü
		};
		$out   = array(
			'title' => sanitize_text_field( isset( $new['title'] ) ? $new['title'] : '' ),
			'menu'  => $ok( isset( $new['menu'] ) ? $new['menu'] : 0 ),
			'gast'  => $ok( isset( $new['gast'] ) ? $new['gast'] : 0 ),
			'user'  => $ok( isset( $new['user'] ) ? $new['user'] : 0 ),
			'stil'  => ( isset( $new['stil'] ) && in_array( $new['stil'], array( 'zeile', 'button', 'umrandet' ), true ) ) ? $new['stil'] : 'liste',
			'tiefe' => ( isset( $new['tiefe'] ) && 2 === (int) $new['tiefe'] ) ? 2 : 1,
			'ausrichtung' => ( isset( $new['ausrichtung'] ) && in_array( $new['ausrichtung'], array( 'mitte', 'rechts' ), true ) ) ? $new['ausrichtung'] : 'links',
			'roles' => array(),
		);
		foreach ( array_keys( wp_roles()->get_names() ) as $r ) {
			$v = $ok( isset( $new[ 'roles_' . $r ] ) ? $new[ 'roles_' . $r ] : 0 );
			if ( $v ) {
				$out['roles'][ $r ] = $v;
			}
		}
		return $out;
	}
}

add_action(
	'widgets_init',
	function () {
		if ( n4s_theme_on( 'widget' ) ) {
			register_widget( 'N4S_Theme_Nav_Widget' );
		}
	}
);

/* ------------------------------------------------------------------ Seitengestaltung und Farbschemen je Seite */

function n4s_theme_post_types() {
	return array_values( array_filter( get_post_types( array( 'public' => true ) ), function ( $t ) {
		return 'attachment' !== $t;
	} ) );
}

/** Farbschema der aktuellen Seite: 0 = Theme-Standard, 1 bis 3. */
function n4s_theme_scheme_now() {
	if ( ! n4s_theme_on( 'schemes' ) ) {
		return 0;
	}
	$n = 0;
	if ( is_singular() ) {
		$n = (int) get_post_meta( get_queried_object_id(), '_n4s_scheme', true );
	}
	if ( $n < 1 || $n > 3 ) {
		$n = (int) n4s_theme_opt()['default'];
	}
	return ( $n >= 1 && $n <= 3 ) ? $n : 0;
}

/** Wahl für die aktuelle Seite: 'header', 'footer' oder 'title' ausblenden? */
function n4s_theme_hidden( $what ) {
	return n4s_theme_on( 'page' ) && is_singular() && '1' === (string) get_post_meta( get_queried_object_id(), '_n4s_hide_' . $what, true );
}

add_filter(
	'body_class',
	function ( $c ) {
		$n = n4s_theme_scheme_now();
		if ( $n ) {
			$c[] = 'n4s-scheme-' . $n;
		}
		foreach ( array( 'header', 'footer', 'title', 'mainmenu', 'hmenu', 'fmenu' ) as $w ) {
			if ( n4s_theme_hidden( $w ) ) {
				$c[] = 'n4s-no-' . $w;
			}
		}
		return $c;
	}
);

/** Neve liest seine eigenen Seitenfelder; sie werden bei gesetztem n4s-Feld überschrieben (nichts wird gespeichert). */
add_filter(
	'get_post_metadata',
	function ( $check, $object_id, $meta_key ) {
		static $map = array(
			'neve_meta_disable_header' => 'header',
			'neve_meta_disable_footer' => 'footer',
			'neve_meta_disable_title'  => 'title',
		);
		if ( isset( $map[ $meta_key ] ) && n4s_theme_on( 'page' ) && '1' === (string) get_post_meta( $object_id, '_n4s_hide_' . $map[ $meta_key ], true ) ) {
			return array( 'on' );
		}
		return $check;
	},
	10,
	3
);

/** Zusätzliche Regeln für Themes ohne Neve: Variablen der WordPress-Standardthemes (Blocktheme-Farben, Twenty Twenty-One) und Links, Überschriften, Kopf-/Fußzeile. */
function n4s_theme_scheme_generic_css( $n, $s ) {
	if ( 'neve' === strtolower( (string) wp_get_theme()->get_template() ) ) {
		return '';
	}
	$g = function ( $k ) use ( $s ) {
		return isset( $s[ $k ] ) ? sanitize_hex_color( $s[ $k ] ) : '';
	};
	$map = array(
		'bg'     => array( '--wp--preset--color--base', '--global--color-background', '--global--color-white' ),
		'text'   => array( '--wp--preset--color--contrast', '--global--color-primary', '--global--color-black' ),
		'accent' => array( '--wp--preset--color--accent-1', '--wp--preset--color--primary', '--global--color-secondary' ),
		'hover'  => array( '--wp--preset--color--accent-2', '--wp--preset--color--secondary' ),
		'light'  => array( '--wp--preset--color--accent-5', '--wp--preset--color--tertiary', '--global--color-light-gray' ),
	);
	$p   = 'body.n4s-scheme-' . $n;
	$v   = '';
	foreach ( $map as $k => $vars ) {
		if ( $g( $k ) ) {
			foreach ( $vars as $var ) {
				$v .= $var . ':' . $g( $k ) . ';';
			}
		}
	}
	$css = '' !== $v ? $p . '{' . $v . '}' : '';
	if ( $g( 'accent' ) ) {
		$css .= $p . ' :where(a:not([class*="button"]):not([class*="btn"])){color:' . $g( 'accent' ) . '}';
		$css .= $p . ' :where(.wp-block-button__link,.wp-element-button,button[type="submit"],input[type="submit"]):not(.has-background){background-color:' . $g( 'accent' ) . ';' . ( $g( 'btntext' ) ? 'color:' . $g( 'btntext' ) . ';' : '' ) . '}';
	}
	if ( $g( 'heading' ) ) {
		$css .= $p . ' :where(h1,h2,h3,h4,h5,h6){color:' . $g( 'heading' ) . '}';
	}
	if ( $g( 'bg' ) ) {
		$css .= $p . ' :where(header.wp-block-template-part,.site-header,#masthead,header[role="banner"]){background-color:' . $g( 'bg' ) . '}';
	}
	if ( $g( 'dark' ) ) {
		$css .= $p . ' :where(footer.wp-block-template-part,.site-footer,#colophon,footer[role="contentinfo"]){background-color:' . $g( 'dark' ) . ';' . ( $g( 'darktext' ) ? 'color:' . $g( 'darktext' ) . ';' : '' ) . '}';
		if ( $g( 'darktext' ) ) {
			$css .= $p . ' :where(footer.wp-block-template-part,.site-footer,#colophon,footer[role="contentinfo"]) :where(a,p,li,h1,h2,h3,h4,h5,h6){color:' . $g( 'darktext' ) . '}';
		}
	}
	return $css;
}

/** Header- und Footer-Farben eines Schemas (Neve: Zeilenvariablen, sonst Elemente des Themes). */
function n4s_theme_scheme_header_footer_css( $n, $s ) {
	$g = function ( $k ) use ( $s ) {
		return isset( $s[ $k ] ) ? sanitize_hex_color( $s[ $k ] ) : '';
	};
	$p     = 'body.n4s-scheme-' . $n;
	$neve  = 'neve' === strtolower( (string) wp_get_theme()->get_template() );
	$css   = '';
	$parts = array(
		'hdr' => array( $neve ? '.header-top,.header-main,.header-bottom,.header-menu-sidebar-bg' : '', 'header.wp-block-template-part,.site-header,#masthead,header[role="banner"]' ),
		'ftr' => array( $neve ? '.footer-top,.footer-main,.footer-bottom' : '', 'footer.wp-block-template-part,.site-footer,#colophon,footer[role="contentinfo"]' ),
	);
	foreach ( $parts as $k => $sel ) {
		$bg = $g( $k . 'bg' );
		$tx = $g( $k . 'text' );
		if ( ! $bg && ! $tx ) {
			continue;
		}
		if ( $neve ) { // Neve liest Farben je Zeile aus --bgcolor und --color.
			$list = array_map( function ( $x ) use ( $p ) {
				return $p . ' ' . $x;
			}, explode( ',', $sel[0] ) );
			$css .= implode( ',', $list ) . '{' . ( $bg ? '--bgcolor:' . $bg . ';' : '' ) . ( $tx ? '--color:' . $tx . ';' : '' ) . '}';
		}
		$list = array_map( function ( $x ) use ( $p ) {
			return $p . ' ' . $x;
		}, explode( ',', $sel[1] ) );
		$css .= implode( ',', $list ) . '{' . ( $bg ? 'background-color:' . $bg . ';' : '' ) . ( $tx ? 'color:' . $tx . ';' : '' ) . '}';
		if ( $tx ) {
			$inner = array_map( function ( $x ) use ( $p ) {
				return $p . ' ' . $x . ' :where(a,p,li,span,h1,h2,h3,h4,h5,h6,.menu-item a)';
			}, explode( ',', $sel[1] ) );
			$css  .= implode( ',', $inner ) . '{color:' . $tx . '}';
		}
	}
	return $css;
}

/** CSS der Seite: Farbschema und Ausblenden (theme-unabhängig, mit Neve-Variablen). */
add_action(
	'wp_head',
	function () {
		$css = '';
		$n   = n4s_theme_scheme_now();
		if ( $n ) {
			$s    = n4s_theme_opt()['schemes'][ $n ]['colors'];
			$vars = '';
			foreach ( n4s_theme_color_fields() as $k => $f ) {
				$v = isset( $s[ $k ] ) ? sanitize_hex_color( $s[ $k ] ) : '';
				if ( $v ) {
					foreach ( $f[1] as $var ) {
						$vars .= $var . ':' . $v . ';';
					}
				}
			}
			$css .= 'body.n4s-scheme-' . $n . '{' . $vars;
			$bg   = isset( $s['bg'] ) ? sanitize_hex_color( $s['bg'] ) : '';
			$tx   = isset( $s['text'] ) ? sanitize_hex_color( $s['text'] ) : '';
			$css .= ( $bg ? 'background-color:' . $bg . ';' : '' ) . ( $tx ? 'color:' . $tx . ';' : '' ) . '}';
			$ac = isset( $s['accent'] ) ? sanitize_hex_color( $s['accent'] ) : '';
			$bt = isset( $s['btntext'] ) ? sanitize_hex_color( $s['btntext'] ) : '';
			$css .= n4s_theme_scheme_generic_css( $n, $s );
			$css .= n4s_theme_scheme_header_footer_css( $n, $s );
			if ( $ac ) { // Block-Buttons ohne eigene Farbe übernehmen die Hauptfarbe.
				$css .= 'body.n4s-scheme-' . $n . ' .wp-block-button:not(.is-style-outline) .wp-block-button__link:not(.has-background){background-color:' . $ac . ';' . ( $bt ? 'color:' . $bt . ';' : '' ) . '}';
			}
		}
		if ( n4s_theme_hidden( 'header' ) ) {
			$css .= 'body.n4s-no-header header.header,body.n4s-no-header #masthead,body.n4s-no-header .site-header,body.n4s-no-header header[role="banner"]{display:none!important}';
		}
		if ( n4s_theme_hidden( 'footer' ) ) {
			$css .= 'body.n4s-no-footer footer.site-footer,body.n4s-no-footer #colophon,body.n4s-no-footer .site-footer,body.n4s-no-footer footer[role="contentinfo"]{display:none!important}';
		}
		if ( n4s_theme_hidden( 'mainmenu' ) ) {
			$css .= 'body.n4s-no-mainmenu .menu-mobile-toggle,body.n4s-no-mainmenu header .wp-block-navigation,body.n4s-no-mainmenu .nv-navbar,body.n4s-no-mainmenu #nv-primary-navigation-main{display:none!important}';
		}
		if ( n4s_theme_hidden( 'title' ) ) {
			$css .= 'body.n4s-no-title .nv-page-title-wrap,body.n4s-no-title .page-header,body.n4s-no-title .entry-header .entry-title,body.n4s-no-title h1.page-title,body.n4s-no-title .n4s-page-head,body.n4s-no-title .n4s-entry__title,body.n4s-no-title .entry-title,body.n4s-no-title .wp-block-post-title,body.n4s-no-title .post-title{display:none!important}';
		}
		if ( '' !== $css ) {
			echo '<style id="n4s-theme-css">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nur geprüfte Hex-Farben und feste Selektoren.
		}
	},
	99
);

/* ------------------------------------------------------------------ Admin */

if ( is_admin() ) {

	/* Metabox Seitengestaltung */
	add_action(
		'add_meta_boxes',
		function () {
			if ( ! n4s_theme_on( 'page' ) && ! n4s_theme_on( 'schemes' ) ) {
				return;
			}
			foreach ( n4s_theme_post_types() as $t ) {
				add_meta_box( 'n4s-theme-page', 'n4s – ' . n4s_t( 'Seitengestaltung', 'Page design' ), 'n4s_theme_metabox', $t, 'side', 'default' );
			}
		}
	);

	function n4s_theme_metabox( $post ) {
		wp_nonce_field( 'n4s_theme_page', 'n4s_theme_page_nonce' );
		echo '<div style="max-width:280px">';
		if ( n4s_theme_on( 'schemes' ) ) {
			$cur = (int) get_post_meta( $post->ID, '_n4s_scheme', true );
			echo '<p style="margin:0 0 8px"><label for="n4s-scheme">' . esc_html( n4s_t( 'Farbschema', 'Color scheme' ) ) . '</label><br><select name="n4s_scheme" id="n4s-scheme" style="width:100%;max-width:280px"><option value="0">' . esc_html( n4s_t( 'Standard', 'Default' ) ) . '</option>';
			foreach ( n4s_theme_opt()['schemes'] as $n => $s ) {
				echo '<option value="' . (int) $n . '"' . selected( $cur, $n, false ) . '>' . esc_html( $n . ' – ' . $s['name'] ) . '</option>';
			}
			echo '</select></p>';
		}
		if ( n4s_theme_on( 'page' ) ) {
			$fields = array(
				'header'   => n4s_t( 'Kopfzeile ausblenden', 'Hide header' ),
				'mainmenu' => n4s_t( 'Hauptmenü ausblenden', 'Hide main menu' ),
			);
			if ( n4s_theme_on( 'above' ) ) {
				$fields['hmenu'] = n4s_t( 'Headermenü ausblenden', 'Hide header menu' );
			}
			if ( n4s_theme_on( 'abovefooter' ) ) {
				$fields['fmenu'] = n4s_t( 'Footermenü ausblenden', 'Hide footer menu' );
			}
			$fields['footer'] = n4s_t( 'Fußzeile ausblenden', 'Hide footer' );
			$fields['title']  = n4s_t( 'Seitentitel ausblenden', 'Hide page title' );
			foreach ( $fields as $k => $l ) {
				echo '<p style="margin:3px 0"><label><input type="checkbox" name="n4s_hide_' . esc_attr( $k ) . '" value="1"' . checked( get_post_meta( $post->ID, '_n4s_hide_' . $k, true ), '1', false ) . '> ' . esc_html( $l ) . '</label></p>';
			}
		}
		echo '</div>';
	}

	add_action(
		'save_post',
		function ( $post_id ) {
			if ( ! isset( $_POST['n4s_theme_page_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['n4s_theme_page_nonce'] ) ), 'n4s_theme_page' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
			if ( n4s_theme_on( 'schemes' ) ) {
				$n = isset( $_POST['n4s_scheme'] ) ? (int) $_POST['n4s_scheme'] : 0;
				if ( $n >= 1 && $n <= 3 ) {
					update_post_meta( $post_id, '_n4s_scheme', $n );
				} else {
					delete_post_meta( $post_id, '_n4s_scheme' );
				}
			}
			if ( n4s_theme_on( 'page' ) ) {
				foreach ( array( 'header', 'footer', 'title', 'mainmenu', 'hmenu', 'fmenu' ) as $k ) {
					if ( ! empty( $_POST[ 'n4s_hide_' . $k ] ) ) {
						update_post_meta( $post_id, '_n4s_hide_' . $k, '1' );
					} else {
						delete_post_meta( $post_id, '_n4s_hide_' . $k );
					}
				}
			}
		}
	);

	/* Sichtbarkeit je Menüpunkt (Menüs bearbeiten) */
	add_action(
		'wp_nav_menu_item_custom_fields',
		function ( $item_id ) {
			if ( ! n4s_theme_on( 'items' ) ) {
				return;
			}
			$v     = n4s_theme_item_vis( $item_id );
			$modes = array(
				'all'   => n4s_t( 'Alle Besucher', 'All visitors' ),
				'guest' => n4s_t( 'Nur Besucher ohne Anmeldung', 'Logged-out visitors only' ),
				'user'  => n4s_t( 'Nur Angemeldete', 'Logged-in users only' ),
				'roles' => n4s_t( 'Nur bestimmte Rollen', 'Only certain roles' ),
			);
			echo '<div class="description-wide" style="margin:6px 0;padding:6px 8px;background:#f6f7f7;border:1px solid #dcdcde"><strong>n4s – ' . esc_html( n4s_t( 'Sichtbar für', 'Visible for' ) ) . '</strong><br>';
			echo '<select name="n4s-vis-mode[' . (int) $item_id . ']">';
			foreach ( $modes as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $v['mode'], $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select><br>';
			foreach ( wp_roles()->get_names() as $r => $label ) {
				echo '<label style="margin-right:10px;white-space:nowrap"><input type="checkbox" name="n4s-vis-roles[' . (int) $item_id . '][]" value="' . esc_attr( $r ) . '"' . checked( in_array( $r, (array) $v['roles'], true ), true, false ) . '> ' . esc_html( translate_user_role( $label ) ) . '</label>';
			}
			echo '</div>';
		}
	);

	/* Neuer Tab je Menüpunkt (Menü-Editor und Customizer) */
	add_action(
		'wp_nav_menu_item_custom_fields',
		function ( $item_id ) {
			if ( ! n4s_theme_on( 'newtab' ) ) {
				return;
			}
			echo '<div class="description-wide" style="margin:6px 0;padding:6px 8px;background:#f6f7f7;border:1px solid #dcdcde"><input type="hidden" name="n4s-newtab-has[' . (int) $item_id . ']" value="1"><label><input type="checkbox" name="n4s-newtab[' . (int) $item_id . ']" value="1"' . checked( (bool) get_post_meta( (int) $item_id, '_n4s_newtab', true ), true, false ) . '> <strong>n4s</strong> – ' . esc_html( n4s_t( 'Link in neuem Tab/Fenster öffnen', 'Open link in a new tab/window' ) ) . '</label></div>';
		}
	);

	add_action(
		'wp_update_nav_menu_item',
		function ( $menu_id, $item_id ) {
			if ( ! isset( $_POST['n4s-newtab-has'][ $item_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Nonce prüft WordPress beim Speichern der Menüs.
				return;
			}
			if ( ! empty( $_POST['n4s-newtab'][ $item_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				update_post_meta( $item_id, '_n4s_newtab', 1 );
			} else {
				delete_post_meta( $item_id, '_n4s_newtab' );
			}
		},
		10,
		2
	);

	add_action(
		'wp_update_nav_menu_item',
		function ( $menu_id, $item_id ) {
			if ( ! isset( $_POST['n4s-vis-mode'][ $item_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- Nonce prüft WordPress beim Speichern der Menüs.
				return;
			}
			$mode  = sanitize_key( wp_unslash( $_POST['n4s-vis-mode'][ $item_id ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$mode  = in_array( $mode, array( 'guest', 'user', 'roles' ), true ) ? $mode : 'all';
			$roles = array();
			if ( isset( $_POST['n4s-vis-roles'][ $item_id ] ) && is_array( $_POST['n4s-vis-roles'][ $item_id ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$valid = array_keys( wp_roles()->get_names() );
				foreach ( wp_unslash( $_POST['n4s-vis-roles'][ $item_id ] ) as $r ) { // phpcs:ignore WordPress.Security
					if ( in_array( $r, $valid, true ) ) {
						$roles[] = $r;
					}
				}
			}
			if ( 'all' === $mode ) {
				delete_post_meta( $item_id, '_n4s_vis' );
			} else {
				update_post_meta( $item_id, '_n4s_vis', array( 'mode' => $mode, 'roles' => $roles ) );
			}
		},
		10,
		2
	);

	/* Box „Anmelden/Abmelden“ im Menü-Editor */
	add_action(
		'admin_init',
		function () {
			if ( n4s_theme_on( 'loginout' ) ) {
				add_meta_box( 'n4s-theme-loginout', 'n4s – ' . n4s_t( 'Anmelden / Abmelden', 'Log in / Log out' ), 'n4s_theme_loginout_box', 'nav-menus', 'side', 'default' );
			}
		}
	);

	/** Die Box im Menü-Editor ist sofort sichtbar (WordPress blendet fremde Boxen sonst über „Ansicht anpassen“ aus). */
	add_filter(
		'get_user_option_metaboxhidden_nav-menus',
		function ( $h ) {
			return is_array( $h ) ? array_values( array_diff( $h, array( 'n4s-theme-loginout' ) ) ) : $h;
		}
	);

	function n4s_theme_loginout_box() {
		echo '<p class="description">' . esc_html( n4s_t( 'Fügt einen Menüpunkt hinzu, der je nach Anmeldestatus erscheint oder umschaltet.', 'Adds a menu item that appears or switches depending on the login status.' ) ) . '</p>';
		$items = array(
			array( '#n4s-loginout', n4s_t( 'Anmelden|Abmelden', 'Log in|Log out' ), n4s_t( 'Umschalten: Anmelden / Abmelden', 'Switch: Log in / Log out' ) ),
			array( '#n4s-login', n4s_t( 'Anmelden', 'Log in' ), n4s_t( 'Nur „Anmelden“ (nur für Besucher)', 'Only "Log in" (visitors only)' ) ),
			array( '#n4s-logout', n4s_t( 'Abmelden', 'Log out' ), n4s_t( 'Nur „Abmelden“ (nur für Angemeldete)', 'Only "Log out" (logged-in users only)' ) ),
		);
		foreach ( $items as $i ) {
			echo '<p><button type="button" class="button n4s-add-lo" data-url="' . esc_attr( $i[0] ) . '" data-title="' . esc_attr( $i[1] ) . '">' . esc_html( $i[2] ) . '</button></p>';
		}
		echo '<p class="description">' . esc_html( n4s_t( 'Beim Umschalten gilt links der Text für Besucher, rechts für Angemeldete (getrennt durch |). Der Text lässt sich im Menüpunkt ändern.', 'When switching, the text on the left is for visitors, on the right for logged-in users (separated by |). The text can be changed in the menu item.' ) ) . '</p>';
		echo '<script>(function(){document.addEventListener("click",function(e){var b=e.target.closest(".n4s-add-lo");if(!b)return;e.preventDefault();if(!window.wpNavMenu||!wpNavMenu.addItemToMenu)return;var o={};o["-1"]={"menu-item-type":"custom","menu-item-url":b.getAttribute("data-url"),"menu-item-title":b.getAttribute("data-title")};wpNavMenu.addItemToMenu(o,wpNavMenu.addMenuItemToBottom,function(){})})})();</script>';
	}

	add_action(
		'admin_enqueue_scripts',
		function ( $hook ) {
			if ( false !== strpos( (string) $hook, 'n4s-theme' ) ) {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
			}
		}
	);

	/** Zusatzmenü: Fundstellen (Widget, Block, Shortcode), Link zum Anlegen eines Menüs und Shortcode-Zusammenstellung. */
	function n4s_theme_widget_panel() {
		$menus = wp_get_nav_menus();
		echo '<p style="max-width:900px">' . esc_html( n4s_t( 'Ein Zusatzmenü besteht aus einem Menü (das du unter Design → Menüs anlegst) und einer Stelle, an der es erscheint:', 'An additional menu consists of a menu (which you create under Appearance → Menus) and a place where it appears:' ) ) . '</p><ol style="max-width:900px;margin-left:20px">';
		echo '<li>' . esc_html( n4s_t( 'Menü anlegen oder bearbeiten: ', 'Create or edit a menu: ' ) ) . '<a href="' . esc_url( admin_url( 'nav-menus.php?action=edit&menu=0' ) ) . '">' . esc_html( n4s_t( 'Neues Menü anlegen', 'Create new menu' ) ) . '</a> · <a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '">' . esc_html( n4s_t( 'Menüs bearbeiten', 'Edit menus' ) ) . '</a></li>';
		echo '<li>' . esc_html( n4s_t( 'In Seiten, Beiträgen und Vorlagen: im Block-Editor den Block „n4s – Menü“ einfügen (Suche „n4s“ oder „Menü“). Rechts wählst du das Menü je Besucher, Angemeldete und Rolle.', 'In pages, posts and templates: insert the block "n4s – Menu" in the block editor (search "n4s" or "menu"). On the right you choose the menu per visitor, logged-in user and role.' ) ) . '</li>';
		echo '<li>' . esc_html( n4s_t( 'In Seitenleisten und Fußzeilen: unter ', 'In sidebars and footers: under ' ) ) . '<a href="' . esc_url( admin_url( 'widgets.php' ) ) . '">' . esc_html( n4s_t( 'Design → Widgets', 'Appearance → Widgets' ) ) . '</a> ' . esc_html( n4s_t( 'denselben Block „n4s – Menü“ einfügen (bei älteren Widget-Bereichen: Widget „n4s – Menü“). Bei Neve auch im Customizer unter Footer-Widgets.', 'insert the same block "n4s – Menu" (for older widget areas: widget "n4s – Menu"). With Neve also in the Customizer under footer widgets.' ) ) . '</li>';
		echo '<li>' . esc_html( n4s_t( 'Überall sonst per Shortcode (unten zusammenstellen und kopieren).', 'Everywhere else with the shortcode (compose and copy it below).' ) ) . '</li></ol>';
		echo '<p style="margin:12px 0 4px"><strong>' . esc_html( n4s_t( 'Shortcode zusammenstellen', 'Compose shortcode' ) ) . '</strong></p>';
		$opt = function ( $first ) use ( $menus ) {
			$h = '';
			foreach ( $first as $v => $l ) {
				$h .= '<option value="' . esc_attr( $v ) . '">' . esc_html( $l ) . '</option>';
			}
			foreach ( $menus as $m ) {
				$h .= '<option value="' . esc_attr( $m->name ) . '">' . esc_html( $m->name ) . '</option>';
			}
			return $h;
		};
		$def  = array( '' => '–', 'kein' => n4s_t( 'Kein Menü', 'No menu' ) );
		$none = array( '' => n4s_t( 'Standard', 'Default' ), 'kein' => n4s_t( 'Kein Menü', 'No menu' ) );
		echo '<div id="n4s-navgen" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px;max-width:900px">';
		echo '<label>' . esc_html( n4s_t( 'Menü (Standard)', 'Menu (default)' ) ) . '<br><select data-n="menu" style="width:100%">' . $opt( array( '' => '–' ) ) . '</select></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Option-HTML selbst escaped.
		echo '<label>' . esc_html( n4s_t( 'Besucher ohne Anmeldung', 'Logged-out visitors' ) ) . '<br><select data-n="gast" style="width:100%">' . $opt( $none ) . '</select></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label>' . esc_html( n4s_t( 'Alle Angemeldeten', 'All logged-in users' ) ) . '<br><select data-n="angemeldet" style="width:100%">' . $opt( $none ) . '</select></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label>' . esc_html( n4s_t( 'Darstellung', 'Appearance' ) ) . '<br><select data-n="stil" style="width:100%"><option value="">' . esc_html( n4s_t( 'Liste (untereinander)', 'List (vertical)' ) ) . '</option><option value="zeile">' . esc_html( n4s_t( 'Zeile (nebeneinander)', 'Row (side by side)' ) ) . '</option><option value="button">' . esc_html( n4s_t( 'Button gefüllt', 'Filled button' ) ) . '</option><option value="umrandet">' . esc_html( n4s_t( 'Button umrandet', 'Outlined button' ) ) . '</option></select></label>';
		echo '<label>' . esc_html( n4s_t( 'Ausrichtung', 'Alignment' ) ) . '<br><select data-n="ausrichtung" style="width:100%"><option value="">' . esc_html( n4s_t( 'Links', 'Left' ) ) . '</option><option value="mitte">' . esc_html( n4s_t( 'Mitte', 'Center' ) ) . '</option><option value="rechts">' . esc_html( n4s_t( 'Rechts', 'Right' ) ) . '</option></select></label><label style="align-self:end"><input type="checkbox" data-n="tiefe" value="2"> ' . esc_html( n4s_t( 'Untermenüs anzeigen', 'Show sub-menus' ) ) . '</label></div>';
		echo '<div style="max-width:900px;margin-top:8px"><strong>' . esc_html( n4s_t( 'Menü je Rolle', 'Menu per role' ) ) . '</strong><br>';
		foreach ( wp_roles()->get_names() as $r => $rl ) {
			echo '<label style="display:inline-block;margin:2px 14px 2px 0">' . esc_html( translate_user_role( $rl ) ) . ' <select data-role="' . esc_attr( $r ) . '">' . $opt( $none ) . '</select></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div><div style="display:flex;gap:8px;align-items:center;max-width:900px;margin-top:10px"><strong>Shortcode:</strong><input type="text" readonly id="n4s-navgen-out" class="code" style="flex:1;min-width:0" value="[n4s_nav]" onclick="this.select()"><button type="button" class="button" id="n4s-navgen-copy" data-done="' . esc_attr( n4s_t( 'Kopiert', 'Copied' ) ) . '">' . esc_html( n4s_t( 'Kopieren', 'Copy' ) ) . '</button></div>';
		echo '<div style="display:flex;gap:8px;align-items:center;max-width:900px;margin-top:8px"><strong>' . esc_html( n4s_t( 'Einlesen:', 'Read in:' ) ) . '</strong><input type="text" id="n4s-navgen-in" class="code" style="flex:1;min-width:0" placeholder="[n4s_nav menu=&quot;Haupt&quot; stil=&quot;button&quot;]"><button type="button" class="button" id="n4s-navgen-load" data-miss="' . esc_attr( n4s_t( 'Kein Shortcode [n4s_nav …] gefunden.', 'No [n4s_nav …] shortcode found.' ) ) . '" data-nomenu="' . esc_attr( n4s_t( 'Menü nicht vorhanden: ', 'Menu not found: ' ) ) . '">' . esc_html( n4s_t( 'Einlesen', 'Read in' ) ) . '</button></div><p class="description" id="n4s-navgen-msg" style="max-width:900px;margin:4px 0 0">' . esc_html( n4s_t( 'Einen vorhandenen Shortcode einfügen, die Auswahl oben übernimmt seine Angaben, danach lässt er sich ändern.', 'Paste an existing shortcode, the selection above takes over its settings and can then be changed.' ) ) . '</p>';
		$js = <<<'N4SJS'
(function(){var g=document.getElementById("n4s-navgen"),o=document.getElementById("n4s-navgen-out"),c=document.getElementById("n4s-navgen-copy");if(!g||!o)return;var wrap=g.parentNode;
function up(){var s="[n4s_nav";["menu","gast","angemeldet","stil","ausrichtung"].forEach(function(n){var e=g.querySelector('[data-n="'+n+'"]');if(e&&e.value)s+=" "+n+'="'+e.value.replace(/"/g,"'")+'"'});
var t=g.querySelector('[data-n="tiefe"]');if(t&&t.checked)s+=' tiefe="2"';var r=[];wrap.querySelectorAll("[data-role]").forEach(function(e){if(e.value)r.push(e.getAttribute("data-role")+":"+e.value.replace(/[;:"]/g," "))});if(r.length)s+=' rollen="'+r.join("; ")+'"';o.value=s+"]"}
wrap.addEventListener("input",up);wrap.addEventListener("change",up);
var li=document.getElementById("n4s-navgen-in"),lb=document.getElementById("n4s-navgen-load"),lm=document.getElementById("n4s-navgen-msg");
function setv(e,v){if(!e)return false;e.value=v;return e.value===v}
lb.addEventListener("click",function(){var t=li.value,m=t.match(/\[n4s_nav\b([^\]]*)\]/i);if(!m){lm.textContent=lb.getAttribute("data-miss");return}
var a={},re=/([a-z_]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'\]]+))/gi,x;while((x=re.exec(m[1]))){a[x[1].toLowerCase()]=x[2]!==undefined?x[2]:(x[3]!==undefined?x[3]:x[4])}
var miss=[];g.querySelectorAll("select[data-n]").forEach(function(e){e.value=""});wrap.querySelectorAll("[data-role]").forEach(function(e){e.value=""});var tt=g.querySelector('[data-n="tiefe"]');tt.checked=false;
["menu","gast","angemeldet","stil","ausrichtung"].forEach(function(n){if(a[n]){var v=a[n].toLowerCase()==="none"||a[n].toLowerCase()==="-1"?"kein":a[n];if(!setv(g.querySelector('[data-n="'+n+'"]'),v))miss.push(a[n])}});
if(a.tiefe&&parseInt(a.tiefe,10)>1)tt.checked=true;
if(a.rollen){a.rollen.split(";").forEach(function(p){var i=p.indexOf(":");if(i<1)return;var r=p.slice(0,i).trim(),v=p.slice(i+1).trim().replace(/^none$|^-1$/i,"kein"),e=wrap.querySelector('[data-role="'+r+'"]');if(!e||!setv(e,v))miss.push(p.trim())})}
up();lm.textContent=miss.length?lb.getAttribute("data-nomenu")+miss.join(", "):"✓"});
c.addEventListener("click",function(){o.select();var d=function(){var x=c.textContent;c.textContent=c.getAttribute("data-done");setTimeout(function(){c.textContent=x},1600)};if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(o.value).then(d,function(){document.execCommand("copy");d()})}else{document.execCommand("copy");d()}});up()})();
N4SJS;
		echo '<script>' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- feste Skriptvorlage.
	}

	/** Vorschlagskarten (Hell, Dunkel, Komplementär) mit Übernehmen-Schaltflächen. */
	function n4s_theme_proposal_cards( $props, $label ) {
		echo '<p style="margin:8px 0 4px"><strong>' . esc_html( $label ) . '</strong></p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;margin:0 0 12px">';
		foreach ( $props as $i => $pr ) {
			echo '<div style="border:1px solid #c3c4c7;padding:8px 10px;background:#fafafa"><strong>' . esc_html( $pr['name'] ) . '</strong><div style="display:flex;gap:3px;margin:6px 0">';
			foreach ( array( 'bg', 'text', 'accent', 'hover', 'light', 'dark' ) as $k ) {
				echo '<span title="' . esc_attr( $k . ' ' . $pr['colors'][ $k ] ) . '" style="width:30px;height:22px;border:1px solid rgba(0,0,0,.2);background:' . esc_attr( $pr['colors'][ $k ] ) . '"></span>';
			}
			echo '</div><label>' . esc_html( n4s_t( 'In Schema', 'Into scheme' ) ) . ' <select class="n4s-prop-target">';
			for ( $n = 1; $n <= 3; $n++ ) {
				echo '<option value="' . (int) $n . '"' . selected( $n, $i + 1, false ) . '>' . (int) $n . '</option>';
			}
			echo '</select></label> <button type="button" class="button n4s-prop-apply" data-name="' . esc_attr( $pr['name'] ) . '" data-colors="' . esc_attr( wp_json_encode( $pr['colors'] ) ) . '">' . esc_html( n4s_t( 'Eintragen', 'Fill in' ) ) . '</button></div>';
		}
		echo '</div>';
	}

	/** Farben des aktuellen Themes, Vorschläge daraus und Farben aus einem Theme-ZIP. */
	function n4s_theme_color_suggestions() {
		$read = n4s_theme_read_colors();
		echo '<div style="border:1px solid #c3c4c7;background:#f6f7f7;padding:10px 14px;margin:0 0 14px"><strong>' . esc_html( n4s_t( 'Farben vorschlagen', 'Suggest colors' ) ) . '</strong><p class="description" style="margin:4px 0 6px">' . esc_html( n4s_t( 'Die Vorschläge werden nur in die Felder eingetragen; gespeichert wird erst mit „Speichern“.', 'The suggestions are only filled into the fields; nothing is saved until you click "Save".' ) ) . '</p>';
		echo '<p style="margin:6px 0"><span>' . esc_html( n4s_t( 'Aktuelles Theme: ', 'Current theme: ' ) ) . '<strong>' . esc_html( $read['source'] ) . '</strong></span> ';
		foreach ( $read['colors'] as $k => $c ) {
			echo '<span title="' . esc_attr( $k . ' ' . $c ) . '" style="display:inline-block;width:26px;height:18px;vertical-align:middle;margin-left:3px;border:1px solid rgba(0,0,0,.2);background:' . esc_attr( $c ) . '"></span>';
		}
		echo '</p>';
		if ( ! empty( $read['colors']['accent'] ) ) {
			n4s_theme_proposal_cards( n4s_theme_proposals( $read['colors'], $read['source'] ), n4s_t( 'Vorschläge aus den Farben des Themes (1 = Farben des Themes)', 'Suggestions from the colors of the theme (1 = colors of the theme)' ) );
		} else {
			echo '<p class="description">' . esc_html( n4s_t( 'Im aktiven Theme wurde keine Hauptfarbe gefunden. Nutze unten ein Theme-ZIP oder trage die Farben selbst ein.', 'No main color was found in the active theme. Use a theme ZIP below or enter the colors yourself.' ) ) . '</p>';
		}
		$imp = get_transient( 'n4s_theme_import_' . get_current_user_id() );
		echo '<p style="margin:12px 0 4px"><strong>' . esc_html( n4s_t( 'Farben aus einem Theme (ZIP) übernehmen', 'Take colors from a theme (ZIP)' ) ) . '</strong></p><p class="description" style="margin:0 0 6px">' . esc_html( n4s_t( 'Die Datei wird nicht installiert und nichts wird ausgeführt: gelesen werden nur theme.json und CSS-Dateien. Die gefundene Hauptfarbe wird zu Farbe 1, die übrigen Farben werden daraus erzeugt.', 'The file is not installed and nothing is executed: only theme.json and CSS files are read. The main color found becomes color 1 and the others are generated from it.' ) ) . '</p>';
		echo '<p style="margin:0 0 6px"><input type="file" name="n4s_theme_zip" accept=".zip" form="n4s-theme-upload"> <button type="submit" form="n4s-theme-upload" class="button">' . esc_html( n4s_t( 'Farben lesen', 'Read colors' ) ) . '</button></p>';
		if ( is_array( $imp ) && ! empty( $imp['accent'] ) ) {
			echo '<p style="margin:6px 0">' . esc_html( n4s_t( 'Aus dem ZIP „', 'From the ZIP "' ) . $imp['name'] . n4s_t( '“: Farbe 1 = ', '": color 1 = ' ) ) . '<span style="display:inline-block;width:26px;height:18px;vertical-align:middle;border:1px solid rgba(0,0,0,.2);background:' . esc_attr( $imp['accent'] ) . '"></span> <code>' . esc_html( $imp['accent'] ) . '</code></p>';
			n4s_theme_proposal_cards( n4s_theme_proposals( array_merge( isset( $imp['colors'] ) ? (array) $imp['colors'] : array(), array( 'accent' => $imp['accent'] ) ), $imp['name'] ), n4s_t( 'Vorschläge aus dem ZIP (1 = Farben des ZIP, Farbe 1 = Hauptfarbe)', 'Suggestions from the ZIP (1 = colors of the ZIP, color 1 = main color)' ) );
		}
		echo '</div>';
		echo '<script>jQuery(function($){$(document).on("click",".n4s-prop-apply",function(){var b=$(this),n=b.closest("div").find(".n4s-prop-target").val(),c=JSON.parse(b.attr("data-colors")),k;$("input[name=\'schemes["+n+"][name]\']").val(b.attr("data-name"));for(k in c){var i=$("input[name=\'schemes["+n+"][colors]["+k+"]\']");if(!i.length)continue;i.val(c[k]);if(c[k]){try{i.wpColorPicker("color",c[k])}catch(e){}}else{i.closest(".wp-picker-container").find(".wp-color-result").css("background-color","")}}b.text(b.text().replace(/ ✓$/,"")+" ✓")})})</script>';
	}

	/** Menü-Auswahl (Select) für die Einstellungen. */
	function n4s_theme_menu_select( $name, $cur ) {
		$h = '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html( n4s_t( 'Standard', 'Default' ) ) . '</option><option value="-1"' . selected( (int) $cur, -1, false ) . '>' . esc_html( n4s_t( 'Kein Menü', 'No menu' ) ) . '</option>';
		foreach ( wp_get_nav_menus() as $m ) {
			$h .= '<option value="' . (int) $m->term_id . '"' . selected( (int) $cur, (int) $m->term_id, false ) . '>' . esc_html( $m->name ) . '</option>';
		}
		return $h . '</select>';
	}

	function n4s_theme_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o      = n4s_theme_opt();
		$th     = wp_get_theme();
		$parent = $th->parent() ? $th->parent() : $th;
		$neve   = 'neve' === strtolower( $parent->get_template() );
		echo '<p>' . esc_html( n4s_t( 'Aktives Theme: ', 'Active theme: ' ) ) . '<strong>' . esc_html( $th->get( 'Name' ) ) . '</strong>' . ( $neve ? ' – ' . esc_html( n4s_t( 'Neve erkannt: Farbschemen färben auch Kopf- und Fußzeile, die Seitenfelder von Neve werden mitgesteuert.', 'Neve detected: color schemes also color header and footer, and Neve\'s page fields are controlled too.' ) ) : ' – ' . esc_html( n4s_t( 'Farbschemen färben die Seite und, soweit das Theme es zulässt, Kopf- und Fußzeile; die Menü-Funktionen gelten für jedes Theme.', 'Color schemes color the page and, as far as the theme allows, header and footer; the menu features work with any theme.' ) ) ) . '</p>';
		n4s_apps_form_head( 'n4s_theme_save', false, 'display:block' );
		$mods = array(
			'menus'       => array( n4s_t( 'Menü je Anmeldestatus und Rolle', 'Menu by login status and role' ), n4s_t( 'Zeigt für Besucher, Angemeldete und einzelne Rollen unterschiedliche Menüs an derselben Menüposition.', 'Shows different menus at the same menu location for visitors, logged-in users and individual roles.' ) ),
			'items'       => array( n4s_t( 'Sichtbarkeit je Menüpunkt', 'Visibility per menu item' ), n4s_t( 'Im Menü-Editor kann jeder Punkt für alle, Besucher, Angemeldete oder bestimmte Rollen gelten.', 'In the menu editor each item can apply to everyone, visitors, logged-in users or certain roles.' ) ),
			'newtab'      => array( n4s_t( 'Neuer Tab je Menüpunkt', 'New tab per menu item' ), n4s_t( 'Im Menü-Editor und im Customizer kann jeder Menüpunkt in allen Menüs seinen Link in einem neuen Tab/Fenster öffnen.', 'In the menu editor and the Customizer each menu item in all menus can open its link in a new tab/window.' ) ),
			'adminbar'    => array( n4s_t( 'Admin-Leiste oben pflegen', 'Manage the admin bar' ), n4s_t( 'Eigenes Menü „Admin-Leiste“ (Design → Menüs und Customizer) für das Dropdown oben, dazu Standard-Einträge ausblenden.', 'Own "Admin bar" menu (Appearance → Menus and Customizer) for the dropdown at the top, plus hiding default entries.' ) ),
			'loginout'    => array( n4s_t( 'Anmelden / Abmelden als Menüpunkt', 'Log in / Log out as menu item' ), n4s_t( 'Im Menü-Editor eine Box zum Hinzufügen; der Punkt schaltet je nach Status um.', 'A box in the menu editor to add it; the item switches depending on the status.' ) ),
			'above'       => array( n4s_t( 'Headermenü', 'Header menu' ), n4s_t( 'Zusätzliche Menüposition „Headermenü“ (Design → Menüs). Die Leiste steht ganz oben über der Kopfzeile, auch mit Menü je Besucher, Angemeldete und Rolle.', 'Additional menu location "Header menu" (Appearance → Menus). The bar sits at the very top above the header, also with a menu per visitor, logged-in user and role.' ) ),
			'abovefooter' => array( n4s_t( 'Footermenü', 'Footer menu' ), n4s_t( 'Zusätzliche Menüposition „Footermenü“ (Design → Menüs). Die Leiste steht über dem Footer, auch mit Menü je Besucher, Angemeldete und Rolle.', 'Additional menu location "Footer menu" (Appearance → Menus). The bar sits above the footer, also with a menu per visitor, logged-in user and role.' ) ),
			'widget'      => array( n4s_t( 'Zusatzmenü (Widget und Shortcode)', 'Additional menu (widget and shortcode)' ), n4s_t( 'Widget „n4s Menü“ für Seitenleisten und Fußzeilen sowie Shortcode [n4s_nav] für Seiten: ein Menü mit eigener Auswahl für Besucher, Angemeldete und Rollen, senkrecht oder in einer Zeile. Funktioniert auch bei Themes mit Block-Navigation.', 'Widget "n4s Menu" for sidebars and footers and shortcode [n4s_nav] for pages: a menu with its own choice for visitors, logged-in users and roles, vertical or in a row. Also works with themes using block navigation.' ) ),
			'page'        => array( n4s_t( 'Seitengestaltung je Seite', 'Page design per page' ), n4s_t( 'Kopfzeile, Hauptmenü, Headermenü, Footermenü, Fußzeile und Seitentitel je Seite ausblenden (Feld im Seiteneditor).', 'Hide header, main menu, header menu, footer menu, footer and page title per page (field in the page editor).' ) ),
			'schemes'     => array( n4s_t( 'Farbschemen (bis zu 3)', 'Color schemes (up to 3)' ), n4s_t( 'Drei Farbschemen definieren und je Seite wählen oder als Standard setzen.', 'Define three color schemes and choose per page or set as default.' ) ),
		);
		// Funktionsübersicht: ein-/ausschalten. Darunter ein zweiter Speichern-Button (speichert wie der am Ende).
		echo '<table class="widefat n4s-swtable" style="width:100%"><thead><tr><th style="width:90px">' . esc_html( n4s_t( 'Aktiv', 'Active' ) ) . '</th><th>' . esc_html( n4s_t( 'Funktion', 'Feature' ) ) . '</th></tr></thead><tbody>';
		foreach ( $mods as $k => $m ) {
			echo '<tr><td><label><input type="checkbox" name="on[' . esc_attr( $k ) . ']" value="1"' . checked( ! empty( $o['on'][ $k ] ), true, false ) . '> ' . esc_html( ! empty( $o['on'][ $k ] ) ? n4s_t( 'an', 'on' ) : n4s_t( 'aus', 'off' ) ) . '</label></td><td><strong>' . esc_html( $m[0] ) . '</strong><br><span class="description">' . esc_html( $m[1] ) . '</span></td></tr>';
		}
		echo '</tbody></table>';
		if ( array_filter( (array) $o['on'] ) ) { // zweiter Button nur, wenn unten Einstellungen stehen
			echo '<p style="margin:10px 0 16px">';
			submit_button( n4s_t( 'Speichern', 'Save' ), 'primary', 'submit-top', false );
			echo ' <span class="description">' . esc_html( n4s_t( 'Nach dem Speichern erscheinen unten die Einstellungen der eingeschalteten Funktionen.', 'After saving, the settings of the switched-on features appear below.' ) ) . '</span></p>';
		} else {
			echo '<p class="description" style="margin:10px 0 16px">' . esc_html( n4s_t( 'Funktion einschalten und speichern: Danach erscheinen hier die Einstellungen.', 'Switch a feature on and save: its settings then appear here.' ) ) . '</p>';
		}

		$render = function ( $key, $body ) use ( $o, $mods ) {
			if ( empty( $o['on'][ $key ] ) ) {
				return;
			}
			echo '<details data-n4s-p="' . esc_attr( $key ) . '" style="background:#fff;border:1px solid #c3c4c7;margin:-1px 0 0;padding:8px 14px"><summary style="cursor:pointer"><strong>' . esc_html( $mods[ $key ][0] ) . '</strong></summary><div style="padding:10px 0 4px">';
			$body();
			if ( in_array( $key, array( 'menus', 'above', 'abovefooter', 'adminbar', 'items', 'newtab', 'page', 'schemes' ), true ) ) { // Zurücksetzen: immer der letzte Punkt
				echo '<p style="margin:14px 0 2px;padding-top:10px;border-top:1px solid #dcdcde"><button type="submit" name="n4s_reset" value="' . esc_attr( $key ) . '" class="button" onclick="return confirm(' . esc_attr( wp_json_encode( n4s_t( 'Einstellungen dieser Funktion auf den Ausgangszustand zurücksetzen?', 'Reset the settings of this feature to their defaults?' ) ) ) . ')">' . esc_html( n4s_t( 'Zurücksetzen', 'Reset' ) ) . '</button> <span class="description">' . esc_html( $key === 'items' ? n4s_t( 'Entfernt alle Sichtbarkeits-Angaben der Menüpunkte.', 'Removes all visibility settings of the menu items.' ) : ( 'newtab' === $key ? n4s_t( 'Entfernt alle Neuer-Tab-Angaben der Menüpunkte.', 'Removes all new-tab settings of the menu items.' ) : ( 'page' === $key ? n4s_t( 'Entfernt alle Ausblende-Angaben der Seiten (Kopfzeile, Menüs, Fußzeile, Titel).', 'Removes all hide settings of the pages (header, menus, footer, title).' ) : n4s_t( 'Stellt die Standardwerte dieser Funktion wieder her.', 'Restores the default values of this feature.' ) ) ) ) . '</span></p>';
			}
			echo '</div></details>';
		};
		$queue = array(); // Felder werden in der Reihenfolge der Funktionsübersicht ausgegeben.
		$panel = function ( $key, $body ) use ( &$queue ) {
			$queue[ $key ] = $body;
		};
		$bar_settings = function ( $key, $what ) use ( $o ) {
			echo '<input type="hidden" name="has[' . esc_attr( $key ) . ']" value="1"><p class="description">' . esc_html( sprintf( n4s_t( 'Das Menü wählst du unter Design → Menüs (Position „%1$s“) oder je Besucher, Angemeldete und Rolle im Feld „Menüs je Position“. Farben leer = die Farben von %2$s.', 'Choose the menu under Appearance → Menus (location "%1$s") or per visitor, logged-in user and role in the "Menus per location" panel. Empty colors = the colors of the %2$s.' ), $what[0], $what[1] ) ) . '</p><p><label>' . esc_html( n4s_t( 'Ausrichtung', 'Alignment' ) ) . ' <select name="' . esc_attr( $key ) . '[align]">';
			foreach ( array( 'left' => n4s_t( 'Links', 'Left' ), 'center' => n4s_t( 'Mitte', 'Center' ), 'right' => n4s_t( 'Rechts', 'Right' ) ) as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $o[ $key ]['align'], $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select></label></p><p><label>' . esc_html( n4s_t( 'Hintergrund', 'Background' ) ) . '<br><input type="text" class="n4s-cp" name="' . esc_attr( $key ) . '[bg]" value="' . esc_attr( $o[ $key ]['bg'] ) . '"></label></p><p><label>' . esc_html( n4s_t( 'Text', 'Text' ) ) . '<br><input type="text" class="n4s-cp" name="' . esc_attr( $key ) . '[text]" value="' . esc_attr( $o[ $key ]['text'] ) . '"></label></p>';
		};
		$help = function ( $text ) {
			return function () use ( $text ) {
				echo '<p class="description" style="max-width:900px">' . esc_html( $text ) . '</p>';
			};
		};

		$panel(
			'menus',
			function () use ( $o ) {
				echo '<input type="hidden" name="has[menus]" value="1"><p class="description">' . esc_html( n4s_t( 'Standard = das der Position zugewiesene Menü (unter Design → Menüs), „Kein Menü“ = an dieser Position wird nichts angezeigt. Für Angemeldete gilt zuerst die Rolle (Administrator vor Redakteur …), dann „Alle Angemeldeten“.', 'Default = the menu assigned to the location (under Appearance → Menus), "No menu" = nothing is shown at this location. For logged-in users the role applies first (administrator before editor …), then "All logged-in users".' ) ) . '</p>';
				$locs = get_registered_nav_menus();
				if ( ! $locs ) {
					echo '<p>' . esc_html( n4s_t( 'Das Theme registriert keine Menüpositionen.', 'The theme registers no menu locations.' ) ) . '</p>';
				}
				foreach ( $locs as $loc => $label ) {
					$c = isset( $o['menus'][ $loc ] ) ? $o['menus'][ $loc ] : array();
					echo '<details data-n4s-p="loc:' . esc_attr( $loc ) . '" style="border:1px solid #dcdcde;margin:-1px 0 0;padding:6px 12px"' . ( $c ? ' open' : '' ) . '><summary><strong>' . esc_html( $label ) . '</strong> <code>' . esc_html( $loc ) . '</code></summary><table class="form-table" style="margin:6px 0"><tbody>';
					echo '<tr><th>' . esc_html( n4s_t( 'Besucher ohne Anmeldung', 'Logged-out visitors' ) ) . '</th><td>' . n4s_theme_menu_select( 'menus[' . $loc . '][gast]', isset( $c['gast'] ) ? $c['gast'] : 0 ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Select-HTML selbst escaped.
					echo '<tr><th>' . esc_html( n4s_t( 'Alle Angemeldeten', 'All logged-in users' ) ) . '</th><td>' . n4s_theme_menu_select( 'menus[' . $loc . '][user]', isset( $c['user'] ) ? $c['user'] : 0 ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					foreach ( wp_roles()->get_names() as $r => $rl ) {
						echo '<tr><th>' . esc_html( translate_user_role( $rl ) ) . '</th><td>' . n4s_theme_menu_select( 'menus[' . $loc . '][roles][' . $r . ']', isset( $c['roles'][ $r ] ) ? $c['roles'][ $r ] : 0 ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo '</tbody></table></details>';
				}
			}
		);
		$panel(
			'above',
			function () use ( $bar_settings ) {
				$bar_settings( 'above', array( n4s_t( 'Headermenü (über dem Header)', 'Header menu (above the header)' ), n4s_t( 'Kopfzeile', 'header' ) ) );
			}
		);
		$panel(
			'abovefooter',
			function () use ( $bar_settings ) {
				$bar_settings( 'abovefooter', array( n4s_t( 'Footermenü (über dem Footer)', 'Footer menu (above the footer)' ), n4s_t( 'Fußzeile', 'footer' ) ) );
			}
		);
		$panel( 'widget', function () {
			n4s_theme_widget_panel();
		} );
		$panel( 'items', $help( n4s_t( 'Im Menü-Editor (Design → Menüs) hat jeder Menüpunkt das Feld „n4s – Sichtbar für“: alle Besucher, nur Besucher ohne Anmeldung, nur Angemeldete oder nur bestimmte Rollen.', 'In the menu editor (Appearance → Menus) each menu item has the field "n4s – Visible for": all visitors, logged-out visitors only, logged-in users only or only certain roles.' ) ) );
		$panel( 'newtab', $help( n4s_t( 'Im Menü-Editor (Design → Menüs) und im Customizer hat jeder Menüpunkt das Feld „n4s – Link in neuem Tab/Fenster öffnen“. Es gilt in allen Menüs, auch im Headermenü und Footermenü.', 'In the menu editor (Appearance → Menus) and the Customizer each menu item has the field "n4s – Open link in a new tab/window". It applies in all menus, including the header and footer menu.' ) ) );
		$panel(
			'adminbar',
			function () use ( $o ) {
				$ab = $o['adminbar'];
				echo '<input type="hidden" name="has[adminbar]" value="1"><p class="description" style="max-width:900px">' . esc_html( n4s_t( 'Die Einträge pflegst du wie ein normales Menü unter Design → Menüs oder im Customizer (Menüs → Position „Admin-Leiste“): hinzufügen, sortieren, einrücken (Untermenü), Sichtbarkeit je Rolle und neuer Tab, falls diese Funktionen aktiv sind.', 'You manage the entries like a normal menu under Appearance → Menus or in the Customizer (Menus → location "Admin bar"): add, sort, indent (sub-menu), visibility by role and new tab, if those features are active.' ) ) . '</p>';
				echo '<p><label>' . esc_html( n4s_t( 'Einträge erscheinen', 'Entries appear' ) ) . ' <select name="adminbar[parent]"><option value="site-name"' . selected( $ab['parent'], 'site-name', false ) . '>' . esc_html( n4s_t( 'im Dropdown des Seitennamens', 'in the site name dropdown' ) ) . '</option><option value="top"' . selected( $ab['parent'], 'top', false ) . '>' . esc_html( n4s_t( 'als eigene Punkte in der Leiste', 'as separate items in the bar' ) ) . '</option></select></label></p>';
				echo '<p><strong>' . esc_html( n4s_t( 'Standard-Einträge ausblenden', 'Hide default entries' ) ) . '</strong></p><p>';
				foreach ( n4s_theme_adminbar_nodes() as $id => $l ) {
					echo '<label style="display:block;margin:2px 0"><input type="checkbox" name="adminbar[hide][]" value="' . esc_attr( $id ) . '"' . checked( in_array( $id, (array) $ab['hide'], true ), true, false ) . '> ' . esc_html( $l ) . '</label>';
				}
				echo '</p><p class="description">' . esc_html( n4s_t( 'Die Menüposition wird gespeichert, sobald du hier speicherst; danach erscheint sie unter Design → Menüs.', 'The menu location becomes available once you save here; it then appears under Appearance → Menus.' ) ) . '</p>';
			}
		);
		$panel( 'loginout', $help( n4s_t( 'Im Menü-Editor (Design → Menüs) gibt es die Box „n4s – Anmelden / Abmelden“. Der Menüpunkt „Anmelden|Abmelden“ zeigt links den Text für Besucher und rechts den für Angemeldete.', 'In the menu editor (Appearance → Menus) there is the box "n4s – Log in / Log out". The item "Log in|Log out" shows the text on the left for visitors and on the right for logged-in users.' ) ) );
		$panel( 'page', $help( n4s_t( 'Im Editor jeder Seite gibt es die Box „n4s – Seitengestaltung“: Kopfzeile, Hauptmenü, Headermenü, Footermenü, Fußzeile und Seitentitel einzeln ausblenden.', 'In the editor of every page there is the box "n4s – Page design": hide header, main menu, header menu, footer menu, footer and page title individually.' ) ) );
		$panel(
			'schemes',
			function () use ( $o ) {
				n4s_theme_color_suggestions();
				echo '<input type="hidden" name="has[schemes]" value="1"><p class="description">' . esc_html( n4s_t( 'Jedes Schema ändert die Farben der Seite. Leere Felder behalten die Farbe des Themes. Gewählt wird das Schema je Seite im Seiteneditor („n4s – Seitengestaltung“).', 'Each scheme changes the colors of the page. Empty fields keep the theme color. The scheme is chosen per page in the page editor ("n4s – Page design").' ) ) . '</p>';
				echo '<p><label>' . esc_html( n4s_t( 'Standard für alle Seiten ohne eigene Wahl: ', 'Default for all pages without their own choice: ' ) ) . '<select name="default"><option value="0">' . esc_html( n4s_t( 'Theme-Farben', 'Theme colors' ) ) . '</option>';
				foreach ( $o['schemes'] as $n => $s ) {
					echo '<option value="' . (int) $n . '"' . selected( (int) $o['default'], $n, false ) . '>' . esc_html( $n . ' – ' . $s['name'] ) . '</option>';
				}
				echo '</select></label></p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px">';
				foreach ( $o['schemes'] as $n => $s ) {
					echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:10px 12px"><p style="margin:0 0 8px"><strong>' . (int) $n . '</strong> <input type="text" name="schemes[' . (int) $n . '][name]" value="' . esc_attr( $s['name'] ) . '" maxlength="30" style="width:70%"></p>';
					foreach ( n4s_theme_color_fields() as $k => $f ) {
						echo '<p style="margin:0 0 6px"><span style="display:inline-block;width:100%">' . esc_html( $f[0] ) . '</span><input type="text" class="n4s-cp" name="schemes[' . (int) $n . '][colors][' . esc_attr( $k ) . ']" value="' . esc_attr( $s['colors'][ $k ] ) . '"></p>';
					}
					echo '</div>';
				}
				echo '</div>';
				// Sicherung: Farbschemen als Datei sichern und wieder einspielen
				echo '<div style="border:1px solid #c3c4c7;background:#f6f7f7;padding:10px 14px;margin:14px 0 0"><strong>' . esc_html( n4s_t( 'Sicherung der Farbschemen', 'Backup of the color schemes' ) ) . '</strong><p class="description" style="margin:4px 0 8px">' . esc_html( n4s_t( 'Exportiert die drei Farbschemen und das Standardschema als Datei (JSON). Der Import ersetzt die gespeicherten Farbschemen der Datei; noch nicht gespeicherte Änderungen im Formular gehen dabei verloren.', 'Exports the three color schemes and the default scheme as a file (JSON). The import replaces the saved color schemes with those of the file; unsaved changes in the form are lost.' ) ) . '</p>';
				echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=n4s_scheme_export' ), 'n4s_scheme_export' ) ) . '">' . esc_html( n4s_t( 'Farbschemen exportieren', 'Export color schemes' ) ) . '</a> ';
				echo '<input type="file" name="n4s_scheme_file" accept=".json,application/json" form="n4s-scheme-import"> <button type="submit" form="n4s-scheme-import" class="button" onclick="return confirm(\'' . esc_js( n4s_t( 'Die gespeicherten Farbschemen durch die Datei ersetzen?', 'Replace the saved color schemes with the file?' ) ) . '\')">' . esc_html( n4s_t( 'Importieren', 'Import' ) ) . '</button></div>';
			}
		);
		foreach ( array_keys( $mods ) as $k ) {
			if ( isset( $queue[ $k ] ) ) {
				$render( $k, $queue[ $k ] );
			}
		}
		echo '<p style="margin-top:14px">';
		submit_button( n4s_t( 'Speichern', 'Save' ), 'primary', 'submit', false );
		echo '</p></form><script>(function(){var K="n4s_theme_open",m={};try{m=JSON.parse(localStorage.getItem(K)||"{}")}catch(e){}if(/[?&]open=schemes/.test(location.search))m.schemes=1;var d=document.querySelectorAll("details[data-n4s-p]");for(var i=0;i<d.length;i++){(function(e){var k=e.getAttribute("data-n4s-p");if(Object.prototype.hasOwnProperty.call(m,k))e.open=!!m[k];e.addEventListener("toggle",function(){m[k]=e.open?1:0;try{localStorage.setItem(K,JSON.stringify(m))}catch(x){}})})(d[i])}document.addEventListener("submit",function(){for(var j=0;j<d.length;j++){m[d[j].getAttribute("data-n4s-p")]=d[j].open?1:0}try{localStorage.setItem(K,JSON.stringify(m))}catch(x){}},true)})();</script><form id="n4s-theme-upload" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="n4s_theme_import"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'n4s_theme_import' ) ) . '"></form><form id="n4s-scheme-import" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="n4s_scheme_import"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'n4s_scheme_import' ) ) . '"></form><script>jQuery(function($){$(".n4s-cp").wpColorPicker({clear:function(){}})});</script>';
	}

	/** Farbschemen als JSON-Datei sichern. */
	add_action(
		'admin_post_n4s_scheme_export',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'n4s_scheme_export' ) ) {
				wp_die( esc_html( n4s_t( 'Der Link ist abgelaufen. Bitte die Seite neu laden.', 'The link has expired. Please reload the page.' ) ), 403 );
			}
			$o   = n4s_theme_opt();
			$out = array( 'n4s_theme_schemes' => 1, 'exported' => gmdate( 'c' ), 'default' => (int) $o['default'], 'schemes' => $o['schemes'] );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="n4s-farbschemen-' . gmdate( 'Ymd' ) . '.json"' );
			echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-Datei.
			exit;
		}
	);

	/** Farbschemen aus einer Sicherungsdatei einspielen (nur bekannte Felder, nur gültige Farben). */
	add_action(
		'admin_post_n4s_scheme_import',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			n4s_apps_verify( 'n4s_scheme_import' );
			$f = isset( $_FILES['n4s_scheme_file'] ) ? $_FILES['n4s_scheme_file'] : null; // phpcs:ignore WordPress.Security
			if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) || (int) $f['size'] > 200000 ) {
				n4s_apps_notice( 'error', n4s_t( 'Bitte eine JSON-Datei bis 200 KB auswählen.', 'Please choose a JSON file up to 200 KB.' ) );
				n4s_apps_back( false, 'n4s-theme' );
			}
			$j = json_decode( (string) file_get_contents( $f['tmp_name'] ), true );
			if ( ! is_array( $j ) || empty( $j['n4s_theme_schemes'] ) || empty( $j['schemes'] ) || ! is_array( $j['schemes'] ) ) {
				n4s_apps_notice( 'error', n4s_t( 'Das ist keine Sicherungsdatei der Farbschemen.', 'This is not a color scheme backup file.' ) );
				n4s_apps_back( false, 'n4s-theme' );
			}
			$d   = n4s_theme_defaults();
			$cur = n4s_theme_opt();
			$n_ok = 0;
			for ( $n = 1; $n <= 3; $n++ ) {
				if ( empty( $j['schemes'][ $n ] ) || ! is_array( $j['schemes'][ $n ] ) ) {
					continue;
				}
				$s      = $j['schemes'][ $n ];
				$name   = isset( $s['name'] ) ? mb_substr( sanitize_text_field( (string) $s['name'] ), 0, 30 ) : '';
				$colors = array();
				foreach ( array_keys( n4s_theme_color_fields() ) as $k ) {
					$v            = isset( $s['colors'][ $k ] ) ? sanitize_hex_color( trim( (string) $s['colors'][ $k ] ) ) : '';
					$colors[ $k ] = $v ? $v : '';
				}
				$cur['schemes'][ $n ] = array( 'name' => '' !== $name ? $name : $d['schemes'][ $n ]['name'], 'colors' => $colors );
				++$n_ok;
			}
			if ( ! $n_ok ) {
				n4s_apps_notice( 'error', n4s_t( 'Die Datei enthält keine Farbschemen.', 'The file contains no color schemes.' ) );
				n4s_apps_back( false, 'n4s-theme' );
			}
			$dn             = isset( $j['default'] ) ? (int) $j['default'] : 0;
			$cur['default'] = ( $dn >= 1 && $dn <= 3 ) ? $dn : 0;
			update_option( 'n4s_theme', $cur, false );
			n4s_theme_opt( true );
			n4s_apps_notice( 'success', sprintf( n4s_t( '%d Farbschema(en) eingespielt.', '%d color scheme(s) imported.' ), $n_ok ) );
			wp_safe_redirect( admin_url( 'admin.php?page=n4s-theme&open=schemes' ) );
			exit;
		}
	);

	/** Theme-ZIP hochladen: nur lesen (theme.json, CSS), Farbe 1 merken. */
	add_action(
		'admin_post_n4s_theme_import',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			n4s_apps_verify( 'n4s_theme_import' );
			$f = isset( $_FILES['n4s_theme_zip'] ) ? $_FILES['n4s_theme_zip'] : null; // phpcs:ignore WordPress.Security
			if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) || 'zip' !== strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) ) || (int) $f['size'] > 20971520 ) {
				n4s_apps_notice( 'error', n4s_t( 'Bitte eine ZIP-Datei bis 20 MB auswählen.', 'Please choose a ZIP file up to 20 MB.' ) );
				n4s_apps_back( false, 'n4s-theme' );
			}
			$r = n4s_theme_zip_colors( $f['tmp_name'] );
			if ( is_wp_error( $r ) ) {
				n4s_apps_notice( 'error', $r->get_error_message() );
				n4s_apps_back( false, 'n4s-theme' );
			}
			set_transient( 'n4s_theme_import_' . get_current_user_id(), $r, 30 * MINUTE_IN_SECONDS );
			n4s_apps_notice( 'success', sprintf( n4s_t( 'Farbe 1 aus „%1$s“: %2$s. Die Vorschläge stehen bei den Farbschemen.', 'Color 1 from "%1$s": %2$s. The suggestions are in the color schemes.' ), $r['name'], $r['accent'] ) );
			wp_safe_redirect( admin_url( 'admin.php?page=n4s-theme&open=schemes' ) );
			exit;
		}
	);

	add_action(
		'admin_post_n4s_theme_save',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			n4s_apps_verify( 'n4s_theme_save' );
			$d   = n4s_theme_defaults();
			$cur = n4s_theme_opt();
			if ( ! empty( $_POST['n4s_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- siehe n4s_apps_verify.
				$k = sanitize_key( wp_unslash( $_POST['n4s_reset'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				if ( 'menus' === $k ) {
					$cur['menus'] = array();
				} elseif ( 'above' === $k || 'abovefooter' === $k || 'adminbar' === $k ) {
					$cur[ $k ] = $d[ $k ];
				} elseif ( 'schemes' === $k ) {
					$cur['schemes'] = $d['schemes'];
					$cur['default'] = 0;
				} elseif ( 'items' === $k ) {
					delete_post_meta_by_key( '_n4s_vis' );
				} elseif ( 'newtab' === $k ) {
					delete_post_meta_by_key( '_n4s_newtab' );
				} elseif ( 'page' === $k ) {
					foreach ( array( 'header', 'footer', 'title', 'mainmenu', 'hmenu', 'fmenu' ) as $w ) {
						delete_post_meta_by_key( '_n4s_hide_' . $w );
					}
				} else {
					n4s_apps_back( false, 'n4s-theme' );
				}
				update_option( 'n4s_theme', $cur, false );
				n4s_theme_opt( true );
				n4s_apps_notice( 'success', n4s_t( 'Einstellungen zurückgesetzt.', 'Settings reset.' ) );
				n4s_apps_back( false, 'n4s-theme' );
			}
			$has = isset( $_POST['has'] ) && is_array( $_POST['has'] ) ? $_POST['has'] : array(); // phpcs:ignore WordPress.Security -- nur Schlüssel geprüft.
			$in  = array(
				'on'          => array(),
				'menus'       => $cur['menus'],
				'default'     => $cur['default'],
				'schemes'     => $cur['schemes'],
				'above'       => $cur['above'],
				'abovefooter' => $cur['abovefooter'],
				'adminbar'    => $cur['adminbar'],
			);
			foreach ( array_keys( $d['on'] ) as $k ) {
				$in['on'][ $k ] = ! empty( $_POST['on'][ $k ] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification -- siehe n4s_apps_verify.
			}
			// Einstellungen nur übernehmen, wenn ihr Feld im Formular war (ausgeschaltete Funktionen bleiben unverändert gespeichert).
			if ( ! empty( $has['menus'] ) ) {
				$valid_menus = array_map( function ( $m ) {
					return (int) $m->term_id;
				}, wp_get_nav_menus() );
				$ok_menu     = function ( $v ) use ( $valid_menus ) {
					$v = (int) $v;
					return ( -1 === $v || in_array( $v, $valid_menus, true ) ) ? $v : 0; // -1 = kein Menü
				};
				$roles       = array_keys( wp_roles()->get_names() );
				$posted      = isset( $_POST['menus'] ) && is_array( $_POST['menus'] ) ? wp_unslash( $_POST['menus'] ) : array(); // phpcs:ignore WordPress.Security
				$in['menus'] = array();
				foreach ( get_registered_nav_menus() as $loc => $l ) {
					if ( empty( $posted[ $loc ] ) || ! is_array( $posted[ $loc ] ) ) {
						continue;
					}
					$c = array(
						'gast'  => $ok_menu( isset( $posted[ $loc ]['gast'] ) ? $posted[ $loc ]['gast'] : 0 ),
						'user'  => $ok_menu( isset( $posted[ $loc ]['user'] ) ? $posted[ $loc ]['user'] : 0 ),
						'roles' => array(),
					);
					foreach ( $roles as $r ) {
						$v = $ok_menu( isset( $posted[ $loc ]['roles'][ $r ] ) ? $posted[ $loc ]['roles'][ $r ] : 0 );
						if ( $v ) {
							$c['roles'][ $r ] = $v;
						}
					}
					if ( $c['gast'] || $c['user'] || $c['roles'] ) {
						$in['menus'][ $loc ] = $c;
					}
				}
			}
			foreach ( array( 'above', 'abovefooter' ) as $bar ) {
				if ( ! empty( $has[ $bar ] ) ) {
					$pa         = isset( $_POST[ $bar ] ) && is_array( $_POST[ $bar ] ) ? wp_unslash( $_POST[ $bar ] ) : array(); // phpcs:ignore WordPress.Security
					$al         = isset( $pa['align'] ) ? sanitize_key( $pa['align'] ) : 'right';
					$in[ $bar ] = array(
						'align' => in_array( $al, array( 'left', 'center', 'right' ), true ) ? $al : 'right',
						'bg'    => isset( $pa['bg'] ) ? (string) sanitize_hex_color( trim( (string) $pa['bg'] ) ) : '',
						'text'  => isset( $pa['text'] ) ? (string) sanitize_hex_color( trim( (string) $pa['text'] ) ) : '',
					);
				}
			}
			if ( ! empty( $has['adminbar'] ) ) {
				$pa     = isset( $_POST['adminbar'] ) && is_array( $_POST['adminbar'] ) ? wp_unslash( $_POST['adminbar'] ) : array(); // phpcs:ignore WordPress.Security
				$hide   = array();
				$valid  = array_keys( n4s_theme_adminbar_nodes() );
				foreach ( isset( $pa['hide'] ) && is_array( $pa['hide'] ) ? $pa['hide'] : array() as $h ) {
					if ( is_string( $h ) && in_array( $h, $valid, true ) ) {
						$hide[] = $h;
					}
				}
				$in['adminbar'] = array(
					'parent' => ( isset( $pa['parent'] ) && 'top' === $pa['parent'] ) ? 'top' : 'site-name',
					'hide'   => $hide,
				);
			}
			if ( ! empty( $has['schemes'] ) ) {
				$dn            = isset( $_POST['default'] ) ? (int) $_POST['default'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
				$in['default'] = ( $dn >= 1 && $dn <= 3 ) ? $dn : 0;
				$ps            = isset( $_POST['schemes'] ) && is_array( $_POST['schemes'] ) ? wp_unslash( $_POST['schemes'] ) : array(); // phpcs:ignore WordPress.Security
				for ( $n = 1; $n <= 3; $n++ ) {
					$name   = isset( $ps[ $n ]['name'] ) ? sanitize_text_field( $ps[ $n ]['name'] ) : '';
					$colors = array();
					foreach ( array_keys( n4s_theme_color_fields() ) as $k ) {
						$v            = isset( $ps[ $n ]['colors'][ $k ] ) ? sanitize_hex_color( trim( (string) $ps[ $n ]['colors'][ $k ] ) ) : '';
						$colors[ $k ] = $v ? $v : '';
					}
					$in['schemes'][ $n ] = array( 'name' => '' !== $name ? mb_substr( $name, 0, 30 ) : $d['schemes'][ $n ]['name'], 'colors' => $colors );
				}
			}
			update_option( 'n4s_theme', $in, false );
			n4s_theme_opt( true );
			n4s_apps_notice( 'success', n4s_t( 'Theme-Einstellungen gespeichert.', 'Theme settings saved.' ) );
			n4s_apps_back( false, 'n4s-theme' );
		}
	);
}
