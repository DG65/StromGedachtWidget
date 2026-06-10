<?php

declare(strict_types=1);

class StromGedachtWidget extends IPSModule
{
    // Instanz-Status
    private const STATUS_NO_ZIP = 104;
    private const STATUS_ZIP_UNKNOWN = 201;
    private const STATUS_API_ERROR = 202;
    private const STATUS_NO_SOURCE = 203;

    private const COLOR_GREY = '#9e9e9e';

    // Zustände laut StromGedacht API:
    // -1 = Supergrün, 1 = Grün, 2 = Gelb (veraltet), 3 = Orange, 4 = Rot
    private const SG_COLORS = [
        -1 => '#00bfa5',
        1  => '#00c853',
        2  => '#ffd600',
        3  => '#ff6d00',
        4  => '#d50000'
    ];

    private const SG_LABELS = [
        -1 => 'Supergrün',
        1  => 'Grün',
        2  => 'Gelb',
        3  => 'Orange',
        4  => 'Rot'
    ];

    private const SG_TEXTS = [
        -1 => 'Besonders viel erneuerbare Energie im Netz – Strom jetzt nutzen',
        1  => 'Normalbetrieb – es ist nichts weiter zu tun',
        2  => 'Angespannte Netzsituation',
        3  => 'Strom sparen bzw. Verbrauch verschieben empfohlen',
        4  => 'Verbrauch reduzieren, um Netzengpass zu vermeiden'
    ];

    // Signale laut Energy-Charts API:
    // -1 = Rot (Netzengpass), 0 = Rot (wenig EE), 1 = Gelb, 2 = Grün
    private const EC_COLORS = [
        -1 => '#d50000',
        0  => '#d50000',
        1  => '#ffd600',
        2  => '#00c853'
    ];

    private const EC_LABELS = [
        -1 => 'Rot (Netzengpass)',
        0  => 'Rot',
        1  => 'Gelb',
        2  => 'Grün'
    ];

    private const EC_TEXTS = [
        -1 => 'Netzengpass – Verbrauch reduzieren',
        0  => 'Niedriger Anteil erneuerbarer Energien',
        1  => 'Durchschnittlicher Anteil erneuerbarer Energien',
        2  => 'Hoher Anteil erneuerbarer Energien'
    ];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('EnableStromGedacht', true);
        $this->RegisterPropertyBoolean('EnableGSI', true);
        $this->RegisterPropertyBoolean('EnableEnergyCharts', true);
        $this->RegisterPropertyString('ZipCode', '');
        $this->RegisterPropertyInteger('UpdateInterval', 300);

        // Profile bewusst getrennt je Datenquelle
        if (!IPS_VariableProfileExists('SGW.State')) {
            IPS_CreateVariableProfile('SGW.State', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('SGW.State', -1, 'Supergrün', '', 0x00BFA5);
            IPS_SetVariableProfileAssociation('SGW.State', 1, 'Grün', '', 0x00C853);
            IPS_SetVariableProfileAssociation('SGW.State', 2, 'Gelb', '', 0xFFD600);
            IPS_SetVariableProfileAssociation('SGW.State', 3, 'Orange', '', 0xFF6D00);
            IPS_SetVariableProfileAssociation('SGW.State', 4, 'Rot', '', 0xD50000);
        }

        if (!IPS_VariableProfileExists('SGW.GSI')) {
            IPS_CreateVariableProfile('SGW.GSI', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileValues('SGW.GSI', 0, 100, 1);
            IPS_SetVariableProfileDigits('SGW.GSI', 1);
            IPS_SetVariableProfileText('SGW.GSI', '', ' %');
        }

        if (!IPS_VariableProfileExists('SGW.ECSignal')) {
            IPS_CreateVariableProfile('SGW.ECSignal', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('SGW.ECSignal', -1, 'Rot (Netzengpass)', '', 0xD50000);
            IPS_SetVariableProfileAssociation('SGW.ECSignal', 0, 'Rot', '', 0xD50000);
            IPS_SetVariableProfileAssociation('SGW.ECSignal', 1, 'Gelb', '', 0xFFD600);
            IPS_SetVariableProfileAssociation('SGW.ECSignal', 2, 'Grün', '', 0x00C853);
        }

        if (!IPS_VariableProfileExists('SGW.Percent')) {
            IPS_CreateVariableProfile('SGW.Percent', VARIABLETYPE_FLOAT);
            IPS_SetVariableProfileValues('SGW.Percent', 0, 100, 1);
            IPS_SetVariableProfileDigits('SGW.Percent', 1);
            IPS_SetVariableProfileText('SGW.Percent', '', ' %');
        }

        $this->RegisterTimer('UpdateTimer', 0, 'SGW_Update($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $sg = $this->ReadPropertyBoolean('EnableStromGedacht');
        $gsi = $this->ReadPropertyBoolean('EnableGSI');
        $ec = $this->ReadPropertyBoolean('EnableEnergyCharts');

        // Variablen je aktivierter Quelle pflegen (nicht benötigte werden entfernt)
        $this->MaintainVariable('State', 'Ampel', VARIABLETYPE_INTEGER, 'SGW.State', 1, $sg);
        $this->MaintainVariable('Text', 'Status Text', VARIABLETYPE_STRING, '', 2, $sg);
        $this->MaintainVariable('GSI', 'GrünstromIndex', VARIABLETYPE_FLOAT, 'SGW.GSI', 3, $gsi);
        $this->MaintainVariable('ECSignal', 'Stromampel', VARIABLETYPE_INTEGER, 'SGW.ECSignal', 4, $ec);
        $this->MaintainVariable('ECShare', 'EE-Anteil', VARIABLETYPE_FLOAT, 'SGW.Percent', 5, $ec);
        $this->MaintainVariable('Updated', 'Aktualisiert', VARIABLETYPE_INTEGER, '~UnixTimestamp', 6, true);
        $this->MaintainVariable('Widget', 'Anzeige', VARIABLETYPE_STRING, '~HTMLBox', 7, true);

        if (!$sg && !$gsi && !$ec) {
            $this->SetStatus(self::STATUS_NO_SOURCE);
            $this->SetTimerInterval('UpdateTimer', 0);
            return;
        }

        if (($sg || $gsi) && trim($this->ReadPropertyString('ZipCode')) === '') {
            $this->SetStatus(self::STATUS_NO_ZIP);
            $this->SetTimerInterval('UpdateTimer', 0);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);
        $this->Update();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
        }
    }

    public function Update()
    {
        $zip = trim($this->ReadPropertyString('ZipCode'));

        $columns = [];
        $ok = 0;
        $zipUnknown = 0;
        $failed = 0;

        if ($this->ReadPropertyBoolean('EnableStromGedacht')) {
            $columns[] = $this->FetchStromGedacht($zip, $ok, $zipUnknown, $failed);
        }
        if ($this->ReadPropertyBoolean('EnableGSI')) {
            $columns[] = $this->FetchGSI($zip, $ok, $zipUnknown, $failed);
        }
        if ($this->ReadPropertyBoolean('EnableEnergyCharts')) {
            $columns[] = $this->FetchEnergyCharts($ok, $failed);
        }

        if (count($columns) === 0) {
            return;
        }

        if ($ok > 0) {
            // Mindestens eine Quelle liefert: Instanz bleibt aktiv,
            // ausgefallene Quellen sind im Widget als "Keine Daten" sichtbar
            $this->SetStatus(IS_ACTIVE);
            $this->SetValue('Updated', time());
        } elseif ($failed === 0 && $zipUnknown > 0) {
            $this->SetStatus(self::STATUS_ZIP_UNKNOWN);
        } else {
            $this->SetStatus(self::STATUS_API_ERROR);
        }

        $this->RenderWidget($columns);
    }

    private function FetchStromGedacht(string $zip, int &$ok, int &$zipUnknown, int &$failed): array
    {
        $column = ['title' => 'StromGedacht', 'color' => self::COLOR_GREY, 'label' => 'Keine Daten', 'text' => ''];

        if ($zip === '') {
            $column['text'] = 'Postleitzahl fehlt';
            $zipUnknown++;
            return $column;
        }

        [$code, $body] = $this->HttpGet('https://api.stromgedacht.de/v1/now?zip=' . urlencode($zip));

        // Die API antwortet mit 400 + Hinweistext, wenn sie die PLZ nicht
        // kennt bzw. keine Daten für das PLZ-Gebiet vorliegen
        if ($code === 400 && stripos((string) $body, 'no data') !== false) {
            $message = 'Für die Postleitzahl ' . $zip . ' liegen keine Daten vor';
            $this->SendDebug('StromGedacht', $message, 0);
            $this->SetValue('Text', $message);
            $column['text'] = $message;
            $zipUnknown++;
            return $column;
        }

        $data = $this->DecodeJson($code, $body);
        if ($data === null || !isset($data['state'])) {
            $this->SendDebug('StromGedacht', 'API nicht erreichbar oder ungültige Antwort', 0);
            $column['text'] = 'API nicht erreichbar';
            $failed++;
            return $column;
        }

        $state = (int) $data['state'];
        $text = self::SG_TEXTS[$state] ?? 'Unbekannter Zustand (' . $state . ')';

        $this->SetValue('State', $state);
        $this->SetValue('Text', $text);

        $ok++;

        return [
            'title' => 'StromGedacht',
            'color' => self::SG_COLORS[$state] ?? self::COLOR_GREY,
            'label' => self::SG_LABELS[$state] ?? 'Unbekannt',
            'text'  => $text
        ];
    }

    private function FetchGSI(string $zip, int &$ok, int &$zipUnknown, int &$failed): array
    {
        $column = ['title' => 'GrünstromIndex', 'color' => self::COLOR_GREY, 'label' => 'Keine Daten', 'text' => ''];

        if ($zip === '') {
            $column['text'] = 'Postleitzahl fehlt';
            $zipUnknown++;
            return $column;
        }

        [$code, $body] = $this->HttpGet('https://api.corrently.io/v2.0/gsi/prediction?zip=' . urlencode($zip));

        $data = $this->DecodeJson($code, $body);
        if ($data === null) {
            $this->SendDebug('GrünstromIndex', 'API nicht erreichbar oder ungültige Antwort', 0);
            $column['text'] = 'API nicht erreichbar';
            $failed++;
            return $column;
        }

        // Bei unbekannter PLZ liefert die API ein leeres forecast-Array
        $forecast = $data['forecast'] ?? [];
        if (count($forecast) === 0) {
            $message = 'Für die Postleitzahl ' . $zip . ' liegen keine Daten vor';
            $this->SendDebug('GrünstromIndex', $message, 0);
            $column['text'] = $message;
            $zipUnknown++;
            return $column;
        }

        // Eintrag suchen, dessen Zeitfenster jetzt abdeckt (sonst den ersten)
        $now = time();
        $current = $forecast[0];
        foreach ($forecast as $entry) {
            $start = (int) ($entry['timeframe']['start'] ?? 0) / 1000;
            $end = (int) ($entry['timeframe']['end'] ?? 0) / 1000;
            if ($start <= $now && $now < $end) {
                $current = $entry;
                break;
            }
        }

        $gsiValue = (float) ($current['gsi'] ?? 0);

        if ($gsiValue >= 66) {
            $color = '#00c853';
            $text = 'Hoher Grünstrom-Anteil in der Region';
        } elseif ($gsiValue >= 33) {
            $color = '#ffd600';
            $text = 'Durchschnittlicher Grünstrom-Anteil in der Region';
        } else {
            $color = '#d50000';
            $text = 'Niedriger Grünstrom-Anteil in der Region';
        }

        $this->SetValue('GSI', $gsiValue);

        $ok++;

        return [
            'title' => 'GrünstromIndex',
            'color' => $color,
            'label' => number_format($gsiValue, 0) . ' %',
            'text'  => $text
        ];
    }

    private function FetchEnergyCharts(int &$ok, int &$failed): array
    {
        $column = ['title' => 'Energy-Charts', 'color' => self::COLOR_GREY, 'label' => 'Keine Daten', 'text' => ''];

        [$code, $body] = $this->HttpGet('https://api.energy-charts.info/signal?country=de');

        $data = $this->DecodeJson($code, $body);
        if ($data === null || !isset($data['unix_seconds'], $data['signal'])) {
            $this->SendDebug('Energy-Charts', 'API nicht erreichbar oder ungültige Antwort', 0);
            $column['text'] = 'API nicht erreichbar';
            $failed++;
            return $column;
        }

        // Letzten 15-Minuten-Slot suchen, der bereits begonnen hat
        $now = time();
        $index = 0;
        foreach ($data['unix_seconds'] as $i => $ts) {
            if ($ts > $now) {
                break;
            }
            $index = $i;
        }

        $signal = (int) ($data['signal'][$index] ?? 0);
        $share = (float) ($data['share'][$index] ?? 0);

        $this->SetValue('ECSignal', $signal);
        $this->SetValue('ECShare', $share);

        $ok++;

        return [
            'title' => 'Energy-Charts',
            'color' => self::EC_COLORS[$signal] ?? self::COLOR_GREY,
            'label' => self::EC_LABELS[$signal] ?? 'Unbekannt',
            'text'  => (self::EC_TEXTS[$signal] ?? 'Unbekanntes Signal') . ' (EE-Anteil: ' . number_format($share, 1, ',', '.') . ' %)'
        ];
    }

    // Liefert [HTTP-Code, Body]; Code 0 = Verbindungsfehler
    private function HttpGet(string $url): array
    {
        [$code, $body] = $this->TryHttpGet($url, false);

        // PHP-Streams haben keinen IPv6→IPv4-Fallback; bei Verbindungsfehler
        // (z. B. nicht erreichbarer IPv6-Endpunkt) erzwungen über IPv4 wiederholen.
        // Ein leerer Body trotz Statuscode bedeutet eine vorzeitig beendete
        // Verbindung und wird ebenfalls als Fehler behandelt
        if ($body === null || trim($body) === '') {
            $this->SendDebug('API', 'Verbindungsfehler, wiederhole über IPv4: ' . $url, 0);
            [$code, $body] = $this->TryHttpGet($url, true);
            if ($body !== null && trim($body) === '') {
                $body = null;
            }
        }

        $this->SendDebug('API', 'HTTP ' . $code . ' ' . $url, 0);
        if ($body !== null) {
            $this->SendDebug('API Response', $body, 0);
        }

        return [$code, $body];
    }

    private function TryHttpGet(string $url, bool $forceIPv4): array
    {
        $options = [
            'http' => [
                'method'        => 'GET',
                'header'        => "User-Agent: Symcon-StromGedacht\r\nAccept: application/json\r\n",
                'timeout'       => 10,
                'ignore_errors' => true
            ]
        ];

        if ($forceIPv4) {
            $options['socket'] = ['bindto' => '0:0'];
        }

        $body = @file_get_contents($url, false, stream_context_create($options));

        $code = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                $code = (int) $match[1];
            }
        }

        return [$code, $body === false ? null : $body];
    }

    private function DecodeJson(int $code, ?string $body): ?array
    {
        if ($code !== 200 || $body === null) {
            return null;
        }

        $data = json_decode($body, true);

        return is_array($data) ? $data : null;
    }

    private function RenderWidget(array $columns)
    {
        $html = '<div style="font-family:Arial; padding:16px; text-align:center;">';
        $html .= '<div style="display:flex; justify-content:center; gap:28px; flex-wrap:wrap;">';

        foreach ($columns as $column) {
            $html .= '
            <div style="min-width:150px; max-width:200px;">
                <div style="font-size:12px; color:gray; margin-bottom:10px;">
                    ' . htmlspecialchars($column['title']) . '
                </div>
                <div style="
                    width:60px;
                    height:60px;
                    border-radius:50%;
                    margin:0 auto 12px auto;
                    background:' . $column['color'] . ';
                    box-shadow:0 0 16px ' . $column['color'] . ';
                "></div>
                <div style="font-size:18px; font-weight:bold; color:' . $column['color'] . '; margin-bottom:8px;">
                    ' . htmlspecialchars($column['label']) . '
                </div>
                <div style="font-size:12px;">
                    ' . htmlspecialchars($column['text']) . '
                </div>
            </div>';
        }

        $html .= '</div>';
        $html .= '<div style="font-size:11px; color:gray; margin-top:14px;">' . date('d.m.Y H:i:s') . '</div>';
        $html .= '</div>';

        $this->SetValue('Widget', $html);
    }
}
