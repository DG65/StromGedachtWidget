# StromGedachtWidget

IP-Symcon-Modul, das den aktuellen Netzampel-Status von [StromGedacht](https://www.stromgedacht.de) (TransnetBW) abruft und als Ampel-Widget darstellt.

StromGedacht zeigt für Baden-Württemberg an, wie die Situation im Übertragungsnetz ist — und wann es sinnvoll ist, Stromverbrauch zu verschieben (z. B. E-Auto laden, Wärmepumpe, Batteriespeicher).

## Ampel-Zustände

| Wert | Zustand | Bedeutung |
|------|-----------|-----------|
| −1 | Supergrün | Besonders viel erneuerbare Energie im Netz — Strom jetzt nutzen |
| 1 | Grün | Normalbetrieb — es ist nichts weiter zu tun |
| 2 | Gelb | Angespannte Netzsituation *(von der API nicht mehr verwendet)* |
| 3 | Orange | Strom sparen bzw. Verbrauch verschieben empfohlen |
| 4 | Rot | Verbrauch reduzieren, um Netzengpass zu vermeiden |

Die Werte entsprechen 1:1 der StromGedacht-API und werden unverändert in der Variable **Ampel** gespeichert.

## Voraussetzungen

- IP-Symcon ab Version 8.0
- Internetzugriff auf `api.stromgedacht.de`
- Eine Postleitzahl im Netzgebiet der TransnetBW (Baden-Württemberg)

## Installation

1. In der IP-Symcon-Konsole die **Modulverwaltung** öffnen
2. Repository hinzufügen: `https://github.com/DG65/StromGedachtWidget`
3. Instanz **StromGedacht Widget** anlegen

Updates werden ebenfalls über die Modulverwaltung eingespielt („Aktualisieren").

## Konfiguration

| Einstellung | Beschreibung | Standard |
|-------------|--------------|----------|
| Postleitzahl | PLZ des Standorts (Pflichtfeld, ohne PLZ bleibt die Instanz inaktiv) | — |
| Aktualisierungsintervall | Abrufintervall in Sekunden (Minimum 60) | 300 |

Über die Schaltfläche **„Jetzt aktualisieren"** lässt sich der Abruf manuell auslösen.

## Erstellte Variablen

| Ident | Name | Typ | Beschreibung |
|-------|------|-----|--------------|
| `State` | Ampel | Integer (Profil `SGW.State`) | Aktueller Zustand mit Farbe und Beschriftung |
| `Text` | Status Text | String | Empfehlungstext zum aktuellen Zustand |
| `Updated` | Aktualisiert | Integer (`~UnixTimestamp`) | Zeitpunkt der letzten erfolgreichen Aktualisierung |
| `Widget` | Anzeige | String (`~HTMLBox`) | Ampel-Darstellung (LED, Zustand, Empfehlung) für die Visualisierung |

## PHP-Funktionen

```php
// Status sofort von der API abrufen und Variablen aktualisieren
SGW_Update(int $InstanzID);
```

## Datenquelle

Das Modul nutzt die öffentliche [StromGedacht-API](https://api.stromgedacht.de/) von TransnetBW:

```
GET https://api.stromgedacht.de/v1/now?zip=<PLZ>
→ {"state": <Zustand>}
```

Der Parameter `zip` ist Pflicht; ohne ihn antwortet die API mit HTTP 400. Es ist kein API-Schlüssel erforderlich. Bitte das Abrufintervall fair wählen (Standard 5 Minuten ist mehr als ausreichend — die Ampel ändert sich selten).

## Fehlerbehebung

**Die Instanz zeigt „Bitte Postleitzahl konfigurieren"**
Es ist keine PLZ hinterlegt. PLZ eintragen und übernehmen.

**Die Ampel zeigt einen anderen Zustand als die StromGedacht-App**
Vermutlich hängt die Variable „Ampel" noch an einem Variablenprofil einer älteren Modulversion. Lösung: Variable „Ampel" öffnen und das benutzerdefinierte Profil entfernen bzw. auf `SGW.State` stellen — oder die Variable löschen und in der Instanz „Übernehmen" klicken (sie wird mit korrektem Profil neu angelegt).

**Keine Daten / Variablen bleiben leer**
Im Debug-Fenster der Instanz prüfen: Bei „HTTP Fehler" ist `api.stromgedacht.de` nicht erreichbar (Firewall/DNS), bei HTTP 400 fehlt die PLZ oder sie liegt außerhalb des TransnetBW-Gebiets.

## Lizenz

[MIT](LICENSE) — © 2026 Dietmar Gureth

StromGedacht ist ein Angebot der TransnetBW GmbH. Dieses Modul ist ein privates Community-Projekt und steht in keiner Verbindung zur TransnetBW.
