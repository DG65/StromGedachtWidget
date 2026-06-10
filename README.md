# StromGedachtWidget

IP-Symcon-Modul, das Stromampel-Signale aus wählbaren Quellen abruft und als Ampel-Widget darstellt:

- **[StromGedacht](https://www.stromgedacht.de)** (TransnetBW) — *Netz-Signal*: Wann ist es netzdienlich, Verbrauch zu verschieben? Abdeckung: Baden-Württemberg sowie Pilotgebiete (z. B. Teile Niedersachsens).
- **[GrünstromIndex](https://gruenstromindex.de)** (Corrently/STROMDAO) — *Öko-Signal*: Wie grün ist der Strommix in der Region (0–100)? Abdeckung: ganz Deutschland, per Postleitzahl.
- **[Energy-Charts Stromampel](https://www.energy-charts.info)** (Fraunhofer ISE) — *Öko-Signal*: Anteil erneuerbarer Energien an der Last, deutschlandweit.

> **Hinweis:** Netz-Signal und Öko-Signal sind unterschiedliche Größen. StromGedacht meldet drohende Netzengpässe (Redispatch), GrünstromIndex und Energy-Charts bewerten den Anteil erneuerbarer Energien. Ein grüner Strommix kann mit angespanntem Netz zusammenfallen — und umgekehrt. Wer beide Signale will, legt einfach zwei Instanzen mit unterschiedlicher Datenquelle an.

## Zustände je Datenquelle

### StromGedacht (Profil `SGW.State`)

| Wert | Zustand | Bedeutung |
|------|-----------|-----------|
| −1 | Supergrün | Besonders viel erneuerbare Energie im Netz — Strom jetzt nutzen |
| 1 | Grün | Normalbetrieb — es ist nichts weiter zu tun |
| 2 | Gelb | Angespannte Netzsituation *(von der API nicht mehr verwendet)* |
| 3 | Orange | Strom sparen bzw. Verbrauch verschieben empfohlen |
| 4 | Rot | Verbrauch reduzieren, um Netzengpass zu vermeiden |

### GrünstromIndex (Profil `SGW.GSI`)

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

Updates werden ebenfalls über die Modulverwaltung eingespielt („Aktualisieren").

## Konfiguration

| Einstellung | Beschreibung | Standard |
|-------------|--------------|----------|
| Datenquelle | StromGedacht, GrünstromIndex oder Energy-Charts | StromGedacht |
| Postleitzahl | PLZ des Standorts (Pflicht bei StromGedacht und GrünstromIndex) | — |
| Aktualisierungsintervall | Abrufintervall in Sekunden (Minimum 60) | 300 |

Über die Schaltfläche **„Jetzt aktualisieren"** lässt sich der Abruf manuell auslösen.

**Beim Wechsel der Datenquelle** werden die Variablen der bisherigen Quelle entfernt (inklusive eventueller Logging-Historie) und die der neuen Quelle angelegt.

## Erstellte Variablen

| Ident | Name | Quelle | Typ | Beschreibung |
|-------|------|--------|-----|--------------|
| `State` | Ampel | StromGedacht | Integer (`SGW.State`) | Aktueller Netz-Zustand |
| `GSI` | GrünstromIndex | GrünstromIndex | Float (`SGW.GSI`) | Aktueller Indexwert 0–100 % |
| `ECSignal` | Stromampel | Energy-Charts | Integer (`SGW.ECSignal`) | Aktuelles Ampelsignal |
| `ECShare` | EE-Anteil | Energy-Charts | Float (`SGW.Percent`) | Anteil erneuerbarer Energien an der Last |
| `Text` | Status Text | alle | String | Empfehlungstext zum aktuellen Zustand |
| `Updated` | Aktualisiert | alle | Integer (`~UnixTimestamp`) | Zeitpunkt der letzten erfolgreichen Aktualisierung |
| `Widget` | Anzeige | alle | String (`~HTMLBox`) | Ampel-Darstellung für die Visualisierung |

## Instanz-Status

| Code | Bedeutung |
|------|-----------|
| 102 | Aktiv — Daten werden abgerufen |
| 104 | Postleitzahl fehlt (bei StromGedacht und GrünstromIndex erforderlich) |
| 201 | Für die konfigurierte Postleitzahl liegen keine Daten vor (z. B. PLZ außerhalb des StromGedacht-Gebiets oder Tippfehler) |
| 202 | API nicht erreichbar oder ungültige Antwort (Details im Debug-Fenster) |

Bei Status 201 wird der Hinweis zusätzlich in die Variable „Status Text" und in das Widget geschrieben, damit er auch in der Visualisierung sichtbar ist.

## PHP-Funktionen

```php
// Status sofort von der konfigurierten Quelle abrufen und Variablen aktualisieren
SGW_Update(int $InstanzID);
```

## Datenquellen / APIs

```
GET https://api.stromgedacht.de/v1/now?zip=<PLZ>            → {"state": <Zustand>}
GET https://api.corrently.io/v2.0/gsi/prediction?zip=<PLZ>  → {"forecast": [...]}
GET https://api.energy-charts.info/signal?country=de        → {"unix_seconds": [...], "share": [...], "signal": [...]}
```

Alle drei APIs sind ohne Schlüssel nutzbar (StromGedacht und GrünstromIndex für private Zwecke, Fair Use). Bitte das Abrufintervall fair wählen — der Standard von 5 Minuten ist mehr als ausreichend.

## Fehlerbehebung

**Die Instanz zeigt „Bitte Postleitzahl konfigurieren"**
Es ist keine PLZ hinterlegt. PLZ eintragen und übernehmen (bei Energy-Charts nicht erforderlich).

**Die Instanz zeigt „Für diese Postleitzahl liegen keine Daten vor"**
Die PLZ ist der Quelle unbekannt oder liegt außerhalb ihres Gebiets. Bei StromGedacht: Die API deckt im Wesentlichen Baden-Württemberg ab — für andere Regionen GrünstromIndex oder Energy-Charts als Datenquelle wählen.

**Die Ampel zeigt einen anderen Zustand als die StromGedacht-App**
Vermutlich hängt die Variable „Ampel" noch an einem Variablenprofil einer älteren Modulversion. Lösung: Variable „Ampel" öffnen und das benutzerdefinierte Profil entfernen bzw. auf `SGW.State` stellen — oder die Variable löschen und in der Instanz „Übernehmen" klicken (sie wird mit korrektem Profil neu angelegt).

**Keine Daten / Variablen bleiben leer**
Im Debug-Fenster der Instanz prüfen: Dort werden HTTP-Code und API-Antwort jedes Abrufs protokolliert.

## Lizenz

[MIT](LICENSE) — © 2026 Dietmar Gureth

StromGedacht ist ein Angebot der TransnetBW GmbH, der GrünstromIndex ein Angebot der STROMDAO GmbH, die Stromampel ein Angebot des Fraunhofer ISE. Dieses Modul ist ein privates Community-Projekt und steht in keiner Verbindung zu diesen Anbietern.
