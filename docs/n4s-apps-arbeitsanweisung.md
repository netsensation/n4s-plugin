# Arbeitsanweisung: n4s Apps (WordPress-Plugin)

Stand: 09.11.2026 · Plugin-Version 2.8.2
Gilt für jede Installation, auf der n4s Apps läuft (bisher netsensation.de, elco-systemloesungen.de, weitere folgen). Diese Datei ist in beiden Projekten **identisch**. Seitenspezifisches steht nur in der Umgebungs-Datei des jeweiligen Projekts. Änderungen immer in beiden Projekten übernehmen.

## 1. Zweck
Rahmen-Plugin für eigene PHP-Apps. Jede App ist eine PHP-Datei, wird im Admin-Menü **n4s Apps** verwaltet und per Shortcode `[n4s_app name="..."]` auf einer Seite eingebunden.

## 2. Aufbau
```
wp-content/plugins/n4s-apps/        wird bei Plugin-Updates komplett ersetzt
├── n4s-apps.php                    Rahmen: Registry, Loader, Shortcode, Übersetzung n4s_t()
├── includes/admin.php              Verwaltungsbereich (Menü n4s, Tabs, Upload, Update, Löschen)
├── includes/security.php           Funktion Sicherheit (Anmeldeschutz, Härtung)
├── includes/appfiles.php           Prüfung und Dateikopf der Apps (immer geladen)
├── includes/catalog.php            App-Katalog: Updates, Installation, Signatur, Cron (immer geladen)
├── includes/trust.php              vertrauenswürdige öffentliche Signaturschlüssel
├── assets/icon.svg                 Icon in der WordPress-Aktualisierungsliste
├── includes/updater.php            Update über WordPress, Quelle GitHub-Releases
└── apps/hello.php                  mitgelieferte Beispiel-App

wp-content/n4s-apps-data/apps/      EIGENE Apps, überstehen Plugin-Updates
```
- `n4s_register_app( $slug, $render, $login = false )` registriert eine App. `$login = true` = nur für angemeldete Benutzer.
- Der Loader lädt erst mitgelieferte, dann eigene Apps. Eine Datei, die beim Laden einen schweren Fehler auslöst, wird automatisch deaktiviert.
- Unbekannte oder deaktivierte Apps zeigt der Shortcode nur Administratoren als Hinweis an.
- REST-Namespace aller Apps: `n4s-apps/v1`
- `n4s_t( 'Deutsch', 'English' )` liefert den Text passend zur Sprache (Benutzer im Admin, Website im Frontend; Deutsch bei `de_*`, sonst Englisch).

## 3. Verwaltungsbereich (Menü „Plugin“ mit n4s-Logo)
- Menüeintrag „Plugin" mit n4s-Logo als Icon, Unterpunkte **Funktionen**, **Apps**, **Sicherheit**. Im Plugin sind dieselben drei Bereiche als Tabs dargestellt. Menü und alle Meldungen sind zweisprachig (DE/EN).
- **Tab Funktionen (ab 2.7.0):** Apps und Sicherheit einzeln ein-/ausschalten (Option `n4s_features`). Ausgeschaltet verschwinden Tab und Menüpunkt und die Funktion ist inaktiv (Apps: keine App geladen, Shortcodes leer; Sicherheit: Schutz aus, Einstellungen und Protokoll bleiben). Standard: Apps an; Sicherheit an, wenn die frühere Security-App schon lief, sonst aus. Hier steht auch das Plugin-Update.
- **Tab Sicherheit:** Anmeldeschutz (Sperren nach Fehlversuchen, Protokoll, Blacklist, IP-Quelle), Härtung (Benutzernamen verbergen, neutrale Login-Meldung, XML-RPC, Sicherheits-Header, Version verbergen), Option „Beiträge deaktivieren", Dashboard-Widget. Die Mail bei vielen abgelehnten Anmeldungen enthält eine Zusammenfassung der gesperrten IPs und der häufigsten Anmeldenamen der letzten 24 Stunden. Die frühere App `security.php` wird nicht mehr geladen (Optionen `n4s_lla_settings`/`n4s_lla_state` bleiben, nichts geht verloren); die Datei kann im Tab Apps gelöscht werden.
- Liste aller Apps: Name, Shortcode, Version, Datei, Quelle, Zugriff, Status, Änderungsdatum
- **Neu:** App-Datei hochladen (Name: Kleinbuchstaben, Ziffern, `-`, `_`, Endung `.php`; `index.php` ist reserviert). Ein Versionssuffix im Dateinamen (`_V2_1_2`) entfernt der Upload, installiert wird unter `slug.php`. Ein App-Name (Slug), der schon in einer anderen Datei registriert ist, wird abgelehnt.
- **Update:** Datei einer eigenen App ersetzen. Zuerst Abgleich installiert ↔ Update, dann Bestätigungsseite; erst danach wird geschrieben, die vorherige Version bleibt als `.bak`.
  - Anderer App-Name (Slug) im Update: Abbruch, nichts wird verändert.
  - Warnung (Häkchen nötig): abweichender Anzeigename oder Dateiname, gleiche oder ältere Version, Update ohne Version bei versionierter App.
  - Hinweis (kein Häkchen): ältere App ohne Version, dann wird nur der Name verglichen.
- **Update-Buttons:** Der Button „Update" einer App erscheint erst, wenn eine Datei ausgewählt wurde (gilt auch für „App hochladen" und das Plugin-ZIP). Gibt es ein Update aus dem Katalog, steht bei genau dieser App „Update verfügbar: x.y.z" mit Button „Aktualisieren".
- **App-Katalog (ab 2.8.0):** Abschnitt im Tab Apps. Apps mit Dateikopf `Update URI: https://n4s.one` werden alle 12 Stunden geprüft („Updates jetzt prüfen" sofort). Je App lässt sich „Automatisch" einschalten (Standard aus). „Katalog laden" zeigt die Apps der Quelle, „Installieren" holt eine neue App. Quelle (Standard https://n4s.one) und Zugangsschlüssel für private Apps stehen im selben Abschnitt. Installiert wird nur mit gültiger Signatur; ist in `includes/trust.php` kein Schlüssel hinterlegt, sind Katalog-Updates und -Installationen aus. Protokoll: `docs/n4s-katalog-protokoll.md`.
- **Tab-Erweiterung:** Apps können per Filter `n4s_apps_tabs` einen eigenen Tab und Menüpunkt einhängen (Schlüssel, `page`, `label`, `render`).
- **Deaktivieren/Aktivieren:** für alle Apps, auch mitgelieferte
- **Löschen:** erst möglich, wenn die App deaktiviert ist (Button ausgegraut, serverseitig erzwungen). Gilt auch für mitgelieferte Apps; die kommen nach einem Plugin-Update deaktiviert zurück.
- **Plugin-Update über WordPress (ab 2.6.0):** Neue Versionen erscheinen wie bei jedem Plugin unter Plugins und Dashboard → Aktualisierungen (auch automatische Updates). Quelle: neueste Release im GitHub-Repository `netsensation/n4s-plugin` mit der Datei `n4s-apps-plugin.zip`. Im Tab Funktionen stehen die neueste Version, „Jetzt prüfen" und bei neuer Version der Button „Jetzt aktualisieren" (startet das normale WordPress-Update). Das Repository ist öffentlich, ein Token ist nicht nötig (ein Eingabefeld ist bewusst nicht vorhanden; kommt ggf. in n4s-plugin-admin). Das ZIP wird vor der Installation wie beim manuellen Update geprüft.
- **Plugin-Update manuell:** neues `n4s-apps-plugin.zip` hochladen, das Plugin ersetzt sich selbst und bleibt aktiv (Datei `n4s-apps-plugin.zip`; im ZIP nur der Ordner `n4s-apps/`, höchstens 5 MB). Es wird nur eine **höhere** Version installiert; gleiche oder ältere werden abgelehnt (Rollback nur über Plugins → Installieren).
- Prüfung vor dem Speichern, ohne Ausführung: PHP-Syntax, `n4s_register_app` mit festem App-Namen als Zeichenkette, `defined( 'ABSPATH' )`-Schutz vorhanden, höchstens 1024 KB
- Hochladen, Ersetzen, Löschen und Plugin-Update brauchen das Recht „Plugins installieren"; bei `DISALLOW_FILE_MODS` sind sie aus.
- Erscheint „Sicherheitsprüfung abgelaufen", Seite neu laden und Aktion wiederholen.

## 3a. Versionen und Versionsanzeige
Zwei getrennte Versionen, beide im Schema `Version: 1.2.0 by n4s` (kein Datum; „n4s" unsichtbar auf https://n4s.one verlinkt; klein und dezent):

- **Plugin-Version** (`N4S_APPS_VERSION`): oben hinter dem n4s-Logo auf allen Tabs im Admin-Bereich (ab 2.8.2, vorher unten rechts auf der Seite „Apps").
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
- Apps, die über einen Katalog verteilt werden, tragen zusätzlich `Update URI: https://n4s.one` im Dateikopf (Opt-in; ohne diese Zeile gibt es keine Verbindung) und werden vor der Veröffentlichung mit `tools/n4s-sign.php` signiert.
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
- Kann nicht: Plugins installieren, aktivieren oder deaktivieren, PHP- oder Server-Dateien schreiben, Apps hochladen, n4s.one direkt aufrufen (die Sandbox erreicht die Domain nicht; Rendern nur über die MCP-Prüfung der Seite).
- Kann zusätzlich über GitHub: Branches, Pull Requests und Merge auf Zuruf in `netsensation/n4s-plugin` und `netsensation/n4s-plugin-admin`, Releases und Workflow-Läufe ansehen.
- Die Seitenvorlage (`_wp_page_template`) ist per MCP geschützt und nicht änderbar. Die Vorlage „Hero" hat keinen Header und Footer. Neue Seiten deshalb per `wp_clone_post_as_draft` von einer Seite mit Standardvorlage anlegen und danach Titel und Inhalt ersetzen.
- Theme-Schreibrechte (Custom CSS) sind in Royal MCP gesperrt, solange „Allow AI to modify theme appearance" aus ist; CSS dann als Style-Block in die Seite legen.
- Solange ein Wartungsmodus aktiv ist, kann Claude Seiten nicht rendern und prüfen.

## 8a. Apps über den Katalog verteilen (erprobter Ablauf)
Getestet am 09.11.2026 mit der Test-App `n4s-test` (1.0.0 → 1.0.1), bis zum Update-Button auf netsensation.de. Betrieb der Admin-App: `docs/n4s-admin.md` im Repository n4s-plugin-admin, Protokoll: `docs/n4s-katalog-protokoll.md`.

**Einmalig eingerichtet:** Schlüsselpaar mit `tools/n4s-keygen.html` (Browser, ohne PHP), öffentlicher Schlüssel in `n4s-apps/includes/trust.php`, privater nur als GitHub-Secret `N4S_SIGNING_KEY` in n4s-plugin-admin; Plugin ab 2.8.1 und Admin-App `n4s-admin_V1_0_0.php` (manuell hochgeladen, Updates der Admin-App ebenfalls manuell) auf n4s.one; GitHub-Token (fine-grained, nur n4s-plugin-admin, „Contents: Read-only“, mit Ablaufdatum) im Tab Admin.

**Pro App-Version:**
1. App bauen (Abschnitt 4 und 5, Dateikopf mit `Update URI: https://n4s.one`), im Repository n4s-plugin-admin unter `apps/<slug>/app.php` und `app.json` ablegen, Version erhöhen, Pull Request. Bei Pull Requests läuft nur die Syntaxprüfung, der Release-Job wird übersprungen.
2. Nach dem Merge auf `main` signiert der Workflow (etwa 30 Sekunden) und legt die Release `app-<slug>-v<Version>` an. Eine geänderte Datei bei gleicher Version bricht den Workflow ab. Prüfen: Releases im Repository ansehen.
3. n4s.one: Tab Admin → „Jetzt abgleichen“ (sonst stündlich) → Version „Freigeben“. Neue Apps sind zuerst privat, „Öffentlich schalten“ ist ein bewusster Klick.
4. Seiten mit dem n4s Plugin: Tab Apps → „Updates jetzt prüfen“ (sonst alle 12 Stunden) → „Aktualisieren“, oder „Automatisch“ je App. Neue Apps: „Katalog laden“ → „Installieren“; private nur mit Zugangsschlüssel.

**Fehlerbilder:**
| Meldung oder Symptom | Ursache und Abhilfe |
|---|---|
| Abgleich: „GitHub HTTP 401/404“ | Token fehlt, abgelaufen oder falsches Repository. Neues Token im Tab Admin eintragen. |
| „Signatur stimmt nicht mit einem vertrauenswürdigen Schlüssel überein“ | Secret `N4S_SIGNING_KEY` und öffentlicher Schlüssel in `trust.php` gehören nicht zusammen, oder die Datei wurde nach dem Signieren verändert. |
| „Kein Signaturschlüssel hinterlegt“ | `trust.php` ist leer oder das Plugin zu alt (unter 2.8.0). |
| Workflow: „Version nicht erhöht“ | Datei geändert, Version im Dateikopf gleich geblieben. Version erhöhen. |
| Seite: „Zugriff verweigert“ | Zugangsschlüssel falsch oder widerrufen, oder die App ist privat und es ist keiner eingetragen. |
| Kein Update-Button | „Updates jetzt prüfen“ klicken (Cache); App ohne `Update URI` im Dateikopf; Version nicht freigegeben; nur manuell hochgeladene (unsignierte) Versionen werden nicht verteilt. |
| Schlüssel kommt nicht an | Manche Server entfernen den Header `Authorization`. Seit 2.8.1 sendet das Plugin zusätzlich `X-N4S-Key`. |

## 9. Arbeitsweise
- Antworten kurz, Rückfragen nur als Text im Chat, Umsetzungsdetails selbst entscheiden.
- Auf Firmenseiten nur auf ausdrückliche Anweisung ändern, Neues als Entwurf.