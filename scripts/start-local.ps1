param(
    [string]$Php = 'php',
    [ValidateRange(1024, 65535)][int]$Port = 8000,
    [switch]$Scheduler
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
& $Php (Join-Path $PSScriptRoot 'check-install.php')
if ($LASTEXITCODE -ne 0) {
    Write-Error 'Installation check failed. Read docs/INSTALL-LOCAL.md: confirm PHP 8.4, preserve APP_KEY, review backup/migrations and clear stale configuration. No database or environment file was changed.'
    exit 1
}
if ($Scheduler) {
    & $Php artisan schedule:work
} else {
    & $Php artisan serve --host=127.0.0.1 --port=$Port
}
exit $LASTEXITCODE
