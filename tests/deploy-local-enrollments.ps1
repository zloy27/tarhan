$ErrorActionPreference = 'Stop'
$renewalRoot = Split-Path $PSScriptRoot -Parent
$renewalLive = 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public'
$renewalBase = Get-Content (Join-Path $renewalRoot '.renewal-baseline.local') -Raw | ConvertFrom-Json
$renewalFiles = @(
 'wp-content/plugins/tarhan-testing/tarhan-enrollments.php',
 'wp-content/plugins/tarhan-testing/tarhan-attempts.php', 'wp-content/plugins/tarhan-testing/tarhan-participant.php',
 'wp-content/plugins/tarhan-testing/tarhan-auth.php', 'wp-content/plugins/tarhan-testing/tarhan-essay.php',
 'wp-content/plugins/tarhan-testing/tarhan-seasons.php','wp-content/plugins/tarhan-testing/tarhan-organizers.php',
 'wp-content/plugins/tarhan-testing/tarhan-organizer-xlsx.php','wp-content/plugins/tarhan-testing/data/organizer-columns.json',
 'wp-content/plugins/tarhan-testing/templates/participants.xlsx',
 'wp-content/themes/tarhan/functions.php','wp-content/themes/tarhan/page-participant.php','wp-content/themes/tarhan/page-test.php',
 'wp-content/themes/tarhan/participant-forms/registration.php','wp-content/plugins/tarhan-testing/tarhan-testing.php')
& (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'content-preflight.php')
foreach ($rel in $renewalFiles) {
 $source = Join-Path $renewalRoot ('wordpress/' + $rel)
 if ($rel.EndsWith('.php')) { & (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script '-l' -ScriptArguments $source }
 $dest = Join-Path $renewalLive $rel
 if ((Test-Path -LiteralPath $dest) -and (Get-FileHash -LiteralPath $dest).Hash -ne $renewalBase.$rel -and (Get-FileHash -LiteralPath $dest).Hash -ne (Get-FileHash -LiteralPath $source).Hash) { throw "Live modified: $rel" }
}
$renewalBackup = Join-Path 'C:/Users/zloy27/.codex/visualizations/2026/10/17/01a14b37-4bc1-7591-b79e-7ebeceaf4e34' ('enrollments-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
foreach ($rel in $renewalFiles) {
 $dest = Join-Path $renewalLive $rel
 if (Test-Path -LiteralPath $dest) {
  $backup = Join-Path $renewalBackup $rel
  New-Item -ItemType Directory -Force -Path (Split-Path $backup -Parent) | Out-Null
  Copy-Item -LiteralPath $dest -Destination $backup
 }
 Copy-Item -LiteralPath (Join-Path $renewalRoot ('wordpress/' + $rel)) -Destination $dest -Force
}
& (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'migrate-local-enrollments.php')
$theme = Join-Path $renewalLive 'wp-content/themes/tarhan'
Copy-Item -LiteralPath (Join-Path $theme 'participant-dist/.vite/manifest.json') -Destination (Join-Path $renewalBackup 'participant-manifest.json')
Copy-Item -LiteralPath (Join-Path $theme 'assets/js/participant-account.js') -Destination (Join-Path $renewalBackup 'participant-account.js')
Copy-Item -LiteralPath (Join-Path $theme 'assets/js/competition-test-server.js') -Destination (Join-Path $renewalBackup 'competition-test-server.js')
Copy-Item -Path (Join-Path $renewalRoot '.participant-build/*') -Destination (Join-Path $theme 'participant-dist') -Recurse -Force
Copy-Item -LiteralPath (Join-Path $renewalRoot '.participant-build/.vite/manifest.json') -Destination (Join-Path $theme 'participant-dist/.vite/manifest.json') -Force
foreach ($js in @('participant-account.js','competition-test-server.js')) { Copy-Item -LiteralPath (Join-Path $renewalRoot ('src/js/components/' + $js)) -Destination (Join-Path $theme ('assets/js/' + $js)) -Force }
Write-Output "Backup: $renewalBackup"
