$ErrorActionPreference = 'Stop'
$workspace = Split-Path $PSScriptRoot -Parent
$public = 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$taskRuntime = Get-TarhanLocalRuntime
$backup = Join-Path 'C:/Users/zloy27/.codex/visualizations/2026/10/17/01a14b37-4bc1-7591-b79e-7ebeceaf4e34' ('wordpress-backup-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $backup | Out-Null

$files = @(
    'wp-content/plugins/tarhan-testing/tarhan-attempts.php',
    'wp-content/plugins/tarhan-testing/tarhan-testing.php',
    'wp-content/themes/tarhan/functions.php',
    'wp-content/themes/tarhan/page-test.php'
)
foreach ($relative in $files) {
    $destination = Join-Path $public $relative
    $saved = Join-Path $backup $relative
    New-Item -ItemType Directory -Force -Path (Split-Path $saved -Parent) | Out-Null
    Copy-Item -LiteralPath $destination -Destination $saved
    Copy-Item -LiteralPath (Join-Path $workspace ('wordpress/' + $relative)) -Destination $destination
}
$clientTarget = Join-Path $public 'wp-content/themes/tarhan/assets/js/competition-test-server.js'
if (Test-Path -LiteralPath $clientTarget) { Copy-Item -LiteralPath $clientTarget -Destination (Join-Path $backup 'competition-test-server.js') }
Copy-Item -LiteralPath (Join-Path $workspace 'src/js/components/competition-test-server.js') -Destination $clientTarget
Write-Output "Backup: $backup"

& $taskRuntime.PhpPath -n -d "extension_dir=$($taskRuntime.ExtensionDir)" -d extension=php_mysqli.dll -d extension=php_mbstring.dll -d "mysqli.default_port=$($taskRuntime.MysqlPort)" (Join-Path $workspace 'tests/verify-attempts.php')
if ($LASTEXITCODE -ne 0) { throw 'Server scenario verification failed' }
