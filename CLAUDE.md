# Hinweise für die Arbeit an diesem Repository

## Sprachregel: alles Nutzersichtbare auf Deutsch

Verbindliche Verbund-Regel (angeordnet von Dietmar am 22.07.2026, gilt für alle zehn Module
der DG65-Energie-Suite): **keine englischen Sätze/Ausdrücke und keine vermeidbaren Anglizismen**
in allem, was der Nutzer zu sehen bekommt.

Betroffen sind:

- Formularbeschriftungen (`caption` in `form.json`), Hinweis- und Warntexte, Bestätigungsdialoge
- Fehler- und Statusmeldungen, Rückgabe-Texte (z. B. `reason`-Felder eines Ergebnis-Arrays)
- `SendDebug`-/`LogMessage`-Ausgaben, Variablen- und Profilnamen
- Texte in der Kachel (`StromGedachtTile/module.html`), README, CHANGELOG

Vermeidbare Anglizismen ersetzen: Dry-Run → Probelauf, Link → Verknüpfung, Event → Ereignis,
Button → Schaltfläche, Checkliste → Prüfliste, Scan/scannen → Suche/suchen.

**Ausgenommen** (bleibt englisch, weil Umbenennen Verträge bricht):

- Bezeichner im Code: Klassen-, Methoden-, Variablen-, Property- und vor allem **Ident-Namen**.
  Konkret hier: `State`, `GSI`, `ECSignal`, `ECShare`, `Text`, `Updated`, `Widget`.
  **Idents sind API und werden nie umbenannt** (Verbund-Konvention).
- Feststehende IP-Symcon-/Technikbegriffe (`SelectVariable`, `WebFront`, Modbus TCP, HTMLBox …)
  und Formularelement-Typen (`"type": "Button"` ist ein Bezeichner, kein Anzeigetext).
- API-Feldnamen der Gegenstelle (`state`, `forecast`, `unix_seconds`, `signal`, `share` …).
- Der Modulname „StromGedacht Widget" selbst (Store-Name, Vertrag).

Für den geplanten `SGW_GetState()`-Vertrag heißt das: Feldnamen englisch
(`state`, `label`, `gsi`, `ecSignal`, `ecShare`, `updated`), das `label`-**Feld** aber mit
deutschem Anzeigetext füllen.

## Zweige

- `beta` — Entwicklung und schnelle Auslieferung an Dietmar und seinen Testerkreis. **Hier entwickeln und pushen.**
- `main` — geprüfter, stabiler Stand (Standardzweig). Nur bewusst dorthin veröffentlichen.
- `master` existiert nicht mehr (am 22.07.2026 repo-übergreifend entfernt).

## Prüfen vor dem Commit

```bash
php -l StromGedachtWidget/module.php && php -l StromGedachtTile/module.php
python3 -m json.tool StromGedachtWidget/form.json > /dev/null
python3 -m json.tool StromGedachtTile/form.json > /dev/null
php tests/smoke.php
```

Der Smoke-Test ruft die echten APIs auf und kann bei Netz-/API-Ausfällen transient
fehlschlagen — bei Fehlern erst ein zweites Mal laufen lassen, bevor man Ursachenforschung
betreibt. `api.stromgedacht.de` ist aus Dietmars Netz nur über IPv4 erreichbar (das Modul hat
dafür einen Fallback).

## Verbund-Regeln (Kurzfassung)

- **Eigenständigkeit:** Das Modul muss ohne jedes andere Modul voll lauffähig sein. Jeder Aufruf
  eines fremden Modulpräfixes gehört hinter `function_exists()` — ein Aufruf einer undefinierten
  Funktion ist in PHP ein **Fatal Error**, ein vorangestelltes `@` unterdrückt ihn **nicht**.
  (Derzeit ruft dieses Modul keine fremden Präfixe auf.)
- **Ein Regler pro Stellgröße:** Steuernd schreibt nur das EMS. Die Wenn→Dann-Automationen dieses
  Moduls sind für den Betrieb ohne EMS gedacht; sie dürfen nicht parallel zum EMS dieselbe
  Stellgröße schreiben.
- **Verträge:** Veröffentlichte `SGW_*`-Funktionen werden nie umbenannt, nur additiv erweitert.
- **Fremde Repos:** Keine Änderungen ohne Absprache; Koordination läuft über Dietmar.
