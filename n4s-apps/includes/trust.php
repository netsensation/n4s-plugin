<?php
/**
 * Vertrauenswürdige öffentliche Ed25519-Schlüssel für App-Updates aus dem Katalog (Base64, 32 Byte).
 * Nur Dateien, die mit dem passenden privaten Schlüssel signiert wurden, werden installiert.
 * Erzeugen: tools/n4s-keygen.html (Browser, ohne PHP) oder php tools/n4s-keygen.php. Den privaten Schlüssel nie hier eintragen.
 * Zusätzliche Schlüssel lassen sich auch per Konstante N4S_APPS_TRUSTED_KEYS in der wp-config.php setzen.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'9IhskpDPQHTsBIIR9J4LGEf+7NuLua43NKWPIHDnHKI=', // n4s-Signaturschlüssel (erzeugt am 09.11.2026)
);
