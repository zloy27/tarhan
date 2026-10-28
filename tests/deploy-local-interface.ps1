$ErrorActionPreference = 'Stop'
$interfaceRoot = Split-Path $PSScriptRoot -Parent
$interfaceLive = 'C:/Users/zloy27/Local Sites/tarhan-wp/app/public/wp-content/themes/tarhan'
$interfaceBackup = Join-Path $interfaceRoot ('.interface-backup-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.local')
& (Join-Path $PSScriptRoot 'invoke-local-php.ps1') -Script (Join-Path $PSScriptRoot 'content-preflight.php')
$interfaceCopies = @{}
foreach ($file in @('functions.php', 'page-participant.php', 'assets/css/header.css', 'assets/css/participant-account.css')) {
    $interfaceCopies[$file] = Join-Path $interfaceRoot ('wordpress/wp-content/themes/tarhan/' + $file)
}
$interfaceCopies['assets/js/page-navigation.js'] = Join-Path $interfaceRoot 'src/js/components/page-navigation.js'
foreach ($bundle in @('dist', 'participant-dist')) {
    $interfaceCopies[$bundle + '/.vite/manifest.json'] = Join-Path $interfaceRoot '.participant-build/.vite/manifest.json'
    foreach ($asset in Get-ChildItem -LiteralPath (Join-Path $interfaceRoot '.participant-build/assets') -File) {
        $interfaceCopies[$bundle + '/assets/' + $asset.Name] = $asset.FullName
    }
}
foreach ($file in $interfaceCopies.Keys) {
    $target = Join-Path $interfaceLive $file
    if (Test-Path -LiteralPath $target) {
        $backup = Join-Path $interfaceBackup $file
        New-Item -ItemType Directory -Force -Path (Split-Path $backup -Parent) | Out-Null
        Copy-Item -LiteralPath $target -Destination $backup
    }
}
foreach ($file in $interfaceCopies.Keys) {
    $target = Join-Path $interfaceLive $file
    New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
    Copy-Item -LiteralPath $interfaceCopies[$file] -Destination $target -Force
}
Write-Output "Interface deployed. Backup: $interfaceBackup"
