<?php

declare(strict_types=1);

class StromGedachtWidget extends IPSModule
{
    private const SOURCE_STROMGEDACHT = 0;
    private const SOURCE_GSI = 1;
    private const SOURCE_ENERGYCHARTS = 2;

    // Instanz-Status
    private const STATUS_NO_ZIP = 104;
    private const STATUS_ZIP_UNKNOWN = 201;
    private const STATUS_API_ERROR = 202;

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
        2  => 'Hoher Anteil erneuerbarer Energien – Strom jetzt nutzen'
    ];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('Source', self::SOURCE_STROMGEDACHT);
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

        $source = $this->ReadPropertyInteger('Source');

        // Variablen je Quelle pflegen (nicht benötigte werden entfernt)
        $this->MaintainVariable('State', 'Ampel', VARIABLETYPE_INTEGER, 'SGW.State', 1, $source === self::SOURCE_STROMGEDACHT);
        $this->MaintainVariable('GSI', 'GrünstromIndex', VARIABLETYPE_FLOAT, 'SGW.GSI', 1, $source === self::SOURCE_GSI);
        $this->MaintainVariable('ECSignal', 'Stromampel', VARIABLETYPE_INTEGER, 'SGW.ECSignal', 1, $source === self::SOURCE_ENERGYCHARTS);
        $this->MaintainVariable('ECShare', 'EE-Anteil', VARIABLETYPE_FLOAT, 'SGW.Percent', 2, $source === self::SOURCE_ENERGYCHARTS);
        $this->MaintainVariable('Text', 'Status Text', VARIABLETYPE_STRING, '', 3, true);
        $this->MaintainVariable('Updated', 'Aktualisiert', VARIABLETYPE_INTEGER, '~UnixTimestamp', 4, true);
        $this->MaintainVariable('Widget', 'Anzeige', VARIABLETYPE_STRING, '~HTMLBox', 5, true);

        $needsZip = $source !== self::SOURCE_ENERGYCHARTS;
        if ($needsZip && trim($this->ReadPropertyString('ZipCode')) === '') {
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
        switch ($this->ReadPropertyInteger('Source')) {
            case self::SOURCE_GSI:
                $this->UpdateGSI();
                break;
            case self::SOURCE_ENERGYCHARTS:
                $this->UpdateEnergyCharts();
                break;
            default:
                $this->UpdateStromGedacht();
        }
    }

    private function UpdateStromGedacht()
    {
        $zip = trim($this->ReadPropertyString('ZipCode'));
        if ($zip === '') {
            return;
        }

        [$code, $body] = $this->HttpGet('https://api.stromgedacht.de/v1/now?zip=' . urlencode($zip));

        // Die API antwortet mit 400 + Hinweistext, wenn sie die PLZ nicht
        // kennt bzw. keine Daten für das PLZ-Gebiet vorliegen
        if ($code === 400 && stripos((string) $body, 'no data') !== false) {
            $this->ReportZipUnknown($zip);
            return;
        }

        $data = $this->DecodeJson($code, $body);
        if ($data === null || !isset($data['state'])) {
            $this->ReportApiError();
            return;
        }

        $state = (int) $data['state'];
        $text = self::SG_TEXTS[$state] ?? 'Unbekannter Zustand (' . $state . ')';

        $this->SetStatus(IS_ACTIVE);
        $this->SetValue('State', $state);
        $this->SetValue('Text', $text);
        $this->SetValue('Updated', time());

        $this->RenderWidget(
            self::SG_COLORS[$state] ?? '#9e9e9e',
            self::SG_LABELS[$state] ?? 'Unbekannt',
            $text,
            'StromGedacht'
        );
    }

    private function UpdateGSI()
    {
        $zip = trim($this->ReadPropertyString('ZipCode'));
        if ($zip === '') {
            return;
        }

        [$code, $body] = $this->HttpGet('https://api.corrently.io/v2.0/gsi/prediction?zip=' . urlencode($zip));

        $data = $this->DecodeJson($code, $body);
        if ($data === null) {
            $this->ReportApiError();
            return;
        }

        // Bei unbekannter PLZ liefert die API ein leeres forecast-Array
        $forecast = $data['forecast'] ?? [];
        if (count($forecast) === 0) {
            $this->ReportZipUnknown($zip);
            return;
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

        $gsi = (float) ($current['gsi'] ?? 0);

        if ($gsi >= 66) {
            $color = '#00c853';
            $text = 'Hoher Grünstrom-Anteil in der Region – Strom jetzt nutzen';
        } elseif ($gsi >= 33) {
            $color = '#ffd600';
            $text = 'Durchschnittlicher Grünstrom-Anteil in der Region';
        } else {
            $color = '#d50000';
            $text = 'Niedriger Grünstrom-Anteil in der Region';
        }

        $this->SetStatus(IS_ACTIVE);
        $this->SetValue('GSI', $gsi);
        $this->SetValue('Text', $text);
        $this->SetValue('Updated', time());

        $this->RenderWidget($color, number_format($gsi, 0) . ' %', $text, 'GrünstromIndex');
    }

    private function UpdateEnergyCharts()
    {
        [$code, $body] = $this->HttpGet('https://api.energy-charts.info/signal?country=de');

        $data = $this->DecodeJson($code, $body);
        if ($data === null || !isset($data['unix_seconds'], $data['signal'])) {
            $this->ReportApiError();
            return;
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
        $text = self::EC_TEXTS[$signal] ?? 'Unbekanntes Signal (' . $signal . ')';

        $this->SetStatus(IS_ACTIVE);
        $this->SetValue('ECSignal', $signal);
        $this->SetValue('ECShare', $share);
        $this->SetValue('Text', $text);
        $this->SetValue('Updated', time());

        $this->RenderWidget(
            self::EC_COLORS[$signal] ?? '#9e9e9e',
            self::EC_LABELS[$signal] ?? 'Unbekannt',
            $text . ' (EE-Anteil: ' . number_format($share, 1, ',', '.') . ' %)',
            'Energy-Charts (Deutschland)'
        );
    }

    private function ReportZipUnknown(string $zip)
    {
        $message = 'Für die Postleitzahl ' . $zip . ' liegen keine Daten vor';
        $this->SendDebug('Update', $message, 0);
        $this->SetStatus(self::STATUS_ZIP_UNKNOWN);
        $this->SetValue('Text', $message);
        $this->RenderWidget('#9e9e9e', 'Keine Daten', $message, '');
    }

    private function ReportApiError()
    {
        $this->SendDebug('Update', 'API nicht erreichbar oder ungültige Antwort', 0);
        $this->SetStatus(self::STATUS_API_ERROR);
    }

    // Liefert [HTTP-Code, Body]; Code 0 = Verbindungsfehler
    private function HttpGet(string $url): array
    {
        [$code, $body] = $this->TryHttpGet($url, false);

        // PHP-Streams haben keinen IPv6→IPv4-Fallback; bei Verbindungsfehler
        // (z. B. nicht erreichbarer IPv6-Endpunkt) erzwungen über IPv4 wiederholen
        if ($body === null) {
            $this->SendDebug('API', 'Verbindungsfehler, wiederhole über IPv4: ' . $url, 0);
            [$code, $body] = $this->TryHttpGet($url, true);
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

    private function RenderWidget(string $color, string $label, string $text, string $source)
    {
        $html = '
        <div style="text-align:center; font-family:Arial; padding:20px;">
            <div style="
                width:80px;
                height:80px;
                border-radius:50%;
                margin:0 auto 15px auto;
                background:' . $color . ';
                box-shadow:0 0 20px ' . $color . ';
            "></div>
            <div style="font-size:22px; font-weight:bold; color:' . $color . '; margin-bottom:10px;">
                ' . htmlspecialchars($label) . '
            </div>
            <div style="font-size:14px; margin-bottom:10px;">
                ' . htmlspecialchars($text) . '
            </div>
            <div style="font-size:11px; color:gray;">
                ' . ($source !== '' ? htmlspecialchars($source) . ' &middot; ' : '') . date('d.m.Y H:i:s') . '
            </div>
        </div>';

        $this->SetValue('Widget', $html);
    }
}
