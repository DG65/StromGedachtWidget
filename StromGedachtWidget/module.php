<?php

declare(strict_types=1);

class StromGedachtWidget extends IPSModule
{
    // Zustände laut StromGedacht API:
    // -1 = Supergrün, 1 = Grün, 2 = Gelb (veraltet), 3 = Orange, 4 = Rot
    private const COLORS = [
        -1 => '#00bfa5',
        1  => '#00c853',
        2  => '#ffd600',
        3  => '#ff6d00',
        4  => '#d50000'
    ];

    private const LABELS = [
        -1 => 'Supergrün',
        1  => 'Grün',
        2  => 'Gelb',
        3  => 'Orange',
        4  => 'Rot'
    ];

    private const TEXTS = [
        -1 => 'Besonders viel erneuerbare Energie im Netz – Strom jetzt nutzen',
        1  => 'Normalbetrieb – es ist nichts weiter zu tun',
        2  => 'Angespannte Netzsituation',
        3  => 'Strom sparen bzw. Verbrauch verschieben empfohlen',
        4  => 'Verbrauch reduzieren, um Netzengpass zu vermeiden'
    ];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('ZipCode', '');
        $this->RegisterPropertyInteger('UpdateInterval', 300);

        if (!IPS_VariableProfileExists('SGW.State')) {
            IPS_CreateVariableProfile('SGW.State', VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileAssociation('SGW.State', -1, 'Supergrün', '', 0x00BFA5);
            IPS_SetVariableProfileAssociation('SGW.State', 1, 'Grün', '', 0x00C853);
            IPS_SetVariableProfileAssociation('SGW.State', 2, 'Gelb', '', 0xFFD600);
            IPS_SetVariableProfileAssociation('SGW.State', 3, 'Orange', '', 0xFF6D00);
            IPS_SetVariableProfileAssociation('SGW.State', 4, 'Rot', '', 0xD50000);
        }

        $this->RegisterVariableInteger('State', 'Ampel', 'SGW.State', 1);
        $this->RegisterVariableString('Text', 'Status Text', '', 2);
        $this->RegisterVariableInteger('Updated', 'Aktualisiert', '~UnixTimestamp', 3);
        $this->RegisterVariableString('Widget', 'Anzeige', '~HTMLBox', 4);

        $this->RegisterTimer('UpdateTimer', 0, 'SGW_Update($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        if (IPS_GetKernelRunlevel() !== KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        if (trim($this->ReadPropertyString('ZipCode')) === '') {
            $this->SetStatus(104); // inaktiv: keine PLZ konfiguriert
            $this->SetTimerInterval('UpdateTimer', 0);
            return;
        }

        $this->SetStatus(102);
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
        if ($zip === '') {
            $this->SendDebug('Update', 'Keine Postleitzahl konfiguriert', 0);
            return;
        }

        $data = $this->CallAPI('https://api.stromgedacht.de/v1/now?zip=' . urlencode($zip));
        if ($data === null || !isset($data['state'])) {
            $this->SendDebug('Update', 'Keine Daten erhalten', 0);
            return;
        }

        $state = (int) $data['state'];
        $text = self::TEXTS[$state] ?? 'Unbekannter Zustand (' . $state . ')';

        $this->SetValue('State', $state);
        $this->SetValue('Text', $text);
        $this->SetValue('Updated', time());

        $this->UpdateWidget($state, $text);
    }

    private function CallAPI(string $url)
    {
        $options = [
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: Symcon-StromGedacht\r\nAccept: application/json\r\n",
                'timeout' => 5
            ]
        ];

        $result = @file_get_contents($url, false, stream_context_create($options));

        if ($result === false) {
            $this->SendDebug('API', 'HTTP Fehler beim Abruf von ' . $url, 0);
            return null;
        }

        $data = json_decode($result, true);

        if (!is_array($data)) {
            $this->SendDebug('API', 'JSON Fehler: ' . $result, 0);
            return null;
        }

        $this->SendDebug('API Response', $result, 0);

        return $data;
    }

    private function UpdateWidget(int $state, string $text)
    {
        $color = self::COLORS[$state] ?? '#9e9e9e';
        $label = self::LABELS[$state] ?? 'Unbekannt';

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
                ' . $label . '
            </div>
            <div style="font-size:14px; margin-bottom:10px;">
                ' . htmlspecialchars($text) . '
            </div>
            <div style="font-size:11px; color:gray;">
                ' . date('d.m.Y H:i:s') . '
            </div>
        </div>';

        $this->SetValue('Widget', $html);
    }
}
