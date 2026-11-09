# Arbeitsanweisung: n4s Apps (WordPress-Plugin)

Stand: 09.11.2026 · Plugin-Version 2.6.0
Gilt für jede Installation, auf der n4s Apps läuft (bisher netsensation.de, elco-systemloesungen.de, weitere folgen). Diese Datei ist in beiden Projekten **identisch**. Seitenspezifisches steht nur in der Umgebungs-Datei des jeweiligen Projekts. Änderungen immer in beiden Projekten übernehmen.

## 1. Zweck
Rahmen-Plugin für eigene PHP-Apps. Jede App ist eine PHP-Datei, wird im Admin-Menü **n4s Apps** verwaltet und per Shortcode `[n4s_app name="..."]` auf einer Seite eingebunden.

## 2. Aufbau
```
wp-content/plugins/n4s-apps/        wird bei Plugin-Updates komplett ersetzt
├── n4s-apps.php                    Rahmen: Registry, Loader, Shortcode, Übersetzung n4s_t()
├── includes/admin.php              Verwaltungsbereich (Menü, Upload, Update, Löschen)
├── includes/updater.php            Update über WordPress, Quelle GitHub-Releases
└── apps/hello.php                  mitgelieferte Beispiel-App

wp-content/n4s-apps-data/apps/      EIGENE Apps, überstehen Plugin-Updates
```
- `n4s_register_app( $slug, $render, $login = false )` registriert eine App. `$login = true` = nur für angemeldete Benutzer.
- Der Loader lädt erst mitgelieferte, dann eigene Apps. Eine Datei, die beim Laden einen schweren Fehler auslöst, wird automatisch deaktiviert.
- Unbekannte oder deaktivierte Apps zeigt der Shortcode nur Administratoren als Hinweis an.
- REST-Namespace aller Apps: `n4s-apps/v1`
- `n4s_t( 'Deutsch', 'English' )` liefert den Text passend zur Sprache (Benutzer im Admin, Website im Frontend; Deutsch bei `de_*`, sonst Englisch).

## 3. Verwaltungsbereich (Menü „n4s Apps")
- Menüeintrag „Apps" mit n4s-Logo; das Menü und alle Meldungen sind zweisprachig (DE/EN)
- Liste aller Apps: Name, Shortcode, Version, Datei, Quelle, Zugriff, Status, Änderungsdatum
- **Neu:** App-Datei hochladen (Name: Kleinbuchstaben, Ziffern, `-`, `_`, Endung `.php`; `index.php` ist reserviert). Ein Versionssuffix im Dateinamen (`_V2_1_2`) entfernt der Upload, installiert wird unter `slug.php`. Ein App-Name (Slug), der schon in einer anderen Datei registriert ist, wird abgelehnt.
- **Update:** Datei einer eigenen App ersetzen. Zuerst Abgleich installiert ↔ Update, dann Bestätigungsseite; erst danach wird geschrieben, die vorherige Version bleibt als `.bak`.
  - Anderer App-Name (Slug) im Update: Abbruch, nichts wird verändert.
  - Warnung (Häkchen nötig): abweichender Anzeigename oder Dateiname, gleiche oder ältere Version, Update ohne Version bei versionierter App.
  - Hinweis (kein Häkchen): ältere App ohne Version, dann wird nur der Name verglichen.
- **Deaktivieren/Aktivieren:** für alle Apps, auch mitgelieferte
- **Löschen:** erst möglich, wenn die App deaktiviert ist (Button ausgegraut, serverseitig erzwungen). Gilt auch für mitgelieferte Apps; die kommen nach einem Plugin-Update deaktiviert zurück.
- **Plugin-Update über WordPress (ab 2.6.0):** Neue Versionen erscheinen wie bei jedem Plugin unter Plugins und Dashboard → Aktualisierungen (auch automatische Updates). Quelle: neueste Release im GitHub-Repository `netsensation/n4s-plugin` mit der Datei `n4s-apps-plugin.zip`. Auf der Seite „Apps" stehen die neueste Version und „Jetzt prüfen". Das Repository ist privat: GitHub-Token (fein granular, nur dieses Repository, Leserecht „Contents") in `wp-config.php` als `define( 'N4S_APPS_GITHUB_TOKEN', '...' );` oder im Menü „Apps" hinterlegen. Das ZIP wird vor der Installation wie beim manuellen Update geprüft.
- **Plugin-Update manuell:** neues `n4s-apps-plugin.zip` hochladen, das Plugin ersetzt sich selbst und bleibt aktiv (Datei `n4s-apps-plugin.zip`; im ZIP nur der Ordner `n4s-apps/`, höchstens 5 MB). Es wird nur eine **höhere** Version installiert; gleiche oder ältere werden abgelehnt (Rollback nur über Plugins → Installieren).
- Prüfung vor dem Speichern, ohne Ausführung: PHP-Syntax, `n4s_register_app` mit festem App-Namen als Zeichenkette, `defined( 'ABSPATH' )`-Schutz vorhanden, höchstens 1024 KB
- Hochladen, Ersetzen, Löschen und Plugin-Update brauchen das Recht „Plugins installieren"; bei `DISALLOW_FILE_MODS` sind sie aus.
- Erscheint „Sicherheitsprüfung abgelaufen", Seite neu laden und Aktion wiederholen.

## 3a. Versionen und Versionsanzeige
Zwei getrennte Versionen, beide im Schema `Version: 1.2.0 by n4s` (kein Datum; „n4s" unsichtbar auf https://n4s.one verlinkt; klein und dezent):

- **Plugin-Version** (`N4S_APPS_VERSION`): fest unten rechts auf der Seite „Apps" im Admin-Bereich.
- **App-Version** (Dateikopf `Version:` der App): rechtsbündig als kleine Zeile unter jeder App. Der Shortcode hängt sie automatisch an (ab 2.5.1); Apps bauen sie nicht selbst ein. Ohne Version im Dateikopf keine Anzeige.
- Dateiname gelieferter Apps immer mit Version: `slug_V<Major>_<Minor>_<Patch>.php`, z. B. `waermepumpen_V2_1_2.php` für 2.1.2 (muss zum Dateikopf passen).

## 4. Neue App bauen (Ablauf)
1. Anforderungen klären: Zweck, wer darf sie nutzen, welche Daten.
2. Claude schreibt **eine einzelne App-Datei** (Vorlage unten), prüft sie mit `php -l` und liefert sie als Datei.
3. Benutzer lädt sie unter n4s Apps → „Neue App hinzufügen" hoch.
4. Claude legt per MCP eine **Entwurfsseite** mit `[n4s_app name="<slug>"]` an.
5. Claude prüft das Rendering per MCP. Ist ein Wartungsmodus aktiv, scheitert das; dann prüft der Benutzer die Vorschau als angemeldeter Admin.
6. Nach Freigabe veröffentlichen und ggf. ins Menü einhängen.

Änderung an einer vorhandenen App: neue Datei mit **gleichem App-Namen und höherer Version** über „Update" in der Liste hochladen und auf der Bestätigungsseite prüfen. Dateiname und Version beim Bauen immer mitliefern und die Version bei jeder Änderung erhöhen.

Vorlage:
```php
<?php
/**
 * App Name: Meine App
 * Version: 1.0.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'n4s-apps/v1', '/meine-app', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
		'callback'            => function () { return array( 'ok' => true ); },
	) );
} );

n4s_register_app( 'meine-app', function ( $atts ) {
	return '<div class="n4s-meine-app">' . esc_html( n4s_t( 'Hallo', 'Hello' ) ) . '</div>';
}, true );
```
Für JavaScript mit REST-Zugriff: Nonce per `wp_create_nonce( 'wp_rest' )` ins HTML schreiben und als Header `X-WP-Nonce` senden.

## 5. Regeln für Apps
- **Schutz vor Reverse-Engineering:** Berechnungen, Bewertungslogik, Schwellenwerte und Entscheidungsregeln nur serverseitig in PHP über einen REST-Endpunkt (`n4s-apps/v1`). Das Browser-JavaScript schickt nur Eingaben und zeigt das Ergebnis an; keine Formeln, Konstanten oder Zwischenwerte in JavaScript, `data-`-Attributen oder Inline-Skripten. Der Endpunkt prüft Rechte und Nonce und liefert nur das Ergebnis. Bei öffentlichen Apps Massenabfragen bremsen (Rate-Begrenzung per Transient je IP).
- Jede App hat im Dateikopf `App Name:` und `Version:` (Schema `1.0.0`, bei jeder Änderung erhöhen); der App-Name (Slug) in `n4s_register_app()` steht als feste Zeichenkette und ändert sich nie, sonst greift der Update-Abgleich nicht.
- Alle sichtbaren Texte zweisprachig (Deutsch, Englisch) über `n4s_t()`; JavaScript-Texte als `data-`-Attribute übergeben.
- Datei beginnt nach dem Kopf mit `defined( 'ABSPATH' ) || exit;` (wird beim Upload geprüft) und kommt ohne weitere Dateien aus: CSS/JS inline per `wp_add_inline_*`.
- Funktionen, Klassen und Hooks mit Präfix `n4s_` bzw. `N4S_`; Dateiname = App-Slug; eine App pro Datei, klein halten.
- Eingaben mit `sanitize_*` bereinigen, Ausgaben mit `esc_*` maskieren, DOM-Ausgabe in JS über `textContent`.
- Schreibende REST-Endpunkte nie mit `__return_true`: Rechte prüfen und Nonce verwenden.
- Eigene Datenbanktabellen nur über `dbDelta` beim ersten Aufruf anlegen, Tabellenname mit `$wpdb->prefix`.
- Keine externen Schriften oder Skripte laden (DSGVO), keine seitenspezifischen IDs hart codieren.
- Vor größeren Änderungen ein Backup auslösen (UpdraftPlus).
- Autoptimize kann App-Skripte zusammenfassen oder verschieben: beim ersten Test prüfen.

## 6. Alte Apps und Kompatibilität
- Die Schnittstelle (`n4s_register_app`, Shortcode `[n4s_app]`, Namespace `n4s-apps/v1`) ist seit 2.0.0 unverändert. Apps ab 2.0.x laufen ohne Anpassung, sofern sie den `ABSPATH`-Schutz enthalten (nötig für neue Uploads und Updates über das Menü).
- Ein Plugin-Update ab 2.4.0 macht **kein** App-Update nötig. Apps ohne Dateikopf funktionieren weiter, ihr Update zeigt nur einen Hinweis statt Versionsabgleich. Beim nächsten Update der App `App Name:`, `Version:` und `n4s_t()` ergänzen.
- Mitgelieferte App `hello` ist ab 2.4.0 versioniert (1.1.0) und zweisprachig.
- Mitgelieferte App `hello`: Beispiel mit öffentlichem REST-Endpunkt. Auf Produktivseiten im Menü deaktivieren und löschen.
- Reste des früheren Namens „ns" (`[ns_app]`, `ns_register_app`) gibt es nicht mehr.
- Wer Version 2.1.0 installiert hatte, hat noch die leere Tabelle `n4s_verwaltungen` und die Option `n4s_verw_db`; unschädlich, kann entfernt werden.

## 7. Updates des Plugins
1. Version erhöhen (zwei Stellen in `n4s-apps.php`), Änderungsprotokoll ergänzen, auf `main` bringen. Der GitHub-Workflow „Release" baut `n4s-apps-plugin.zip` und legt die Release `v<Version>` an (Notiz aus dem Änderungsprotokoll).
2. Die Seiten zeigen das Update innerhalb von 12 Stunden (WordPress-Prüfung, Release-Cache 6 Stunden) oder sofort nach „Jetzt prüfen" im Menü Apps; installiert wird über Plugins bzw. Dashboard → Aktualisierungen.
3. Alternativ weiterhin manuell: `n4s-apps-plugin.zip` (Ordner im ZIP bleibt `n4s-apps`, Version höher als die installierte) im Menü n4s Apps → „Manuell per ZIP" hochladen (oder über Plugins → Installieren → Hochladen).
4. Eigene Apps bleiben erhalten, mitgelieferte Apps werden mit dem Plugin ersetzt.
- Erstinstallation und Wechsel von Versionen unter 2.2.0 gehen nur über Plugins → Installieren → Hochladen.

## 8. Was Claude per MCP kann und nicht kann
- Kann: Seiten und Beiträge anlegen und ändern, Menüs, Medien, freigegebene Optionen, Vorschau-Links, Rendering prüfen, Seiten in den Papierkorb legen.
- Kann nicht: Plugins installieren, aktivieren oder deaktivieren, PHP- oder Server-Dateien schreiben, Apps hochladen.
- Theme-Schreibrechte (Custom CSS) sind in Royal MCP gesperrt, solange „Allow AI to modify theme appearance" aus ist; CSS dann als Style-Block in die Seite legen.
- Solange ein Wartungsmodus aktiv ist, kann Claude Seiten nicht rendern und prüfen.

## 9. Arbeitsweise
- Antworten kurz, Rückfragen nur als Text im Chat, Umsetzungsdetails selbst entscheiden.
- Auf Firmenseiten nur auf ausdrückliche Anweisung ändern, Neues als Entwurf.