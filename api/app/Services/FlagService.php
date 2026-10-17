<?php

namespace App\Services;

class FlagService
{
    private const CATALOG = [
        'FLAG-01' => ['description' => 'Reconnaissance', 'points' => 50],
        'FLAG-02' => ['description' => 'Container image discovery', 'points' => 50],
        'FLAG-03' => ['description' => 'Historical Docker layer discovery', 'points' => 100],
        'FLAG-04' => ['description' => 'Training secret recovery', 'points' => 100],
        'FLAG-05' => ['description' => 'Cryptographic construction identification', 'points' => 100],
        'FLAG-06' => ['description' => 'Authorization artifact discovery', 'points' => 100],
        'FLAG-07' => ['description' => 'Authorization decision forensic evidence', 'points' => 150],
        'FLAG-08' => ['description' => 'Simulated unauthorized release', 'points' => 200],
        'FLAG-09' => ['description' => 'Forensic timeline reconstruction', 'points' => 150],
        'FLAG-10' => ['description' => 'Secure remediation verification', 'points' => 200],
    ];

    public function catalog(): array
    {
        return self::CATALOG;
    }

    public function valueFor(string $code): ?string
    {
        if (!array_key_exists($code, self::CATALOG)) {
            return null;
        }

        $file = config('flags.file', '/run/secrets/lab_flags');
        if (!is_readable($file)) {
            return null;
        }

        $values = parse_ini_file($file, false, INI_SCANNER_RAW);
        if (!is_array($values)) {
            return null;
        }

        $key = str_replace('-', '_', $code);
        $value = $values[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
