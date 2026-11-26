<?php
/**
 * Seitenzugriff je Rolle (Funktion „Seitenzugriff“): Seiten lassen sich auf bestimmte Rollen beschränken.
 * Nicht berechtigte Benutzer können die Seite nicht aufrufen (Besucher ohne Anmeldung gehen zur Anmeldung,
 * Angemeldete sehen 404), sie erscheint nicht in Listen, Menüs, Suche und REST und lässt sich nicht bearbeiten.
 * Administratoren (manage_options) haben immer Zugriff. Feld je Seite: Meta _n4s_access = Liste von Rollen.
 */

defined( 'ABSPATH' ) || exit;

/** Beschränkbare Beitragstypen. */
function n4s_access_types() {
	return (array) apply_filters( 'n4s_access_post_types', array( 'page' ) );
}

/** Rollen, die eine Seite sehen dürfen (leer = alle). */
function n4s_access_roles( $id ) {
	$v = get_post_meta( (int) $id, '_n4s_access', true );
	return is_array( $v ) ? array_values( array_filter( array_map( 'strval', $v ) ) ) : array();
}

/** Darf der aktuelle Benutzer die Seite sehen? */
function n4s_access_ok( $id ) {
	$roles = n4s_access_roles( $id );
	if ( ! $roles || current_user_can( 'manage_options' ) ) {
		return true;
	}
	if ( ! is_user_logged_in() ) {
		return false;
	}
	return (bool) array_intersect( (array) wp_get_current_user()->roles, $roles );
}

/** Prüfung gilt nur für Seiten der beschränkbaren Typen, nicht bei Cron und WP-CLI. */
function n4s_access_active() {
	return ! ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) );
}

function n4s_access_blocked( $post ) {
	return $post instanceof WP_Post && in_array( $post->post_type, n4s_access_types(), true ) && ! n4s_access_ok( $post->ID );
}

/** Beschränkte Seiten aus Abfragen entfernen (Frontend, Liste im Admin, Suche, REST, Sitemap). */
add_filter(
	'the_posts',
	function ( $posts, $query ) {
		if ( ! n4s_access_active() || ! is_array( $posts ) || ! $posts ) {
			return $posts;
		}
		$removed = false;
		foreach ( $posts as $k => $p ) {
			if ( n4s_access_blocked( $p ) ) {
				unset( $posts[ $k ] );
				$removed = true;
			}
		}
		if ( $removed && $query->is_main_query() && ! is_admin() && $query->is_singular() && ! is_user_logged_in() ) {
			$url = home_url( add_query_arg( null, null ) );
			wp_safe_redirect( wp_login_url( $url ) );
			exit;
		}
		return $removed ? array_values( $posts ) : $posts;
	},
	10,
	2
);

/** Bearbeiten, Lesen und Löschen einzelner beschränkter Seiten sperren (Editor, REST, direkte Links). */
add_filter(
	'map_meta_cap',
	function ( $caps, $cap, $user_id, $args ) {
		static $busy = false;
		if ( $busy || ! n4s_access_active() || ! in_array( $cap, array( 'edit_post', 'edit_page', 'delete_post', 'delete_page', 'read_post', 'read_page', 'publish_post', 'publish_page' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		if ( get_current_user_id() !== (int) $user_id ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		$busy = true;
		$deny = n4s_access_blocked( $post );
		$busy = false;
		return $deny ? array( 'do_not_allow' ) : $caps;
	},
	10,
	4
);

/** Menüpunkte beschränkter Seiten ausblenden (samt Untermenü). */
add_filter(
	'wp_nav_menu_objects',
	function ( $items ) {
		if ( ! n4s_access_active() ) {
			return $items;
		}
		$drop = array();
		foreach ( $items as $it ) {
			if ( 'post_type' === $it->type && in_array( $it->object, n4s_access_types(), true ) && ! n4s_access_ok( (int) $it->object_id ) ) {
				$drop[ (int) $it->ID ] = true;
			}
		}
		if ( ! $drop ) {
			return $items;
		}
		do {
			$added = false;
			foreach ( $items as $it ) {
				if ( ! isset( $drop[ (int) $it->ID ] ) && isset( $drop[ (int) $it->menu_item_parent ] ) ) {
					$drop[ (int) $it->ID ] = true;
					$added                 = true;
				}
			}
		} while ( $added );
		return array_values(
			array_filter(
				$items,
				function ( $it ) use ( $drop ) {
					return ! isset( $drop[ (int) $it->ID ] );
				}
			)
		);
	},
	6
);

/** Seitenlisten (wp_list_pages, get_pages). */
add_filter(
	'get_pages',
	function ( $pages ) {
		if ( ! n4s_access_active() || ! is_array( $pages ) ) {
			return $pages;
		}
		return array_values(
			array_filter(
				$pages,
				function ( $p ) {
					return ! n4s_access_blocked( $p );
				}
			)
		);
	}
);

/* ------------------------------------------------------------------ Feld im Editor (nur Administratoren) */

add_action(
	'add_meta_boxes',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( n4s_access_types() as $t ) {
			add_meta_box( 'n4s-access', 'n4s – ' . n4s_t( 'Zugriff', 'Access' ), 'n4s_access_box', $t, 'side', 'default' );
		}
	}
);

function n4s_access_box( $post ) {
	$sel = n4s_access_roles( $post->ID );
	wp_nonce_field( 'n4s_access_save', 'n4s_access_nonce' );
	echo '<p class="description">' . esc_html( n4s_t( 'Keine Rolle gewählt = die Seite ist für alle sichtbar. Sonst sehen, öffnen und bearbeiten nur die gewählten Rollen die Seite (Administratoren immer).', 'No role selected = the page is visible to everyone. Otherwise only the selected roles can see, open and edit the page (administrators always).' ) ) . '</p>';
	foreach ( wp_roles()->get_names() as $r => $label ) {
		if ( 'administrator' === $r ) {
			continue;
		}
		echo '<label style="display:block;margin:2px 0"><input type="checkbox" name="n4s_access_roles[]" value="' . esc_attr( $r ) . '"' . checked( in_array( $r, $sel, true ), true, false ) . '> ' . esc_html( translate_user_role( $label ) ) . '</label>';
	}
}

add_action(
	'save_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['n4s_access_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['n4s_access_nonce'] ) ), 'n4s_access_save' ) || ! current_user_can( 'manage_options' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		$valid = array_diff( array_keys( wp_roles()->get_names() ), array( 'administrator' ) );
		$roles = array();
		if ( isset( $_POST['n4s_access_roles'] ) && is_array( $_POST['n4s_access_roles'] ) ) {
			foreach ( wp_unslash( $_POST['n4s_access_roles'] ) as $r ) { // phpcs:ignore WordPress.Security
				if ( is_string( $r ) && in_array( $r, $valid, true ) ) {
					$roles[] = $r;
				}
			}
		}
		if ( $roles ) {
			update_post_meta( $post_id, '_n4s_access', $roles );
		} else {
			delete_post_meta( $post_id, '_n4s_access' );
		}
	}
);

/** Spalte „Zugriff“ in der Seitenliste (nur Administratoren). */
add_filter(
	'manage_pages_columns',
	function ( $c ) {
		if ( current_user_can( 'manage_options' ) ) {
			$c['n4s_access'] = n4s_t( 'Zugriff', 'Access' );
		}
		return $c;
	}
);
add_action(
	'manage_pages_custom_column',
	function ( $col, $id ) {
		if ( 'n4s_access' !== $col ) {
			return;
		}
		$r = n4s_access_roles( $id );
		echo '<span class="n4s-access-data" data-roles="' . esc_attr( implode( ',', $r ) ) . '">';
		if ( ! $r ) {
			echo '—</span>';
			return;
		}
		$names = wp_roles()->get_names();
		echo esc_html( implode( ', ', array_map( function ( $x ) use ( $names ) { return isset( $names[ $x ] ) ? translate_user_role( $names[ $x ] ) : $x; }, $r ) ) ) . '</span>';
	},
	10,
	2
);

/** Schnellbearbeitung: Rollen direkt in der Seitenliste setzen (Speichern über save_post, siehe oben). */
add_action(
	'quick_edit_custom_box',
	function ( $column, $post_type ) {
		if ( 'n4s_access' !== $column || ! in_array( $post_type, n4s_access_types(), true ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_nonce_field( 'n4s_access_save', 'n4s_access_nonce', false );
		echo '<fieldset class="inline-edit-col-left" style="clear:both"><div class="inline-edit-col"><span class="title">n4s – ' . esc_html( n4s_t( 'Zugriff', 'Access' ) ) . '</span><span class="description" style="display:block;margin:2px 0 6px">' . esc_html( n4s_t( 'Keine Rolle gewählt = für alle sichtbar. Administratoren haben immer Zugriff.', 'No role selected = visible to everyone. Administrators always have access.' ) ) . '</span>';
		foreach ( wp_roles()->get_names() as $r => $label ) {
			if ( 'administrator' === $r ) {
				continue;
			}
			echo '<label style="display:inline-block;margin:0 14px 4px 0"><input type="checkbox" name="n4s_access_roles[]" value="' . esc_attr( $r ) . '"> ' . esc_html( translate_user_role( $label ) ) . '</label>';
		}
		echo '</div></fieldset>';
	},
	10,
	2
);

add_action(
	'admin_footer-edit.php',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<script>(function(){if(!window.inlineEditPost)return;var o=inlineEditPost.edit;inlineEditPost.edit=function(id){o.apply(this,arguments);var pid=typeof id==="object"?this.getId(id):id;var d=document.querySelector("#post-"+pid+" .n4s-access-data");var r=d?(d.getAttribute("data-roles")||"").split(","):[];document.querySelectorAll("#edit-"+pid+" input[name=\\"n4s_access_roles[]\\"]").forEach(function(c){c.checked=r.indexOf(c.value)>-1})}})();</script>';
	}
);
