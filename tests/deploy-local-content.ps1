$ErrorActionPreference = 'Stop'
$contentRoot = Split-Path $PSScriptRoot -Parent
$contentTheme = 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-content/themes/tarhan'
$contentSource = Join-Path $contentRoot 'wordpress/wp-content/themes/tarhan'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$contentRuntime = Get-TarhanLocalRuntime
& (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'content-preflight.php')
$contentBaseline = Get-Content -LiteralPath (Join-Path $contentRoot '.content-deploy-baseline.local') -Raw | ConvertFrom-Json
foreach ($item in $contentBaseline.PSObject.Properties) {
    $currentHash = (Get-FileHash -LiteralPath (Join-Path $contentTheme $item.Name)).Hash
    $newHash = (Get-FileHash -LiteralPath (Join-Path $contentSource $item.Name)).Hash
    if ($currentHash -ne $item.Value -and $currentHash -ne $newHash) { throw "Live file changed since inspection: $($item.Name)" }
}
$contentBackup = Join-Path 'C:/Users/zloy27/.codex/visualizations/2026/10/17/01a14b37-4bc1-7591-b79e-7ebeceaf4e34' ('content-pages-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Path $contentBackup | Out-Null
Copy-Item -LiteralPath $contentTheme -Destination (Join-Path $contentBackup 'theme') -Recurse
$contentFiles = @('functions.php','header.php','footer.php','front-page.php','page-jury.php','page-ambassadors.php','page-season.php','page-archive.php','page-preparation.php','page-information.php',
    'inc/content-editor.php','inc/content-pages.php','template-parts/people.php','assets/js/content-editor.js','assets/css/content-editor.css','assets/css/content-pages.css',
    'content/information.html','content/participant.html','content/season-2021-2022.json','content/ambassadors.json')
foreach ($relative in $contentFiles) {
    $destination = Join-Path $contentTheme $relative
    New-Item -ItemType Directory -Force -Path (Split-Path $destination -Parent) | Out-Null
    Copy-Item -LiteralPath (Join-Path $contentSource $relative) -Destination $destination -Force
}
Copy-Item -Path (Join-Path $contentRoot '.participant-build/*') -Destination (Join-Path $contentTheme 'participant-dist') -Recurse -Force
Copy-Item -LiteralPath (Join-Path $contentRoot '.participant-build/.vite/manifest.json') -Destination (Join-Path $contentTheme 'participant-dist/.vite/manifest.json') -Force
Copy-Item -LiteralPath (Join-Path $contentRoot 'src/js/components/page-navigation.js') -Destination (Join-Path $contentTheme 'assets/js/page-navigation.js') -Force
$previousBackup = $env:TARHAN_CONTENT_BACKUP
try {
    $env:TARHAN_CONTENT_BACKUP = $contentBackup
    & (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'install-local-content.php')
    & (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'import-local-ambassadors.php')
    & (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'verify-content-pages.php')
} finally { $env:TARHAN_CONTENT_BACKUP = $previousBackup }
Write-Output "Backup: $contentBackup"
