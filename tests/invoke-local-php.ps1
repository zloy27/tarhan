param(
    [Parameter(Mandatory = $true)][string]$Script,
    [Parameter(ValueFromRemainingArguments = $true)][string[]]$ScriptArguments
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'local-runtime.ps1')
$taskRuntime = Get-TarhanLocalRuntime
& $taskRuntime.PhpPath -n -d "extension_dir=$($taskRuntime.ExtensionDir)" -d extension=php_mysqli.dll -d extension=php_mbstring.dll -d extension=php_fileinfo.dll -d extension=php_zip.dll -d "mysqli.default_port=$($taskRuntime.MysqlPort)" $Script @ScriptArguments
if ($LASTEXITCODE -ne 0) { throw "Local PHP command failed: $Script" }
