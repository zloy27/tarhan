$ErrorActionPreference = 'Stop'
$organizerRoot = Split-Path $PSScriptRoot -Parent
$organizerSource = Join-Path $organizerRoot 'wordpress/wp-content/plugins/tarhan-testing'
$organizerLive = 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-content/plugins/tarhan-testing'
& (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'content-preflight.php')
$organizerExpected = '5D65E8A80F7E6B465643D5E38E09E74D2B5F3923E7A852489C134E26C672DCC4'
$organizerMainHash = (Get-FileHash -LiteralPath (Join-Path $organizerLive 'tarhan-testing.php')).Hash
$organizerNewHash = (Get-FileHash -LiteralPath (Join-Path $organizerSource 'tarhan-testing.php')).Hash
if ($organizerMainHash -ne $organizerExpected -and $organizerMainHash -ne $organizerNewHash) { throw 'Live plugin changed since inspection' }
$organizerFiles = @('tarhan-organizers.php','tarhan-organizer-xlsx.php','assets/organizers.css','data/organizer-columns.json','templates/participants.xlsx','tarhan-testing.php')
foreach ($relative in $organizerFiles) {
    if ($relative -eq 'tarhan-testing.php') { continue }
    $existing = Join-Path $organizerLive $relative
    if ((Test-Path -LiteralPath $existing) -and (Get-FileHash -LiteralPath $existing).Hash -ne (Get-FileHash -LiteralPath (Join-Path $organizerSource $relative)).Hash) { throw "Unexpected existing organizer file: $relative" }
}
$organizerBackup = Join-Path 'C:/Users/zloy27/.codex/visualizations/2026/10/17/01a14b37-4bc1-7591-b79e-7ebeceaf4e34' ('organizers-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $organizerBackup | Out-Null
Copy-Item -LiteralPath (Join-Path $organizerLive 'tarhan-testing.php') -Destination (Join-Path $organizerBackup 'tarhan-testing.php')
# Copy the entry point last, after all dependencies exist.
foreach ($relative in $organizerFiles) {
    $destination = Join-Path $organizerLive $relative
    New-Item -ItemType Directory -Force -Path (Split-Path $destination -Parent) | Out-Null
    Copy-Item -LiteralPath (Join-Path $organizerSource $relative) -Destination $destination -Force
    if ((Get-FileHash -LiteralPath $destination).Hash -ne (Get-FileHash -LiteralPath (Join-Path $organizerSource $relative)).Hash) { throw "Copy mismatch: $relative" }
}
Write-Output "Backup: $organizerBackup"
