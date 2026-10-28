$ErrorActionPreference = 'Stop'
$workspace = Split-Path $PSScriptRoot -Parent
$sitePublic = 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$taskRuntime = Get-TarhanLocalRuntime
if (!(Test-Path -LiteralPath (Join-Path $workspace '.participant-build/.vite/manifest.json'))) { throw 'Build participant assets first' }
# Full pre-change backup was created before implementation. Keep a second copy on repeat deployment.
$taskBackup = Join-Path 'C:/Users/zloy27/.codex/visualizations/2026/10/17/01a14b37-4bc1-7591-b79e-7ebeceaf4e34' ('participants-deploy-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $taskBackup | Out-Null
foreach ($relative in @('wp-content/plugins/tarhan-testing','wp-content/themes/tarhan')) {
    $target = Join-Path $sitePublic $relative
    $saved = Join-Path $taskBackup $relative
    New-Item -ItemType Directory -Force -Path (Split-Path $saved -Parent) | Out-Null
    Copy-Item -LiteralPath $target -Destination $saved -Recurse
    Copy-Item -Path (Join-Path $workspace "wordpress/$relative/*") -Destination $target -Recurse -Force
}
$theme = Join-Path $sitePublic 'wp-content/themes/tarhan'
New-Item -ItemType Directory -Force -Path (Join-Path $theme 'participant-dist') | Out-Null
Copy-Item -Path (Join-Path $workspace '.participant-build/*') -Destination (Join-Path $theme 'participant-dist') -Recurse -Force
Copy-Item -LiteralPath (Join-Path $workspace '.participant-build/.vite') -Destination (Join-Path $theme 'participant-dist') -Recurse -Force
foreach ($name in @('participant-account.js','page-navigation.js','privacy-policy-page.js')) {
    Copy-Item -LiteralPath (Join-Path $workspace "src/js/components/$name") -Destination (Join-Path $theme "assets/js/$name") -Force
}
# Local must already own the running services; verify existing testing behavior.
& (Join-Path $PSScriptRoot 'deploy-local-testing.ps1')
if ($LASTEXITCODE -ne 0) { throw 'Existing test regression failed' }
& $taskRuntime.PhpPath -n -d "extension_dir=$($taskRuntime.ExtensionDir)" -d extension=php_mysqli.dll -d extension=php_mbstring.dll -d extension=php_fileinfo.dll -d extension=php_zip.dll -d "mysqli.default_port=$($taskRuntime.MysqlPort)" (Join-Path $PSScriptRoot 'install-local-participants.php')
if ($LASTEXITCODE -ne 0) { throw 'Participant installation failed' }
Write-Output "Backup: $taskBackup"
