<?php
/**
 * Vertrauenswürdige öffentliche Ed25519-Schlüssel für App-Updates aus dem Katalog (Base64, 32 Byte).
 * Nur Dateien, die mit dem passenden privaten Schlüssel signiert wurden, werden installiert.
 * Erzeugen: php tools/n4s-keygen.php (im Repository). Den privaten Schlüssel nie hier eintragen.
 * Zusätzliche Schlüssel lassen sich auch per Konstante N4S_APPS_TRUSTED_KEYS in der wp-config.php setzen.
 */

defined( 'ABSPATH' ) || exit;

return array(
	// 'BASE64_OEFFENTLICHER_SCHLUESSEL',
);
