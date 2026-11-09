<?php
/**
 * Erzeugt ein Ed25519-Schlüsselpaar für die Signatur von n4s-Apps (Katalog-Updates).
 * Aufruf: php tools/n4s-keygen.php
 *
 * - Öffentlicher Schlüssel: in n4s-apps/includes/trust.php eintragen (Plugin) und Plugin neu veröffentlichen.
 * - Privater Schlüssel: nur als geheimen Wert (z. B. GitHub-Secret N4S_SIGNING_KEY) speichern, nie committen,
 *   nie auf dem Katalog-Server ablegen.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
	fwrite( STDERR, "libsodium fehlt (PHP ab 7.2 bringt sie mit).\n" );
	exit( 1 );
}
$kp = sodium_crypto_sign_keypair();
echo "Öffentlicher Schlüssel (in n4s-apps/includes/trust.php eintragen):\n" . base64_encode( sodium_crypto_sign_publickey( $kp ) ) . "\n\n";
echo "Privater Schlüssel (nur als geheimes Secret speichern, niemals committen):\n" . base64_encode( sodium_crypto_sign_secretkey( $kp ) ) . "\n";
