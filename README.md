# n4s Plugin for WordPress

**n4s Apps** – eigenes WordPress-Plugin, mit dem PHP-Apps als einzelne Dateien verwaltet und per Shortcode `[n4s_app name="..."]` in Seiten eingebunden werden. Aktuelle Version: **2.7.2**. Öffentliches Repository.

## Funktionen
- Menü „Plugin“ (n4s-Logo) mit Tabs **Funktionen**, **Apps**, **Sicherheit**; Apps und Sicherheit lassen sich im Tab Funktionen einzeln ein-/ausschalten
- Sicherheit: Anmeldeschutz (Sperren, Protokoll, Mail mit Zusammenfassung gesperrter IPs und häufigster Anmeldenamen) und Härtung
- Verwaltung im Admin-Menü „Apps": Upload, Update mit Versionsabgleich und Bestätigung, (De)aktivieren, Löschen
- Eigene Apps liegen in `wp-content/n4s-apps-data/apps/` und überstehen Plugin-Updates
- Fehlerhafte Apps werden automatisch deaktiviert
- Statische Prüfung von Uploads (Syntax, ABSPATH-Schutz, Größe bis 1024 KB)
- Plugin-Update über den normalen WordPress-Update-Prozess, Quelle GitHub-Releases (kein Token nötig; optional per `N4S_APPS_GITHUB_TOKEN` oder im Menü „Apps")
- Plugin-Selbstupdate per ZIP weiterhin möglich (nur höhere Version)
- Zweisprachig Deutsch/Englisch (`n4s_t()`), Versionsanzeige „Version: x.y.z by n4s"

## Aufbau
```
n4s-apps/            Plugin (Ordnername im ZIP muss n4s-apps bleiben)
├── n4s-apps.php     Registry, Loader, Shortcode
├── includes/admin.php
├── includes/updater.php   Update über WordPress (GitHub)
├── includes/security.php  Funktion Sicherheit
└── apps/hello.php   Beispiel-App
docs/                Arbeitsanweisung, Entscheidungen, Änderungsprotokoll
.claude/skills/      Skill für Claude Code
```

## Build
```
./build.sh   # prüft php -l und erzeugt dist/n4s-apps-plugin.zip
```
Installation: ZIP unter Plugins → Installieren → Hochladen. Updates danach über Plugins / Dashboard → Aktualisierungen oder manuell im Menü „Apps".

Release: Version in `n4s-apps/n4s-apps.php` erhöhen und nach `main` mergen; der Workflow `Release` legt `v<Version>` mit `n4s-apps-plugin.zip` an.

## Dokumentation
[Arbeitsanweisung](docs/n4s-apps-arbeitsanweisung.md) · [Entscheidungen](docs/n4s-apps-entscheidungen.md) · [Änderungsprotokoll](docs/n4s-apps-aenderungsprotokoll.md) · [CLAUDE.md](CLAUDE.md)

© n4s – https://n4s.one
