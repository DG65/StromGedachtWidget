<?php

// Smoke-Test außerhalb von IP-Symcon: simuliert die IPSModule-Basisklasse
// und ruft Update() für alle Datenquellen gegen die echten APIs auf.
// Aufruf: php tests/smoke.php

declare(strict_types=1);

const VARIABLETYPE_INTEGER = 1;
const VARIABLETYPE_FLOAT = 2;
const VARIABLETYPE_STRING = 3;
const KR_READY = 10103;
const IPS_KERNELSTARTED = 10001;
const IS_ACTIVE = 102;

function IPS_VariableProfileExists($name) { return true; }
function IPS_CreateVariableProfile($name, $type) {}
function IPS_SetVariableProfileAssociation($name, $value, $caption, $icon, $color) {}
function IPS_SetVariableProfileValues($name, $min, $max, $step) {}
function IPS_SetVariableProfileDigits($name, $digits) {}
function IPS_SetVariableProfileText($name, $prefix, $suffix) {}
function IPS_GetKernelRunlevel() { return KR_READY; }

class IPSModule
{
    public $properties = [];
    public $values = [];
    public $status = 0;
    public $timer = null;

    public function __construct(array $properties) { $this->properties = $properties; }
    public function Create() {}
    public function ApplyChanges() {}
    public function RegisterPropertyInteger($name, $default) { $this->properties[$name] ??= $default; }
    public function RegisterPropertyString($name, $default) { $this->properties[$name] ??= $default; }
    public function ReadPropertyInteger($name) { return (int) $this->properties[$name]; }
    public function ReadPropertyString($name) { return (string) $this->properties[$name]; }
    public function RegisterTimer($ident, $interval, $script) {}
    public function SetTimerInterval($ident, $interval) { $this->timer = $interval; }
    public function RegisterMessage($sender, $message) {}
    public function MaintainVariable($ident, $name, $type, $profile, $position, $keep) {}
    public function SetValue($ident, $value) { $this->values[$ident] = $value; }
    public function SetStatus($status) { $this->status = $status; }
    public function SendDebug($caption, $message, $format)
    {
        if (getenv('SMOKE_DEBUG')) {
            echo "    DEBUG [$caption] $message\n";
        }
    }
}

require __DIR__ . '/../StromGedachtWidget/module.php';

$cases = [
    'StromGedacht, gültige PLZ (70173)'    => [['Source' => 0, 'ZipCode' => '70173'], IS_ACTIVE, 'State'],
    'StromGedacht, PLZ ohne Daten (10115)' => [['Source' => 0, 'ZipCode' => '10115'], 201, null],
    'GrünstromIndex, gültige PLZ (70173)'  => [['Source' => 1, 'ZipCode' => '70173'], IS_ACTIVE, 'GSI'],
    'GrünstromIndex, PLZ unbekannt (00000)' => [['Source' => 1, 'ZipCode' => '00000'], 201, null],
    'Energy-Charts (ohne PLZ)'             => [['Source' => 2, 'ZipCode' => ''], IS_ACTIVE, 'ECSignal'],
];

$failures = 0;
foreach ($cases as $label => [$props, $expectedStatus, $valueIdent]) {
    $module = new StromGedachtWidget($props + ['UpdateInterval' => 300]);
    $module->Create();
    $module->status = IS_ACTIVE;
    $module->Update();

    $ok = $module->status === $expectedStatus
        && ($valueIdent === null || array_key_exists($valueIdent, $module->values));

    $detail = 'Status ' . $module->status;
    if ($valueIdent !== null && isset($module->values[$valueIdent])) {
        $detail .= ', ' . $valueIdent . ' = ' . var_export($module->values[$valueIdent], true);
    }
    if (isset($module->values['Text'])) {
        $detail .= ', Text = "' . $module->values['Text'] . '"';
    }

    printf("%s %s — %s\n", $ok ? 'PASS' : 'FAIL', $label, $detail);
    if (!$ok) {
        $failures++;
    }
}

exit($failures === 0 ? 0 : 1);
