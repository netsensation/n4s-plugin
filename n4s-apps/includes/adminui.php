<?php
/**
 * Oberfläche der n4s-Verwaltung: Stil (Navigation links, Karten, Schalter), Übersicht und Schalterzeilen.
 * Der Stil gilt nur innerhalb von .n4s-ui; WordPress-Seiten außerhalb bleiben unberührt.
 */

defined( 'ABSPATH' ) || exit;

/** Schalterzeile: Titel, Beschreibung, Schalter (Checkbox mit Name). */
function n4s_apps_ui_toggle( $name, $on, $title, $desc = '' ) {
	echo '<div class="n4s-set"><div><b>' . esc_html( $title ) . '</b>' . ( '' !== $desc ? '<small>' . esc_html( $desc ) . '</small>' : '' ) . '</div><label class="n4s-sw"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $on ), true, false ) . '><span class="screen-reader-text">' . esc_html( $title ) . '</span></label></div>';
}

/**
 * Einstellungszeile: links Überschrift und Erklärung (optional weitere Bedienelemente darunter), rechts mittig das Bedienelement.
 * $control und $extra sind fertiges, schon escaptes HTML.
 */
function n4s_apps_ui_row( $title, $desc, $control, $extra = '' ) {
	echo '<div class="n4s-set"><div><b>' . esc_html( $title ) . '</b>' . ( '' !== $desc ? '<small>' . esc_html( $desc ) . '</small>' : '' ) . $extra . '</div><div class="n4s-ctl">' . $control . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Bausteine sind escaped.
}

/** Schalter als HTML (Checkbox mit Name). */
function n4s_apps_ui_switch( $name, $on, $label ) {
	return '<label class="n4s-sw"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $on ), true, false ) . '><span class="screen-reader-text">' . esc_html( $label ) . '</span></label>';
}

/** Schalter, der beim Umlegen sofort eine Aktion (admin-post) ausführt. */
function n4s_apps_ui_switch_action( $action, $fields, $on, $label ) {
	$h = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="n4s-swform"><input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
	foreach ( $fields as $k => $v ) {
		$h .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	return $h . '<label class="n4s-sw"><input type="checkbox" onchange="this.form.submit()"' . checked( ! empty( $on ), true, false ) . '><span class="screen-reader-text">' . esc_html( $label ) . '</span></label></form>';
}

/** Apps und Plugin bei jedem Aufruf der Übersicht und der Apps auf Updates prüfen (höchstens einmal je Minute, damit schnelles Neuladen den Katalog nicht belastet). */
function n4s_apps_refresh_updates() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	if ( get_transient( 'n4s_apps_refresh' ) ) {
		return;
	}
	set_transient( 'n4s_apps_refresh', 1, 60 );
	if ( n4s_feature_on( 'apps' ) && function_exists( 'n4s_apps_update_sources' ) && n4s_apps_update_sources() ) {
		n4s_apps_updates( true );
	}
	if ( current_user_can( 'update_plugins' ) ) {
		n4s_apps_gh_release( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
	}
}

/** Kennzahl-Kachel; mit $url als Link. */
function n4s_apps_ui_stat( $value, $label, $cls = '', $url = '' ) {
	$tag = '' !== $url ? 'a href="' . esc_url( $url ) . '"' : 'div';
	return '<' . $tag . ' class="n4s-stat ' . esc_attr( $cls ) . '"><b>' . esc_html( $value ) . '</b><span>' . esc_html( $label ) . '</span></' . ( '' !== $url ? 'a' : 'div' ) . '>';
}

/** Status-Zeile (nur Anzeige) mit Link zum Tab. */
function n4s_apps_ui_status( $on, $title, $desc, $page ) {
	return '<a class="n4s-set n4s-linkrow" href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '"><div><b>' . esc_html( $title ) . '</b><small>' . esc_html( $desc ) . '</small></div><span class="n4s-pill ' . ( $on ? 'g' : 'x' ) . '">' . esc_html( $on ? n4s_t( 'an', 'on' ) : n4s_t( 'aus', 'off' ) ) . '</span></a>';
}

/** Tab Übersicht. */
function n4s_apps_tab_overview() {
	n4s_apps_refresh_updates();
	$f      = n4s_features();
	$keys   = array( 'apps', 'security', 'snippets', 'theme', 'mcp' ); // wie im Tab Funktionen (die weiteren Funktionen stehen im Tab Customize)
	$active = 0;
	foreach ( $keys as $k ) {
		$active += empty( $f[ $k ] ) ? 0 : 1;
	}
	// Customize-Funktionen: die Funktionen ohne eigene Einstellungen (Tab Customize) und die Module der WP-Customize-Funktion.
	$ckeys   = array( 'dup', 'mediareplace', 'login', 'barmenu', 'pageaccess' );
	$cmods   = array( 'menus', 'items', 'loginout', 'adminbar', 'widget', 'above', 'abovefooter', 'page', 'schemes' );
	$cactive = 0;
	foreach ( $ckeys as $k ) {
		$cactive += empty( $f[ $k ] ) ? 0 : 1;
	}
	if ( ! empty( $f['theme'] ) && function_exists( 'n4s_theme_opt' ) ) {
		$con = n4s_theme_opt()['on'];
		foreach ( $cmods as $k ) {
			$cactive += empty( $con[ $k ] ) ? 0 : 1;
		}
	}
	$ctotal   = count( $ckeys ) + count( $cmods );
	$disabled = (array) get_option( 'n4s_apps_disabled', array() );
	$apps     = 0;
	$apps_on  = 0;
	foreach ( n4s_apps_files() as $a ) {
		$key = $a['source'] . ':' . basename( $a['file'] );
		++$apps;
		if ( ! in_array( $key, $disabled, true ) && ! n4s_apps_replaced( $key ) ) {
			++$apps_on;
		}
	}
	$upd = ( ! empty( $f['apps'] ) && function_exists( 'n4s_apps_update_sources' ) && n4s_apps_update_sources() ) ? count( (array) n4s_apps_pending_updates( false, true ) ) : 0;
	$locks = 0;
	$fails = 0;
	$log   = array();
	if ( ! empty( $f['security'] ) && function_exists( 'n4s_sec_state' ) ) {
		$s = n4s_sec_state();
		foreach ( (array) $s['locks'] as $until ) {
			$locks += ( (int) $until > time() ) ? 1 : 0;
		}
		foreach ( (array) $s['fails'] as $c ) {
			$fails += (int) $c;
		}
		$log = array_slice( (array) $s['log'], 0, 4 );
	}
	$pu     = get_site_transient( 'update_plugins' );
	$self   = plugin_basename( N4S_APPS_FILE );
	$newer  = ( $pu && isset( $pu->response[ $self ] ) ) ? $pu->response[ $self ]->new_version : '';

	echo '<h2>' . esc_html( n4s_t( 'Übersicht', 'Overview' ) ) . '</h2><p class="n4s-lead">' . esc_html( n4s_t( 'Zustand von n4s Apps auf einen Blick', 'State of n4s Apps at a glance' ) ) . '</p>';
	$pg = function ( $page ) {
		return admin_url( 'admin.php?page=' . $page );
	};
	echo '<div class="n4s-stats">';
	echo n4s_apps_ui_stat( N4S_APPS_VERSION, $newer ? sprintf( n4s_t( 'Version (%s verfügbar)', 'Version (%s available)' ), $newer ) : n4s_t( 'Version', 'Version' ), $newer ? 'warn' : '', $pg( 'n4s' ) . '#n4s-update' ); // phpcs:ignore WordPress.Security.EscapeOutput -- in n4s_apps_ui_stat escaped.
	echo n4s_apps_ui_stat( $active . ' / ' . count( $keys ), n4s_t( 'Funktionen aktiv', 'Features active' ), 'ok', $pg( 'n4s-features' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo n4s_apps_ui_stat( $cactive . ' / ' . $ctotal, n4s_t( 'Customize-Funktionen aktiv', 'Customize features active' ), 'ok', $pg( 'n4s-theme' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo n4s_apps_ui_stat( $apps_on . ' / ' . $apps, n4s_t( 'Apps aktiv', 'Apps active' ), '', $pg( 'n4s-apps' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo n4s_apps_ui_stat( (string) $upd, n4s_t( 'App-Updates', 'App updates' ), $upd ? 'warn' : '', $pg( 'n4s-apps' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo n4s_apps_ui_stat( ! empty( $f['security'] ) ? (string) $locks : '–', n4s_t( 'Aktive Sperren', 'Active locks' ), $locks ? 'warn' : '', $pg( ! empty( $f['security'] ) ? 'n4s-security' : 'n4s-features' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';

	$mcp = function_exists( 'n4s_mcp_opt' ) ? n4s_mcp_opt() : array( 'on' => 0 );
	echo '<div class="n4s-grid2"><section class="n4s-card"><h3>' . esc_html( n4s_t( 'Status', 'Status' ) ) . '</h3>';
	echo n4s_apps_ui_status( ! empty( $f['security'] ), n4s_t( 'Anmeldeschutz', 'Login protection' ), ! empty( $f['security'] ) ? sprintf( n4s_t( '%d Fehlversuche in 24 Stunden', '%d failed attempts in 24 hours' ), $fails ) : n4s_t( 'Funktion ausgeschaltet', 'Feature switched off' ), 'n4s-security' ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( ! empty( $f['theme'] ) && function_exists( 'n4s_theme_opt' ) ) {
		$on = array_sum( array_map( 'intval', n4s_theme_opt()['on'] ) );
		echo n4s_apps_ui_status( true, 'WP-Customize', sprintf( n4s_t( '%d Module an', '%d modules on' ), $on ), 'n4s-theme' ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo n4s_apps_ui_status( false, 'WP-Customize', n4s_t( 'Funktion ausgeschaltet', 'Feature switched off' ), 'n4s-features' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo n4s_apps_ui_status( ! empty( $f['login'] ), n4s_t( 'Eigenes Login-Design', 'Custom login design' ), n4s_t( 'Logo und Farben des Themes', 'Logo and colors of the theme' ), 'n4s-features' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo n4s_apps_ui_status( ! empty( $f['mcp'] ), n4s_t( 'KI-Anbindung', 'AI connection' ), ! empty( $f['mcp'] ) ? ( ! empty( $mcp['on'] ) ? n4s_t( 'Schreibzugriff erlaubt', 'Write access allowed' ) : n4s_t( 'Schreibzugriff aus', 'Write access off' ) ) : n4s_t( 'Funktion ausgeschaltet', 'Feature switched off' ), ! empty( $f['mcp'] ) ? 'n4s-ki' : 'n4s-features' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</section><section class="n4s-card"><h3>' . esc_html( n4s_t( 'Sicherheit', 'Security' ) ) . '</h3>';
	if ( $log && function_exists( 'n4s_sec_dt' ) ) {
		echo '<table class="n4s-tbl"><tr><th>' . esc_html( n4s_t( 'Zeit', 'Time' ) ) . '</th><th>' . esc_html( n4s_t( 'Ereignis', 'Event' ) ) . '</th><th>IP</th></tr>';
		foreach ( $log as $l ) {
			echo '<tr><td>' . esc_html( n4s_sec_dt( $l[0] ) ) . '</td><td>' . esc_html( n4s_sec_event( $l[1] ) ) . '</td><td>' . esc_html( $l[2] ) . '</td></tr>';
		}
		echo '</table>';
	} else {
		echo '<p class="n4s-lead">' . esc_html( n4s_t( 'Keine Ereignisse im Anmeldeschutz.', 'No events in the login protection.' ) ) . '</p>';
	}
	$act = get_option( 'n4s_apps_activity', array() );
	echo '<h4 style="margin:16px 0 6px">' . esc_html( n4s_t( 'Aktivitäten', 'Activity' ) ) . '</h4>';
	if ( is_array( $act ) && $act ) {
		echo '<div class="n4s-actlog" style="max-height:260px;overflow:auto"><table class="n4s-tbl"><tr><th>' . esc_html( n4s_t( 'Zeit', 'Time' ) ) . '</th><th>' . esc_html( n4s_t( 'Aktivität', 'Activity' ) ) . '</th><th>' . esc_html( n4s_t( 'Von', 'By' ) ) . '</th></tr>';
		foreach ( $act as $a ) {
			echo '<tr><td style="white-space:nowrap">' . esc_html( wp_date( n4s_t( 'd.m.Y H:i', 'Y-m-d H:i' ), (int) $a[0] ) ) . '</td><td>' . esc_html( $a[1] ) . '</td><td>' . esc_html( '' !== $a[2] ? $a[2] : n4s_t( 'automatisch', 'automatic' ) ) . '</td></tr>';
		}
		echo '</table></div>';
	} else {
		echo '<p class="n4s-lead">' . esc_html( n4s_t( 'Noch keine Aktivitäten aufgezeichnet.', 'No activity recorded yet.' ) ) . '</p>';
	}
	echo '</section></div>';

	/* Updates: Apps und Plugin (bei jedem Aufruf geprüft) */
	echo '<h2 id="n4s-update">' . esc_html( n4s_t( 'Update', 'Update' ) ) . '</h2>';
	$pend = ( ! empty( $f['apps'] ) && function_exists( 'n4s_apps_update_sources' ) && n4s_apps_update_sources() ) ? (array) n4s_apps_pending_updates( false, true ) : array();
	if ( $pend ) {
		echo '<div class="n4s-card"><h3>' . esc_html( n4s_t( 'App-Updates', 'App updates' ) ) . '</h3>';
		foreach ( $pend as $slug => $u ) {
			n4s_apps_ui_row( (string) $slug, n4s_t( 'Neue Version ', 'New version ' ) . $u['version'], '<a class="button button-primary" href="' . esc_url( $pg( 'n4s-apps' ) ) . '">' . esc_html( n4s_t( 'Zu den Apps', 'Go to apps' ) ) . '</a>' );
		}
		echo '</div>';
	}
	n4s_apps_update_section();
}

/** Stil der Verwaltung. */
function n4s_apps_ui_css() {
	return <<<'N4SCSS'
.n4s-ui{margin:0 20px 0 2px;color:#1d2433}
.n4s-ui .n4s-top{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:12px 20px;margin:14px 0 14px}
.n4s-ui .n4s-logo{display:inline-flex;height:26px}.n4s-ui .n4s-ver{margin-left:auto;font-size:12px;color:#6b7385}
.n4s-ui .n4s-layout{display:flex;gap:18px;align-items:flex-start}
.n4s-ui .n4s-side{flex:0 0 190px;background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:10px;position:sticky;top:42px}
.n4s-ui .n4s-side a{display:block;padding:9px 12px;border-radius:8px;color:#3b4357;text-decoration:none;margin-bottom:2px}
.n4s-ui .n4s-side a:hover{background:#f3f4f8}.n4s-ui .n4s-side a.act{background:#eceaff;color:#4a3bc7;font-weight:600}
.n4s-ui .n4s-main{flex:1;min-width:0;max-width:1240px}
.n4s-ui .n4s-main>h2:first-child,.n4s-ui .n4s-main>form>h2:first-child{font-size:22px;line-height:1.4;margin:0 0 4px;padding:6px 0 2px;overflow:visible}
.n4s-ui .n4s-main>.n4s-lead:nth-child(2){line-height:1.5;margin-top:0;overflow:visible;white-space:normal}
.n4s-ui .n4s-lead{color:#6b7385;margin:2px 0 16px}
.n4s-ui .n4s-main h2{font-size:17px;margin:22px 0 8px}.n4s-ui .n4s-main h3{font-size:15px}
.n4s-ui .n4s-card,.n4s-ui .n4s-main details{background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:16px 20px;margin-bottom:14px}
.n4s-ui .n4s-card>h3{margin:0 0 8px}
.n4s-ui .n4s-main details{padding:12px 18px}.n4s-ui .n4s-main details[open]{border-color:#5b4bdb}.n4s-ui .n4s-main details>summary{cursor:pointer;font-weight:600}
.n4s-ui .n4s-set{display:flex;align-items:center;gap:14px;padding:11px 0;border-top:1px solid #eef0f5;color:inherit;text-decoration:none}
.n4s-ui .n4s-card>.n4s-set:first-child,.n4s-ui .n4s-card>h3+.n4s-set{border-top:0}
.n4s-ui .n4s-set>div:first-child{flex:1;min-width:0}.n4s-ui .n4s-set small{display:block;color:#6b7385;font-size:12px;margin-top:1px}
.n4s-ui .n4s-set .n4s-ctl{flex:none;display:flex;align-items:center;gap:6px;margin-left:auto}
.n4s-ui .n4s-set .n4s-extra{margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.n4s-ui .n4s-swform{margin:0;display:inline-flex}
.n4s-ui a.n4s-stat{color:inherit;text-decoration:none;display:block}.n4s-ui a.n4s-stat:hover{border-color:#5b4bdb;box-shadow:0 1px 4px rgba(91,75,219,.15)}
.n4s-ui .n4s-swtable{border:0!important;background:transparent!important;box-shadow:none!important}
.n4s-ui .n4s-swtable thead{display:none}
.n4s-ui .n4s-swtable tbody{display:block;background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:0 20px}
.n4s-ui .n4s-swtable tr{display:flex;flex-direction:row-reverse;align-items:center;gap:14px;background:transparent!important;border-top:1px solid #eef0f5}.n4s-ui .n4s-swtable tr:first-child{border-top:0}
.n4s-ui .n4s-swtable td{display:block;border:0!important;background:transparent!important;padding:12px 0}
.n4s-ui .n4s-swtable td:first-child{flex:none;width:80px;text-align:right}.n4s-ui .n4s-swtable td:nth-child(2){flex:1;min-width:0}
.n4s-ui .n4s-num{display:inline-flex;align-items:center;gap:8px;width:240px}.n4s-ui .n4s-num input{width:80px;flex:none}.n4s-ui .n4s-num span{color:#3b4357}
.n4s-ui .n4s-main label:has(>input[type=checkbox]){display:inline-flex;align-items:center;gap:6px}
.n4s-ui .n4s-swtable td strong{color:#1d2433;font-size:14px}
.n4s-ui .n4s-t-apps{max-width:1500px}
.n4s-ui .n4s-apptbl{border:0!important;background:transparent!important;box-shadow:none!important;border-collapse:separate;border-spacing:0 8px}
.n4s-ui .n4s-apptbl thead th{background:transparent!important;border:0!important;text-transform:uppercase;font-size:11px;letter-spacing:.04em;color:#6b7385;padding:2px 12px}
.n4s-ui .n4s-apptbl tbody td{background:#fff!important;border-top:1px solid #e3e6ee;border-bottom:1px solid #e3e6ee;padding:12px;vertical-align:middle}
.n4s-ui .n4s-apptbl tbody td:first-child{border-left:1px solid #e3e6ee;border-radius:12px 0 0 12px}.n4s-ui .n4s-apptbl tbody td:last-child{border-right:1px solid #e3e6ee;border-radius:0 12px 12px 0}
.n4s-ui .n4s-apptbl tbody tr.n4s-off td{opacity:.8}
.n4s-ui .n4s-apptbl .n4s-upd{margin-top:6px;padding:6px 8px;background:#f4f2ff;border-radius:8px}
.n4s-ui .n4s-apptbl details{border:0;padding:0;margin:6px 0 0;background:transparent}
.n4s-ui a.n4s-linkrow:hover{background:#fafbfd}
.n4s-ui .n4s-stats{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.n4s-ui .n4s-stat{flex:1;min-width:130px;background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:14px 16px}
.n4s-ui .n4s-stat b{display:block;font-size:24px;line-height:1.2}.n4s-ui .n4s-stat span{color:#6b7385;font-size:12px}
.n4s-ui .n4s-stat.ok b{color:#14833b}.n4s-ui .n4s-stat.warn b{color:#b45309}
.n4s-ui .n4s-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:1100px){.n4s-ui .n4s-grid2{grid-template-columns:1fr}}
@media(max-width:782px){.n4s-ui .n4s-layout{flex-direction:column}.n4s-ui .n4s-side{flex:none;width:100%;position:static;display:flex;flex-wrap:wrap;gap:4px}}
.n4s-ui .n4s-pill{padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600}.n4s-ui .n4s-pill.g{background:#dcf5e3;color:#14833b}.n4s-ui .n4s-pill.x{background:#eceef3;color:#6b7385}
.n4s-ui .n4s-tbl{width:100%;border-collapse:collapse}.n4s-ui .n4s-tbl th{text-align:left;color:#6b7385;font-weight:500;font-size:12px;padding:5px 8px;border-bottom:1px solid #e3e6ee}.n4s-ui .n4s-tbl td{padding:8px;border-bottom:1px solid #f0f2f7}
.n4s-ui .n4s-steps{margin:4px 0 0 18px}.n4s-ui .n4s-steps li{margin-bottom:5px}
.n4s-ui .n4s-main .widefat{border:1px solid #e3e6ee;border-radius:10px;overflow:hidden;box-shadow:none}
.n4s-ui .n4s-main .widefat thead th{background:#f7f8fb;color:#6b7385;font-weight:500}
.n4s-ui .n4s-main .button{border-radius:8px;border-color:#cdd2de;color:#3b4357}
.n4s-ui .n4s-main .button-primary{background:#5b4bdb;border-color:#5b4bdb;color:#fff}.n4s-ui .n4s-main .button-primary:hover{background:#4a3bc7;border-color:#4a3bc7}
.n4s-ui .n4s-main input[type=text],.n4s-ui .n4s-main input[type=number],.n4s-ui .n4s-main input[type=password],.n4s-ui .n4s-main select,.n4s-ui .n4s-main textarea{border-radius:8px;border-color:#cdd2de}
.n4s-ui .n4s-main .notice{border-radius:8px}
.n4s-ui .n4s-main .form-table{background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:6px 18px;margin:8px 0 14px;width:100%;box-sizing:border-box}
.n4s-ui .n4s-main .form-table th{padding:14px 10px 14px 0}.n4s-ui .n4s-main .form-table td{padding:10px 0}
.n4s-ui .n4s-main .form-table tr+tr th,.n4s-ui .n4s-main .form-table tr+tr td{border-top:1px solid #eef0f5}
.n4s-ui .n4s-main{overflow-x:auto;padding-bottom:2px}
.n4s-ui .n4s-t-admin .n4s-cols{grid-template-columns:1em minmax(100px,1.2fr) minmax(90px,1fr) 70px minmax(80px,.8fr) 80px minmax(90px,1fr) minmax(190px,auto)!important}
.n4s-ui .n4s-title{font-size:22px;margin:0 0 2px;padding:0}
.n4s-ui .n4s-main input[type=checkbox]{-webkit-appearance:none;appearance:none;position:relative;display:inline-block;vertical-align:middle;width:40px;height:22px;min-width:40px;margin:0 8px 0 0;border:0;border-radius:12px;background:#cdd2de;cursor:pointer;box-shadow:none;transition:background .15s}
.n4s-ui .n4s-main input[type=checkbox]::before{content:"";position:absolute;float:none;top:3px;left:3px;width:16px;height:16px;margin:0;border-radius:50%;background:#fff;box-shadow:none;transition:left .15s}
.n4s-ui .n4s-main input[type=checkbox]:checked{background:#5b4bdb}.n4s-ui .n4s-main input[type=checkbox]:checked::before{content:"";left:21px;background:#fff}
.n4s-ui .n4s-main input[type=checkbox]:focus{outline:2px solid #5b4bdb;outline-offset:2px;box-shadow:none}
.n4s-ui .n4s-main input[type=checkbox]:disabled{opacity:.55;cursor:default}
.n4s-ui .n4s-sw{display:inline-flex;flex:none}.n4s-ui .n4s-sw input{margin:0!important}
.n4s-ui .n4s-main div[style*="max-width"],.n4s-ui .n4s-main table[style*="max-width"],.n4s-ui .n4s-main form[style*="max-width"]{max-width:none!important}
.n4s-ui .n4s-main p{max-width:900px}.n4s-ui .n4s-main p.description{max-width:900px}
.n4s-ui .n4s-main .widefat th{white-space:nowrap}.n4s-ui .n4s-main .widefat td,.n4s-ui .n4s-main .widefat th{vertical-align:top}
.n4s-ui .n4s-main .widefat td code{white-space:normal;word-break:break-all}
.n4s-ui .n4s-main div[style*="dcdcde"],.n4s-ui .n4s-main svg[style*="dcdcde"]{border-color:#e3e6ee!important;border-radius:12px!important}
.n4s-ui .n4s-main .n4s-card .widefat{border:0}
.n4s-ui .n4s-main .n4s-card>.n4s-card,.n4s-ui .n4s-card .n4s-card{border:0;padding:0;margin:0}
.n4s-ui .n4s-main input[type=file]{max-width:100%}
.n4s-ui .n4s-main .widefat .button,.n4s-ui .n4s-main .widefat form{margin:0 4px 4px 0}
.n4s-ui .n4s-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:10px 16px;margin:0 0 14px}
.n4s-ui .n4s-toolbar .description{margin:0}
.n4s-ui .n4s-main .form-table td p.description{max-width:none}
.n4s-ui .n4s-main .form-table input.regular-text,.n4s-ui .n4s-main .form-table textarea{max-width:100%}
.n4s-ui .n4s-main hr{border:0;border-top:1px solid #e3e6ee}
N4SCSS;
}
