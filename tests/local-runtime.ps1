# Local owns service lifetimes and ports. Never start mysqld/PHP/nginx behind its back.
function Get-TarhanLocalRuntime {
    $taskLocalRoot = Join-Path $env:APPDATA 'Local'
    $taskSites = Get-Content -LiteralPath (Join-Path $taskLocalRoot 'sites.json') -Raw | ConvertFrom-Json
    $taskSiteId = 'wrlKHRIGs'
    $taskSite = $taskSites.$taskSiteId
    if (!$taskSite -or $taskSite.domain -ne 'tarhan-wp.local') { throw 'Expected local Tarhan site was not found' }
    $taskStatuses = Get-Content -LiteralPath (Join-Path $taskLocalRoot 'site-statuses.json') -Raw | ConvertFrom-Json
    if ($taskStatuses.$taskSiteId -ne 'running') {
        throw 'Start Tarhan-wp in the Local app before running checks. Scripts never start separate service processes.'
    }
    $taskMysqlPort = [int]$taskSite.services.mysql.ports.MYSQL[0]
    if ($taskMysqlPort -lt 1 -or $taskMysqlPort -gt 65535) { throw 'Invalid current MySQL port in Local configuration' }
    $taskPhpVersion = $taskSite.services.php.version
    $taskPhpPackages = Get-ChildItem -LiteralPath (Join-Path $taskLocalRoot 'lightning-services') -Directory |
        Where-Object { $_.Name -eq "php-$taskPhpVersion" -or $_.Name -match ('^php-' + [regex]::Escape($taskPhpVersion) + '\+\d+$') } |
        Sort-Object @{Expression = { if ($_.Name -match '\+(\d+)$') { [int]$Matches[1] } else { -1 } }} -Descending
    if (!$taskPhpPackages) { throw 'Configured Local PHP package was not found' }
    $taskPhpDirectory = Join-Path @($taskPhpPackages)[0].FullName 'bin/win64'
    $taskPhpPath = Join-Path $taskPhpDirectory 'php.exe'
    if (!(Test-Path -LiteralPath $taskPhpPath)) { throw 'Configured Local PHP runtime was not found' }
    return [pscustomobject]@{
        SiteId = $taskSiteId
        MysqlPort = $taskMysqlPort
        PhpPath = $taskPhpPath
        ExtensionDir = Join-Path $taskPhpDirectory 'ext'
    }
}
