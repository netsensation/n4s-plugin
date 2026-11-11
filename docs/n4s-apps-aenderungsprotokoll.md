# n4s Apps: Änderungsprotokoll

Die gepflegte Fassung liegt im Claude-Projekt (claude/n4s-apps-changelog.md). Diese Kopie: Stand 2.11.0.

| Datum | Version | Änderung |
|---|---|---|
| 13.10.2026 | 1.0.0 | Erstes Gerüst „NS Apps" mit Beispiel-App `hello` |
| 13.10.2026 | 2.0.0 | Umbenennung auf „n4s Apps" (Ordner `n4s-apps`, Shortcode `[n4s_app]`, Präfix `n4s_`) |
| 01.11.2026 | 2.1.0 | Frontend-App „Verwaltungen"; missverstanden, in 2.2.0 entfernt |
| 01.11.2026 | 2.2.0 | Verwaltungsbereich im Admin-Menü: App neu/Update/löschen/(de)aktivieren, Plugin-Selbstupdate per ZIP; eigene Apps in `wp-content/n4s-apps-data/apps/`; Syntaxprüfung; automatische Deaktivierung fehlerhafter Apps |
| 01.11.2026 | 2.3.0 | Fix `index.php` als App; Löschen nur nach Deaktivierung; Nonce-Hinweis; ABSPATH-Pflicht, ZIP-Prüfung, Größenlimits |
| 03.11.2026 | 2.4.0 | App-Update mit Abgleich und Bestätigungsseite; Dateikopf `App Name:`/`Version:`; zweisprachig DE/EN; neues Menü-Icon |
| 03.11.2026 | 2.4.1–2.4.4 | Icon/Titel: Schriftzug, Menü-Icon per CSS, Zentrierung, Hinweis für Apps ohne Version |
| 05.11.2026 | 2.5.0 | Versionsanzeige unten rechts auf der Seite „Apps" |
| 05.11.2026 | 2.5.1 | Anzeige „Version: x by n4s" ohne Datum; Shortcode hängt die App-Versionszeile automatisch an |
| 06.11.2026 | 2.5.2 | App-Limit 1024 KB; Auslieferung als `n4s-apps-plugin.zip` |
| 06.11.2026 | 2.5.3 | Versionssuffix im App-Dateinamen (`_V2_1_2`) wird beim Upload entfernt |
| 09.11.2026 | 2.6.0 | Update über den normalen WordPress-Update-Prozess, Quelle GitHub-Releases (`n4s-apps-plugin.zip`, Token optional); manuelles ZIP-Update bleibt; Release-Workflow auf GitHub |
| 09.11.2026 | 2.7.0 | Menü „n4s“ (Logo) mit Unterpunkten Funktionen, Apps, Sicherheit; Tabs im Plugin; neue Funktion Sicherheit (Anmeldeschutz, Härtung, aus der App `security`); Tab Funktionen schaltet Apps und Sicherheit ein/aus; Sicherheits-Mail mit Zusammenfassung gesperrter IPs und häufigster Anmeldenamen |
| 09.11.2026 | 2.7.1 | Update-Icon in der WordPress-Aktualisierungsliste (Datei `assets/icon.svg` statt data:-Adresse, die WordPress entfernt) |
| 09.11.2026 | 2.7.2 | Menüpunkt heißt „Plugin“ (Logo n4s als Icon), nicht mehr doppelt „n4s“ |
| 09.11.2026 | 2.7.3 | „Jetzt prüfen“ bleibt im Tab Funktionen; bei neuer Version Button „Jetzt aktualisieren“ (WordPress-Update direkt); Token-Eingabe entfernt (kommt in n4s-plugin-admin) |
| 09.11.2026 | 2.8.0 | App-Katalog (Client): Updates und Installation von Apps aus einem Katalog (Dateikopf `Update URI`), Signatur Ed25519 + SHA-256, Zugangsschlüssel für private Apps, automatische Updates je App (Cron), Tab-Erweiterungspunkt `n4s_apps_tabs`; Update-Buttons je App erst nach Dateiauswahl oder bei verfügbarem Update; Werkzeuge `tools/n4s-keygen.php`, `tools/n4s-sign.php`; Protokoll in `docs/n4s-katalog-protokoll.md` |
| 09.11.2026 | 2.8.1 | Katalog-Client sendet den Zugangsschlüssel zusätzlich als Header `X-N4S-Key` (Fallback bei Servern, die `Authorization` entfernen) |
| 09.11.2026 | 2.8.2 | Plugin-Version oben hinter dem Logo auf allen Tabs (statt fest unten rechts) |
| 09.11.2026 | 2.8.3 | Filter `n4s_apps_pre_request`: ein Katalog-Server im selben WordPress (Admin-App auf n4s.one) beantwortet Anfragen im Prozess, ohne HTTP-Aufruf an die eigene Adresse (Admin-App wird so selbst über den Katalog aktualisiert) |
| 10.11.2026 | 2.8.4 | Liste der Apps: Quelle „n4s“ für Apps mit Update URI auf n4s.one (Katalog) statt „eigene“ |
| 10.11.2026 | 2.9.0 | Sicherheit: Option „Admin-Meldungen ausblenden“ (Funktionen): entfernt die Meldungen von Plugins und Themes im Admin-Bereich für alle Benutzer; Meldungen von WordPress und n4s bleiben; Standard aus |
| 10.11.2026 | 2.10.0 | Neue Funktionen (Tab Funktionen, Standard aus): „Seite duplizieren“ (Zeilenaktion in der Seitenliste, Kopie als Entwurf mit Metafeldern und Taxonomien) und „Mediendatei ersetzen“ (Aktion in der Mediathek, gleicher oder neuer Dateiname mit Anpassung der Verweise) |
| 11.11.2026 | 2.10.1 | App-Katalog: Update-Prüfung und automatische Updates der Apps laufen stündlich statt zweimal täglich (bestehende Seiten werden beim nächsten Seitenaufruf umgestellt) |
| 11.11.2026 | 2.11.0 | Neue Funktion „Eigenes Login-Design“ (Tab Funktionen, Standard aus): WordPress-Anmeldeseite mit dem Logo der Website (Customizer, Divi, Elementor oder Website-Icon, sonst Name) und den Farben des aktiven Themes (theme.json, Divi, Elementor, Astra, GeneratePress, Customizer); Logo verlinkt auf die Startseite; Anmeldung, Passwort vergessen und Anmeldeschutz unverändert |
