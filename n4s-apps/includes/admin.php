<?php
/**
 * n4s Apps / Verwaltungsbereich (Menüpunkt in der Admin-Sidebar), zweisprachig DE/EN.
 * Funktionen: App hinzufügen, App aktualisieren (mit Abgleich + Bestätigung), löschen (nur deaktiviert),
 * (de)aktivieren, Plugin per ZIP aktualisieren (nur neuere Version), Update-Quelle GitHub anzeigen/prüfen.
 */

defined( 'ABSPATH' ) || exit;

/** n4s-Schriftzug als SVG. $dark = true: weiße Buchstaben (dunkler Hintergrund), sonst fast schwarze. */
function n4s_apps_logo( $dark = false, $size = '' ) {
	$ink = $dark ? '#fff' : '#0C0E10';
	$d   = array(
		'M-58.5185546875 -39.8056640625C-58.5185546875 -48.9111328125 -54.8681640625 -54.2021484375 -49.6181640625 -54.2021484375C-44.4501953125 -54.2021484375 -41.3330078125 -49.095703125 -41.3330078125 -40.5439453125V0.0H-29.0283203125V-43.7431640625C-29.0283203125 -59.8623046875 -35.2626953125 -69.64453125 -44.7783203125 -69.64453125C-51.8330078125 -69.64453125 -56.4677734375 -64.1689453125 -59.0517578125 -54.5712890625L-59.2158203125 -68.783203125H-70.8232421875V0.0H-58.5185546875Z',
		'M-21.6357421875 -16.3037109375H7.6083984375V0.0H19.6259765625V-16.3037109375H27.2548828125V-31.4384765625H19.6259765625V-91.669921875H3.9169921875L-21.6357421875 -31.130859375ZM7.8544921875 -31.4384765625H-9.0029296875V-32.1767578125L7.3623046875 -70.998046875H7.8544921875Z',
		'M52.4892578125 1.353515625C64.5068359375 1.353515625 72.9970703125 -7.6904296875 72.9970703125 -21.2255859375C72.9970703125 -31.0078125 68.7314453125 -37.0986328125 59.8720703125 -39.8056640625L51.6279296875 -42.2666015625C47.2802734375 -43.6201171875 45.7216796875 -46.01953125 45.7216796875 -49.46484375C45.7216796875 -53.5869140625 48.8388671875 -56.4169921875 52.8583984375 -56.4169921875C57.3291015625 -56.4169921875 59.9130859375 -53.0947265625 60.8974609375 -48.4189453125L71.8076171875 -51.310546875C70.0849609375 -62.5078125 63.5634765625 -69.64453125 52.6943359375 -69.64453125C41.0869140625 -69.64453125 33.4169921875 -61.5234375 33.4169921875 -48.2958984375C33.4169921875 -38.021484375 37.7646484375 -31.5 46.6240234375 -28.79296875L54.5400390625 -26.33203125C58.5595703125 -25.1015625 60.4462890625 -22.88671875 60.4462890625 -19.2568359375C60.4462890625 -15.134765625 57.5341796875 -12.181640625 52.8583984375 -12.181640625C47.8134765625 -12.181640625 44.8603515625 -15.3193359375 43.6708984375 -21.779296875L32.2275390625 -18.826171875C33.8271484375 -6.029296875 41.4150390625 1.353515625 52.4892578125 1.353515625Z',
	);
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-74 -49 150 98"' . $size . '><g transform="translate(0,45.2)">'
		. '<path d="' . $d[0] . '" fill="' . $ink . '"/>'
		. '<path d="' . $d[1] . '" fill="#00CCFF"/>'
		. '<path d="' . $d[2] . '" fill="' . $ink . '"/></g></svg>';
}

/** Menü-Icon (Seitenleiste ist dunkel): n4s-Schriftzug, weiß mit cyanfarbener 4. Per CSS, damit WordPress es nicht einfarbig umfärbt. */
function n4s_apps_icon_css() {
	$uri = 'data:image/svg+xml;base64,' . base64_encode( n4s_apps_logo( true ) );
	return '#adminmenu #toplevel_page_n4s div.wp-menu-image{background:url(' . $uri . ') center center/22px auto no-repeat;}#adminmenu #toplevel_page_n4s div.wp-menu-image:before{display:none;}';
}

add_action(
	'admin_head',
	function () {
		echo '<style>' . n4s_apps_icon_css() . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

/** Dateiänderungen (Upload, Löschen, Plugin-Update) nur mit Plugin-Installationsrecht. */
function n4s_apps_can_files() {
	return current_user_can( 'install_plugins' ) && ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
}

function n4s_apps_notice( $type, $text ) {
	set_transient( 'n4s_apps_notice_' . get_current_user_id(), array( $type, $text ), 120 );
}

function n4s_apps_back( $review = false, $page = '' ) {
	if ( '' === $page ) {
		$page = n4s_feature_on( 'apps' ) ? 'n4s-apps' : 'n4s';
	}
	wp_safe_redirect( admin_url( 'admin.php?page=' . $page . ( $review ? '&review=1' : '' ) ) );
	exit;
}

function n4s_apps_deny() {
	wp_die( esc_html( n4s_t( 'Keine Berechtigung.', 'Permission denied.' ) ), 403 );
}

/** Nonce prüfen. Bei Fehler Hinweis auf der Verwaltungsseite statt WordPress-Fehlerseite. */
function n4s_apps_verify( $action ) {
	$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, $action ) ) {
		n4s_apps_notice( 'error', n4s_t( 'Die Sicherheitsprüfung ist abgelaufen oder fehlgeschlagen. Bitte die Seite neu laden und die Aktion erneut ausführen.', 'The security check expired or failed. Please reload the page and try again.' ) );
		n4s_apps_back();
	}
}

function n4s_apps_read_upload( $key, $max_bytes ) {
	if ( empty( $_FILES[ $key ]['tmp_name'] ) ) {
		return n4s_t( 'Bitte eine Datei auswählen.', 'Please choose a file.' );
	}
	$f = $_FILES[ $key ];
	if ( UPLOAD_ERR_OK !== (int) $f['error'] ) {
		return n4s_t( 'Upload fehlgeschlagen (Code ', 'Upload failed (code ' ) . (int) $f['error'] . ').';
	}
	if ( ! is_uploaded_file( $f['tmp_name'] ) ) {
		return n4s_t( 'Ungültiger Upload.', 'Invalid upload.' );
	}
	if ( (int) $f['size'] > $max_bytes ) {
		return n4s_t( 'Die Datei ist zu groß (maximal ', 'The file is too large (maximum ' ) . round( $max_bytes / 1024 ) . ' KB).';
	}
	return $f;
}

/** Geprüfte Schlüssel-Angabe "bundled:x.php" / "user:x.php" aus POST. */
function n4s_apps_post_key() {
	$key = sanitize_text_field( wp_unslash( isset( $_POST['key'] ) ? $_POST['key'] : '' ) );
	if ( ! preg_match( '/^(bundled|user):([a-z0-9_-]+\.php)$/', $key, $m ) || ! n4s_apps_valid_name( $m[2] ) ) {
		n4s_apps_notice( 'error', n4s_t( 'Ungültige App.', 'Invalid app.' ) );
		n4s_apps_back();
	}
	return array( $key, $m[1], $m[2] );
}

function n4s_apps_pending_key() {
	return 'n4s_apps_pending_' . get_current_user_id();
}

/* ------------------------------------------------------------------ Menü */

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'n4s', 'Plugin', 'manage_options', 'n4s', '', 'none', 58 ); // Seite liefert der erste Unterpunkt (Funktionen)
		add_submenu_page( 'n4s', 'n4s – ' . n4s_t( 'Funktionen', 'Features' ), n4s_t( 'Funktionen', 'Features' ), 'manage_options', 'n4s', function () { n4s_apps_shell( 'features' ); } );
		if ( n4s_feature_on( 'apps' ) ) {
			add_submenu_page( 'n4s', 'n4s – Apps', 'Apps', 'manage_options', 'n4s-apps', function () { n4s_apps_shell( 'apps' ); } );
		}
		if ( n4s_feature_on( 'security' ) ) {
			add_submenu_page( 'n4s', 'n4s – ' . n4s_t( 'Sicherheit', 'Security' ), n4s_t( 'Sicherheit', 'Security' ), 'manage_options', 'n4s-security', function () { n4s_apps_shell( 'security' ); } );
		}
		foreach ( n4s_apps_extra_tabs() as $k => $t ) {
			add_submenu_page( 'n4s', 'n4s – ' . $t['label'], $t['label'], 'manage_options', $t['page'], function () use ( $k ) { n4s_apps_shell( $k ); } );
		}
	}
);

/**
 * Zusätzliche Tabs, die Apps oder Erweiterungen einhängen (Filter n4s_apps_tabs):
 * add_filter( 'n4s_apps_tabs', function ( $t ) { $t['admin'] = array( 'page' => 'n4s-admin', 'label' => 'Admin', 'render' => 'meine_funktion' ); return $t; } );
 */
function n4s_apps_extra_tabs() {
	$out = array();
	foreach ( (array) apply_filters( 'n4s_apps_tabs', array() ) as $k => $t ) {
		$k = sanitize_key( $k );
		if ( $k && ! in_array( $k, array( 'features', 'apps', 'security' ), true ) && is_array( $t ) && ! empty( $t['page'] ) && ! empty( $t['label'] ) && ! empty( $t['render'] ) && is_callable( $t['render'] ) ) {
			$out[ $k ] = array(
				'page'   => sanitize_key( $t['page'] ),
				'label'  => (string) $t['label'],
				'render' => $t['render'],
			);
		}
	}
	return $out;
}

/* -------------------------------------------------------------- Aktionen */

/** Upload einer App: neu = sofort speichern; Update = Abgleich, dann Bestätigungsseite. */
add_action(
	'admin_post_n4s_app_save',
	function () {
		if ( ! n4s_apps_can_files() ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_app_save' );

		$mode = ( isset( $_POST['mode'] ) && 'update' === $_POST['mode'] ) ? 'update' : 'new';
		$f    = n4s_apps_read_upload( 'n4s_app_file', 1048576 );
		if ( ! is_array( $f ) ) {
			n4s_apps_notice( 'error', $f );
			n4s_apps_back();
		}
		$code = (string) file_get_contents( $f['tmp_name'] );
		$ok   = n4s_apps_validate_code( $code );
		if ( true !== $ok ) {
			n4s_apps_notice( 'error', $ok );
			n4s_apps_back();
		}
		$upload_name = strtolower( sanitize_file_name( $f['name'] ) );
		// Versionssuffix im Dateinamen (z. B. waermepumpen_V2_1_2.php) entfernen: installiert wird unter dem Namen ohne Version.
		$upload_name = preg_replace( '/_v\d+(?:_\d+)*(?=\.php$)/', '', $upload_name );
		$name        = ( 'update' === $mode )
			? sanitize_file_name( wp_unslash( isset( $_POST['target'] ) ? $_POST['target'] : '' ) )
			: $upload_name;
		if ( ! n4s_apps_valid_name( $name ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Ungültiger Dateiname. Erlaubt: Kleinbuchstaben, Ziffern, - und _ mit Endung .php (index.php ist reserviert).', 'Invalid file name. Allowed: lowercase letters, digits, - and _ with .php extension (index.php is reserved).' ) );
			n4s_apps_back();
		}
		$path = N4S_APPS_USER_DIR . $name;

		if ( 'new' === $mode ) {
			if ( file_exists( $path ) || file_exists( N4S_APPS_DIR . 'apps/' . $name ) ) {
				n4s_apps_notice( 'error', n4s_t( 'Die Datei ' . $name . ' existiert bereits. Zum Ersetzen bitte „Update" in der Liste nutzen oder die Datei umbenennen.', 'The file ' . $name . ' already exists. Use "Update" in the list to replace it, or rename the file.' ) );
				n4s_apps_back();
			}
			// Kein App-Name (Slug), der schon in einer anderen Datei registriert ist.
			$taken = n4s_apps_installed_slugs();
			foreach ( n4s_apps_meta( $code )['slugs'] as $s ) {
				if ( isset( $taken[ $s ] ) ) {
					n4s_apps_notice( 'error', n4s_t( 'Der App-Name „' . $s . '" ist bereits vergeben (' . substr( $taken[ $s ], strpos( $taken[ $s ], ':' ) + 1 ) . '). Bitte einen anderen Namen wählen oder die vorhandene App per „Update" ersetzen.', 'The app name "' . $s . '" is already in use (' . substr( $taken[ $s ], strpos( $taken[ $s ], ':' ) + 1 ) . '). Choose another name or replace the existing app via "Update".' ) );
					n4s_apps_back();
				}
			}
			if ( ! n4s_apps_prepare_dir() ) {
				n4s_apps_notice( 'error', n4s_t( 'Der Ordner für eigene Apps konnte nicht angelegt werden.', 'The folder for custom apps could not be created.' ) );
				n4s_apps_back();
			}
			if ( false === file_put_contents( $path, $code, LOCK_EX ) ) {
				n4s_apps_notice( 'error', n4s_t( 'Die Datei konnte nicht gespeichert werden.', 'The file could not be saved.' ) );
				n4s_apps_back();
			}
			n4s_apps_notice( 'success', n4s_t( 'App hinzugefügt: ', 'App added: ' ) . $name );
			n4s_apps_back();
		}

		// Update: erst vergleichen, dann bestätigen lassen. Es wird noch nichts geschrieben.
		if ( ! file_exists( $path ) ) {
			n4s_apps_notice( 'error', n4s_t( 'App-Datei ' . $name . ' nicht gefunden.', 'App file ' . $name . ' not found.' ) );
			n4s_apps_back();
		}
		$old_code = (string) file_get_contents( $path );
		$cmp      = n4s_apps_compare( $old_code, $code, $name, $upload_name );
		if ( $cmp['error'] ) {
			n4s_apps_notice( 'error', $cmp['error'] );
			n4s_apps_back();
		}
		set_transient(
			n4s_apps_pending_key(),
			array(
				'name'     => $name,
				'code'     => $code,
				'old_hash' => md5( $old_code ),
				'token'    => wp_generate_password( 20, false ),
				'cmp'      => $cmp,
				'upload'   => $upload_name,
			),
			900
		);
		n4s_apps_back( true );
	}
);

/** Update bestätigen: speichert die zwischengespeicherte Datei. */
add_action(
	'admin_post_n4s_app_confirm',
	function () {
		if ( ! n4s_apps_can_files() ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_app_confirm' );
		$p     = get_transient( n4s_apps_pending_key() );
		$token = sanitize_text_field( wp_unslash( isset( $_POST['token'] ) ? $_POST['token'] : '' ) );
		if ( ! $p || ! hash_equals( $p['token'], $token ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Das Update ist abgelaufen oder ungültig. Bitte die Datei erneut hochladen.', 'The update expired or is invalid. Please upload the file again.' ) );
			n4s_apps_back();
		}
		delete_transient( n4s_apps_pending_key() );

		$name = $p['name'];
		$path = N4S_APPS_USER_DIR . $name;
		$ok   = n4s_apps_valid_name( $name ) ? n4s_apps_validate_code( $p['code'] ) : false;
		if ( true !== $ok || ! file_exists( $path ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Das Update konnte nicht übernommen werden.', 'The update could not be applied.' ) );
			n4s_apps_back();
		}
		if ( md5( (string) file_get_contents( $path ) ) !== $p['old_hash'] ) {
			n4s_apps_notice( 'error', n4s_t( 'Die installierte App wurde zwischenzeitlich geändert. Bitte das Update erneut hochladen.', 'The installed app changed in the meantime. Please upload the update again.' ) );
			n4s_apps_back();
		}
		if ( $p['cmp']['warnings'] && empty( $_POST['ack'] ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Bitte die Warnungen bestätigen.', 'Please acknowledge the warnings.' ) );
			n4s_apps_back();
		}
		copy( $path, $path . '.bak' ); // Vorherige Version bleibt als .bak erhalten.
		if ( false === file_put_contents( $path, $p['code'], LOCK_EX ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Die Datei konnte nicht gespeichert werden.', 'The file could not be saved.' ) );
			n4s_apps_back();
		}
		$v = $p['cmp']['new']['version'];
		n4s_apps_notice( 'success', n4s_t( 'App aktualisiert: ', 'App updated: ' ) . $name . ( $v ? ' (' . $v . ')' : '' ) );
		n4s_apps_back();
	}
);

/** Update abbrechen. */
add_action(
	'admin_post_n4s_app_cancel',
	function () {
		if ( ! n4s_apps_can_files() ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_app_cancel' );
		delete_transient( n4s_apps_pending_key() );
		n4s_apps_notice( 'success', n4s_t( 'Update abgebrochen. Nichts wurde verändert.', 'Update cancelled. Nothing was changed.' ) );
		n4s_apps_back();
	}
);

/** App löschen. Nur möglich, wenn sie zuvor deaktiviert wurde. */
add_action(
	'admin_post_n4s_app_delete',
	function () {
		if ( ! n4s_apps_can_files() ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_app_delete' );
		list( $key, $src, $file ) = n4s_apps_post_key();

		$list = (array) get_option( 'n4s_apps_disabled', array() );
		if ( ! n4s_apps_replaced( $key ) && ! in_array( $key, $list, true ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Die App muss zuerst deaktiviert werden, bevor sie gelöscht werden kann.', 'The app must be deactivated before it can be deleted.' ) );
			n4s_apps_back();
		}
		$path = ( 'bundled' === $src ? N4S_APPS_DIR . 'apps/' : N4S_APPS_USER_DIR ) . $file;
		if ( ! file_exists( $path ) ) {
			n4s_apps_notice( 'error', n4s_t( 'App-Datei nicht gefunden.', 'App file not found.' ) );
			n4s_apps_back();
		}
		unlink( $path );
		if ( file_exists( $path . '.bak' ) ) {
			unlink( $path . '.bak' );
		}
		if ( 'user' === $src ) {
			update_option( 'n4s_apps_disabled', array_values( array_diff( $list, array( $key ) ) ) );
			n4s_apps_notice( 'success', n4s_t( 'App gelöscht: ', 'App deleted: ' ) . $file );
		} else {
			n4s_apps_notice( 'success', n4s_t( 'Mitgelieferte App gelöscht: ' . $file . '. Ein Plugin-Update bringt sie deaktiviert zurück.', 'Bundled app deleted: ' . $file . '. A plugin update restores it as deactivated.' ) );
		}
		n4s_apps_back();
	}
);

/** App aktivieren oder deaktivieren (auch mitgelieferte). */
add_action(
	'admin_post_n4s_app_toggle',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_app_toggle' );
		list( $key, , $file ) = n4s_apps_post_key();
		$list = (array) get_option( 'n4s_apps_disabled', array() );
		if ( in_array( $key, $list, true ) ) {
			$list = array_diff( $list, array( $key ) );
			n4s_apps_notice( 'success', n4s_t( 'App aktiviert: ', 'App activated: ' ) . $file );
		} else {
			$list[] = $key;
			n4s_apps_notice( 'success', n4s_t( 'App deaktiviert: ', 'App deactivated: ' ) . $file );
		}
		update_option( 'n4s_apps_disabled', array_values( array_unique( $list ) ) );
		n4s_apps_back();
	}
);

/** Plugin n4s Apps selbst per ZIP aktualisieren (nur höhere Version). */
add_action(
	'admin_post_n4s_self_update',
	function () {
		if ( ! n4s_apps_can_files() ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_self_update' );

		$f = n4s_apps_read_upload( 'n4s_zip', 5242880 );
		if ( ! is_array( $f ) ) {
			n4s_apps_notice( 'error', $f );
			n4s_apps_back();
		}
		$info = n4s_apps_inspect_zip( $f['tmp_name'] );
		if ( ! is_array( $info ) ) {
			n4s_apps_notice( 'error', $info );
			n4s_apps_back();
		}
		if ( version_compare( $info['version'], N4S_APPS_VERSION, '<=' ) ) {
			n4s_apps_notice( 'error', n4s_t(
				'Version ' . $info['version'] . ' ist nicht neuer als die installierte ' . N4S_APPS_VERSION . '. Es wurde nichts verändert.',
				'Version ' . $info['version'] . ' is not newer than the installed ' . N4S_APPS_VERSION . '. Nothing was changed.'
			) );
			n4s_apps_back();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! WP_Filesystem() ) {
			n4s_apps_notice( 'error', n4s_t( 'Das Dateisystem verlangt Zugangsdaten (FTP). Bitte über Plugins → Installieren → Hochladen aktualisieren.', 'The filesystem requires credentials (FTP). Please update via Plugins → Add New → Upload.' ) );
			n4s_apps_back();
		}
		$tmp = wp_tempnam( 'n4s-apps.zip' );
		if ( ! $tmp || ! copy( $f['tmp_name'], $tmp ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Temporäre Datei konnte nicht angelegt werden.', 'Temporary file could not be created.' ) );
			n4s_apps_back();
		}

		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $tmp, array( 'overwrite_package' => true ) );
		if ( file_exists( $tmp ) ) {
			unlink( $tmp );
		}

		if ( is_wp_error( $result ) || ! $result ) {
			$why = is_wp_error( $result ) ? $result->get_error_message() : implode( ' ', array_map( 'wp_strip_all_tags', $skin->get_upgrade_messages() ) );
			n4s_apps_notice( 'error', n4s_t( 'Update fehlgeschlagen. ', 'Update failed. ' ) . $why );
		} else {
			$now = get_file_data( N4S_APPS_DIR . 'n4s-apps.php', array( 'v' => 'Version' ) );
			n4s_apps_notice( 'success', n4s_t( 'n4s Apps aktualisiert auf Version ' . $now['v'] . '. Eigene Apps sind unverändert.', 'n4s Apps updated to version ' . $now['v'] . '. Custom apps are unchanged.' ) );
		}
		n4s_apps_back();
	}
);

/** Funktionen ein-/ausschalten (Tab Funktionen). */
add_action(
	'admin_post_n4s_features_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_features_save' );
		update_option(
			'n4s_features',
			array(
				'apps'     => empty( $_POST['apps'] ) ? 0 : 1,
				'security' => empty( $_POST['security'] ) ? 0 : 1,
				'dup'      => empty( $_POST['dup'] ) ? 0 : 1,
				'mediareplace' => empty( $_POST['mediareplace'] ) ? 0 : 1,
			)
		);
		n4s_apps_notice( 'success', n4s_t( 'Funktionen gespeichert.', 'Features saved.' ) );
		n4s_apps_back( false, 'n4s' );
	}
);

/* ---------------------------------------------------------------- App-Katalog */

/** Quelle und Zugangsschlüssel des Katalogs speichern. */
add_action(
	'admin_post_n4s_catalog_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_catalog_save' );
		$src = n4s_apps_base( isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '' );
		if ( '' === $src ) {
			n4s_apps_notice( 'error', n4s_t( 'Ungültige Quelle. Erlaubt ist eine https-Adresse, z. B. https://n4s.one.', 'Invalid source. An https address is required, e.g. https://n4s.one.' ) );
			n4s_apps_back();
		}
		$old = get_option( 'n4s_apps_catalog', array() );
		$key = isset( $old['key'] ) ? $old['key'] : '';
		if ( ! empty( $_POST['key_clear'] ) ) {
			$key = '';
		}
		$new = isset( $_POST['key'] ) ? preg_replace( '/[^A-Za-z0-9_.-]/', '', wp_unslash( $_POST['key'] ) ) : '';
		if ( '' !== $new ) {
			$key = $new;
		}
		update_option( 'n4s_apps_catalog', array( 'source' => $src, 'key' => $key ), false );
		delete_transient( 'n4s_apps_updates' );
		delete_transient( 'n4s_apps_cat_' . get_current_user_id() );
		n4s_apps_notice( 'success', n4s_t( 'Katalog-Zugang gespeichert.', 'Catalog access saved.' ) );
		n4s_apps_back();
	}
);

/** Updates für Apps mit Update-Quelle jetzt prüfen. */
add_action(
	'admin_post_n4s_catalog_check',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_catalog_check' );
		if ( ! n4s_apps_update_sources() ) {
			n4s_apps_notice( 'error', n4s_t( 'Keine App hat eine Update-Quelle (Dateikopf „Update URI:“).', 'No app has an update source ("Update URI:" in the file header).' ) );
			n4s_apps_back();
		}
		$r = n4s_apps_updates( true );
		if ( $r['errors'] ) {
			n4s_apps_notice( 'error', n4s_t( 'Update-Prüfung fehlgeschlagen: ', 'Update check failed: ' ) . implode( ' · ', $r['errors'] ) );
		} else {
			$n = count( n4s_apps_pending_updates( false, true ) );
			n4s_apps_notice( 'success', $n ? sprintf( n4s_t( '%d App-Update(s) verfügbar.', '%d app update(s) available.' ), $n ) : n4s_t( 'Alle Apps mit Update-Quelle sind aktuell.', 'All apps with an update source are up to date.' ) );
		}
		n4s_apps_back();
	}
);

/** Katalog der Quelle laden (nur auf Klick). */
add_action(
	'admin_post_n4s_catalog_list',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_catalog_list' );
		$l = n4s_apps_catalog_list();
		if ( is_wp_error( $l ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Katalog konnte nicht geladen werden: ', 'Could not load the catalog: ' ) . $l->get_error_message() );
		} else {
			set_transient( 'n4s_apps_cat_' . get_current_user_id(), $l, 600 );
		}
		n4s_apps_back();
	}
);

/** Neue App aus dem geladenen Katalog installieren. */
add_action(
	'admin_post_n4s_catalog_install',
	function () {
		if ( ! n4s_apps_can_files() ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_catalog_install' );
		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$list = (array) get_transient( 'n4s_apps_cat_' . get_current_user_id() );
		$hit  = null;
		foreach ( $list as $e ) {
			if ( is_array( $e ) && isset( $e['slug'] ) && $e['slug'] === $slug ) {
				$hit = $e;
			}
		}
		if ( ! $hit ) {
			n4s_apps_notice( 'error', n4s_t( 'Die Katalog-Liste ist abgelaufen. Bitte „Katalog laden“ erneut ausführen.', 'The catalog list expired. Please run "Load catalog" again.' ) );
			n4s_apps_back();
		}
		$r = n4s_apps_catalog_apply( $hit, 'new' );
		n4s_apps_notice( is_wp_error( $r ) ? 'error' : 'success', is_wp_error( $r ) ? $r->get_error_message() : n4s_t( 'App installiert: ', 'App installed: ' ) . $r['name'] . ' (' . $r['version'] . ')' );
		n4s_apps_back();
	}
);

/** Verfügbares Update einer App installieren. */
add_action(
	'admin_post_n4s_app_catalog_update',
	function () {
		if ( ! n4s_apps_can_files() ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_app_catalog_update' );
		$slug    = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$pending = n4s_apps_pending_updates( false, true );
		if ( ! isset( $pending[ $slug ] ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Kein Update bekannt. Bitte „Updates jetzt prüfen“ ausführen.', 'No update known. Please run "Check for updates".' ) );
			n4s_apps_back();
		}
		$r = n4s_apps_catalog_apply( $pending[ $slug ], 'update' );
		n4s_apps_notice( is_wp_error( $r ) ? 'error' : 'success', is_wp_error( $r ) ? $r->get_error_message() : n4s_t( 'App aktualisiert: ', 'App updated: ' ) . $r['name'] . ' (' . $r['version'] . ')' );
		n4s_apps_back();
	}
);

/** Automatische Updates einer App ein-/ausschalten. */
add_action(
	'admin_post_n4s_app_autoupdate',
	function () {
		if ( ! n4s_apps_can_files() ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_app_autoupdate' );
		$slug = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		if ( ! isset( n4s_apps_update_sources()[ $slug ] ) ) {
			n4s_apps_back();
		}
		$list = (array) get_option( 'n4s_apps_autoupdate', array() );
		if ( in_array( $slug, $list, true ) ) {
			$list = array_diff( $list, array( $slug ) );
			n4s_apps_notice( 'success', n4s_t( 'Automatische Updates ausgeschaltet: ', 'Automatic updates switched off: ' ) . $slug );
		} else {
			$list[] = $slug;
			n4s_apps_notice( 'success', n4s_t( 'Automatische Updates eingeschaltet: ', 'Automatic updates switched on: ' ) . $slug );
		}
		update_option( 'n4s_apps_autoupdate', array_values( array_unique( $list ) ), false );
		n4s_apps_back();
	}
);

/** Update-Quelle GitHub: sofort prüfen (Cache leeren, WordPress-Update-Prüfung anstoßen). */
add_action(
	'admin_post_n4s_gh_check',
	function () {
		if ( ! current_user_can( 'update_plugins' ) ) {
			n4s_apps_deny();
		}
		n4s_apps_verify( 'n4s_gh_check' );
		$r = n4s_apps_gh_release( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
		if ( ! empty( $r['error'] ) ) {
			n4s_apps_notice( 'error', n4s_t( 'Update-Prüfung fehlgeschlagen: ', 'Update check failed: ' ) . $r['error'] );
		} elseif ( version_compare( $r['version'], N4S_APPS_VERSION, '>' ) ) {
			n4s_apps_notice( 'success', n4s_t( 'Neue Version ' . $r['version'] . ' verfügbar. Aktualisieren unter Plugins oder Dashboard → Aktualisierungen.', 'New version ' . $r['version'] . ' available. Update under Plugins or Dashboard → Updates.' ) );
		} else {
			n4s_apps_notice( 'success', n4s_t( 'n4s Apps ist aktuell (' . N4S_APPS_VERSION . ').', 'n4s Apps is up to date (' . N4S_APPS_VERSION . ').' ) );
		}
		n4s_apps_back( false, 'n4s' );
	}
);

/* ----------------------------------------------------------------- Seite */

function n4s_apps_form_head( $action, $multipart = false, $style = 'display:inline' ) {
	echo '<form method="post"' . ( $multipart ? ' enctype="multipart/form-data"' : '' ) . ' action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="' . esc_attr( $style ) . '">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
}

function n4s_apps_post_form( $action, $fields, $label, $class = 'button', $confirm = '', $disabled = false, $title = '' ) {
	n4s_apps_form_head( $action );
	foreach ( $fields as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	echo '<button type="submit" class="' . esc_attr( $class ) . '"'
		. ( $disabled ? ' disabled aria-disabled="true"' : '' )
		. ( $title ? ' title="' . esc_attr( $title ) . '"' : '' )
		. ( $confirm && ! $disabled ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' )
		. '>' . esc_html( $label ) . '</button></form>';
}

/** Upload-Formular. Der Button erscheint erst, wenn eine Datei ausgewählt wurde. */
function n4s_apps_upload_form( $action, $fields, $input, $accept, $label ) {
	n4s_apps_form_head( $action, true, 'display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap' );
	foreach ( $fields as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	echo '<input type="file" name="' . esc_attr( $input ) . '" accept="' . esc_attr( $accept ) . '" required onchange="var b=this.form.querySelector(\'.n4s-rev\');if(b){b.style.display=this.files.length?\'inline-block\':\'none\';}">';
	echo '<button type="submit" class="button n4s-rev" style="display:none">' . esc_html( $label ) . '</button></form>';
}

/** Bestätigungsseite für ein Update: Vergleich installiert ↔ Update. */
function n4s_apps_render_review( $p ) {
	$c   = $p['cmp'];
	$old = $c['old'];
	$new = $c['new'];
	$dash = function ( $v ) { return '' === $v ? '–' : $v; };

	echo '<div class="notice notice-' . ( $c['warnings'] ? 'warning' : 'info' ) . '" style="padding:12px 16px"><h2 style="margin-top:0">' . esc_html( n4s_t( 'Update prüfen und bestätigen', 'Review and confirm update' ) ) . '</h2>';
	echo '<table class="widefat striped" style="max-width:640px"><thead><tr><th></th><th>' . esc_html( n4s_t( 'Installiert', 'Installed' ) ) . '</th><th>' . esc_html( n4s_t( 'Update', 'Update' ) ) . '</th></tr></thead><tbody>';
	$rows = array(
		array( n4s_t( 'Datei', 'File' ), $p['name'], $p['upload'] ),
		array( n4s_t( 'App-Name (Slug)', 'App slug' ), implode( ', ', $old['slugs'] ), implode( ', ', $new['slugs'] ) ),
		array( n4s_t( 'Anzeigename', 'Display name' ), $dash( $old['name'] ), $dash( $new['name'] ) ),
		array( n4s_t( 'Version', 'Version' ), $dash( $old['version'] ), $dash( $new['version'] ) ),
	);
	foreach ( $rows as $r ) {
		echo '<tr><td><strong>' . esc_html( $r[0] ) . '</strong></td><td><code>' . esc_html( $r[1] ) . '</code></td><td><code>' . esc_html( $r[2] ) . '</code></td></tr>';
	}
	echo '</tbody></table>';

	if ( $c['warnings'] ) {
		echo '<ul style="list-style:disc;margin-left:20px;color:#b32d2e">';
		foreach ( $c['warnings'] as $w ) {
			echo '<li>' . esc_html( $w ) . '</li>';
		}
		echo '</ul>';
	}
	foreach ( $c['notes'] as $n ) {
		echo '<p class="description" style="font-size:13px">' . esc_html( $n ) . '</p>';
	}
	if ( ! $c['warnings'] && ! $c['notes'] ) {
		echo '<p>' . esc_html( n4s_t( 'Name stimmt überein, die Version ist neuer.', 'Name matches, the version is newer.' ) ) . '</p>';
	}
	echo '<p>' . esc_html( n4s_t( 'Die installierte Datei wird ersetzt, die bisherige Version bleibt als .bak erhalten.', 'The installed file will be replaced; the previous version is kept as .bak.' ) ) . '</p>';

	n4s_apps_form_head( 'n4s_app_confirm' );
	echo '<input type="hidden" name="token" value="' . esc_attr( $p['token'] ) . '">';
	if ( $c['warnings'] ) {
		echo '<label><input type="checkbox" name="ack" value="1" required> ' . esc_html( n4s_t( 'Ich habe die Warnungen gelesen und möchte trotzdem aktualisieren.', 'I have read the warnings and want to update anyway.' ) ) . '</label> ';
	}
	echo '<button type="submit" class="button button-primary">' . esc_html( n4s_t( 'Update durchführen', 'Apply update' ) ) . '</button></form> ';
	n4s_apps_post_form( 'n4s_app_cancel', array(), n4s_t( 'Abbrechen', 'Cancel' ) );
	echo '</div>';
}

/** Rahmen aller Tabs: Titel (Logo mit Plugin-Version dahinter), Meldungen, Tab-Leiste, Inhalt. */
function n4s_apps_shell( $tab ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tabs = array( 'features' => array( 'n4s', n4s_t( 'Funktionen', 'Features' ) ) );
	if ( n4s_feature_on( 'apps' ) ) {
		$tabs['apps'] = array( 'n4s-apps', 'Apps' );
	}
	if ( n4s_feature_on( 'security' ) ) {
		$tabs['security'] = array( 'n4s-security', n4s_t( 'Sicherheit', 'Security' ) );
	}
	$extra = n4s_apps_extra_tabs();
	foreach ( $extra as $k => $t ) {
		$tabs[ $k ] = array( $t['page'], $t['label'] );
	}

	echo '<div class="wrap"><h1 style="display:flex;align-items:center;font-weight:600"><span style="display:inline-flex;height:26px">' . n4s_apps_logo( false, ' height="26"' ) . '</span><span class="screen-reader-text">n4s</span><span style="margin-left:10px;font-size:11px;font-weight:400;line-height:1;opacity:.6;align-self:flex-end;padding-bottom:3px">' . n4s_apps_byline( N4S_APPS_VERSION ) . '</span></h1>'; // phpcs:ignore WordPress.Security.EscapeOutput

	$nkey = 'n4s_apps_notice_' . get_current_user_id();
	$note = get_transient( $nkey );
	if ( $note ) {
		delete_transient( $nkey );
		echo '<div class="notice notice-' . ( 'success' === $note[0] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $note[1] ) . '</p></div>';
	}

	echo '<nav class="nav-tab-wrapper" style="margin:12px 0 16px">';
	foreach ( $tabs as $k => $t ) {
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $t[0] ) ) . '">' . esc_html( $t[1] ) . '</a>';
	}
	echo '</nav>';

	if ( 'apps' === $tab && isset( $tabs['apps'] ) ) {
		n4s_apps_tab_apps();
	} elseif ( 'security' === $tab && isset( $tabs['security'] ) && function_exists( 'n4s_sec_render' ) ) {
		n4s_sec_render();
	} elseif ( isset( $extra[ $tab ] ) ) {
		call_user_func( $extra[ $tab ]['render'] );
	} else {
		n4s_apps_tab_features();
	}

	echo '</div>';
}

/** Tab Funktionen: Apps und Sicherheit einzeln ein-/ausschalten, Plugin-Update. */
function n4s_apps_tab_features() {
	$f = n4s_features();
	echo '<h2>' . esc_html( n4s_t( 'Funktionen', 'Features' ) ) . '</h2>';
	n4s_apps_form_head( 'n4s_features_save', false, 'display:block' );
	echo '<table class="form-table"><tbody>';
	$rows = array(
		'apps'     => array(
			'Apps',
			n4s_t( 'Eigene PHP-Apps verwalten und per Shortcode [n4s_app] einbinden. Ausgeschaltet: Tab und Menü verschwinden, keine App wird geladen und die Shortcodes bleiben leer.', 'Manage custom PHP apps and embed them with the [n4s_app] shortcode. Switched off: tab and menu disappear, no app is loaded and the shortcodes stay empty.' ),
		),
		'security' => array(
			n4s_t( 'Sicherheit', 'Security' ),
			n4s_t( 'Anmeldeschutz (Sperren nach Fehlversuchen, Protokoll, Mail-Zusammenfassung) und Härtung. Ausgeschaltet: Tab und Menü verschwinden und der Schutz ist inaktiv; Einstellungen und Protokoll bleiben gespeichert.', 'Login protection (lockouts after failed attempts, log, email summary) and hardening. Switched off: tab and menu disappear and the protection is inactive; settings and log stay saved.' ),
		),
		'dup'          => array(
			n4s_t( 'Seite duplizieren', 'Duplicate page' ),
			n4s_t( 'Zeilenaktion „Duplizieren“ in der Seitenliste: legt eine Kopie als Entwurf an (Inhalt, Vorlage, Elternseite, Taxonomien und alle Metafelder, auch von Elementor oder Divi).', 'Row action "Duplicate" in the pages list: creates a copy as a draft (content, template, parent, taxonomies and all custom fields, including Elementor or Divi).' ),
		),
		'mediareplace' => array(
			n4s_t( 'Mediendatei ersetzen', 'Replace media file' ),
			n4s_t( 'Aktion „Datei ersetzen“ in der Mediathek: Datei tauschen und dabei Name und Adresse behalten, oder mit neuem Dateinamen ersetzen (Verweise in Seiteninhalten werden angepasst).', 'Action "Replace file" in the media library: swap the file and keep name and address, or replace it with a new filename (references in page content are updated).' ),
		),
	);
	foreach ( $rows as $k => $r ) {
		echo '<tr><th>' . esc_html( $r[0] ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $k ) . '" value="1"' . checked( ! empty( $f[ $k ] ), true, false ) . '> ' . esc_html( n4s_t( 'Eingeschaltet', 'Switched on' ) ) . '</label><p class="description">' . esc_html( $r[1] ) . '</p></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( n4s_t( 'Speichern', 'Save' ), 'primary', 'submit', true );
	echo '</form>';
	n4s_apps_update_section();
}

/** Plugin-Update (WordPress/GitHub und manuell per ZIP), im Tab Funktionen. */
function n4s_apps_update_section() {
	$can = n4s_apps_can_files();
	if ( current_user_can( 'update_plugins' ) ) {
		echo '<h2>' . esc_html( n4s_t( 'n4s Apps aktualisieren', 'Update n4s Apps' ) ) . '</h2>';
		echo '<p>' . esc_html( n4s_t( 'Installierte Version: ', 'Installed version: ' ) ) . '<strong>' . esc_html( N4S_APPS_VERSION ) . '</strong></p>';

		/* Automatisch über WordPress (GitHub) */
		$r = n4s_apps_gh_release();
		echo '<h3>' . esc_html( n4s_t( 'Über WordPress-Updates (GitHub)', 'Via WordPress updates (GitHub)' ) ) . '</h3><p>';
		echo esc_html( n4s_t( 'Quelle: ', 'Source: ' ) ) . '<code>github.com/' . esc_html( N4S_APPS_GITHUB_REPO ) . '</code> (' . esc_html( n4s_t( 'Release-Datei ', 'release file ' ) . N4S_APPS_ASSET ) . '). ';
		if ( ! empty( $r['error'] ) ) {
			echo '<span style="color:#b32d2e">' . esc_html( $r['error'] ) . '</span>';
		} elseif ( version_compare( $r['version'], N4S_APPS_VERSION, '>' ) ) {
			$base = plugin_basename( N4S_APPS_FILE );
			$ut   = get_site_transient( 'update_plugins' );
			echo '<strong>' . esc_html( n4s_t( 'Neue Version verfügbar: ', 'New version available: ' ) . $r['version'] ) . '</strong>';
			if ( $ut && isset( $ut->response[ $base ] ) ) {
				echo ' <a class="button button-primary" href="' . esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $base ) ), 'upgrade-plugin_' . $base ) ) . '">' . esc_html( n4s_t( 'Jetzt aktualisieren', 'Update now' ) ) . '</a>';
			} else {
				echo ' <span class="description">' . esc_html( n4s_t( '(zum Aktualisieren bitte „Jetzt prüfen“ klicken)', '(click "Check now" to update)' ) ) . '</span>';
			}
		} else {
			echo esc_html( n4s_t( 'Neueste Version: ', 'Latest version: ' ) . $r['version'] . n4s_t( ' (aktuell)', ' (up to date)' ) );
		}
		if ( ! empty( $r['checked'] ) ) {
			echo ' <span class="description">' . esc_html( n4s_t( 'Geprüft: ', 'Checked: ' ) . wp_date( 'd.m.Y H:i', $r['checked'] ) ) . '</span>';
		}
		echo '</p><p>';
		n4s_apps_post_form( 'n4s_gh_check', array(), n4s_t( 'Jetzt prüfen', 'Check now' ) );
		echo '</p><p class="description">' . esc_html( n4s_t(
			'Das Update erscheint wie bei jedem anderen Plugin unter Plugins und Dashboard → Aktualisierungen; automatische Updates lassen sich dort einschalten. Das ZIP wird vor der Installation geprüft.',
			'The update appears like for any other plugin under Plugins and Dashboard → Updates, where automatic updates can be enabled. The ZIP is checked before installation.'
		) ) . '</p>';
	}

	if ( $can ) {
		/* Manuell per ZIP */
		echo '<h3>' . esc_html( n4s_t( 'Manuell per ZIP', 'Manually via ZIP' ) ) . '</h3>';
		echo '<p>' . esc_html( n4s_t( 'Lade das neue Plugin-ZIP (n4s-apps-plugin.zip) hoch. Es wird nur eine neuere Version installiert; das Plugin bleibt aktiv, eigene Apps bleiben erhalten.', 'Upload the new plugin ZIP (n4s-apps-plugin.zip). Only a newer version is installed; the plugin stays active and custom apps are kept.' ) ) . '</p>';
		n4s_apps_upload_form( 'n4s_self_update', array(), 'n4s_zip', '.zip', n4s_t( 'Plugin aktualisieren', 'Update plugin' ) );
	}
}

/** Abschnitt „App-Katalog“ im Apps-Tab: Zugang, Update-Prüfung, Katalog laden und Apps installieren. */
function n4s_apps_catalog_section( $can, $sigok ) {
	$cfg = n4s_apps_catalog_cfg();
	$cur = get_option( 'n4s_apps_catalog', array() );
	$has = ! empty( $cur['key'] );
	$upd = n4s_apps_updates( false, true );
	echo '<h2>' . esc_html( n4s_t( 'App-Katalog', 'App catalog' ) ) . '</h2>';
	echo '<p>' . esc_html( n4s_t(
		'Apps mit dem Dateikopf „Update URI:“ werden alle 12 Stunden beim Katalog auf neue Versionen geprüft (nur Name und Version der App werden gesendet). Der Katalog wird nur auf Klick geladen. Dateien werden nur installiert, wenn ihre Signatur stimmt.',
		'Apps with the file header "Update URI:" are checked against the catalog for new versions every 12 hours (only app name and version are sent). The catalog is only loaded on click. Files are only installed if their signature is valid.'
	) ) . '</p>';
	if ( ! $sigok ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( n4s_t( 'Es ist kein Signaturschlüssel hinterlegt (includes/trust.php). Updates und Installationen aus dem Katalog sind deaktiviert.', 'No signing key is configured (includes/trust.php). Updates and installations from the catalog are disabled.' ) ) . '</p></div>';
	}
	if ( current_user_can( 'manage_options' ) ) {
		n4s_apps_form_head( 'n4s_catalog_save', false, 'display:block;margin-bottom:8px' );
		echo '<p><label>' . esc_html( n4s_t( 'Quelle: ', 'Source: ' ) ) . '<input type="url" name="source" value="' . esc_attr( $cfg['source'] ) . '" class="regular-text"></label> ';
		echo '<label>' . esc_html( n4s_t( 'Zugangsschlüssel (für private Apps): ', 'Access key (for private apps): ' ) ) . '<input type="password" name="key" autocomplete="off" class="regular-text" placeholder="' . esc_attr( $has ? n4s_t( 'gespeichert, leer lassen zum Behalten', 'saved, leave empty to keep' ) : '' ) . '"></label> ';
		if ( $has ) {
			echo '<label><input type="checkbox" name="key_clear" value="1"> ' . esc_html( n4s_t( 'Schlüssel entfernen', 'Remove key' ) ) . '</label> ';
		}
		submit_button( n4s_t( 'Speichern', 'Save' ), 'secondary', 'submit', false );
		echo '</p></form>';
		echo '<p style="display:flex;gap:6px;flex-wrap:wrap">';
		n4s_apps_post_form( 'n4s_catalog_check', array(), n4s_t( 'Updates jetzt prüfen', 'Check for updates' ) );
		n4s_apps_post_form( 'n4s_catalog_list', array(), n4s_t( 'Katalog laden', 'Load catalog' ) );
		echo '</p>';
	}
	if ( ! empty( $upd['checked'] ) ) {
		echo '<p class="description">' . esc_html( n4s_t( 'Zuletzt geprüft: ', 'Last checked: ' ) . wp_date( 'd.m.Y H:i', $upd['checked'] ) ) . ( $upd['errors'] ? ' · <span style="color:#b32d2e">' . esc_html( implode( ' · ', $upd['errors'] ) ) . '</span>' : '' ) . '</p>';
	}
	$autolog = get_option( 'n4s_apps_autolog', array() );
	if ( ! empty( $autolog['log'] ) ) {
		$parts = array();
		foreach ( $autolog['log'] as $s => $m ) {
			$parts[] = $s . ': ' . $m;
		}
		echo '<p class="description">' . esc_html( n4s_t( 'Letzte automatische Updates (', 'Last automatic updates (' ) . wp_date( 'd.m.Y H:i', (int) $autolog['time'] ) . '): ' . implode( ' · ', $parts ) ) . '</p>';
	}
	$list = get_transient( 'n4s_apps_cat_' . get_current_user_id() );
	if ( is_array( $list ) ) {
		$have = n4s_apps_installed_slugs();
		echo '<table class="widefat striped" style="margin-top:8px"><thead><tr><th>' . esc_html( n4s_t( 'App', 'App' ) ) . '</th><th>' . esc_html( n4s_t( 'Version', 'Version' ) ) . '</th><th>' . esc_html( n4s_t( 'Zugang', 'Access' ) ) . '</th><th>' . esc_html( n4s_t( 'Beschreibung', 'Description' ) ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $list ) {
			echo '<tr><td colspan="5">' . esc_html( n4s_t( 'Der Katalog enthält keine Apps (für diesen Zugang).', 'The catalog contains no apps (for this access).' ) ) . '</td></tr>';
		}
		foreach ( $list as $e ) {
			echo '<tr><td><strong>' . esc_html( $e['name'] ) . '</strong><br><code>' . esc_html( $e['slug'] ) . '</code></td><td>' . esc_html( $e['version'] ) . '</td><td>' . esc_html( 'private' === $e['access'] ? n4s_t( 'privat', 'private' ) : n4s_t( 'öffentlich', 'public' ) ) . '</td><td>' . esc_html( $e['description'] . ( $e['license'] ? ' (' . $e['license'] . ')' : '' ) ) . '</td><td>';
			if ( isset( $have[ $e['slug'] ] ) ) {
				echo '<span class="description">' . esc_html( n4s_t( 'installiert', 'installed' ) ) . '</span>';
			} elseif ( $can ) {
				n4s_apps_post_form( 'n4s_catalog_install', array( 'slug' => $e['slug'] ), n4s_t( 'Installieren', 'Install' ), 'button button-primary', '', ! $sigok, $sigok ? '' : n4s_t( 'Kein Signaturschlüssel hinterlegt.', 'No signing key configured.' ) );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

/** Tab Apps. */
function n4s_apps_tab_apps() {
	$can      = n4s_apps_can_files();
	$disabled = (array) get_option( 'n4s_apps_disabled', array() );

	if ( ! $can ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( n4s_t( 'Hochladen, Aktualisieren und Löschen sind nicht möglich: Dir fehlt das Recht „Plugins installieren" oder Dateiänderungen sind gesperrt (DISALLOW_FILE_MODS).', 'Uploading, updating and deleting are not possible: you lack the "install plugins" capability or file changes are disabled (DISALLOW_FILE_MODS).' ) ) . '</p></div>';
	}

	if ( $can && isset( $_GET['review'] ) ) {
		$p = get_transient( n4s_apps_pending_key() );
		if ( $p ) {
			n4s_apps_render_review( $p );
		}
	}

	/* Liste */
	echo '<h2>' . esc_html( n4s_t( 'Apps', 'Apps' ) ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr>';
	foreach ( array(
		n4s_t( 'App', 'App' ), 'Shortcode', n4s_t( 'Version', 'Version' ), n4s_t( 'Datei', 'File' ), n4s_t( 'Quelle', 'Source' ),
		n4s_t( 'Zugriff', 'Access' ), n4s_t( 'Status', 'Status' ), n4s_t( 'Geändert', 'Modified' ), n4s_t( 'Aktionen', 'Actions' ),
	) as $h ) {
		echo '<th>' . esc_html( $h ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	$files   = n4s_apps_files();
	$usrc    = n4s_apps_update_sources();
	$pending = $usrc ? n4s_apps_pending_updates( false, true ) : array();
	$auto    = (array) get_option( 'n4s_apps_autoupdate', array() );
	$sigok   = (bool) n4s_apps_trusted_keys();
	if ( ! $files ) {
		echo '<tr><td colspan="9">' . esc_html( n4s_t( 'Noch keine Apps vorhanden.', 'No apps yet.' ) ) . '</td></tr>';
	}
	foreach ( $files as $f ) {
		$name  = basename( $f['file'] );
		$key   = $f['source'] . ':' . $name;
		$repl  = n4s_apps_replaced( $key );
		$off   = $repl || in_array( $key, $disabled, true );
		$meta  = n4s_apps_meta( (string) file_get_contents( $f['file'] ) );
		$slugs = $meta['slugs'];
		$login = array();
		foreach ( $slugs as $s ) {
			if ( isset( $GLOBALS['n4s_apps'][ $s ] ) ) {
				$login[] = $GLOBALS['n4s_apps'][ $s ]['login'] ? n4s_t( 'nur angemeldet', 'logged-in only' ) : n4s_t( 'öffentlich', 'public' );
			}
		}
		echo '<tr>';
		echo '<td><strong>' . esc_html( $meta['name'] ? $meta['name'] : ( $slugs ? implode( ', ', $slugs ) : '–' ) ) . '</strong></td>';
		echo '<td>' . ( $slugs ? '<code>[n4s_app name="' . esc_html( $slugs[0] ) . '"]</code>' : '–' ) . '</td>';
		echo '<td>' . esc_html( $meta['version'] ? $meta['version'] : '–' ) . '</td>';
		echo '<td><code>' . esc_html( $name ) . '</code></td>';
		// Quelle: mitgeliefert, n4s (Apps mit Update URI auf den n4s-Katalog) oder eigene.
		$uhost = $meta['update_uri'] ? strtolower( (string) wp_parse_url( n4s_apps_base( $meta['update_uri'] ), PHP_URL_HOST ) ) : '';
		if ( 'bundled' === $f['source'] ) {
			$origin = n4s_t( 'mitgeliefert', 'bundled' );
		} elseif ( 'n4s.one' === preg_replace( '/^www\./', '', $uhost ) ) {
			$origin = 'n4s';
		} else {
			$origin = n4s_t( 'eigene', 'custom' );
		}
		echo '<td>' . esc_html( $origin ) . '</td>';
		echo '<td>' . ( $login ? esc_html( implode( ', ', array_unique( $login ) ) ) : '–' ) . '</td>';
		echo '<td>' . ( $repl ? '<span style="color:#b32d2e">' . esc_html( n4s_t( 'ersetzt: Sicherheit steckt jetzt im Plugin, Datei kann gelöscht werden', 'replaced: security is now part of the plugin, file can be deleted' ) ) . '</span>' : ( $off ? '<span style="color:#b32d2e">' . esc_html( n4s_t( 'deaktiviert', 'deactivated' ) ) . '</span>' : esc_html( n4s_t( 'aktiv', 'active' ) ) ) ) . '</td>';
		echo '<td>' . esc_html( wp_date( 'd.m.Y H:i', filemtime( $f['file'] ) ) ) . '</td>';
		echo '<td>';
		n4s_apps_post_form( 'n4s_app_toggle', array( 'key' => $key ), $off ? n4s_t( 'Aktivieren', 'Activate' ) : n4s_t( 'Deaktivieren', 'Deactivate' ) );
		if ( $can ) {
			echo ' ';
			n4s_apps_post_form(
				'n4s_app_delete',
				array( 'key' => $key ),
				n4s_t( 'Löschen', 'Delete' ),
				'button button-link-delete',
				n4s_t( 'App ' . $name . ' wirklich löschen?', 'Really delete app ' . $name . '?' ),
				! $off,
				$off ? '' : n4s_t( 'Zum Löschen die App zuerst deaktivieren.', 'Deactivate the app first to delete it.' )
			);
		}
		if ( 'user' === $f['source'] && $can ) {
			$s1 = ( 1 === count( $slugs ) ) ? $slugs[0] : '';
			if ( $s1 && isset( $pending[ $s1 ] ) ) {
				echo '<div style="margin:8px 0;padding:6px 8px;background:#f0f6fc;border-left:3px solid #2271b1"><strong>' . esc_html( n4s_t( 'Update verfügbar: ', 'Update available: ' ) . $pending[ $s1 ]['version'] ) . '</strong>';
				if ( $pending[ $s1 ]['changelog'] ) {
					echo '<br><span class="description">' . esc_html( $pending[ $s1 ]['changelog'] ) . '</span>';
				}
				echo '<br>';
				n4s_apps_post_form( 'n4s_app_catalog_update', array( 'slug' => $s1 ), n4s_t( 'Aktualisieren', 'Update now' ), 'button button-primary', '', ! $sigok, $sigok ? '' : n4s_t( 'Kein Signaturschlüssel hinterlegt.', 'No signing key configured.' ) );
				echo '</div>';
			}
			if ( $s1 && isset( $usrc[ $s1 ] ) ) {
				echo '<div class="description" style="margin:4px 0">' . esc_html( n4s_t( 'Update-Quelle: ', 'Update source: ' ) . n4s_apps_host( $usrc[ $s1 ]['base'] ) ) . ' · ';
				n4s_apps_post_form( 'n4s_app_autoupdate', array( 'slug' => $s1 ), in_array( $s1, $auto, true ) ? n4s_t( 'Automatisch: an', 'Automatic: on' ) : n4s_t( 'Automatisch: aus', 'Automatic: off' ), 'button-link' );
				echo '</div>';
			} else {
				echo '<br>';
			}
			n4s_apps_upload_form( 'n4s_app_save', array( 'mode' => 'update', 'target' => $name ), 'n4s_app_file', '.php', 'Update' );
		} elseif ( 'bundled' === $f['source'] ) {
			echo '<br><span class="description">' . esc_html( n4s_t( 'Update über Plugin-Update', 'Updated via plugin update' ) ) . '</span>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	n4s_apps_catalog_section( $can, $sigok );

	if ( $can ) {
		echo '<h2>' . esc_html( n4s_t( 'Neue App hinzufügen', 'Add new app' ) ) . '</h2>';
		echo '<p>' . esc_html( n4s_t(
			'PHP-Datei mit n4s_register_app(), defined( \'ABSPATH\' )-Schutz und optional Dateikopf „App Name:" / „Version:" hochladen. Der Code wird vor dem Speichern auf Syntaxfehler geprüft, aber nicht ausgeführt. Dateiname: Kleinbuchstaben, Ziffern, - und _. Ein bereits vergebener App-Name wird abgelehnt.',
			'Upload a PHP file with n4s_register_app(), the defined( \'ABSPATH\' ) guard and optionally the header "App Name:" / "Version:". The code is syntax-checked but not executed. File name: lowercase letters, digits, - and _. An app name that is already in use is rejected.'
		) ) . '</p>';
		n4s_apps_upload_form( 'n4s_app_save', array( 'mode' => 'new' ), 'n4s_app_file', '.php', n4s_t( 'App hochladen', 'Upload app' ) );
	}
	echo '<hr><p class="description">' . esc_html( n4s_t(
		'Eigene Apps liegen in wp-content/n4s-apps-data/apps/ und überstehen Plugin-Updates. Eine App, die beim Laden einen schweren Fehler verursacht, wird automatisch deaktiviert. Löschen ist erst nach dem Deaktivieren möglich. Vor Änderungen ein Backup anlegen.',
		'Custom apps live in wp-content/n4s-apps-data/apps/ and survive plugin updates. An app that causes a fatal error on load is deactivated automatically. Deleting is only possible after deactivating. Create a backup before making changes.'
	) ) . '</p>';
}
