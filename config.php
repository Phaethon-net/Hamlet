<?php
// Hamlet configuration & session boot.
// IMPORTANT: this file must produce NO output of any kind so that
// session_start() and any later header() calls succeed.

session_name('HAMLETSESS');
session_start();
ini_set('memory_limit', '256M');

$ini = parse_ini_file(__DIR__ . '/config.ini', true);
if ($ini === false) {
    $ini = [];
}

$Sitle   = $ini['site']['title']   ?? 'Hamlet';
$version = $ini['site']['version'] ?? '00000000A';
$favicon = $ini['site']['favicon'] ?? 'favicon.ico';
$today   = date('Ymd');

// Resolve per-server paths
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$pathsKey   = 'paths.' . $serverName;
$pathsCfg   = $ini[$pathsKey] ?? $ini['paths.localhost'] ?? [
    'data_fs'  => 'D:/HVF_Data/',
    'data_url' => '/HVF_Data/',
    'base_url' => '/Hamlet/',
];

$path    = $pathsCfg['data_fs'];
$dataURL = $pathsCfg['data_url'];
$baseURL = $pathsCfg['base_url'];
$file    = $_SERVER['SCRIPT_NAME'] ?? '/Hamlet/index.php';

require_once __DIR__ . '/lib/patient.php';
require_once __DIR__ . '/lib/xml.php';
require_once __DIR__ . '/lib/render.php';
