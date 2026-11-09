---
name: "n4s-apps-plugin"
description: "n4s Apps (WordPress-Plugin) bauen, ändern, ausliefern: App-Dateien, Plugin-ZIP, Versionierung, Update-Abgleich, DE/EN, Doku. Bei jeder Arbeit an n4s Apps oder neuen n4s-Apps nutzen."
---

# n4s Apps: Plugin und Apps bauen

Gilt für jede WordPress-Installation, auf der n4s Apps läuft (bisher netsensation.de und elco-systemloesungen.de, weitere folgen). Das Plugin und die Apps sind immer dieselben, nichts seitenspezifisch hart codieren. Seitenspezifisches steht in der Umgebungs-Datei des jeweiligen Projekts. Firmen- und fremde Seiten nur auf ausdrückliche Anweisung ändern, Neues als Entwurf. Marke und Link: n4s, https://n4s.one.

## Arbeitsweise
- Antworten kurz. Rückfragen nur als Text im Chat (keine Auswahlkarten). Umsetzungsdetails selbst entscheiden. Keine Unteragenten-Läufe. Tokenverbrauch nur auf Nachfrage, nur als Zahl.
- Zuerst lesen: Arbeitsanweisung (Google-Doc n4s Apps Arbeitsanweisung, im Projekt verknüpft) und claude/n4s-apps-changelog.md.
- Per MCP geht: Seiten, Menüs, Medien, Vorschau-Links, Rendering prüfen. Nicht möglich: Plugins installieren, Serverdateien schreiben. Der Benutzer lädt App-Dateien und Plugin-ZIPs im Admin-Menü Apps hoch. Bei aktivem Wartungsmodus ist kein Rendering-Check per MCP möglich.

## Plugin-Aufbau (Ordner n4s-apps)
- n4s-apps.php: Header (Plugin Name: n4s Apps, Version), Konstante N4S_APPS_VERSION, n4s_register_app( $slug, $render, $login ), n4s_t( $de, $en ), n4s_apps_byline( $version ), Loader (fatale Fehler deaktivieren die Datei automatisch), Shortcode [n4s_app name=...].
- includes/admin.php: Menü Apps, Liste, Upload, Update mit Abgleich und Bestätigungsseite, Löschen nur nach Deaktivierung, Plugin-Selbstupdate per ZIP, Update-Quelle GitHub (Jetzt prüfen, Token), Versionsanzeige des Plugins.
- includes/security.php (ab 2.7.0): Funktion Sicherheit (Anmeldeschutz, Härtung), Tab/Menü „Sicherheit“; Menü n4s hat Tabs Funktionen/Apps/Sicherheit, Funktionen schaltbar über n4s_features(). Die App-Datei security.php wird nicht mehr geladen.
- includes/updater.php (ab 2.6.0, immer geladen): Update über WordPress aus GitHub-Releases (Update URI, update_plugins_github.com, plugins_api, upgrader_pre_download mit ZIP-Prüfung). Repository öffentlich, kein Token nötig; optional per N4S_APPS_GITHUB_TOKEN oder Option.
- apps/hello.php: Beispiel-App mit öffentlichem REST-Endpunkt (auf Produktivseiten deaktivieren und löschen).
- Eigene Apps liegen in wp-content/n4s-apps-data/apps/ und überstehen Plugin-Updates. REST-Namespace: n4s-apps/v1.

## Versionen und Versionsanzeige
Es gibt zwei getrennte Versionen, beide im selben Schema: Version: 1.2.0 by n4s. Kein Datum. n4s ist unsichtbar verlinkt auf https://n4s.one (ohne Unterstreichung, Farbe vererbt). Klein und dezent (ca. 11 px, Deckkraft 0,6). Die Zeile erzeugt n4s_apps_byline( $version ).
- Plugin-Version (N4S_APPS_VERSION): fest unten rechts auf der Seite Apps im Admin-Bereich.
- App-Version (Dateikopf Version der jeweiligen App): rechtsbündig als kleine Zeile unter der Ausgabe jeder App. Der Plugin-Shortcode hängt sie ab Plugin 2.5.1 automatisch an. Apps bauen sie nicht selbst ein (keine Doppelung; liefert eine App die Klasse n4s-app-ver selbst, wird nichts angehängt). Apps ohne Version im Dateikopf zeigen nichts, deshalb hat jede neu gebaute App einen Dateikopf mit Version.
- Beim Bauen einer App immer prüfen: Dateikopf mit App Name und Version vorhanden, Version bei jeder Änderung erhöht.

## App bauen
1. Zweck, Zugriff (öffentlich oder nur angemeldet) und Daten klären.
2. Eine einzelne Datei: Dateikopf mit App Name und Version (1.0.0), danach defined( 'ABSPATH' ) || exit; dann n4s_register_app( 'slug', ... ) mit festem Slug als Zeichenkette (der Slug enthält keine Version). Maximale Dateigröße: 1024 KB (ab Plugin 2.5.2; ältere Versionen lehnen Dateien über 512 KB mit Die Datei ist zu groß ab). Vor der Lieferung die Dateigröße prüfen; wächst eine App darüber, den Code schlank halten (keine großen eingebetteten Daten oder Bilder) oder die App aufteilen.
   - Dateiname der gelieferten App enthält immer die Versionsnummer: slug_V<Major>_<Minor>_<Patch>.php, Punkte der Version werden zu Unterstrichen. Beispiel: waermepumpen_V2_1_2.php für Version 2.1.2. Die Version im Dateinamen muss mit Version im Dateikopf übereinstimmen und steigt bei jeder Änderung. Slug im Dateinamen: Kleinbuchstaben, Ziffern, - und _, nicht index.php.
   - Ab Plugin 2.5.3 entfernt der Upload das Versionssuffix (_V2_1_2) und installiert die App unter slug.php (z. B. waermepumpen.php). So bleibt der installierte Name stabil, ein Update zeigt keine Dateinamen-Warnung und die (De)aktivierung bleibt erhalten. In älteren Plugin-Versionen gilt: Update speichert unter dem installierten Namen mit einer Dateinamen-Warnung, eine neue App würde mit Suffix gespeichert.
3. Alle sichtbaren Texte zweisprachig über n4s_t( 'Deutsch', 'English' ); JavaScript-Texte als data-Attribute. Präfix n4s_ für Funktionen.
4. Sicherheit: Eingaben mit sanitize_*, Ausgaben mit esc_*, schreibende REST-Endpunkte nie mit __return_true (current_user_can plus Nonce wp_rest). Keine externen Schriften oder Skripte (DSGVO). CSS und JS inline.
5. Schutz vor Reverse-Engineering: Berechnungen, Bewertungslogik, Schwellenwerte, Preis- oder Entscheidungsregeln immer serverseitig in PHP über einen REST-Endpunkt (n4s-apps/v1) ausführen. Der Browser-JavaScript schickt nur die Eingaben und zeigt das Ergebnis an (Ausgabe per textContent). Nichts davon im JavaScript, in data-Attributen oder in Inline-Skripten offenlegen, auch keine Zwischenwerte, Formeln oder Konstanten. Der Endpunkt prüft Rechte und Nonce, bereinigt Eingaben und liefert nur das Ergebnis zurück, keine Details der Berechnung. Bei öffentlichen Apps zusätzlich missbräuchliche Massenabfragen bremsen (z. B. einfache Rate-Begrenzung per Transient je IP).
6. Mit php -l prüfen, als Datei mit Versionsnummer im Namen liefern. Danach per MCP Entwurfsseite mit dem Shortcode anlegen und Rendering prüfen (die Versionszeile unter der App muss erscheinen).
7. Update einer vorhandenen App: gleicher Slug, höhere Version. Der Upload prüft Slug, Anzeigename, Dateiname und Version und zeigt eine Bestätigungsseite (anderer Slug bricht ab, gleiche oder ältere Version nur mit Häkchen). Ältere Apps ohne Dateikopf funktionieren weiter und zeigen nur einen Hinweis; sie bekommen die Versionszeile beim nächsten Update mit Dateikopf.

## Release des Plugins (jedes Mal)
1. Version in n4s-apps.php an zwei Stellen erhöhen (Header und N4S_APPS_VERSION). Das Plugin-ZIP wird nur bei höherer Version installiert.
2. Beide Versionsanzeigen prüfen (Admin-Seite und App-Zeile).
3. php -l für alle Dateien, bei Logik-Änderungen kleine Stub-Tests (Vergleich, Slugs, n4s_t, Versions-Auslesen). Optisches (Icon, Titel) per Playwright rendern. Bei Ersetzungen mit sed die Zahlen genau prüfen (524288 kommt auch in 5242880 vor).
4. ZIP bauen und ausliefern: Die Datei heißt immer n4s-apps-plugin.zip (nicht n4s-apps.zip). Der Ordner im ZIP bleibt n4s-apps (Pflicht für den Selbstupdate-Check und den Plugin-Ordner): zip -rq n4s-apps-plugin.zip n4s-apps -x '*/.*' (nur Ordner n4s-apps/, höchstens 5 MB), dann mit SendUserFile liefern. Auch in Texten an den Benutzer die Datei n4s-apps-plugin.zip nennen.
5. Nach dem Merge auf main legt der Workflow Release die GitHub-Release v<Version> mit n4s-apps-plugin.zip an; die Seiten bieten das Update dann über WordPress an. Notiz der Release = Zeile der Version im Änderungsprotokoll (docs/n4s-apps-aenderungsprotokoll.md), also dort vor dem Merge eintragen.
6. Änderungsprotokoll im Projekt ergänzen (Datum, Version, Änderung). Bei Regeländerungen die Arbeitsanweisung aktualisieren. Das Google-Doc lässt sich über den Drive-Connector nicht überschreiben: neues Doc anlegen (HTML-Upload) und Benutzer die Quelle umstellen lassen. Das Feld Anweisungen im Projekt kann Claude nicht ändern: Text liefern.

## Erkenntnisse (Fehler vermeiden)
- Menü-Icon: Ein SVG als Data-URI wird vom WordPress-Skript nachträglich einfarbig umgefärbt. Bei Farbe das Icon per CSS als Hintergrund von div.wp-menu-image setzen (center, 22px) und das :before ausblenden. Alternative: PNG 20x20 und 40x40, oder einfarbiges weißes SVG (viewBox 0 0 20 20, 20x20 px). Ob es in WordPress passt, ist ohne Screenshot des Benutzers nicht prüfbar.
- Nonce-Fehler beim ersten Upload gab es einmal; eigene Nonce-Prüfung mit freundlichem Hinweis statt WordPress-Fehlerseite verwenden.
- Der Verzeichnisschutz index.php darf nicht als App erscheinen und ist als Name reserviert.
- Uploads nur statisch prüfen (Größe bis 1024 KB, php-Tag, n4s_register_app, ABSPATH-Schutz, token_get_all mit TOKEN_PARSE), nie ausführen. Upload-Limit des Servers (upload_max_filesize) liegt über der Dateigrenze, Plugin-ZIP höchstens 5 MB.
- Löschen nur für deaktivierte Apps (Button ausgegraut, serverseitig erzwungen) mit Browser-Bestätigung; mitgelieferte Apps kommen nach einem Plugin-Update deaktiviert zurück.
- Custom CSS per MCP ist gesperrt, solange Allow AI to modify theme appearance aus ist; CSS dann als Style-Block in die Seite legen.
- n4s-Schriftzug als SVG: hell mit Buchstaben #0C0E10, dunkel mit #fff, die 4 immer #00CCFF.