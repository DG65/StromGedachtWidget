<?php

class StromGedachtWidget extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Einstellungen
        $this->RegisterPropertyInteger("UpdateInterval", 300);

        // Timer
        $this->RegisterTimer("UpdateTimer", 0, 'SGW_Update($_IPS["TARGET"]);');

        // Profil
        $this->CreateProfile();

        // Variablen
        $this->RegisterVariableInteger("State", "Ampel", "SGW.State", 1);
        $this->RegisterVariableString("Text", "Status Text", "", 2);
        $this->RegisterVariableString("Updated", "Aktualisiert", "", 3);

        // HTML Widget
        $this->RegisterVariableString("Widget", "Anzeige", "~HTMLBox", 4);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $interval = $this->ReadPropertyInteger("UpdateInterval") * 1000;
        $this->SetTimerInterval("UpdateTimer", $interval);

        $this->Update();
    }

    private function CreateProfile()
    {
        if (!IPS_VariableProfileExists("SGW.State")) {
            IPS_CreateVariableProfile("SGW.State", VARIABLETYPE_INTEGER);
            IPS_SetVariableProfileValues("SGW.State", 0, 2, 0);

            IPS_SetVariableProfileAssociation("SGW.State", 0, "Grün", "", 0x00FF00);
            IPS_SetVariableProfileAssociation("SGW.State", 1, "Gelb", "", 0xFFFF00);
            IPS_SetVariableProfileAssociation("SGW.State", 2, "Rot", "", 0xFF0000);
        }
    }

    public function Update()
    {
        $url = "https://api.stromgedacht.de/v1/now";

        $context = stream_context_create([
            "http" => [
                "timeout" => 5
            ]
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $this->SendDebug("API", "Fehler beim Abruf", 0);
            return;
        }

        $data = json_decode($result, true);
        if (!$data) return;

        $stateMap = [
            "GREEN" => 0,
            "YELLOW" => 1,
            "RED" => 2
        ];

        $state = $stateMap[$data["state"]] ?? 1;
        $text = $data["details"]["recommendation"] ?? "Keine Info";

        SetValue($this->GetIDForIdent("State"), $state);
        SetValue($this->GetIDForIdent("Text"), $text);
        SetValue($this->GetIDForIdent("Updated"), date("d.m.Y H:i:s"));

        $this->UpdateWidget($state, $text);
    }

    private function UpdateWidget($state, $text)
    {
        $colors = [
            0 => "#00c853",
            1 => "#ffd600",
            2 => "#d50000"
        ];

        $icons = [
            0 => "🟢",
            1 => "🟡",
            2 => "🔴"
        ];

        $html = '
        <div style="
            text-align:center;
            font-family:Arial;
            padding:20px;
        ">
            <div style="
                font-size:60px;
                margin-bottom:10px;
            ">
                ' . $icons[$state] . '
            </div>

            <div style="
                font-size:24px;
                font-weight:bold;
                color:' . $colors[$state] . ';
                margin-bottom:10px;
            ">
                Stromstatus
            </div>

            <div style="
                font-size:16px;
                margin-bottom:10px;
            ">
                ' . $text . '
            </div>

            <div style="
                font-size:12px;
                color:gray;
            ">
                Aktualisiert: ' . date("d.m.Y H:i:s") . '
            </div>
        </div>';

        SetValue($this->GetIDForIdent("Widget"), $html);
    }

    public function ManualUpdate()
    {
        $this->Update();
    }
}
