<?php
/**
 * Oberfläche der n4s-Verwaltung: Stil (Navigation links, Karten, Schalter), Übersicht und Schalterzeilen.
 * Der Stil gilt nur innerhalb von .n4s-ui; WordPress-Seiten außerhalb bleiben unberührt.
 */

defined( 'ABSPATH' ) || exit;

/** Schalterzeile: Titel, Beschreibung, Schalter (Checkbox mit Name). */
function n4s_apps_ui_toggle( $name, $on, $title, $desc = '' ) {
	echo '<div class="n4s-row"><div><b>' . esc_html( $title ) . '</b>' . ( '' !== $desc ? '<small>' . esc_html( $desc ) . '</small>' : '' ) . '</div><label class="n4s-sw"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $on ), true, false ) . '><i></i><span class="screen-reader-text">' . esc_html( $title ) . '</span></label></div>';
}

/** Kennzahl-Kachel. */
function n4s_apps_ui_stat( $value, $label, $cls = '' ) {
	return '<div class="n4s-stat ' . esc_attr( $cls ) . '"><b>' . esc_html( $value ) . '</b><span>' . esc_html( $label ) . '</span></div>';
}

/** Status-Zeile (nur Anzeige) mit Link zum Tab. */
function n4s_apps_ui_status( $on, $title, $desc, $page ) {
	return '<a class="n4s-row n4s-linkrow" href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '"><div><b>' . esc_html( $title ) . '</b><small>' . esc_html( $desc ) . '</small></div><span class="n4s-pill ' . ( $on ? 'g' : 'x' ) . '">' . esc_html( $on ? n4s_t( 'an', 'on' ) : n4s_t( 'aus', 'off' ) ) . '</span></a>';
}

/** Tab Übersicht. */
function n4s_apps_tab_overview() {
	$f      = n4s_features();
	$keys   = array( 'apps', 'security', 'dup', 'mediareplace', 'login', 'theme', 'mcp' );
	$active = 0;
	foreach ( $keys as $k ) {
		$active += empty( $f[ $k ] ) ? 0 : 1;
	}
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
	echo '<div class="n4s-stats">';
	echo n4s_apps_ui_stat( N4S_APPS_VERSION, n4s_t( 'Version', 'Version' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- in n4s_apps_ui_stat escaped.
	echo n4s_apps_ui_stat( $active . ' / ' . count( $keys ), n4s_t( 'Funktionen aktiv', 'Features active' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo n4s_apps_ui_stat( $apps_on . ' / ' . $apps, n4s_t( 'Apps aktiv', 'Apps active' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo n4s_apps_ui_stat( (string) $upd, n4s_t( 'App-Updates', 'App updates' ), $upd ? 'warn' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( ! empty( $f['security'] ) ) {
		echo n4s_apps_ui_stat( (string) $locks, n4s_t( 'Aktive Sperren', 'Active locks' ), $locks ? 'warn' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';

	$mcp = function_exists( 'n4s_mcp_opt' ) ? n4s_mcp_opt() : array( 'on' => 0 );
	echo '<div class="n4s-grid2"><section class="n4s-card"><h3>' . esc_html( n4s_t( 'Status', 'Status' ) ) . '</h3>';
	echo n4s_apps_ui_status( ! empty( $f['security'] ), n4s_t( 'Anmeldeschutz', 'Login protection' ), ! empty( $f['security'] ) ? sprintf( n4s_t( '%d Fehlversuche in 24 Stunden', '%d failed attempts in 24 hours' ), $fails ) : n4s_t( 'Funktion ausgeschaltet', 'Feature switched off' ), 'n4s-security' ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( ! empty( $f['theme'] ) && function_exists( 'n4s_theme_opt' ) ) {
		$on = array_sum( array_map( 'intval', n4s_theme_opt()['on'] ) );
		echo n4s_apps_ui_status( true, n4s_t( 'Theme-Engine', 'Theme engine' ), sprintf( n4s_t( '%d Module an', '%d modules on' ), $on ), 'n4s-theme' ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo n4s_apps_ui_status( false, n4s_t( 'Theme-Engine', 'Theme engine' ), n4s_t( 'Funktion ausgeschaltet', 'Feature switched off' ), 'n4s-features' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo n4s_apps_ui_status( ! empty( $f['login'] ), n4s_t( 'Eigenes Login-Design', 'Custom login design' ), n4s_t( 'Logo und Farben des Themes', 'Logo and colors of the theme' ), 'n4s-features' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo n4s_apps_ui_status( ! empty( $f['mcp'] ), n4s_t( 'KI-Anbindung', 'AI connection' ), ! empty( $f['mcp'] ) ? ( ! empty( $mcp['on'] ) ? n4s_t( 'Schreibzugriff erlaubt', 'Write access allowed' ) : n4s_t( 'Schreibzugriff aus', 'Write access off' ) ) : n4s_t( 'Funktion ausgeschaltet', 'Feature switched off' ), ! empty( $f['mcp'] ) ? 'n4s-ki' : 'n4s-features' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</section><section class="n4s-card"><h3>' . esc_html( n4s_t( 'Zuletzt', 'Recent' ) ) . '</h3>';
	if ( $log && function_exists( 'n4s_sec_dt' ) ) {
		echo '<table class="n4s-tbl"><tr><th>' . esc_html( n4s_t( 'Zeit', 'Time' ) ) . '</th><th>' . esc_html( n4s_t( 'Ereignis', 'Event' ) ) . '</th><th>IP</th></tr>';
		foreach ( $log as $l ) {
			echo '<tr><td>' . esc_html( n4s_sec_dt( $l[0] ) ) . '</td><td>' . esc_html( n4s_sec_event( $l[1] ) ) . '</td><td>' . esc_html( $l[2] ) . '</td></tr>';
		}
		echo '</table>';
	} else {
		echo '<p class="n4s-lead">' . esc_html( n4s_t( 'Keine Ereignisse im Anmeldeschutz.', 'No events in the login protection.' ) ) . '</p>';
	}
	echo '</section></div>';

	$steps = array();
	if ( $newer ) {
		$steps[] = array( sprintf( n4s_t( 'Plugin-Update auf %s verfügbar', 'Plugin update to %s available' ), $newer ), 'n4s-update' );
	}
	if ( $upd ) {
		$steps[] = array( sprintf( n4s_t( '%d App-Update(s) im Katalog prüfen', 'Check %d app update(s) in the catalog' ), $upd ), 'n4s-catalog' );
	}
	if ( ! empty( $f['mcp'] ) && ! empty( $mcp['on'] ) ) {
		$steps[] = array( n4s_t( 'KI-Schreibzugriff ist eingeschaltet: nach der Arbeit wieder ausschalten', 'AI write access is on: switch it off again after the work' ), 'n4s-ki' );
	}
	if ( $locks ) {
		$steps[] = array( sprintf( n4s_t( '%d aktive IP-Sperre(n) im Anmeldeschutz prüfen', 'Check %d active IP lock(s) in the login protection' ), $locks ), 'n4s-security' );
	}
	echo '<section class="n4s-card"><h3>' . esc_html( n4s_t( 'Nächste Schritte', 'Next steps' ) ) . '</h3>';
	if ( $steps ) {
		echo '<ol class="n4s-steps">';
		foreach ( $steps as $st ) {
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . $st[1] ) ) . '">' . esc_html( $st[0] ) . '</a></li>';
		}
		echo '</ol>';
	} else {
		echo '<p class="n4s-lead">' . esc_html( n4s_t( 'Nichts zu tun.', 'Nothing to do.' ) ) . '</p>';
	}
	echo '</section>';
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
.n4s-ui .n4s-main{flex:1;min-width:0;max-width:1000px}
.n4s-ui .n4s-main>h2:first-child,.n4s-ui .n4s-main>form>h2:first-child{font-size:22px;margin:0 0 4px}
.n4s-ui .n4s-lead{color:#6b7385;margin:2px 0 16px}
.n4s-ui .n4s-main h2{font-size:17px;margin:22px 0 8px}.n4s-ui .n4s-main h3{font-size:15px}
.n4s-ui .n4s-card,.n4s-ui .n4s-main details{background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:16px 20px;margin-bottom:14px}
.n4s-ui .n4s-card>h3{margin:0 0 8px}
.n4s-ui .n4s-main details{padding:12px 18px}.n4s-ui .n4s-main details[open]{border-color:#5b4bdb}.n4s-ui .n4s-main details>summary{cursor:pointer;font-weight:600}
.n4s-ui .n4s-row{display:flex;align-items:center;gap:14px;padding:11px 0;border-top:1px solid #eef0f5;color:inherit;text-decoration:none}
.n4s-ui .n4s-card>.n4s-row:first-child,.n4s-ui .n4s-card>h3+.n4s-row{border-top:0}
.n4s-ui .n4s-row>div{flex:1}.n4s-ui .n4s-row small{display:block;color:#6b7385;font-size:12px;margin-top:1px}
.n4s-ui a.n4s-linkrow:hover{background:#fafbfd}
.n4s-ui .n4s-sw{position:relative;display:inline-block;width:40px;height:22px;flex:none}
.n4s-ui .n4s-sw input{position:absolute;opacity:0;inset:0;margin:0;cursor:pointer;z-index:1;width:100%;height:100%}
.n4s-ui .n4s-sw i{position:absolute;inset:0;border-radius:12px;background:#cdd2de;transition:background .15s}
.n4s-ui .n4s-sw i:after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:left .15s}
.n4s-ui .n4s-sw input:checked+i{background:#5b4bdb}.n4s-ui .n4s-sw input:checked+i:after{left:21px}
.n4s-ui .n4s-sw input:focus-visible+i{outline:2px solid #5b4bdb;outline-offset:2px}
.n4s-ui .form-table input[type=checkbox],.n4s-ui .n4s-main label>input[type=checkbox]{accent-color:#5b4bdb}
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
.n4s-ui .n4s-main{overflow-x:auto}
.n4s-ui .n4s-main .form-table{background:#fff;border:1px solid #e3e6ee;border-radius:12px;padding:6px 18px;margin:8px 0 14px;width:100%;box-sizing:border-box}
.n4s-ui .n4s-main .form-table th{padding:14px 10px 14px 0}.n4s-ui .n4s-main .form-table td{padding:10px 0}
.n4s-ui .n4s-main .form-table tr+tr th,.n4s-ui .n4s-main .form-table tr+tr td{border-top:1px solid #eef0f5}
.n4s-ui .n4s-main hr{border:0;border-top:1px solid #e3e6ee}
N4SCSS;
}
