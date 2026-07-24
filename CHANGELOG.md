# Changelog

Alle nennenswerten Änderungen an StromGedachtWidget.

## 1.5.0 (2026-07-24)

- **`SGW_GetState()`**: stabiler NRG-Stack-Vertrag (contractVersion 1.0) — aktueller Zustand aller aktivierten Quellen als Array, für andere Module des Verbunds (z. B. EMS)
- **`SGW_GetForecast()`**: Vorschau der StromGedacht-Netzampel für einen Zeitraum, auf Basis der bisher nicht angebundenen `/v1/statesRelative`-API (Horizont max. 48 h); GrünstromIndex/Energy-Charts liefern hier laut Verbund-Vorgabe bewusst noch keine Einträge (nicht planungsrelevant)
- Lizenzwechsel MIT → PolyForm Noncommercial 1.0.0 (NRG-Stack-weite Umstellung: privat/nicht-kommerziell frei, gewerblich lizenzpflichtig)
- Teil des NRG-Stack-Modulverbunds (vormals „DG65 Energie-Suite") — siehe [SUITE.md](https://github.com/DG65/EMS/blob/main/SUITE.md)

## 1.4.0 (2026-07-13)

- Neues eigenständiges Kachel-Modul **StromGedachtTile** — grafisch einstellbare native Kachel fürs WebFront (Ampelfarben, Flächen-/Textfarben, Schrift), erkennt die Quelle automatisch bei genau einer StromGedachtWidget-Instanz
- **Automationen (Wenn → Dann)** im Instanzformular und in der Kachel: Bedingung = einer der aktivierten Ampel-/Signal-Werte, Ziel = beliebige schaltbare Variable im System, mehrere Bedingungen UND-verknüpfbar, flankengesteuert
- Regel-Editor der Kachel bietet den Vergleichswert als Dropdown an, sobald der gewählte Datenpunkt bekannte Profilwerte hat (StromGedacht-Ampel, Energy-Charts-Signal); im klassischen Instanzformular (technisch nicht möglich) stattdessen eine Werte-Übersicht als Hilfetext
- Dokumentations-ExpansionPanel „📖 Dokumentation & Hilfe" im Instanzformular und in der Kachel
- Dismissbarer Bewertungs-Hinweis mit Link zur [Symcon-Community](https://community.symcon.de/t/modul-strom-gedacht-ampel-widget/143960)

## 1.3 (2026-06-19)

- Vendor in `module.json` auf TransnetBW GmbH korrigiert (Symcon-Review: vendor = Dienstanbieter, nicht Entwickler)
- Alle drei Datenquellen laufen parallel statt Einzelauswahl (Widget mit Spalten nebeneinander); Instanz bleibt aktiv, solange eine Quelle liefert

## 1.2 (2026-06-19)

- Mehrere Datenquellen: zusätzlich zu StromGedacht (Netz-Signal) jetzt auch Corrently GrünstromIndex und Energy-Charts-Signal (beide Öko-Signal), je Quelle einzeln aktivierbar, getrennte Variablenprofile

## 1.0 (2026-06-10)

- Erste funktionierende Version: PLZ-Parameter (`zip`) für die StromGedacht-API ergänzt, numerisches State-Mapping (−1/1/2/3/4 statt GREEN/YELLOW/RED), IPv4-Fallback für PHP-Streams ohne IPv6-Fallback, `form.json` instand gesetzt
