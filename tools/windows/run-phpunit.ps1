Param(
    [string]$PhpPath = "",
    [string]$PhpUnitPhar = ""
)

$ErrorActionPreference = "Stop"

$RepoRoot = Join-Path $PSScriptRoot "..\.."
if ([string]::IsNullOrWhiteSpace($PhpPath)) { $PhpPath = Join-Path $RepoRoot "build\portable\php\php.exe" }
if ([string]::IsNullOrWhiteSpace($PhpUnitPhar)) { $PhpUnitPhar = Join-Path $RepoRoot "phpunit.phar" }

if (!(Test-Path $PhpPath)) {
    Write-Error "PHP portabile non trovato: $PhpPath"
}

if (!(Test-Path $PhpUnitPhar)) {
    Write-Host "Scarico phpunit.phar (v9)..."
    $url = "https://phar.phpunit.de/phpunit-9.phar"
    Invoke-WebRequest -Uri $url -OutFile $PhpUnitPhar -UseBasicParsing
}

& $PhpPath $PhpUnitPhar --bootstrap (Join-Path $RepoRoot "tests\bootstrap.php") --colors=always --testdox


