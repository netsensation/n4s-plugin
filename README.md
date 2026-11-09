# n4s Plugin for WordPress

**n4s Apps** – eigenes WordPress-Plugin, mit dem PHP-Apps als einzelne Dateien verwaltet und per Shortcode `[n4s_app name="..."]` in Seiten eingebunden werden. Aktuelle Version: **2.5.3**. Privates Repository.

## Funktionen
- Verwaltung im Admin-Menü „Apps": Upload, Update mit Versionsabgleich und Bestätigung, (De)aktivieren, Löschen
- Eigene Apps liegen in `wp-content/n4s-apps-data/apps/` und überstehen Plugin-Updates
- Fehlerhafte Apps werden automatisch deaktiviert
- Statische Prüfung von Uploads (Syntax, ABSPATH-Schutz, Größe bis 1024 KB)
- Plugin-Selbstupdate per ZIP (nur höhere Version)
- Zweisprachig Deutsch/Englisch (`n4s_t()`), Versionsanzeige „Version: x.y.z by n4s"

## Aufbau
```
n4s-apps/            Plugin (Ordnername im ZIP muss n4s-apps bleiben)
├── n4s-apps.php     Registry, Loader, Shortcode
├── includes/admin.php
└── apps/hello.php   Beispiel-App
docs/                Arbeitsanweisung, Entscheidungen, Änderungsprotokoll
.claude/skills/      Skill für Claude Code
```

## Build
```
./build.sh   # prüft php -l und erzeugt dist/n4s-apps-plugin.zip
```
Installation: ZIP unter Plugins → Installieren → Hochladen, Updates danach im Menü „Apps".

## Dokumentation
[Arbeitsanweisung](docs/n4s-apps-arbeitsanweisung.md) · [Entscheidungen](docs/n4s-apps-entscheidungen.md) · [Änderungsprotokoll](docs/n4s-apps-aenderungsprotokoll.md) · [CLAUDE.md](CLAUDE.md)

© n4s – https://n4s.one
