$ErrorActionPreference = "Stop"
$root = Split-Path $PSScriptRoot -Parent
$env:ANDESLOG_URL = "http://127.0.0.1:5173"
Set-Location (Join-Path $root "desktop")
npm run dev
