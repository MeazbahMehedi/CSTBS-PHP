# Windows PowerShell 5.1 compatible. The launcher changes policy for this process only.
[CmdletBinding()]
param([string]$PhpPath, [switch]$CheckOnly, [switch]$NonInteractive, [switch]$EnableExtensions)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$oldLocation = Get-Location
$oldOutputEncoding = $OutputEncoding
$oldConsoleEncoding = [Console]::OutputEncoding
$OutputEncoding = New-Object System.Text.UTF8Encoding($false)
[Console]::OutputEncoding = $OutputEncoding

function Read-Default([string]$Label, [string]$Default) {
    $answer = Read-Host "$Label [$Default]"
    if ([string]::IsNullOrWhiteSpace($answer)) { return $Default }
    return $answer.Trim()
}
function Invoke-Setup([string]$Command, [string]$InputJson) {
    if ($InputJson) { $result = $InputJson | & $script:php $script:helper $Command }
    else { $result = & $script:php $script:helper $Command }
    if ($LASTEXITCODE -ne 0) { throw "The $Command step could not finish. Your existing accounts are unchanged." }
    return $result
}

try {
    Set-Location -LiteralPath $projectRoot
    Write-Host "`nCSTBS - Laptop Setup`n" -ForegroundColor Cyan
    $script:helper = Join-Path $PSScriptRoot 'setup.php'
    $candidates = @()
    if ($PhpPath) { $candidates += $PhpPath }
    else {
        $xamppRoot = Split-Path -Parent (Split-Path -Parent $projectRoot)
        $candidates += (Join-Path $xamppRoot 'php\php.exe')
        $candidates += 'C:\xampp\php\php.exe'
        $onPath = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($onPath) { $candidates += $onPath.Source }
    }
    $script:php = $candidates | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } | Select-Object -First 1
    if (!$script:php -and !$NonInteractive -and !$CheckOnly) { $script:php = Read-Host 'Full path to XAMPP php.exe' }
    if (!$script:php -or !(Test-Path -LiteralPath $script:php -PathType Leaf)) { throw 'PHP was not found. Install XAMPP with PHP 8.2 or newer, then run setup again.' }
    $check = (Invoke-Setup 'check') | ConvertFrom-Json
    if (!$check.supported) { throw "PHP $($check.version) is too old. This application requires PHP 8.2 or newer." }
    Write-Host "Found PHP $($check.version) at $script:php"
    if ($CheckOnly) {
        Write-Host ('Missing extensions: ' + ($check.missing -join ', '))
        if ($check.missing.Count -gt 0) { throw 'PHP extensions are missing.' }
        Write-Host 'PHP checks passed. No files or database records were changed.'
        exit 0
    }
    if ($check.missing.Count -gt 0) {
        Write-Host ('PHP needs these extensions: ' + ($check.missing -join ', '))
        if ($NonInteractive -and !$EnableExtensions) { throw 'Enable the listed extensions before noninteractive setup.' }
        $enable = if ($EnableExtensions) { 'Y' } else { Read-Default 'Enable available extensions in this XAMPP PHP configuration? Y/n' 'Y' }
        if ($enable -notmatch '^(y|yes)$' -or !$check.ini) { throw 'Setup stopped. Enable the listed PHP extensions and run setup again.' }
        $iniPath = [string]$check.ini
        $iniText = [IO.File]::ReadAllText($iniPath)
        $updated = $iniText
        foreach ($extension in $check.missing) {
            $pattern = '(?m)^(\s*);\s*(extension\s*=\s*"?(?:php_)?' + [regex]::Escape($extension) + '(?:\.dll)?"?\s*(?:;[^\r\n]*)?)\r?$'
            $updated = [regex]::Replace($updated, $pattern, '$1$2')
        }
        if ($updated -eq $iniText) { throw 'No matching extension settings were found. Install a complete XAMPP PHP build with the listed extensions.' }
        $iniBackup = $iniPath + '.cstbs-' + [guid]::NewGuid().ToString('N') + '.bak'
        Copy-Item -LiteralPath $iniPath -Destination $iniBackup
        try {
            [IO.File]::WriteAllText($iniPath, $updated, (New-Object Text.UTF8Encoding($false)))
            $check = (Invoke-Setup 'check') | ConvertFrom-Json
            if ($check.missing.Count -gt 0) {
                throw ('Extensions could not load: ' + ($check.missing -join ', '))
            }
        } catch {
            Copy-Item -LiteralPath $iniBackup -Destination $iniPath -Force
            throw ('Original PHP configuration restored. ' + $_.Exception.Message)
        }
        Write-Host "Extensions enabled. Restart Apache in XAMPP after setup. Backup: $iniBackup"
    }

    $toolsDirectory = Join-Path $projectRoot 'storage\tools'
    New-Item -ItemType Directory -Path $toolsDirectory -Force | Out-Null
    $composer = Join-Path $toolsDirectory 'composer.phar'
    if (!(Test-Path -LiteralPath $composer -PathType Leaf)) {
        Write-Host 'Downloading and verifying Composer. No global Composer installation is needed.'
        [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
        $installer = Join-Path $toolsDirectory ('installer-' + [guid]::NewGuid().ToString('N') + '.php')
        try {
            $signature = (Invoke-WebRequest 'https://composer.github.io/installer.sig' -UseBasicParsing).Content
            $expected = if ($signature -is [byte[]]) { [Text.Encoding]::UTF8.GetString($signature).Trim() } else { ([string]$signature).Trim() }
            if ($expected -notmatch '^[a-fA-F0-9]{96}$') { throw 'Composer verification service returned an invalid checksum.' }
            Invoke-WebRequest 'https://getcomposer.org/installer' -UseBasicParsing -OutFile $installer
            $sha384 = [Security.Cryptography.SHA384]::Create()
            $download = [IO.File]::OpenRead($installer)
            try { $actual = [BitConverter]::ToString($sha384.ComputeHash($download)).Replace('-', '') }
            finally { $download.Dispose(); $sha384.Dispose() }
            if ($actual -ine $expected) { throw 'Composer verification failed. The download was not executed. Run setup again.' }
            & $script:php $installer --2 --install-dir=$toolsDirectory --filename=composer.phar
            if ($LASTEXITCODE -ne 0) { throw 'Composer download failed. Check your internet connection and PHP HTTPS certificate configuration.' }
        } finally { if (Test-Path -LiteralPath $installer) { Remove-Item -LiteralPath $installer } }
    }
    Write-Host 'Installing the locked application dependencies...'
    & $script:php $composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
    if ($LASTEXITCODE -ne 0) { throw 'Dependency installation failed. Review the Composer message above, then run setup again.' }
    & $script:php $composer check-platform-reqs --no-dev
    if ($LASTEXITCODE -ne 0) { throw 'The installed PHP does not meet the application requirements.' }

    $configuration = @{}
    if (Test-Path -LiteralPath (Join-Path $projectRoot 'config.local.php')) {
        Write-Host 'Keeping your existing private configuration.'
    } else {
        if ($NonInteractive) { throw 'Noninteractive setup requires an existing config.local.php.' }
        Write-Host "`nStart Apache and MySQL in XAMPP before continuing."
        $configuration.db_host = Read-Default 'Database host' '127.0.0.1'
        $configuration.db_port = Read-Default 'MySQL port' '3306'
        $configuration.db_name = Read-Default 'Database name' 'cstbs_php'
        $configuration.db_user = Read-Default 'Database user' 'root'
        $securePassword = Read-Host 'Database password (press Enter for a fresh XAMPP installation)' -AsSecureString
        $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
        try { $configuration.db_password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
        finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
        $folder = Split-Path -Leaf $projectRoot
        $configuration.base_path = Read-Default 'Website folder path' ('/' + $folder)
    }
    Invoke-Setup 'configure' ($configuration | ConvertTo-Json -Compress)
    $configuration.Clear()
    Invoke-Setup 'database'
    $admin = (Invoke-Setup 'admin-status') | ConvertFrom-Json
    if (!$admin.exists -and !$NonInteractive) {
        Write-Host "`nCreate the first administrator. No default password will be created."
        $email = Read-Host 'Administrator email'
        $name = Read-Default 'Administrator name' 'University Administrator'
        Invoke-Setup 'admin' (@{email=$email;name=$name} | ConvertTo-Json -Compress)
        Write-Host 'Use the single-use key above on the activation page within 20 minutes.'
    } elseif ($admin.exists) { Write-Host 'Existing administrators and passwords were preserved.' }
    else { Write-Host 'Create the first administrator with bin/console.php admin-setup when ready.' }
    if (!$NonInteractive) {
        $samples = Read-Default 'Add sample events if the calendar is empty? Y/n' 'Y'
        if ($samples -match '^(y|yes)$') {
            & $script:php (Join-Path $PSScriptRoot 'console.php') seed-events
            if ($LASTEXITCODE -ne 0) { throw 'Sample events could not be added.' }
        }
    }
    $summary = (Invoke-Setup 'summary') | ConvertFrom-Json
    Write-Host "`nSetup complete." -ForegroundColor Green
    Write-Host "Website: $($summary.url)"
    Write-Host "Administrator activation: $($summary.activation)"
    Write-Host 'Keep Apache and MySQL running in XAMPP. Run this setup only after cloning or updating.'
} catch {
    Write-Host "`n$($_.Exception.Message)" -ForegroundColor Red
    exit 1
} finally {
    Set-Location -LiteralPath $oldLocation
    $OutputEncoding = $oldOutputEncoding
    [Console]::OutputEncoding = $oldConsoleEncoding
}
