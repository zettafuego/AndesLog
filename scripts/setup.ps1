$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
$php = (Get-Command php).Source
$ext = Join-Path (Split-Path $php) "ext"
$iniPath = Join-Path $root "config\php.ini"

if (-not (Test-Path $iniPath)) {
  @"
[PHP]
memory_limit = 512M
date.timezone = America/Lima
extension_dir = "$($ext -replace '\\','/')"
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=zip
extension=intl
"@ | Set-Content -Path $iniPath -Encoding ASCII
}

$env:PHPRC = $iniPath
$composer = Join-Path $root "bin\composer.phar"
if (-not (Test-Path $composer)) {
  New-Item -ItemType Directory -Force -Path (Join-Path $root "bin") | Out-Null
  Invoke-WebRequest -Uri "https://getcomposer.org/download/latest-stable/composer.phar" -OutFile $composer
}

Set-Location (Join-Path $root "backend")
& $php $composer install --no-interaction
& $php artisan migrate --force
$check = Join-Path $env:TEMP "andeslog-seeded.php"
@'
<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo App\Models\Operator::query()->exists() ? "yes" : "no";
'@ | Set-Content -Path $check -Encoding ASCII
$seeded = & $php $check
Remove-Item $check
if ($seeded -ne "yes") {
  & $php artisan db:seed --force
}

Set-Location (Join-Path $root "frontend")
npm install

Set-Location (Join-Path $root "desktop")
npm install

Write-Host "Listo. Arranca con scripts\dev.ps1"
