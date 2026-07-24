# StromGedachtWidget

> Teil des **NRG-Stack** — welche Modulstände zusammenpassen: [SUITE.md](https://github.com/DG65/EMS/blob/main/SUITE.md)

IP-Symcon-Modul, das Stromampel-Signale aus bis zu drei Quellen parallel abruft und nebeneinander als Ampel-Widget darstellt:

- **[StromGedacht](https://www.stromgedacht.de)** (TransnetBW) — *Netz-Signal*: Wann ist es netzdienlich, Verbrauch zu verschieben? Abdeckung: Baden-Württemberg sowie Pilotgebiete (z. B. Teile Niedersachsens).
- **[GrünstromIndex](https://gruenstromindex.de)** (Corrently/STROMDAO) — *Öko-Signal*: Wie grün ist der Strommix in der Region (0–100)? Abdeckung: ganz Deutschland, per Postleitzahl.
- **[Energy-Charts Stromampel](https://www.energy-charts.info)** (Fraunhofer ISE) — *Öko-Signal*: Anteil erneuerbarer Energien an der Last, deutschlandweit.

> **Hinweis:** Netz-Signal und Öko-Signal sind unterschiedliche Größen. StromGedacht meldet drohende Netzengpässe (Redispatch), GrünstromIndex und Energy-Charts bewerten den Anteil erneuerbarer Energien. Ein grüner Strommix kann mit angespanntem Netz zusammenfallen — und umgekehrt. Genau deshalb zeigt das Widget alle aktivierten Quellen nebeneinander.

Jede Quelle lässt sich einzeln aktivieren. Liefert eine Quelle keine Daten (z. B. weil die Postleitzahl außerhalb des StromGedacht-Gebiets liegt), zeigt ihre Spalte im Widget „Keine Daten" mit grauer LED — die übrigen Quellen laufen normal weiter.

## Zustände je Datenquelle

### StromGedacht (Profil `SGW.State`)

| Wert | Zustand | Bedeutung |
|------|-----------|-----------|
| −1 | Supergrün | Besonders viel erneuerbare Energie im Netz — Strom jetzt nutzen |
| 1 | Grün | Normalbetrieb — es ist nichts weiter zu tun |
| 2 | Gelb | Angespannte Netzsituation *(von der API nicht mehr verwendet)* |
| 3 | Orange | Strom sparen bzw. Verbrauch verschieben empfohlen |
| 4 | Rot | Verbrauch reduzieren, um Netzengpass zu vermeiden |

### GrünstromIndex (Profil `NRG.Percent`)

Index 0–100 %. Für die Widget-Darstellung gilt: ≥ 66 grün, ≥ 33 gelb, darunter rot.

### Energy-Charts (Profil `SGW.ECSignal`)

| Wert | Zustand | Bedeutung |
|------|-----------|-----------|
| −1 | Rot (Netzengpass) | Netzengpass — Verbrauch reduzieren |
| 0 | Rot | Niedriger Anteil erneuerbarer Energien |
| 1 | Gelb | Durchschnittlicher Anteil erneuerbarer Energien |
| 2 | Grün | Hoher Anteil erneuerbarer Energien |

Die Werte entsprechen jeweils 1:1 der Quell-API; jede Quelle nutzt ihr eigenes Variablenprofil.

## Voraussetzungen

- IP-Symcon ab Version 9.0
- Internetzugriff auf die jeweilige API
- Für StromGedacht und GrünstromIndex: eine deutsche Postleitzahl (StromGedacht nur im abgedeckten Netzgebiet)

## Installation

1. In der IP-Symcon-Konsole die **Modulverwaltung** öffnen
2. Repository hinzufügen: `https://github.com/DG65/StromGedachtWidget`
3. Instanz **StromGedacht Widget** anlegen
4. Optional zusätzlich eine Instanz **StromGedachtTile** anlegen, wenn eine native, grafisch einstellbare Kachel im WebFront gewünscht ist (siehe [Kachel](#kachel))

Aktualisierungen werden ebenfalls über die Modulverwaltung eingespielt („Aktualisieren").

## Konfiguration

| Einstellung | Beschreibung | Standard |
|-------------|--------------|----------|
| StromGedacht aktivieren | Netz-Signal der TransnetBW abrufen | an |
| GrünstromIndex aktivieren | Öko-Signal von Corrently abrufen | an |
| Energy-Charts aktivieren | Öko-Signal des Fraunhofer ISE abrufen | an |
| Postleitzahl | PLZ des Standorts (Pflicht, sobald StromGedacht oder GrünstromIndex aktiv ist) | — |
| Aktualisierungsintervall | Abrufintervall in Sekunden (Minimum 60) | 300 |

Über die Schaltfläche **„Jetzt aktualisieren"** lässt sich der Abruf manuell auslösen.

**Beim Deaktivieren einer Quelle** werden ihre Variablen entfernt (inklusive eventueller Logging-Historie) und beim erneuten Aktivieren neu angelegt.

## Erstellte Variablen

| Ident | Name | Quelle | Typ | Beschreibung |
|-------|------|--------|-----|--------------|
| `State` | Ampel | StromGedacht | Integer (`SGW.State`) | Aktueller Netz-Zustand |
| `Text` | Status Text | StromGedacht | String | Empfehlungstext zum aktuellen Zustand |
| `GSI` | GrünstromIndex | GrünstromIndex | Float (`NRG.Percent`) | Aktueller Indexwert 0–100 % |
| `ECSignal` | Stromampel | Energy-Charts | Integer (`SGW.ECSignal`) | Aktuelles Ampelsignal |
| `ECShare` | EE-Anteil | Energy-Charts | Float (`NRG.Percent`) | Anteil erneuerbarer Energien an der Last |
| `Updated` | Aktualisiert | immer | Integer (`~UnixTimestamp`) | Zeitpunkt der letzten erfolgreichen Aktualisierung |
| `Widget` | Anzeige | immer | String (`~HTMLBox`) | Alle aktivierten Quellen nebeneinander als Ampel-Spalten |

Die quellenspezifischen Variablen existieren nur, solange die jeweilige Quelle aktiviert ist.

## Kachel

Für eine native, grafisch einstellbare Darstellung im WebFront gibt es das eigenständige Modul **StromGedachtTile**. Es ist bewusst von der Datenlogik getrennt (eigene Instanz, liest die Werte einer StromGedachtWidget-Instanz über deren Objektbaum) — ein Problem in der Kachel kann die Datenabfrage nicht beeinträchtigen.

- Bei genau einer StromGedachtWidget-Instanz wird sie automatisch als Quelle erkannt; bei mehreren lässt sie sich manuell wählen.
- Ampelfarben (Supergrün/Grün/Gelb/Orange/Rot), Flächen-/Textfarben und Schrift sind frei einstellbar; ohne eigene Angabe folgt die Kachel dem hellen/dunklen Design des Endgeräts. Ein Tap auf 🔄 aktualisiert die Quelle sofort.
- Die Kachel kann die Automationen der Quelle vollständig anzeigen und verwalten (siehe unten).

## Automationen (Wenn → Dann)

Im Instanzformular von StromGedacht Widget lassen sich Regeln über die Ampel-/Signal-Werte anlegen, die beim Eintreten der Bedingung eine beliebige schaltbare Variable im System schalten — z. B. „Wenn StromGedacht-Ampel = Rot, dann Wallbox ausschalten". Als Wenn-Datenpunkt stehen die Werte der jeweils aktivierten Quellen zur Verfügung (StromGedacht-Ampel, GrünstromIndex, Energy-Charts-Signal, Energy-Charts EE-Anteil); mehrere Bedingungen werden mit UND verknüpft. Regeln feuern flankengesteuert — beim Eintreten der Bedingung, nicht bei jeder Datenmeldung erneut. Die Kachel StromGedachtTile kann dieselben Regeln anzeigen, anlegen, bearbeiten, löschen und ein-/ausschalten.

## Instanz-Status

| Code | Bedeutung |
|------|-----------|
| 102 | Aktiv — mindestens eine aktivierte Quelle liefert Daten |
| 104 | Postleitzahl fehlt (bei StromGedacht und GrünstromIndex erforderlich) |
| 201 | Für die konfigurierte Postleitzahl liegen bei keiner aktivierten Quelle Daten vor |
| 202 | Keine aktivierte Quelle erreichbar (Details im Debug-Fenster) |
| 203 | Keine Datenquelle aktiviert |

Fällt nur ein Teil der Quellen aus, bleibt die Instanz aktiv — der Ausfall ist in der betroffenen Widget-Spalte („Keine Daten", graue LED) und im Debug-Fenster sichtbar.

## PHP-Funktionen

```php
// Status sofort von der konfigurierten Quelle abrufen und Variablen aktualisieren
SGW_Update(int $InstanzID);
```

Weitere Funktionen verwalten die Automationen (v. a. für die Kachel gedacht, siehe [Automationen](#automationen-wenn--dann)): `SGW_GetDataActions`, `SGW_SetDataAction`, `SGW_DeleteDataAction`, `SGW_SetDataActionActive`, `SGW_GetDataActionEditor`, `SGW_GetTargetValueOptions`.

## Datenquellen / APIs

```
GET https://api.stromgedacht.de/v1/now?zip=<PLZ>            → {"state": <Zustand>}
GET https://api.corrently.io/v2.0/gsi/prediction?zip=<PLZ>  → {"forecast": [...]}
GET https://api.energy-charts.info/signal?country=de        → {"unix_seconds": [...], "share": [...], "signal": [...]}
```

Alle drei APIs sind ohne Schlüssel nutzbar (StromGedacht und GrünstromIndex für private Zwecke, Fair Use). Bitte das Abrufintervall fair wählen — der Standard von 5 Minuten ist mehr als ausreichend.

## Fehlerbehebung

**Die Instanz zeigt „Bitte Postleitzahl konfigurieren"**
Es ist keine PLZ hinterlegt. PLZ eintragen und übernehmen (nur nötig, wenn StromGedacht oder GrünstromIndex aktiviert ist).

**Die StromGedacht-Spalte zeigt „Keine Daten"**
Die PLZ liegt außerhalb des StromGedacht-Gebiets (im Wesentlichen Baden-Württemberg). GrünstromIndex und Energy-Charts decken ganz Deutschland ab und laufen davon unabhängig weiter.

**Die Ampel zeigt einen anderen Zustand als die StromGedacht-App**
Vermutlich hängt die Variable „Ampel" noch an einem Variablenprofil einer älteren Modulversion. Lösung: Variable „Ampel" öffnen und das benutzerdefinierte Profil entfernen bzw. auf `SGW.State` stellen — oder die Variable löschen und in der Instanz „Übernehmen" klicken (sie wird mit korrektem Profil neu angelegt).

**Keine Daten / Variablen bleiben leer**
Im Debug-Fenster der Instanz prüfen: Dort werden HTTP-Code und API-Antwort jedes Abrufs protokolliert.

## Änderungen

Siehe [CHANGELOG.md](CHANGELOG.md).

## Lizenz

[PolyForm Noncommercial License 1.0.0](LICENSE) — © 2026 Dietmar Gureth (DG65). Private und nicht-kommerzielle Nutzung ist frei; gewerbliche Nutzung ist lizenzpflichtig (Kontakt: DG65). Ältere Versionen, die unter MIT veröffentlicht wurden, bleiben MIT.

StromGedacht ist ein Angebot der TransnetBW GmbH, der GrünstromIndex ein Angebot der STROMDAO GmbH, die Stromampel ein Angebot des Fraunhofer ISE. Dieses Modul ist ein privates Community-Projekt und steht in keiner Verbindung zu diesen Anbietern.
