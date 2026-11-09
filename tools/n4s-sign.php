<?php
/**
 * Signiert eine n4s-App-Datei (Ed25519) und gibt die Katalog-Angaben als JSON aus.
 * Aufruf: N4S_SIGNING_KEY=<privater Schlüssel, Base64> php tools/n4s-sign.php pfad/zur/app.php
 *
 * Ausgabe: { "slug", "version", "name", "sha256", "size", "signature" }
 * Signiert wird die Nachricht "n4s-app-v1\n<slug>\n<version>\n<sha256>" (siehe docs/n4s-katalog-protokoll.md).
 * Der Schlüssel wird nur aus der Umgebungsvariable gelesen und nie ausgegeben.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
	fwrite( STDERR, "libsodium fehlt.\n" );
	exit( 1 );
}
$file = isset( $argv[1] ) ? $argv[1] : '';
$key  = base64_decode( (string) getenv( 'N4S_SIGNING_KEY' ), true );
if ( '' === $file || ! is_readable( $file ) || false === $key || 64 !== strlen( $key ) ) {
	fwrite( STDERR, "Aufruf: N4S_SIGNING_KEY=<Base64> php tools/n4s-sign.php datei.php\n" );
	exit( 1 );
}
$code = (string) file_get_contents( $file );
if ( strlen( $code ) > 1048576 ) {
	fwrite( STDERR, "Datei größer als 1024 KB.\n" );
	exit( 1 );
}
$head = substr( $code, 0, 8192 );
preg_match_all( '/n4s_register_app\s*\(\s*([\'"])([A-Za-z0-9_-]+)\1/', $code, $m );
$slugs = array_values( array_unique( array_map( 'strtolower', $m[2] ) ) );
if ( 1 !== count( $slugs ) || ! preg_match( '/^[a-z0-9_-]{1,60}$/', $slugs[0] ) ) {
	fwrite( STDERR, "Die Datei muss genau einen App-Namen (Slug) in n4s_register_app( 'name', … ) enthalten.\n" );
	exit( 1 );
}
$version = preg_match( '/^[ \t\/*#@]*Version:[ \t]*([^\r\n]+)/mi', $head, $v ) ? trim( $v[1], " \t*/" ) : '';
$name    = preg_match( '/^[ \t\/*#@]*App Name:[ \t]*([^\r\n]+)/mi', $head, $n ) ? trim( $n[1], " \t*/" ) : $slugs[0];
if ( ! preg_match( '/^[0-9][0-9A-Za-z.+-]{0,30}$/', $version ) ) {
	fwrite( STDERR, "Im Dateikopf fehlt eine gültige Version (Version: 1.0.0).\n" );
	exit( 1 );
}
$sha = hash( 'sha256', $code );
$sig = sodium_crypto_sign_detached( "n4s-app-v1\n" . $slugs[0] . "\n" . $version . "\n" . $sha, $key );
echo json_encode(
	array(
		'slug'      => $slugs[0],
		'version'   => $version,
		'name'      => $name,
		'sha256'    => $sha,
		'size'      => strlen( $code ),
		'signature' => base64_encode( $sig ),
	),
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
) . "\n";
