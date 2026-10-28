$ErrorActionPreference = 'Stop'
$seasonRoot = Split-Path $PSScriptRoot -Parent
$seasonLive = 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public'
$seasonBaseline = Get-Content -LiteralPath (Join-Path $seasonRoot '.season-baseline.local') -Raw | ConvertFrom-Json
& (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'content-preflight.php')
$seasonFiles = @('wp-content/plugins/tarhan-testing/tarhan-seasons.php') + @($seasonBaseline.PSObject.Properties.Name)
foreach ($relative in $seasonFiles) {
    $source = Join-Path $seasonRoot ('wordpress/' + $relative)
    & (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script '-l' -ScriptArguments $source
    $destination = Join-Path $seasonLive $relative
    if (Test-Path -LiteralPath $destination) {
        $liveHash = (Get-FileHash -LiteralPath $destination).Hash
        if ($liveHash -ne $seasonBaseline.$relative -and $liveHash -ne (Get-FileHash -LiteralPath $source).Hash) { throw "Live changed: $relative" }
    }
}
$seasonBackup = Join-Path 'C:/Users/zloy27/.codex/visualizations/2026/10/17/01a14b37-4bc1-7591-b79e-7ebeceaf4e34' ('seasons-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
foreach ($relative in $seasonFiles) {
    $destination = Join-Path $seasonLive $relative
    if (Test-Path -LiteralPath $destination) {
        $backup = Join-Path $seasonBackup $relative
        New-Item -ItemType Directory -Force -Path (Split-Path $backup -Parent) | Out-Null
        Copy-Item -LiteralPath $destination -Destination $backup
    }
    Copy-Item -LiteralPath (Join-Path $seasonRoot ('wordpress/' + $relative)) -Destination $destination -Force
    if ((Get-FileHash -LiteralPath $destination).Hash -ne (Get-FileHash -LiteralPath (Join-Path $seasonRoot ('wordpress/' + $relative))).Hash) { throw 'Copy mismatch' }
}
Write-Output "Backup: $seasonBackup"
