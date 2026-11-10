<?php
/**
 * Werkzeuge (Nachbau der freien Funktionen von Duplicate Page and Post und Enable Media Replace):
 * - Seite duplizieren: Zeilenaktion „Duplizieren“ in der Seitenliste (Kopie als Entwurf).
 * - Mediendatei ersetzen: „Datei ersetzen“ in der Mediathek (gleicher oder neuer Dateiname).
 * Jede Funktion wird nur geladen, wenn sie unter n4s → Funktionen eingeschaltet ist.
 */

defined( 'ABSPATH' ) || exit;

/** Meta-Schlüssel, die beim Duplizieren nicht mitkopiert werden. */
function n4s_tools_dup_skip_meta() {
	return array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug' );
}

/** Zeilenaktion „Duplizieren“ in der Seitenliste. */
function n4s_tools_dup_row_action( $actions, $post ) {
	if ( 'page' === $post->post_type && current_user_can( 'edit_post', $post->ID ) && current_user_can( 'edit_pages' ) ) {
		$url                 = wp_nonce_url( admin_url( 'admin-post.php?action=n4s_tools_dup&post=' . (int) $post->ID ), 'n4s_tools_dup_' . (int) $post->ID );
		$actions['n4s_dup'] = '<a href="' . esc_url( $url ) . '">' . esc_html( n4s_t( 'Duplizieren', 'Duplicate' ) ) . '</a>';
	}
	return $actions;
}

/** Seite als Entwurf kopieren (Inhalt, Auszug, Vorlage, Elternseite, Reihenfolge, Taxonomien, alle Metafelder). */
function n4s_tools_do_dup() {
	$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- unten geprüft.
	check_admin_referer( 'n4s_tools_dup_' . $id );
	$src = $id ? get_post( $id ) : null;
	if ( ! $src || 'page' !== $src->post_type || ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html( n4s_t( 'Diese Seite kann nicht dupliziert werden.', 'This page cannot be duplicated.' ) ), '', array( 'response' => 403 ) );
	}
	$new = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => 'page',
				'post_status'    => 'draft',
				'post_title'     => $src->post_title . ' ' . n4s_t( '(Kopie)', '(copy)' ),
				'post_content'   => $src->post_content,
				'post_excerpt'   => $src->post_excerpt,
				'post_parent'    => $src->post_parent,
				'menu_order'     => $src->menu_order,
				'comment_status' => $src->comment_status,
				'ping_status'    => $src->ping_status,
				'post_password'  => $src->post_password,
				'post_author'    => get_current_user_id(),
			)
		),
		true
	);
	if ( is_wp_error( $new ) || ! $new ) {
		wp_die( esc_html( n4s_t( 'Die Kopie konnte nicht angelegt werden.', 'The copy could not be created.' ) ) );
	}
	foreach ( get_object_taxonomies( 'page' ) as $tax ) {
		$terms = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) && $terms ) {
			wp_set_object_terms( $new, $terms, $tax );
		}
	}
	$skip = n4s_tools_dup_skip_meta();
	foreach ( get_post_custom( $id ) as $key => $values ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}
		foreach ( $values as $v ) {
			add_post_meta( $new, $key, wp_slash( maybe_unserialize( $v ) ) );
		}
	}
	wp_safe_redirect( add_query_arg( array( 'post_type' => 'page', 'n4s_dup' => $new ), admin_url( 'edit.php' ) ) );
	exit;
}

function n4s_tools_dup_notice() {
	$id = isset( $_GET['n4s_dup'] ) ? absint( $_GET['n4s_dup'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige.
	$p  = $id ? get_post( $id ) : null;
	if ( $p && 'page' === $p->post_type && current_user_can( 'edit_post', $id ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( n4s_t( 'Kopie als Entwurf angelegt: ', 'Copy created as draft: ' ) . $p->post_title ) . ' <a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( n4s_t( 'Bearbeiten', 'Edit' ) ) . '</a></p></div>';
	}
}

/* ---- Mediendatei ersetzen ---- */

function n4s_tools_mr_url( $id ) {
	return add_query_arg( array( 'page' => 'n4s-media-replace', 'id' => (int) $id ), admin_url( 'upload.php' ) );
}

function n4s_tools_mr_row_action( $actions, $post ) {
	if ( current_user_can( 'edit_post', $post->ID ) ) {
		$actions['n4s_replace'] = '<a href="' . esc_url( n4s_tools_mr_url( $post->ID ) ) . '">' . esc_html( n4s_t( 'Datei ersetzen', 'Replace file' ) ) . '</a>';
	}
	return $actions;
}

/** Hinweis mit Link im Anhang-Bearbeitungsbildschirm und im Medien-Dialog. */
function n4s_tools_mr_field( $fields, $post ) {
	if ( current_user_can( 'edit_post', $post->ID ) ) {
		$fields['n4s_replace'] = array(
			'label' => n4s_t( 'Datei ersetzen', 'Replace file' ),
			'input' => 'html',
			'html'  => '<a class="button" href="' . esc_url( n4s_tools_mr_url( $post->ID ) ) . '">' . esc_html( n4s_t( 'Neue Datei hochladen …', 'Upload new file …' ) ) . '</a>',
		);
	}
	return $fields;
}

function n4s_tools_mr_page() {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige.
	$p  = $id ? get_post( $id ) : null;
	if ( ! $p || 'attachment' !== $p->post_type || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html( n4s_t( 'Datei nicht gefunden.', 'File not found.' ) ) );
	}
	$nk = 'n4s_tools_mr_notice_' . get_current_user_id();
	$n  = get_transient( $nk );
	echo '<div class="wrap"><h1>' . esc_html( n4s_t( 'Datei ersetzen', 'Replace file' ) ) . '</h1>';
	if ( $n ) {
		delete_transient( $nk );
		echo '<div class="notice notice-' . ( 'success' === $n[0] ? 'success' : 'error' ) . '"><p>' . esc_html( $n[1] ) . '</p></div>';
	}
	$file = get_attached_file( $id );
	echo '<p><strong>' . esc_html( $p->post_title ) . '</strong> · <code>' . esc_html( wp_basename( (string) $file ) ) . '</code> · ' . esc_html( (string) $p->post_mime_type ) . '</p>';
	if ( wp_attachment_is_image( $id ) ) {
		echo '<p>' . wp_get_attachment_image( $id, array( 220, 220 ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress-Funktion.
	}
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="n4s_tools_mr"><input type="hidden" name="id" value="' . (int) $id . '">';
	wp_nonce_field( 'n4s_tools_mr_' . $id );
	echo '<p><input type="file" name="n4s_file" required></p>';
	echo '<p><label><input type="radio" name="mode" value="same" checked> <strong>' . esc_html( n4s_t( 'Nur Datei ersetzen', 'Replace file only' ) ) . '</strong> – ' . esc_html( n4s_t( 'Dateiname und Adresse bleiben gleich, alle Verwendungen zeigen die neue Datei. Die neue Datei muss denselben Dateityp haben.', 'Filename and address stay the same, all uses show the new file. The new file must be of the same type.' ) ) . '</label><br>';
	echo '<label><input type="radio" name="mode" value="rename"> <strong>' . esc_html( n4s_t( 'Ersetzen und neuen Dateinamen verwenden', 'Replace and use the new filename' ) ) . '</strong> – ' . esc_html( n4s_t( 'Die Adresse ändert sich, Verweise in Seiten und Inhalten werden angepasst. Beliebiger Dateityp.', 'The address changes, references in pages and content are updated. Any file type.' ) ) . '</label></p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html( n4s_t( 'Datei ersetzen', 'Replace file' ) ) . '</button> <a class="button" href="' . esc_url( admin_url( 'upload.php' ) ) . '">' . esc_html( n4s_t( 'Zurück zur Mediathek', 'Back to media library' ) ) . '</a></p></form>';
	echo '<p class="description">' . esc_html( n4s_t( 'Hinweis: Browser und Seitencaches zeigen die alte Datei möglicherweise noch kurz an.', 'Note: browsers and page caches may show the old file for a short while.' ) ) . '</p></div>';
}

function n4s_tools_mr_back( $id, $type, $text ) {
	set_transient( 'n4s_tools_mr_notice_' . get_current_user_id(), array( $type, $text ), 60 );
	wp_safe_redirect( n4s_tools_mr_url( $id ) );
	exit;
}

/** Relative Pfade (ab dem Upload-Ordner) der Hauptdatei und aller Größen: array( alter Pfad => Name der Größe oder '' ). */
function n4s_tools_mr_paths( $id ) {
	$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
	$dir  = dirname( $rel );
	$dir  = '.' === $dir ? '' : $dir . '/';
	$out  = array( $rel => '' );
	$meta = wp_get_attachment_metadata( $id );
	if ( is_array( $meta ) && ! empty( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $name => $s ) {
			if ( ! empty( $s['file'] ) ) {
				$out[ $dir . $s['file'] ] = $name;
			}
		}
	}
	if ( is_array( $meta ) && ! empty( $meta['original_image'] ) ) {
		$out[ $dir . $meta['original_image'] ] = '@original';
	}
	return $out;
}

/** Verweise in Seiteninhalten und (nicht serialisierten) Metafeldern ersetzen. */
function n4s_tools_mr_replace_urls( $map ) {
	global $wpdb;
	foreach ( $map as $old => $new ) {
		if ( $old === $new || '' === $old ) {
			continue;
		}
		foreach ( array( array( '/' . $old, '/' . $new ), array( str_replace( '/', '\\/', '/' . $old ), str_replace( '/', '\\/', '/' . $new ) ) ) as $pair ) {
			$like = '%' . $wpdb->esc_like( $pair[0] ) . '%';
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s", $pair[0], $pair[1], $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s AND meta_value NOT REGEXP '^[aOsibdC]:[0-9]+[:;]'", $pair[0], $pair[1], $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
	clean_post_cache( 0 );
}

function n4s_tools_do_mr() {
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	check_admin_referer( 'n4s_tools_mr_' . $id );
	$p = $id ? get_post( $id ) : null;
	if ( ! $p || 'attachment' !== $p->post_type || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html( n4s_t( 'Datei nicht gefunden.', 'File not found.' ) ), '', array( 'response' => 403 ) );
	}
	if ( empty( $_FILES['n4s_file']['name'] ) || ! empty( $_FILES['n4s_file']['error'] ) ) {
		n4s_tools_mr_back( $id, 'error', n4s_t( 'Es wurde keine Datei hochgeladen.', 'No file was uploaded.' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$rename = isset( $_POST['mode'] ) && 'rename' === $_POST['mode'];
	$old    = (string) get_attached_file( $id );
	$oldrel = (string) get_post_meta( $id, '_wp_attached_file', true );
	$paths  = n4s_tools_mr_paths( $id );
	$up     = wp_handle_upload( $_FILES['n4s_file'], array( 'test_form' => false ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! empty( $up['error'] ) ) {
		n4s_tools_mr_back( $id, 'error', $up['error'] );
	}
	$newfile = $up['file'];
	$newtype = $up['type'];
	if ( ! $rename && $newtype !== $p->post_mime_type ) {
		wp_delete_file( $newfile );
		n4s_tools_mr_back( $id, 'error', sprintf( n4s_t( 'Der Dateityp passt nicht (%1$s statt %2$s). Bitte „Ersetzen und neuen Dateinamen verwenden“ wählen.', 'The file type does not match (%1$s instead of %2$s). Please choose "Replace and use the new filename".' ), $newtype, $p->post_mime_type ) );
	}
	$dir = dirname( $old );
	foreach ( $paths as $rel => $name ) { // Alte Größen und Original entfernen; die Hauptdatei bleibt bis zum Überschreiben.
		$f = trailingslashit( wp_get_upload_dir()['basedir'] ) . $rel;
		if ( '' !== $name && file_exists( $f ) ) {
			wp_delete_file( $f );
		}
	}
	if ( ! $rename ) {
		if ( ! @rename( $newfile, $old ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.rename_rename
			wp_delete_file( $newfile );
			n4s_tools_mr_back( $id, 'error', n4s_t( 'Die Datei konnte nicht ersetzt werden (Schreibrechte?).', 'The file could not be replaced (permissions?).' ) );
		}
		delete_post_meta( $id, '_wp_attachment_backup_sizes' );
		$meta = wp_generate_attachment_metadata( $id, $old );
		wp_update_attachment_metadata( $id, $meta );
		wp_update_post( array( 'ID' => $id, 'post_mime_type' => $newtype ) );
		n4s_tools_mr_back( $id, 'success', n4s_t( 'Die Datei wurde ersetzt.', 'The file was replaced.' ) );
	}
	// Neuer Dateiname: neue Datei anhängen, Metadaten neu erzeugen, alte Dateien löschen, Verweise anpassen.
	$meta = wp_generate_attachment_metadata( $id, $newfile );
	update_attached_file( $id, $newfile );
	delete_post_meta( $id, '_wp_attachment_backup_sizes' );
	wp_update_attachment_metadata( $id, $meta );
	$newrel = (string) get_post_meta( $id, '_wp_attached_file', true );
	$ndir   = dirname( $newrel );
	$ndir   = '.' === $ndir ? '' : $ndir . '/';
	wp_update_post( array( 'ID' => $id, 'post_mime_type' => $newtype, 'guid' => wp_get_attachment_url( $id ) ) );
	$map = array( $oldrel => $newrel );
	foreach ( $paths as $rel => $name ) {
		if ( '' === $name ) {
			continue;
		}
		$map[ $rel ] = ( is_array( $meta ) && ! empty( $meta['sizes'][ $name ]['file'] ) ) ? $ndir . $meta['sizes'][ $name ]['file'] : $newrel;
	}
	// Längere (Größen-)Pfade zuerst, damit kein Teilpfad der Hauptdatei vorher greift.
	uksort( $map, function ( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	} );
	n4s_tools_mr_replace_urls( $map );
	if ( $old !== $newfile && file_exists( $old ) ) {
		wp_delete_file( $old );
	}
	n4s_tools_mr_back( $id, 'success', n4s_t( 'Die Datei wurde ersetzt, Verweise wurden angepasst.', 'The file was replaced, references were updated.' ) );
}

/* ------------------------------------------------------------------ Einhängen */

if ( n4s_feature_on( 'dup' ) ) {
	add_action( 'admin_post_n4s_tools_dup', 'n4s_tools_do_dup' );
	add_filter( 'page_row_actions', 'n4s_tools_dup_row_action', 10, 2 );
	add_action( 'admin_notices', 'n4s_tools_dup_notice' );
}

if ( n4s_feature_on( 'mediareplace' ) ) {
	add_action( 'admin_post_n4s_tools_mr', 'n4s_tools_do_mr' );
	add_filter( 'media_row_actions', 'n4s_tools_mr_row_action', 10, 2 );
	add_filter( 'attachment_fields_to_edit', 'n4s_tools_mr_field', 10, 2 );
	add_action(
		'admin_menu',
		function () {
			add_submenu_page( 'upload.php', n4s_t( 'Datei ersetzen', 'Replace file' ), '', 'upload_files', 'n4s-media-replace', 'n4s_tools_mr_page' );
			remove_submenu_page( 'upload.php', 'n4s-media-replace' );
		}
	);
}
