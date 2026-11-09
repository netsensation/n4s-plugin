# n4s Apps: Änderungsprotokoll

Die gepflegte Fassung liegt im Claude-Projekt (claude/n4s-apps-changelog.md). Diese Kopie: Stand 2.7.1.

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
