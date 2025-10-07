Param(
    [string]$ToolDir = "",
    [string]$PhpUnitPhar = ""
)

$ErrorActionPreference = "Stop"

$RepoRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
if ([string]::IsNullOrWhiteSpace($ToolDir)) { $ToolDir = Join-Path $RepoRoot ".tools\php82" }
if ([string]::IsNullOrWhiteSpace($PhpUnitPhar)) { $PhpUnitPhar = Join-Path $RepoRoot "phpunit.phar" }

New-Item -ItemType Directory -Force -Path $ToolDir | Out-Null

$PhpZipUrls = @(
    "https://windows.php.net/downloads/releases/archives/php-8.2.25-nts-Win32-vs16-x64.zip",
    "https://windows.php.net/downloads/releases/archives/php-8.2.12-nts-Win32-vs16-x64.zip"
)

$PhpZip = Join-Path $ToolDir "php.zip"
$PhpDir = Join-Path $ToolDir "php"
$PhpExe = Join-Path $PhpDir "php.exe"

if (!(Test-Path $PhpExe)) {
    Write-Host "Scarico PHP 8.2 NTS x64..."
    $downloaded = $false
    foreach ($u in $PhpZipUrls) {
        try {
            Invoke-WebRequest -Uri $u -OutFile $PhpZip -UseBasicParsing
            $downloaded = $true
            break
        } catch {
            Write-Warning ("Download fallito da {0}: {1}" -f $u, $($_))
        }
    }
    if (-not $downloaded) {
        throw "Impossibile scaricare PHP."
    }

    Write-Host "Estraggo PHP..."
    Expand-Archive -Path $PhpZip -DestinationPath $PhpDir -Force

    # Il pacchetto ZIP contiene la cartella con nome versione, normalizziamo
    $inner = Get-ChildItem -Directory $PhpDir | Select-Object -First 1
    if ($inner) {
        Get-ChildItem -Path $inner.FullName -Force | Move-Item -Destination $PhpDir -Force
        Remove-Item $inner.FullName -Recurse -Force
    }

    # Abilita estensioni necessarie per PHPUnit
    $iniPath = Join-Path $PhpDir "php.ini"
    $iniDev = Join-Path $PhpDir "php.ini-development"
    if (Test-Path $iniDev) { Copy-Item $iniDev $iniPath -Force }
    Add-Content -Path $iniPath -Value 'extension_dir="ext"'
    Add-Content -Path $iniPath -Value 'extension=php_mbstring.dll'
    Add-Content -Path $iniPath -Value 'extension=php_xml.dll'
    Add-Content -Path $iniPath -Value '; optional: dom and tokenizer are bundled with xml on Windows'
}

if (!(Test-Path $PhpUnitPhar)) {
    Write-Host "Scarico phpunit.phar (v9)..."
    Invoke-WebRequest -Uri "https://phar.phpunit.de/phpunit-9.phar" -OutFile $PhpUnitPhar -UseBasicParsing
}

Write-Host "Eseguo PHPUnit..."
& $PhpExe $PhpUnitPhar --bootstrap (Join-Path $RepoRoot "tests\bootstrap.php") --colors=always --testdox


