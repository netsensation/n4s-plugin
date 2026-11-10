# n4s Apps (WordPress-Plugin)

Quellcode in `n4s-apps/` (Plugin, Version steht in `n4s-apps/n4s-apps.php`). Regeln und Ablauf: Skill `.claude/skills/n4s-apps-plugin/SKILL.md`. Dokumentation: `docs/n4s-apps-arbeitsanweisung.md` (Aufbau, Regeln), `docs/n4s-apps-entscheidungen.md` (Warum, offene Punkte), `docs/n4s-apps-aenderungsprotokoll.md` (Versionen).

Kurz: Bei jeder Änderung Version in `n4s-apps.php` an zwei Stellen erhöhen, Änderungsprotokoll ergänzen, `php -l` aller Dateien, ZIP als `n4s-apps-plugin.zip` bauen (Ordner darin `n4s-apps`, höchstens 5 MB). Antworten kurz, Rückfragen als Text. Keine Auswahldialoge.

Dauerfreigabe (10.11.2026): Pull Requests nach dem Anlegen und Testen sofort mergen (squash), ohne Nachfrage. Danach Release prüfen und die nächsten Schritte für den Benutzer nennen.
