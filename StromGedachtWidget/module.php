<?php

<?php

class StromGedachtWidget extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger("UpdateInterval", 300);

        // ✅ FIX: direkter Methodenaufruf
        $this->RegisterTimer(
            "UpdateTimer",
            0,
            '$this->Update();'
        );

        $this->RegisterVariableInteger("State", "Ampel", "", 1);
        $this->RegisterVariableString("Text", "Status Text", "", 2);
        $this->RegisterVariableString("Updated", "Aktualisiert", "", 3);
        $this->RegisterVariableString("Widget", "Anzeige", "~HTMLBox", 4);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $interval = $this->ReadPropertyInteger("UpdateInterval") * 1000;
        $this->SetTimerInterval("UpdateTimer", $interval);

        $this->Update();
    }

    public function Update()
    {
        $data = $this->CallAPI();

        if ($data === null) {
            $this->SendDebug("Update", "Keine Daten erhalten", 0);
            return;
        }

        $map = [
            "GREEN" => 0,
            "YELLOW" => 1,
            "RED" => 2
        ];

        $state = $map[$data["state"]] ?? 1;
        $text = $data["details"]["recommendation"] ?? "Keine Info";

        SetValue($this->GetIDForIdent("State"), $state);
        SetValue($this->GetIDForIdent("Text"), $text);
        SetValue($this->GetIDForIdent("Updated"), date("d.m.Y H:i:s"));

        $this->UpdateWidget($state, $text);
    }

    // ✅ Profi API Call mit Headern + Debug
    private function CallAPI()
    {
        $url = "https://api.stromgedacht.de/v1/now";

        $options = [
            "http" => [
                "method" => "GET",
                "header" =>
                    "User-Agent: Symcon-StromGedacht\r\n" .
                    "Accept: application/json\r\n",
                "timeout" => 5
            ]
        ];

        $context = stream_context_create($options);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $this->SendDebug("API", "HTTP Fehler", 0);
            return null;
        }

        $data = json_decode($result, true);

        if (!$data) {
            $this->SendDebug("API", "JSON Fehler", 0);
            return null;
        }

        // ✅ Debug Output (sehr hilfreich)
        $this->SendDebug("API Response", json_encode($data), 0);

        return $data;
    }

    // ✅ Visuelles Widget (verbesserte Version)
    private function UpdateWidget($state, $text)
    {
        $colors = [
            0 => "#00c853",
            1 => "#ffd600",
            2 => "#d50000"
        ];

        $labels = [
            0 => "Grün",
            1 => "Gelb",
            2 => "Rot"
        ];

        $html = '
        <div style="
            text-align:center;
            font-family:Arial;
            padding:20px;
        ">

            <div style="
                width:80px;
                height:80px;
                border-radius:50%;
                margin:0 auto 15px auto;
                background:' . $colors[$state] . ';
                box-shadow:0 0 20px ' . $colors[$state] . ';
            ">
            </div>

            <div style="
                font-size:22px;
                font-weight:bold;
                color:' . $colors[$state] . ';
                margin-bottom:10px;
            ">
                ' . $labels[$state] . '
            </div>

            <div style="
                font-size:14px;
                margin-bottom:10px;
            ">
                ' . $text . '
            </div>

            <div style="
                font-size:11px;
                color:gray;
            ">
                ' . date("d.m.Y H:i:s") . '
            </div>

        </div>';

        SetValue($this->GetIDForIdent("Widget"), $html);
    }
}
