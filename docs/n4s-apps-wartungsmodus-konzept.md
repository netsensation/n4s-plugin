# n4s Apps: Wartungsmodus (Konzept)

Stand: 27.11.2026. Gebaut in n4s Apps 2.40.0 (siehe Abschnitt 10); Abschnitte 1 bis 9 sind das ursprüngliche Konzept. Anlehnung: WP Maintenance (wpmaintenancemode.com). Gelesen wurden dessen Startseite und die Doku zu Zugriff und erweiterten Einstellungen; Zeitplan, HTTP-Status und Countdown standen dort nicht, das Konzept dazu ist eigene Planung.

## 1. Ziel

Ein Schalter in der Admin-Leiste (oben im WordPress-Admin und auf der Website) schaltet den Wartungsmodus ein und aus. Im Wartungsmodus sehen Besucher eine einzelne Seite im Theme der Startseite. Die Seite wird wie jede andere im Seitenbaukasten der Seite gepflegt (Gutenberg, Divi, Elementor). Sie trägt den Zusatz „– Wartungsseite“. Berechtigte (zum Beispiel Administratoren) sehen die normale Website weiter.

Einordnung: neue Funktion „Wartungsmodus“ im Tab Customize unter „Funktionen“ (Schalter, Standard aus), eigene Datei `includes/maintenance.php`, Einstellungen im Tab Customize. Bei ausgeschalteter Funktion gibt es keinen Eintrag in der Admin-Leiste und keine Auswirkung.

## 2. Grundfunktion

| Baustein | Verhalten |
|---|---|
| Schalter in der Admin-Leiste | Eintrag „Wartung: aus / an / geplant“ mit Statuspunkt (grau, rot, gelb). Ein Klick schaltet um (Nonce-Link, Recht `manage_options`, einstellbar auf weitere Rollen). Dropdown: Wartungsseite bearbeiten, Vorschau, Einstellungen, „Verlängern um 1 h“, „Jetzt beenden“. |
| Wartungsseite | Eine normale WordPress-Seite, markiert mit dem Meta `_n4s_maintenance_page`. Titel mit Zusatz „– Wartungsseite“ (in der Seitenliste als Kennzeichnung „Wartungsseite“). Button „Wartungsseite anlegen“ erzeugt einen Entwurf mit Mustertext (zweisprachig). Gutenberg, Divi und Elementor rendern wie gewohnt. |
| Anzeige | Im Wartungsmodus setzt `template_redirect` die Hauptabfrage auf die Wartungsseite (ohne Weiterleitung, die Adresse bleibt) und liefert sie mit der Seitenvorlage der Startseite aus. Kopfzeile, Hauptmenü und Fußzeile lassen sich über die vorhandene „Seitengestaltung je Seite“ ausblenden (Voreinstellung bei neuer Wartungsseite: aus-/einblendbar). |
| Normalbetrieb | Die Wartungsseite ist von außen nicht erreichbar (404), steht in keinem Menü, keiner Suche und keiner Sitemap und hat `noindex`. |
| Fehlt die Seite | Eingebaute Standardseite („Wir sind gleich wieder da“, deutsch und englisch). Der Modus sperrt sich nie selbst aus. |

## 3. Wer die Wartung umgeht

| Ausnahme | Hinweis |
|---|---|
| Angemeldete Rollen | Wählbar, Voreinstellung Administrator und Redakteur. |
| Geheimer Link | `?n4s-wartung=<Schlüssel>` setzt ein Cookie (24 h, einstellbar): für Kunden, Agentur, Tests. Schlüssel jederzeit erneuerbar. |
| IP-Liste | Eine IP oder ein Bereich je Zeile (auch CIDR), Knopf „Meine IP eintragen“. Weiterleitungs-Header nur auf ausdrückliche Einstellung. |
| URL-Ausnahmen | Pfade je Zeile (zum Beispiel Zahlungs-Rückrufe, Webhooks). Immer frei: Anmeldeseite, `wp-admin`, `admin-ajax`, Cron, und die Katalog- und Paket-Schnittstellen von n4s (Abgleich, Updates, Lizenz). |
| REST-Schnittstelle | Einstellbar. Voreinstellung: gesperrt, außer den n4s-Schnittstellen, damit Abgleich und Updates nicht brechen. Öffentliche App-Schnittstellen (zum Beispiel Wärmepumpen-Rechner) folgen dem Schalter „Apps während der Wartung erreichbar“. |
| Notausgang | Konstante `N4S_MAINTENANCE_OFF` in `wp-config.php` schaltet den Modus ab, falls jemand ausgesperrt ist (etwa durch Cache). |

## 4. Wartung ab / bis (Zeitplan)

Ja, sinnvoll und für Wartungsfenster nahezu Pflicht.

- **Einmalig:** Von und bis (Datum, Uhrzeit, Zeitzone der Website). Status: aus, geplant, an, beendet.
- **Wiederkehrend:** Wochentage und Uhrzeiten (zum Beispiel nachts für Backups), mehrere Fenster.
- **Ohne Cron:** Der Status wird bei jedem Aufruf aus den Zeiten berechnet, damit der Start genau stimmt. WP-Cron dient nur für Mails.
- **Sicherheitsnetz:** Höchstdauer (zum Beispiel 24 h). Danach schaltet sich der Modus aus und es geht eine Mail an den Administrator. Verhindert vergessene Wartung.
- **Vorankündigung:** Optionales Banner „Wartung ab 22:00 Uhr“ für Besucher und ein Hinweis in der Admin-Leiste.
- **Countdown:** Shortcode und Block für die Wartungsseite (zählt bis „bis“).

## 5. HTTP, Suchmaschinen, Cache

- **Status 503** mit `Retry-After` (aus „bis“, sonst 1 h). So behält Google die Seiten im Index. Alternativ Modus „Bald verfügbar“ mit 200 und `noindex` für neue Websites (mit Hinweis auf die SEO-Folgen).
- **Nicht cachen:** `nocache_headers()`, `DONOTCACHEPAGE`, Cache-Plugins nach dem Umschalten leeren (WP Rocket, LiteSpeed, W3 Total Cache, Autoptimize, soweit vorhanden). Hinweis im Backend, falls ein Seiten-Cache oder CDN davor sitzt.
- **Kein Eingriff in WordPress selbst:** Die WordPress-Datei `.maintenance` (Updates) bleibt unberührt.

## 6. Weitere sinnvolle Funktionen

Priorität: A = Mindestumfang, B = zweite Stufe, C = später.

| Pr. | Funktion | Nutzen |
|---|---|---|
| A | Schalter, Wartungsseite, Rollen/Geheimlink/IP/URL-Ausnahmen, 503, `noindex`, Notausgang | Kernfunktion |
| A | Status sichtbar (Admin-Leiste rot, Hinweis im Admin-Bereich), Protokoll in „Aktivitäten“ (wer, wann, an/aus) | Nachvollziehbarkeit |
| B | Zeitplan ab/bis, wiederkehrend, Höchstdauer, Vorankündigung, Countdown | Wartungsfenster |
| B | Freigabelink für Kunden („Vorschau für Kunden“, 24 h bis 7 Tage, widerrufbar) | Agentur, Abnahme ohne Anmeldung |
| B | Mail bei Start, Ende, Auto-Ende (an einstellbare Adressen) | nichts vergessen |
| B | KI-Werkzeug `n4s_set_maintenance` (Royal MCP, schreibende Gruppe wie die anderen) | Fernsteuerung, Terminplanung |
| B | Mehrere Wartungsseiten zur Auswahl (Wartung, Update läuft, Bald verfügbar, Notfall), je Anlass wählbar | passende Botschaft |
| C | Eigene Seite für WordPress-Updates (Drop-in `wp-content/maintenance.php`) statt der Standardmeldung | einheitliches Bild |
| C | Anmeldeknopf dezent auf der Wartungsseite, Statusseite mit Kontaktdaten | Anlaufstelle |
| C | Automatisches Wartungsfenster bei Plugin- und Theme-Updates oder Backups (UpdraftPlus) | Automatik |
| C | E-Mail-Adressen sammeln („Benachrichtigen, wenn wir zurück sind“) | nur mit Datenschutzkonzept (Einwilligung, Löschfristen), besser über ein vorhandenes Formular-Plugin per Shortcode als eigene Speicherung |
| C | Mehrsprachigkeit (Polylang, WPML): je Sprache eine Wartungsseite | Mehrsprachige Websites |

Bewusst nicht vorgesehen: Marketing- und CRM-Anbindungen, Bildersuche, White-Label (nicht Zweck von n4s Apps).

## 7. Technik

- **Datei und Funktion:** `includes/maintenance.php`, Feature `maintenance` (Standard aus) im Tab Customize. Option `n4s_maintenance`: an, seit, von, bis, Wiederholungen, Rollen, IPs, URLs, Seiten-ID, Status 503 oder 200, Retry, Schlüssel, Mails, Höchstdauer.
- **Zustand:** Eine Funktion `n4s_maint_state()` liefert aus, geplant oder an (Handschalter und Zeitplan). Alle Stellen nutzen sie.
- **Ausspielen:** `template_redirect` mit früher Priorität, nach der Prüfung der Ausnahmen; vor dem Seitenzugriff je Rolle. Für REST `rest_authentication_errors`, für Feeds und Sitemaps eigene Prüfung. Statuscode und Header vor jeder Ausgabe.
- **Admin-Leiste:** `admin_bar_menu` (läuft auch auf der Website); Umschalten über `admin-post.php` mit Nonce und Rechteprüfung.
- **Sicherheit:** Eingaben bereinigt, IPs validiert, kein Weiterleiten auf fremde Adressen, Schlüssel zufällig und nur als Hash verglichen, Cookie mit `httponly`.
- **Datenschutz:** Gespeichert werden nur Einstellungen (IP-Ausnahmen). Keine Besucherdaten.
- **Tests:** Stub-Tests für Zustand, Zeitplan, Ausnahmen; Browsertest (Playwright) gegen eine lokale WordPress-Installation für Ausspielung, Statuscode und Header.
- **Zusammenspiel:** „Seitenzugriff je Rolle“ und „Seitengestaltung je Seite“ greifen auf der Wartungsseite wie auf jeder Seite.

## 8. Vorgehen

1. **Stufe A** (eine Version): Funktion, Schalter, Wartungsseite, Ausnahmen, 503, Protokoll, Notausgang.
2. **Stufe B** (zweite Version): Zeitplan, Countdown, Vorankündigung, Mails, Freigabelink, KI-Werkzeug, mehrere Seiten.
3. **Stufe C** nach Bedarf.

## 9. Offene Entscheidungen

1. Dürfen auch Redakteure den Schalter bedienen (Voreinstellung: nur Administratoren)?
2. Sollen öffentliche n4s-App-Schnittstellen (zum Beispiel Wärmepumpen-Rechner auf Kundenseiten) während der Wartung erreichbar bleiben (Voreinstellung vorgeschlagen: ja, einstellbar)?
3. Zeitplan schon in der ersten Version (dann ist Stufe A etwas größer)?
4. Eine Wartungsseite genügt zunächst, oder gleich mehrere zur Auswahl?
5. Voreinstellung für Kopfzeile und Fußzeile auf der Wartungsseite: ausblenden oder anzeigen?

## 10. Umsetzung in 2.40.0

Entscheidungen vom 27.11.2026: Schalter nur für Administratoren; öffentliche App-Schnittstellen bleiben erreichbar (einstellbar); Zeitplan mit ab/bis, Vorankündigung und Countdown von Anfang an; drei Wartungsseiten (Wartung, Update läuft, Bald verfügbar); Kopf- und Fußbereiche der Wartungsseite einstellbar (auch die Leisten von WP-Customize); als eigene Funktion „Wartungsmodus“ (Tab Funktionen, eigener Tab „Wartung“), nicht unter Customize.

Gebaut (Datei `includes/maintenance.php`, Option `n4s_maint`): Schalter in der Admin-Leiste (an, aus, geplant, Verlängern, Vorschau), drei Wartungsseiten als Entwurfsseiten mit Titelzusatz „– Wartungsseite“, Ausspielen mit Status 503 und `Retry-After` (Bald verfügbar: 200), `noindex`, kein Cache, Ausnahmen (Rollen, IP und Bereiche, URL, Freigabelink, App-Schnittstellen, Notausgang), Zeitplan (ab, bis, Höchstdauer), Vorankündigung als Banner, Countdown (Shortcode und automatisch), Freigabelinks für Kunden (Cookie, widerrufbar, nur als Hash gespeichert), Mail und Eintrag in den Aktivitäten bei Start, Ende und Auto-Ende, KI-Werkzeuge `n4s_get_maintenance` und `n4s_set_maintenance` (Gruppe „Wartungsmodus“).

Nicht gebaut: wiederkehrende Wartungsfenster, eigene Seite für WordPress-Updates (Drop-in), Abonnenten-Sammlung, Mehrsprachigkeit je Sprache, automatisches Fenster bei Updates oder Backups.
