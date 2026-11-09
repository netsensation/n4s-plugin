# n4s Apps: Entscheidungen und offene Punkte

Stand: 09.11.2026 · Plugin-Version 2.5.3. Ergänzt die Arbeitsanweisung um das Warum.

## Entscheidungen
- **Eigene Apps außerhalb des Plugin-Ordners** (`wp-content/n4s-apps-data/apps/`), damit Plugin-Updates sie nicht überschreiben. Ordner mit `.htaccess` und `index.php` geschützt; auf Nginx schützt nur der `ABSPATH`-Guard in den App-Dateien.
- **Upload nur statisch geprüft** (Größe bis 1024 KB, `<?php`, `n4s_register_app` mit festem Slug, `ABSPATH`-Schutz, `token_get_all( $code, TOKEN_PARSE )`), nie ausgeführt.
- **Fehlerhafte Apps** werden beim Laden automatisch deaktiviert (Shutdown-Handler, Option `n4s_apps_disabled`, Schlüssel `bundled:datei.php` / `user:datei.php`), damit die Seite erreichbar bleibt.
- **Löschen nur nach Deaktivierung**, serverseitig erzwungen (Button ausgegraut + Browser-Bestätigung). Mitgelieferte Apps kommen nach einem Plugin-Update deaktiviert zurück.
- **App-Update mit Abgleich und Bestätigung:** Der Slug (aus `n4s_register_app`) muss gleich sein, sonst Abbruch. Warnung mit Häkchen bei abweichendem Anzeigenamen, gleicher oder älterer Version, fehlender Version im Update. Ältere Apps ohne Version: nur Hinweis. Neue Apps mit schon vergebenem Slug werden abgelehnt. Das Update liegt bis zur Bestätigung in einem Transient (15 Minuten), vor dem Schreiben wird neu validiert und geprüft, ob die installierte Datei unverändert ist. Vorherige Version bleibt als `.bak`.
- **Plugin-Selbstupdate:** `Plugin_Upgrader::install` mit `overwrite_package`; ZIP vorab geprüft (nur Ordner `n4s-apps/`, höchstens 5 MB gepackt, 20 MB entpackt, Plugin Name „n4s Apps"), nur höhere Version wird installiert. Rollback nur über Plugins → Installieren.
- **Dateiname im ZIP:** Auslieferung als `n4s-apps-plugin.zip`, der Ordner darin muss `n4s-apps` bleiben (Pflicht für Prüfung und Plugin-Ordner).
- **Nonce:** eigene Prüfung `n4s_apps_verify()` mit freundlichem Hinweis statt WordPress-Fehlerseite (einmaliger Nonce-Fehler beim ersten Upload, nicht reproduzierbar).
- **Zweisprachig DE/EN** über `n4s_t( $de, $en )` anhand `determine_locale()` (Deutsch bei `de_*`).
- **Versionsanzeige:** zwei getrennte Versionen (Plugin, jede App), Schema `Version: x.y.z by n4s`, kein Datum (ein Build-Datum war mehrdeutig und steht ohnehin in der Admin-Oberfläche). Die App-Zeile hängt der Shortcode automatisch an, Version aus dem App-Dateikopf; keine Doppelung bei Klasse `n4s-app-ver`.
- **Dateiname der Apps mit Version** (`slug_V2_1_2.php`); der Upload entfernt das Suffix per Regex `/_v\d+(?:_\d+)*(?=\.php$)/` (nach `strtolower`), installiert wird `slug.php`. So bleiben Update-Abgleich und Deaktivierungs-Schlüssel stabil.
- **Reverse-Engineering-Schutz:** Logik nur serverseitig über REST-Endpunkt, nichts davon im Browser-JavaScript.
- **Menü-Icon:** SVG als Data-URI wird vom WordPress-Skript nachträglich einfarbig umgefärbt. Lösung: Icon per CSS als Hintergrund von `div.wp-menu-image` (center, 22 px), `:before` ausgeblendet, Menü-Icon-Parameter `none`. Zentrierung in WordPress zuletzt nicht per Screenshot bestätigt. Fallback: einfarbiges weißes SVG (viewBox 0 0 20 20) oder PNG 20×20 / 40×40.
- **Marke:** n4s-Schriftzug als SVG, hell mit Buchstaben `#0C0E10`, dunkel mit `#fff`, die 4 immer `#00CCFF`.

## Grenzen (Claude per MCP)
- Kann: Seiten, Menüs, Medien, Vorschau-Links, Rendering prüfen. Kann nicht: Plugins installieren, Serverdateien schreiben, Apps hochladen. Custom CSS per MCP gesperrt, solange „Allow AI to modify theme appearance" aus ist. Bei Wartungsmodus kein Rendering-Check.
- Das Google-Doc „n4s Apps Arbeitsanweisung" lässt sich per Drive-Connector nicht überschreiben (nur Metadaten): neues Doc anlegen, Quelle in den Projekten umstellen. Das Feld „Anweisungen" im Projekt kann nur der Benutzer ändern.

## Offene Punkte
- Zentrierung des Menü-Icons und die Versionsanzeige (Admin, unter Apps) in WordPress per Screenshot prüfen.
- Beispiel-App `hello` hat einen öffentlichen REST-Endpunkt: auf Produktivseiten (ELCO) deaktivieren und löschen.
- Erste echte App festlegen (Security-App für ELCO als Ersatz für Limit Login Attempts ist geplant).
- Wer 2.1.0 installiert hatte: leere Tabelle `n4s_verwaltungen` und Option `n4s_verw_db` können entfernt werden.
