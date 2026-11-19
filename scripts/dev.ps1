$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
$phpIni = Join-Path $root "config\php.ini"
$php = (Get-Command php).Source
$backend = Join-Path $root "backend"
$frontend = Join-Path $root "frontend"

if (-not (Test-Path (Join-Path $backend "vendor"))) {
  Write-Error "Falta backend\vendor. Corre primero scripts\setup.ps1"
}

$backendCmd = "`$env:PHPRC='$phpIni'; Set-Location '$backend'; & '$php' artisan serve --host=127.0.0.1 --port=8000"
$frontendCmd = "Set-Location '$frontend'; npm run dev -- --host 127.0.0.1 --port 5173"

Start-Process powershell -ArgumentList "-NoExit", "-Command", $backendCmd
Start-Process powershell -ArgumentList "-NoExit", "-Command", $frontendCmd
Start-Sleep -Seconds 2
Start-Process "http://127.0.0.1:5173"
Write-Host "API en http://127.0.0.1:8000 y UI en http://127.0.0.1:5173"
