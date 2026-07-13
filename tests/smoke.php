<?php

// Smoke-Test außerhalb von IP-Symcon: simuliert die IPSModule-Basisklasse
// und ruft Update() mit verschiedenen Quellen-Kombinationen gegen die
// echten APIs auf.
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
    public $attributes = [];
    public $values = [];
    public $status = 0;
    public $timer = null;

    public function __construct(array $properties) { $this->properties = $properties; }
    public function Create() {}
    public function ApplyChanges() {}
    public function RegisterPropertyBoolean($name, $default) { $this->properties[$name] ??= $default; }
    public function RegisterPropertyInteger($name, $default) { $this->properties[$name] ??= $default; }
    public function RegisterPropertyString($name, $default) { $this->properties[$name] ??= $default; }
    public function ReadPropertyBoolean($name) { return (bool) $this->properties[$name]; }
    public function ReadPropertyInteger($name) { return (int) $this->properties[$name]; }
    public function ReadPropertyString($name) { return (string) $this->properties[$name]; }
    public function RegisterAttributeBoolean($name, $default) { $this->attributes[$name] ??= $default; }
    public function RegisterAttributeString($name, $default) { $this->attributes[$name] ??= $default; }
    public function ReadAttributeBoolean($name) { return (bool) $this->attributes[$name]; }
    public function ReadAttributeString($name) { return (string) $this->attributes[$name]; }
    public function WriteAttributeBoolean($name, $value) { $this->attributes[$name] = $value; }
    public function WriteAttributeString($name, $value) { $this->attributes[$name] = $value; }
    public function RegisterTimer($ident, $interval, $script) {}
    public function SetTimerInterval($ident, $interval) { $this->timer = $interval; }
    public function RegisterMessage($sender, $message) {}
    public function MaintainVariable($ident, $name, $type, $profile, $position, $keep) {}
    public function SetValue($ident, $value) { $this->values[$ident] = $value; }
    public function GetValue($ident) { return $this->values[$ident] ?? null; }
    public function SetStatus($status) { $this->status = $status; }

    public function SendDebug($caption, $message, $format)
    {
        if (getenv('SMOKE_DEBUG')) {
            echo "    DEBUG [$caption] $message\n";
        }
    }
}

require __DIR__ . '/../StromGedachtWidget/module.php';

// [Properties, erwarteter Status, erwartete Variablen, verbotene Variablen]
$cases = [
    'Alle Quellen, gültige PLZ (70173)' => [
        ['EnableStromGedacht' => true, 'EnableGSI' => true, 'EnableEnergyCharts' => true, 'ZipCode' => '70173'],
        IS_ACTIVE, ['State', 'Text', 'GSI', 'ECSignal', 'ECShare', 'Updated', 'Widget'], []
    ],
    'Alle Quellen, PLZ ohne StromGedacht-Daten (10115)' => [
        ['EnableStromGedacht' => true, 'EnableGSI' => true, 'EnableEnergyCharts' => true, 'ZipCode' => '10115'],
        IS_ACTIVE, ['GSI', 'ECSignal', 'Widget'], ['State']
    ],
    'Nur StromGedacht + GSI, PLZ unbekannt (00000)' => [
        ['EnableStromGedacht' => true, 'EnableGSI' => true, 'EnableEnergyCharts' => false, 'ZipCode' => '00000'],
        201, ['Widget'], ['State', 'GSI', 'ECSignal']
    ],
    'Nur Energy-Charts, ohne PLZ' => [
        ['EnableStromGedacht' => false, 'EnableGSI' => false, 'EnableEnergyCharts' => true, 'ZipCode' => ''],
        IS_ACTIVE, ['ECSignal', 'ECShare', 'Widget'], ['State', 'GSI']
    ],
    'Nur StromGedacht, gültige PLZ (70173)' => [
        ['EnableStromGedacht' => true, 'EnableGSI' => false, 'EnableEnergyCharts' => false, 'ZipCode' => '70173'],
        IS_ACTIVE, ['State', 'Text', 'Widget'], ['GSI', 'ECSignal']
    ],
];

$failures = 0;
foreach ($cases as $label => [$props, $expectedStatus, $expectedIdents, $forbiddenIdents]) {
    $module = new StromGedachtWidget($props + ['UpdateInterval' => 300]);
    $module->Create();
    $module->status = IS_ACTIVE;
    $module->Update();

    $problems = [];
    if ($module->status !== $expectedStatus) {
        $problems[] = 'Status ' . $module->status . ' statt ' . $expectedStatus;
    }
    foreach ($expectedIdents as $ident) {
        if (!array_key_exists($ident, $module->values)) {
            $problems[] = $ident . ' fehlt';
        }
    }
    foreach ($forbiddenIdents as $ident) {
        if (array_key_exists($ident, $module->values)) {
            $problems[] = $ident . ' gesetzt, obwohl nicht erwartet';
        }
    }

    $summary = [];
    foreach (['State', 'GSI', 'ECSignal', 'ECShare'] as $ident) {
        if (isset($module->values[$ident])) {
            $summary[] = $ident . ' = ' . var_export($module->values[$ident], true);
        }
    }

    printf(
        "%s %s — Status %d%s%s\n",
        count($problems) === 0 ? 'PASS' : 'FAIL',
        $label,
        $module->status,
        $summary === [] ? '' : ', ' . implode(', ', $summary),
        $problems === [] ? '' : ' [' . implode('; ', $problems) . ']'
    );
    if (count($problems) > 0) {
        $failures++;
    }
}

exit($failures === 0 ? 0 : 1);
