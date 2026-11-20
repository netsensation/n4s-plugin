<?php
/**
 * n4s Theme-Engine: Menüs je Anmeldestatus und Rolle, Menüpunkte nach Sichtbarkeit, Anmelden/Abmelden als Menüpunkt,
 * Seitengestaltung (Kopfzeile, Fußzeile, Titel ausblenden) und bis zu drei Farbschemen je Seite.
 *
 * Alles läuft über WordPress-Hooks und braucht keine Änderung am Theme. Die Menü-Funktionen sind theme-unabhängig.
 * Die Farbschemen setzen die Farbvariablen von Neve (--nv-…); bei anderen Themes färben sie Hintergrund und Text der Seite.
 * Jedes Modul ist im Tab „Theme“ einzeln schaltbar (Standard: aus).
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
	);
}

function n4s_theme_defaults() {
	return array(
		'on'      => array( 'menus' => 0, 'items' => 0, 'loginout' => 0, 'widget' => 0, 'above' => 0, 'page' => 0, 'schemes' => 0 ),
		'above'   => array( 'align' => 'right', 'bg' => '', 'text' => '' ),
		'menus'   => array(),
		'default' => 0,
		'schemes' => array(
			1 => array(
				'name'   => n4s_t( 'Hell', 'Light' ),
				'colors' => array( 'bg' => '#ffffff', 'text' => '#272626', 'accent' => '#2f5aae', 'hover' => '#24478c', 'light' => '#f4f5f7', 'dark' => '#121212', 'darktext' => '#ffffff', 'btntext' => '#ffffff', 'heading' => '' ),
			),
			2 => array(
				'name'   => n4s_t( 'Dunkel', 'Dark' ),
				'colors' => array( 'bg' => '#0f1419', 'text' => '#e6e9ee', 'accent' => '#4098e0', 'hover' => '#6ec1f5', 'light' => '#1a212a', 'dark' => '#06090c', 'darktext' => '#e6e9ee', 'btntext' => '#0f1419', 'heading' => '' ),
			),
			3 => array(
				'name'   => n4s_t( 'Akzent', 'Accent' ),
				'colors' => array( 'bg' => '#2f5aae', 'text' => '#ffffff', 'accent' => '#ffd54a', 'hover' => '#ffe27d', 'light' => '#3a68c2', 'dark' => '#1d3a75', 'darktext' => '#ffffff', 'btntext' => '#1d1d1d', 'heading' => '#ffffff' ),
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
		foreach ( array( 'on', 'menus', 'default', 'above' ) as $k ) {
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

/* ------------------------------------------------------------------ Menüleiste über dem Header */

add_action(
	'after_setup_theme',
	function () {
		if ( n4s_theme_on( 'above' ) ) {
			register_nav_menus( array( 'n4s-above' => 'n4s – ' . n4s_t( 'Leiste über dem Header', 'Bar above the header' ) ) );
		}
	},
	30
);

add_action(
	'wp_body_open',
	function () {
		static $done = false;
		if ( $done || ! n4s_theme_on( 'above' ) ) {
			return;
		}
		$menu = wp_nav_menu(
			array(
				'theme_location'  => 'n4s-above',
				'container'       => false,
				'menu_class'      => 'n4s-above-list',
				'menu_id'         => '',
				'depth'           => 1,
				'fallback_cb'     => '__return_empty_string',
				'echo'            => false,
			)
		);
		if ( ! $menu || '' === trim( wp_strip_all_tags( $menu ) ) ) {
			return;
		}
		$done  = true;
		$o     = n4s_theme_opt()['above'];
		$bg    = sanitize_hex_color( (string) $o['bg'] );
		$tx    = sanitize_hex_color( (string) $o['text'] );
		$just  = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' );
		$al    = isset( $just[ $o['align'] ] ) ? $o['align'] : 'right';
		$css   = '.n4s-above-header{background:' . ( $bg ? $bg : 'var(--nv-dark-bg,#1d2327)' ) . ';color:' . ( $tx ? $tx : 'var(--nv-text-dark-bg,#fff)' ) . ';font-size:.85rem;line-height:1.4}'
			. '.n4s-above-header .n4s-above-inner{max-width:var(--container,1170px);margin:0 auto;padding:6px 15px;display:flex;justify-content:' . $just[ $al ] . '}'
			. '.n4s-above-header .n4s-above-list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:2px 18px}'
			. '.n4s-above-header a{color:inherit;text-decoration:none}.n4s-above-header a:hover{text-decoration:underline}'
			. 'body.n4s-no-header .n4s-above-header{display:none}';
		echo '<style id="n4s-above-css">' . $css . '</style><div class="n4s-above-header"><div class="n4s-above-inner">' . $menu . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Menü von wp_nav_menu, Farben als Hex geprüft.
	},
	1
);

/* ------------------------------------------------------------------ Zusatzmenü: Widget und Shortcode */

/** Menü-Objekt aus Name, Slug oder ID. */
function n4s_theme_menu_id( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v || '0' === $v ) {
		return 0;
	}
	$m = wp_get_nav_menu_object( ctype_digit( $v ) ? (int) $v : $v );
	return $m ? (int) $m->term_id : 0;
}

/** Zusatzmenü als HTML. cfg: menu, gast, user, roles (Rolle => Menü), stil (liste|zeile), tiefe, klasse. */
function n4s_theme_nav_html( $cfg ) {
	$def  = n4s_theme_menu_id( isset( $cfg['menu'] ) ? $cfg['menu'] : 0 );
	if ( ! is_user_logged_in() ) {
		$id = n4s_theme_menu_id( isset( $cfg['gast'] ) ? $cfg['gast'] : 0 );
	} else {
		$id = n4s_theme_menu_for( array( 'user' => n4s_theme_menu_id( isset( $cfg['user'] ) ? $cfg['user'] : 0 ), 'roles' => array_map( 'n4s_theme_menu_id', (array) ( isset( $cfg['roles'] ) ? $cfg['roles'] : array() ) ) ) );
	}
	$id = $id ? $id : $def;
	if ( ! $id ) {
		return '';
	}
	$stil = ( isset( $cfg['stil'] ) && 'zeile' === $cfg['stil'] ) ? 'zeile' : 'liste';
	$tief = ( isset( $cfg['tiefe'] ) && (int) $cfg['tiefe'] > 1 ) ? 2 : 1;
	$kl   = isset( $cfg['klasse'] ) ? implode( ' ', array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $cfg['klasse'] ) ) ) ) : '';
	static $css = false;
	$out = '';
	if ( ! $css ) {
		$css = true;
		$out = '<style>.n4s-nav .n4s-nav-list,.n4s-nav .n4s-nav-list ul{list-style:none;margin:0;padding:0}.n4s-nav .n4s-nav-list ul{padding-left:1em}.n4s-nav a{text-decoration:none}.n4s-nav-liste .n4s-nav-list>li{margin:0 0 .4em}.n4s-nav-zeile .n4s-nav-list{display:flex;flex-wrap:wrap;gap:.4em 1.2em}.n4s-nav-zeile .n4s-nav-list ul{display:none}</style>';
	}
	return $out . wp_nav_menu(
		array(
			'menu'            => $id,
			'container'       => 'nav',
			'container_class' => 'n4s-nav n4s-nav-' . $stil . ( '' !== $kl ? ' ' . $kl : '' ),
			'menu_class'      => 'n4s-nav-list',
			'menu_id'         => '',
			'depth'           => $tief,
			'fallback_cb'     => '__return_empty_string',
			'echo'            => false,
		)
	);
}

add_shortcode(
	'n4s_nav',
	function ( $atts ) {
		if ( ! n4s_theme_on( 'widget' ) ) {
			return '';
		}
		$a     = shortcode_atts( array( 'menu' => '', 'gast' => '', 'angemeldet' => '', 'rollen' => '', 'stil' => 'liste', 'tiefe' => '1', 'klasse' => '' ), $atts, 'n4s_nav' );
		$roles = array();
		foreach ( explode( ';', (string) $a['rollen'] ) as $pair ) { // rollen="editor:Redaktion; author:Autorenmenü"
			$p = array_map( 'trim', explode( ':', $pair, 2 ) );
			if ( 2 === count( $p ) && '' !== $p[0] ) {
				$roles[ sanitize_key( $p[0] ) ] = $p[1];
			}
		}
		return n4s_theme_nav_html( array( 'menu' => $a['menu'], 'gast' => $a['gast'], 'user' => $a['angemeldet'], 'roles' => $roles, 'stil' => $a['stil'], 'tiefe' => $a['tiefe'], 'klasse' => $a['klasse'] ) );
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
		echo '<p><label for="' . esc_attr( $this->get_field_id( $field ) ) . '">' . esc_html( $label ) . '</label><select class="widefat" id="' . esc_attr( $this->get_field_id( $field ) ) . '" name="' . esc_attr( $this->get_field_name( $field ) ) . '"><option value="0">–</option>';
		foreach ( wp_get_nav_menus() as $m ) {
			echo '<option value="' . (int) $m->term_id . '"' . selected( (int) $cur, (int) $m->term_id, false ) . '>' . esc_html( $m->name ) . '</option>';
		}
		echo '</select></p>';
	}

	public function form( $inst ) {
		$inst = wp_parse_args( (array) $inst, array( 'title' => '', 'menu' => 0, 'gast' => 0, 'user' => 0, 'roles' => array(), 'stil' => 'liste', 'tiefe' => 1 ) );
		echo '<p><label for="' . esc_attr( $this->get_field_id( 'title' ) ) . '">' . esc_html( n4s_t( 'Titel', 'Title' ) ) . '</label><input class="widefat" type="text" id="' . esc_attr( $this->get_field_id( 'title' ) ) . '" name="' . esc_attr( $this->get_field_name( 'title' ) ) . '" value="' . esc_attr( $inst['title'] ) . '"></p>';
		$this->menu_select( 'menu', $inst['menu'], n4s_t( 'Menü (Standard)', 'Menu (default)' ) );
		$this->menu_select( 'gast', $inst['gast'], n4s_t( 'Menü für Besucher ohne Anmeldung', 'Menu for logged-out visitors' ) );
		$this->menu_select( 'user', $inst['user'], n4s_t( 'Menü für alle Angemeldeten', 'Menu for all logged-in users' ) );
		echo '<details><summary>' . esc_html( n4s_t( 'Menü je Rolle', 'Menu per role' ) ) . '</summary>';
		foreach ( wp_roles()->get_names() as $r => $label ) {
			$this->menu_select( 'roles_' . $r, isset( $inst['roles'][ $r ] ) ? $inst['roles'][ $r ] : 0, translate_user_role( $label ) );
		}
		echo '</details><p><label for="' . esc_attr( $this->get_field_id( 'stil' ) ) . '">' . esc_html( n4s_t( 'Darstellung', 'Appearance' ) ) . '</label><select class="widefat" id="' . esc_attr( $this->get_field_id( 'stil' ) ) . '" name="' . esc_attr( $this->get_field_name( 'stil' ) ) . '"><option value="liste"' . selected( $inst['stil'], 'liste', false ) . '>' . esc_html( n4s_t( 'Liste (untereinander)', 'List (vertical)' ) ) . '</option><option value="zeile"' . selected( $inst['stil'], 'zeile', false ) . '>' . esc_html( n4s_t( 'Zeile (nebeneinander)', 'Row (side by side)' ) ) . '</option></select></p>';
		echo '<p><label><input type="checkbox" name="' . esc_attr( $this->get_field_name( 'tiefe' ) ) . '" value="2"' . checked( (int) $inst['tiefe'], 2, false ) . '> ' . esc_html( n4s_t( 'Untermenüs anzeigen (Liste)', 'Show sub-menus (list)' ) ) . '</label></p>';
	}

	public function update( $new, $old ) {
		$valid = array_map( function ( $m ) {
			return (int) $m->term_id;
		}, wp_get_nav_menus() );
		$ok    = function ( $v ) use ( $valid ) {
			$v = (int) $v;
			return in_array( $v, $valid, true ) ? $v : 0;
		};
		$out   = array(
			'title' => sanitize_text_field( isset( $new['title'] ) ? $new['title'] : '' ),
			'menu'  => $ok( isset( $new['menu'] ) ? $new['menu'] : 0 ),
			'gast'  => $ok( isset( $new['gast'] ) ? $new['gast'] : 0 ),
			'user'  => $ok( isset( $new['user'] ) ? $new['user'] : 0 ),
			'stil'  => ( isset( $new['stil'] ) && 'zeile' === $new['stil'] ) ? 'zeile' : 'liste',
			'tiefe' => ( isset( $new['tiefe'] ) && 2 === (int) $new['tiefe'] ) ? 2 : 1,
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
		foreach ( array( 'header', 'footer', 'title' ) as $w ) {
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
		if ( n4s_theme_hidden( 'title' ) ) {
			$css .= 'body.n4s-no-title .nv-page-title-wrap,body.n4s-no-title .page-header,body.n4s-no-title .entry-header .entry-title,body.n4s-no-title h1.page-title{display:none!important}';
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
		if ( n4s_theme_on( 'schemes' ) ) {
			$cur = (int) get_post_meta( $post->ID, '_n4s_scheme', true );
			echo '<p><label for="n4s-scheme">' . esc_html( n4s_t( 'Farbschema', 'Color scheme' ) ) . '</label><br><select name="n4s_scheme" id="n4s-scheme" style="width:100%"><option value="0">' . esc_html( n4s_t( 'Standard', 'Default' ) ) . '</option>';
			foreach ( n4s_theme_opt()['schemes'] as $n => $s ) {
				echo '<option value="' . (int) $n . '"' . selected( $cur, $n, false ) . '>' . esc_html( $n . ' – ' . $s['name'] ) . '</option>';
			}
			echo '</select></p>';
		}
		if ( n4s_theme_on( 'page' ) ) {
			foreach ( array( 'header' => n4s_t( 'Kopfzeile ausblenden', 'Hide header' ), 'footer' => n4s_t( 'Fußzeile ausblenden', 'Hide footer' ), 'title' => n4s_t( 'Seitentitel ausblenden', 'Hide page title' ) ) as $k => $l ) {
				echo '<p style="margin:4px 0"><label><input type="checkbox" name="n4s_hide_' . esc_attr( $k ) . '" value="1"' . checked( get_post_meta( $post->ID, '_n4s_hide_' . $k, true ), '1', false ) . '> ' . esc_html( $l ) . '</label></p>';
			}
		}
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
				foreach ( array( 'header', 'footer', 'title' ) as $k ) {
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

	/* Tab „Theme“ */
	add_filter(
		'n4s_apps_tabs',
		function ( $t ) {
			$t['theme'] = array( 'page' => 'n4s-theme', 'label' => 'Theme', 'render' => 'n4s_theme_render' );
			return $t;
		}
	);

	add_action(
		'admin_enqueue_scripts',
		function ( $hook ) {
			if ( false !== strpos( (string) $hook, 'n4s-theme' ) ) {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
			}
		}
	);

	function n4s_theme_render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o      = n4s_theme_opt();
		$th     = wp_get_theme();
		$parent = $th->parent() ? $th->parent() : $th;
		$neve   = 'neve' === strtolower( $parent->get_template() );
		echo '<h2>Theme</h2><p>' . esc_html( n4s_t( 'Aktives Theme: ', 'Active theme: ' ) ) . '<strong>' . esc_html( $th->get( 'Name' ) ) . '</strong>' . ( $neve ? ' – ' . esc_html( n4s_t( 'Neve erkannt: Farbschemen färben auch Kopf- und Fußzeile, die Seitenfelder von Neve werden mitgesteuert.', 'Neve detected: color schemes also color header and footer, and Neve\'s page fields are controlled too.' ) ) : ' – ' . esc_html( n4s_t( 'Farbschemen färben Hintergrund und Text der Seite; die Menü-Funktionen gelten für jedes Theme.', 'Color schemes color the page background and text; the menu features work with any theme.' ) ) ) . '</p>';
		n4s_apps_form_head( 'n4s_theme_save', false, 'display:block' );
		$mods = array(
			'menus'    => array( n4s_t( 'Menü je Anmeldestatus und Rolle', 'Menu by login status and role' ), n4s_t( 'Zeigt für Besucher, Angemeldete und einzelne Rollen unterschiedliche Menüs an derselben Menüposition.', 'Shows different menus at the same menu location for visitors, logged-in users and individual roles.' ) ),
			'items'    => array( n4s_t( 'Sichtbarkeit je Menüpunkt', 'Visibility per menu item' ), n4s_t( 'Im Menü-Editor kann jeder Punkt für alle, Besucher, Angemeldete oder bestimmte Rollen gelten.', 'In the menu editor each item can apply to everyone, visitors, logged-in users or certain roles.' ) ),
			'loginout' => array( n4s_t( 'Anmelden / Abmelden als Menüpunkt', 'Log in / Log out as menu item' ), n4s_t( 'Im Menü-Editor eine Box zum Hinzufügen; der Punkt schaltet je nach Status um.', 'A box in the menu editor to add it; the item switches depending on the status.' ) ),
			'above'    => array( n4s_t( 'Menüleiste über dem Header', 'Menu bar above the header' ), n4s_t( 'Zusätzliche Menüposition „n4s – Leiste über dem Header“ (Design → Menüs). Die Leiste steht ganz oben über der Kopfzeile, auch mit Menü je Besucher, Angemeldete und Rolle.', 'Additional menu location "n4s – bar above the header" (Appearance → Menus). The bar sits at the very top above the header, also with a menu per visitor, logged-in user and role.' ) ),
			'widget'   => array( n4s_t( 'Zusatzmenü (Widget und Shortcode)', 'Additional menu (widget and shortcode)' ), n4s_t( 'Widget „n4s Menü“ für Seitenleisten und Fußzeilen sowie Shortcode [n4s_nav] für Seiten: ein Menü mit eigener Auswahl für Besucher, Angemeldete und Rollen, senkrecht oder in einer Zeile. Funktioniert auch bei Themes mit Block-Navigation.', 'Widget "n4s Menu" for sidebars and footers and shortcode [n4s_nav] for pages: a menu with its own choice for visitors, logged-in users and roles, vertical or in a row. Also works with themes using block navigation.' ) ),
			'page'     => array( n4s_t( 'Seitengestaltung je Seite', 'Page design per page' ), n4s_t( 'Kopfzeile, Fußzeile und Seitentitel je Seite ausblenden (Feld im Seiteneditor).', 'Hide header, footer and page title per page (field in the page editor).' ) ),
			'schemes'  => array( n4s_t( 'Farbschemen (bis zu 3)', 'Color schemes (up to 3)' ), n4s_t( 'Drei Farbschemen definieren und je Seite wählen oder als Standard setzen.', 'Define three color schemes and choose per page or set as default.' ) ),
		);
		echo '<table class="widefat striped" style="width:100%"><thead><tr><th style="width:90px">' . esc_html( n4s_t( 'Aktiv', 'Active' ) ) . '</th><th>' . esc_html( n4s_t( 'Funktion', 'Feature' ) ) . '</th></tr></thead><tbody>';
		foreach ( $mods as $k => $m ) {
			echo '<tr><td><label><input type="checkbox" name="on[' . esc_attr( $k ) . ']" value="1"' . checked( ! empty( $o['on'][ $k ] ), true, false ) . '> ' . esc_html( ! empty( $o['on'][ $k ] ) ? n4s_t( 'an', 'on' ) : n4s_t( 'aus', 'off' ) ) . '</label></td><td><strong>' . esc_html( $m[0] ) . '</strong><br><span class="description">' . esc_html( $m[1] ) . '</span></td></tr>';
		}
		echo '</tbody></table>';

		/* Leiste über dem Header */
		echo '<h3 style="margin-top:20px">' . esc_html( n4s_t( 'Menüleiste über dem Header', 'Menu bar above the header' ) ) . '</h3><p class="description">' . esc_html( n4s_t( 'Das Menü wählst du unten bei „Menüs je Position“ (Position „n4s – Leiste über dem Header“, auch je Rolle) oder unter Design → Menüs. Farben leer = Farben des Themes bzw. des Farbschemas (dunkle Fläche).', 'Choose the menu below under "Menus per location" (location "n4s – bar above the header", also per role) or under Appearance → Menus. Empty colors = theme or color scheme colors (dark surface).' ) ) . '</p><p><label>' . esc_html( n4s_t( 'Ausrichtung', 'Alignment' ) ) . ' <select name="above[align]">';
		foreach ( array( 'left' => n4s_t( 'Links', 'Left' ), 'center' => n4s_t( 'Mitte', 'Center' ), 'right' => n4s_t( 'Rechts', 'Right' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $o['above']['align'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label> &nbsp; <label>' . esc_html( n4s_t( 'Hintergrund', 'Background' ) ) . ' <input type="text" class="n4s-cp" name="above[bg]" value="' . esc_attr( $o['above']['bg'] ) . '"></label> &nbsp; <label>' . esc_html( n4s_t( 'Text', 'Text' ) ) . ' <input type="text" class="n4s-cp" name="above[text]" value="' . esc_attr( $o['above']['text'] ) . '"></label></p>';

		/* Menüs je Position */
		echo '<h3 style="margin-top:20px">' . esc_html( n4s_t( 'Menüs je Position', 'Menus per location' ) ) . '</h3><p class="description">' . esc_html( n4s_t( 'Standard = das der Position zugewiesene Menü (unter Design → Menüs). Für Angemeldete gilt zuerst die Rolle (Administrator vor Redakteur …), dann „Alle Angemeldeten“.', 'Default = the menu assigned to the location (under Appearance → Menus). For logged-in users the role applies first (administrator before editor …), then "All logged-in users".' ) ) . '</p>';
		$menus = wp_get_nav_menus();
		$locs  = get_registered_nav_menus();
		$sel   = function ( $name, $cur ) use ( $menus ) {
			$h = '<select name="' . esc_attr( $name ) . '"><option value="0">' . esc_html( n4s_t( 'Standard', 'Default' ) ) . '</option>';
			foreach ( $menus as $m ) {
				$h .= '<option value="' . (int) $m->term_id . '"' . selected( (int) $cur, (int) $m->term_id, false ) . '>' . esc_html( $m->name ) . '</option>';
			}
			return $h . '</select>';
		};
		if ( ! $locs ) {
			echo '<p>' . esc_html( n4s_t( 'Das Theme registriert keine Menüpositionen.', 'The theme registers no menu locations.' ) ) . '</p>';
		}
		foreach ( $locs as $loc => $label ) {
			$c = isset( $o['menus'][ $loc ] ) ? $o['menus'][ $loc ] : array();
			echo '<details style="background:#fff;border:1px solid #c3c4c7;margin:-1px 0 0;padding:6px 12px"' . ( $c ? ' open' : '' ) . '><summary><strong>' . esc_html( $label ) . '</strong> <code>' . esc_html( $loc ) . '</code></summary><table class="form-table" style="margin:6px 0"><tbody>';
			echo '<tr><th>' . esc_html( n4s_t( 'Besucher ohne Anmeldung', 'Logged-out visitors' ) ) . '</th><td>' . $sel( 'menus[' . $loc . '][gast]', isset( $c['gast'] ) ? $c['gast'] : 0 ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Select-HTML selbst escaped.
			echo '<tr><th>' . esc_html( n4s_t( 'Alle Angemeldeten', 'All logged-in users' ) ) . '</th><td>' . $sel( 'menus[' . $loc . '][user]', isset( $c['user'] ) ? $c['user'] : 0 ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			foreach ( wp_roles()->get_names() as $r => $rl ) {
				echo '<tr><th>' . esc_html( translate_user_role( $rl ) ) . '</th><td>' . $sel( 'menus[' . $loc . '][roles][' . $r . ']', isset( $c['roles'][ $r ] ) ? $c['roles'][ $r ] : 0 ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tbody></table></details>';
		}

		/* Farbschemen */
		echo '<h3 style="margin-top:20px">' . esc_html( n4s_t( 'Farbschemen', 'Color schemes' ) ) . '</h3><p class="description">' . esc_html( n4s_t( 'Jedes Schema ändert die Farben der Seite. Leere Felder behalten die Farbe des Themes. Gewählt wird das Schema je Seite im Seiteneditor („n4s – Seitengestaltung“).', 'Each scheme changes the colors of the page. Empty fields keep the theme color. The scheme is chosen per page in the page editor ("n4s – Page design").' ) ) . '</p>';
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
		echo '</div><p>';
		submit_button( n4s_t( 'Speichern', 'Save' ), 'primary', 'submit', false );
		echo '</p></form><script>jQuery(function($){$(".n4s-cp").wpColorPicker({clear:function(){}})});</script>';
	}

	add_action(
		'admin_post_n4s_theme_save',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				n4s_apps_deny();
			}
			n4s_apps_verify( 'n4s_theme_save' );
			$d  = n4s_theme_defaults();
			$in = array(
				'on'      => array(),
				'menus'   => array(),
				'default' => 0,
				'schemes' => array(),
			);
			foreach ( array_keys( $d['on'] ) as $k ) {
				$in['on'][ $k ] = ! empty( $_POST['on'][ $k ] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification -- siehe n4s_apps_verify.
			}
			$valid_menus = array_map( function ( $m ) {
				return (int) $m->term_id;
			}, wp_get_nav_menus() );
			$ok_menu     = function ( $v ) use ( $valid_menus ) {
				$v = (int) $v;
				return in_array( $v, $valid_menus, true ) ? $v : 0;
			};
			$roles       = array_keys( wp_roles()->get_names() );
			$posted      = isset( $_POST['menus'] ) && is_array( $_POST['menus'] ) ? wp_unslash( $_POST['menus'] ) : array(); // phpcs:ignore WordPress.Security
			foreach ( get_registered_nav_menus() as $loc => $l ) {
				if ( empty( $posted[ $loc ] ) || ! is_array( $posted[ $loc ] ) ) {
					continue;
				}
				$c   = array(
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
			$pa             = isset( $_POST['above'] ) && is_array( $_POST['above'] ) ? wp_unslash( $_POST['above'] ) : array(); // phpcs:ignore WordPress.Security
			$al             = isset( $pa['align'] ) ? sanitize_key( $pa['align'] ) : 'right';
			$in['above']    = array(
				'align' => in_array( $al, array( 'left', 'center', 'right' ), true ) ? $al : 'right',
				'bg'    => isset( $pa['bg'] ) ? (string) sanitize_hex_color( trim( (string) $pa['bg'] ) ) : '',
				'text'  => isset( $pa['text'] ) ? (string) sanitize_hex_color( trim( (string) $pa['text'] ) ) : '',
			);
			$dn = isset( $_POST['default'] ) ? (int) $_POST['default'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
			$in['default'] = ( $dn >= 1 && $dn <= 3 ) ? $dn : 0;
			$ps = isset( $_POST['schemes'] ) && is_array( $_POST['schemes'] ) ? wp_unslash( $_POST['schemes'] ) : array(); // phpcs:ignore WordPress.Security
			for ( $n = 1; $n <= 3; $n++ ) {
				$name   = isset( $ps[ $n ]['name'] ) ? sanitize_text_field( $ps[ $n ]['name'] ) : '';
				$colors = array();
				foreach ( array_keys( n4s_theme_color_fields() ) as $k ) {
					$v            = isset( $ps[ $n ]['colors'][ $k ] ) ? sanitize_hex_color( trim( (string) $ps[ $n ]['colors'][ $k ] ) ) : '';
					$colors[ $k ] = $v ? $v : '';
				}
				$in['schemes'][ $n ] = array( 'name' => '' !== $name ? mb_substr( $name, 0, 30 ) : $d['schemes'][ $n ]['name'], 'colors' => $colors );
			}
			update_option( 'n4s_theme', $in, false );
			n4s_theme_opt( true );
			n4s_apps_notice( 'success', n4s_t( 'Theme-Einstellungen gespeichert.', 'Theme settings saved.' ) );
			n4s_apps_back( false, 'n4s-theme' );
		}
	);
}
