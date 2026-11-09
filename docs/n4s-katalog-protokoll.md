# n4s-Katalog: Protokoll (Version 1)

Beschreibt, wie das Plugin n4s Apps (ab 2.8.0, `includes/catalog.php`) Apps von einem Katalog-Server (z. B. https://n4s.one) bezieht. Der Server (Admin-App `n4s-admin`, Repository n4s-plugin-admin) setzt dieses Protokoll um.

## Grundsätze
- **Opt-in je App:** Geprüft werden nur installierte eigene Apps mit dem Dateikopf `Update URI: https://host` (genau ein Slug je Datei). Der Katalog (Liste, Installation) wird nur auf Klick geladen.
- **Nur https** (Ausnahme Entwicklung: Filter `n4s_apps_allow_http`). Download-Adressen müssen denselben Host wie die Quelle haben, Weiterleitungen werden nicht verfolgt.
- **Zugangsschlüssel** für private Apps: Header `Authorization: Bearer <Schlüssel>` und zusätzlich `X-N4S-Key: <Schlüssel>` (Fallback, falls ein Server den Authorization-Header entfernt), gesendet nur an den Host der eingestellten Quelle (Option `n4s_apps_catalog` oder Konstanten `N4S_APPS_CATALOG_SOURCE` / `N4S_APPS_CATALOG_KEY`).
- **Signatur Pflicht:** Eine Datei wird nur installiert, wenn SHA-256 und Ed25519-Signatur stimmen. Ohne hinterlegten Schlüssel (`includes/trust.php`, Konstante `N4S_APPS_TRUSTED_KEYS`, Filter `n4s_apps_trusted_keys`) sind Katalog-Installationen aus.
- Anschließend dieselbe statische Prüfung wie beim manuellen Upload (Größe bis 1024 KB, `<?php`, `n4s_register_app` mit festem Slug, `ABSPATH`-Schutz, Syntax), Slug und Version der Datei müssen zum Eintrag passen, die Version muss höher sein, die vorherige Datei bleibt als `.bak`.

## Schnittstelle
REST-Namespace `n4s-admin/v1`. Der Client ruft `<Quelle>/?rest_route=/n4s-admin/v1/<Pfad>` auf (funktioniert auch ohne schöne Permalinks). Antworten JSON, UTF-8.

| Aufruf | Zweck |
|---|---|
| `GET check?apps=slug:version,slug2:version` | Neueste Version je angefragter App (nur Apps, die der Aufrufer sehen darf) |
| `GET catalog` | Liste aller für den Aufrufer sichtbaren Apps (öffentlich, mit gültigem Schlüssel auch privat) |
| `GET download/<slug>?v=<version>` | Rohdatei der App (`text/plain`), bei privaten Apps nur mit Schlüssel |

Antwort `check`: `{ "apps": { "<slug>": Eintrag } }`. Antwort `catalog`: `{ "apps": [ Eintrag, … ] }`.

Eintrag:

```json
{
  "slug": "waermepumpen",
  "version": "2.1.2",
  "name": "Wärmepumpen",
  "sha256": "<64 Hex-Zeichen der Datei>",
  "size": 48213,
  "signature": "<Base64, 64 Byte Ed25519>",
  "download": "https://n4s.one/?rest_route=/n4s-admin/v1/download/waermepumpen&v=2.1.2",
  "access": "public",
  "description": { "de": "…", "en": "…" },
  "changelog": "Kurzer Text zur Version",
  "license": "…"
}
```

Fehler: `401`/`403` ohne gültigen Schlüssel bei privaten Apps (im Client „Zugriff verweigert“), sonst `404`. Ein Eintrag mit ungültigen Feldern (Slug, Version, Hash, Signatur, Download-Host) wird vom Client verworfen.

## Signatur
- Algorithmus Ed25519 (libsodium, in PHP ab 7.2). Der Schlüssel liegt **nicht** auf dem Katalog-Server, sondern z. B. als GitHub-Secret `N4S_SIGNING_KEY`; der Server verteilt nur fertig signierte Dateien. Wird der Server gehackt, lassen sich keine gefälschten Updates verteilen.
- Signiert wird die Nachricht (UTF-8, Zeilenumbruch `\n`, kein Abschluss-Umbruch):

```
n4s-app-v1
<slug>
<version>
<sha256 der Datei, Hex, klein>
```

- Die Nachricht bindet Slug und Version an den Dateiinhalt: Eine alte oder fremde Datei lässt sich nicht als anderes Update ausgeben.
- Werkzeuge im Repository (nur Kommandozeile, kein WordPress nötig):
  - `tools/n4s-keygen.html`: erzeugt das Schlüsselpaar ohne PHP. Datei per Doppelklick lokal im Browser öffnen (Chrome/Edge ab 137, Firefox ab 129, Safari ab 17), alles läuft offline im Browser. Gleichwertig: `php tools/n4s-keygen.php`.
  - Die Schlüssel beider Werkzeuge sind austauschbar (libsodium-Format, vom Selbsttest und von `n4s-sign.php` geprüft). Den **öffentlichen** Schlüssel in `n4s-apps/includes/trust.php` eintragen und das Plugin neu veröffentlichen, den **privaten** nur als geheimen Wert speichern.
  - `N4S_SIGNING_KEY=<privat> php tools/n4s-sign.php app.php`: gibt `slug`, `version`, `name`, `sha256`, `size`, `signature` als JSON aus.
- Schlüsselwechsel: Mehrere öffentliche Schlüssel dürfen gleichzeitig vertraut werden. Erst den neuen Schlüssel per Plugin-Update verteilen, dann damit signieren, danach den alten entfernen.
- Von Hand hochgeladene Apps sind unsigniert und lassen sich nur manuell installieren.

## Datenschutz
Der Client sendet bei der Prüfung nur Slug und Version der Apps mit `Update URI` (und bei privaten Apps den Schlüssel). Der Server sieht dabei die IP-Adresse des anfragenden Servers.
