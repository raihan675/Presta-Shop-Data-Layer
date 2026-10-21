# ==============================================================================
# Enterprise GA4 & sGTM DataLayer — Automated Release & Verification Engine
# ==============================================================================

$ErrorActionPreference = "Stop"

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$parentDir = Split-Path -Parent $scriptDir
$targetZip = Join-Path $parentDir "ps_gtm_datalayer.zip"

Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "  Synchronizing & Verifying Module: ps_gtm_datalayer" -ForegroundColor Cyan
Write-Host "=================================================================" -ForegroundColor Cyan

# 1. Read Version Numbers Across Core Files
$phpVersionMatch = Select-String -Path "$scriptDir\ps_gtm_datalayer.php" -Pattern '\$this->version\s*=\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]'
$phpVersion = if ($phpVersionMatch) { $phpVersionMatch.Matches[0].Groups[1].Value } else { "" }

$xmlVersionMatch = Select-String -Path "$scriptDir\config.xml" -Pattern '<version><!\[CDATA\[([^\]]+)\]\]><\/version>'
$xmlVersion = if ($xmlVersionMatch) { $xmlVersionMatch.Matches[0].Groups[1].Value } else { "" }

$manifestContent = Get-Content -Raw "$scriptDir\update-manifest.json" | ConvertFrom-Json
$manifestVersion = $manifestContent.latest_version

$changelogMatch = Select-String -Path "$scriptDir\CHANGELOG.md" -Pattern '## \[([0-9]+\.[0-9]+\.[0-9]+)\]'
$changelogVersion = if ($changelogMatch) { $changelogMatch.Matches[0].Groups[1].Value } else { "" }

Write-Host "Version in ps_gtm_datalayer.php : $phpVersion"
Write-Host "Version in config.xml           : $xmlVersion"
Write-Host "Version in update-manifest.json : $manifestVersion"
Write-Host "Version in CHANGELOG.md         : $changelogVersion"

# 2. Enforce Strict Version Parity
if ($phpVersion -ne $xmlVersion -or $phpVersion -ne $manifestVersion -or $phpVersion -ne $changelogVersion) {
    Write-Host "[ERROR] Version mismatch detected across project files!" -ForegroundColor Red
    exit 1
}
Write-Host "[OK] Version consistency verified: v$phpVersion" -ForegroundColor Green

# 3. Run Automated Regression Test Suite
Write-Host "`nRunning test suite..." -ForegroundColor Yellow
Set-Location $scriptDir
node --test tests/catalogIdentity.test.js
if ($LASTEXITCODE -ne 0) {
    Write-Host "[ERROR] Test suite failed! Aborting release." -ForegroundColor Red
    exit 1
}
Write-Host "[OK] All automated tests passed." -ForegroundColor Green

# 4. Build POSIX-Compliant ZIP Package (Forward slashes for Linux ZipArchive compatibility)
Write-Host "`nPackaging POSIX-compliant module archive..." -ForegroundColor Yellow
Set-Location $parentDir

if (Test-Path $targetZip) {
    Remove-Item -Force $targetZip
}

tar.exe -a -cf $targetZip --exclude=".git" --exclude="tests" --exclude="*.test.js" --exclude="sync_release.ps1" --exclude="update-manifest.json" ps_gtm_datalayer

$hash = (Get-FileHash -Algorithm SHA256 $targetZip).Hash.ToLower()
Write-Host "Built archive: $targetZip"
Write-Host "Binary SHA-256: $hash"

# 5. Update and Sync Manifest Checksum if Needed
if ($manifestContent.checksum -ne $hash) {
    Write-Host "Updating update-manifest.json with new binary checksum..." -ForegroundColor Yellow
    $manifestRaw = Get-Content -Raw "$scriptDir\update-manifest.json"
    $updatedManifest = $manifestRaw -replace '"checksum":\s*"[^"]*"', ('"checksum": "' + $hash + '"')
    Set-Content -Path "$scriptDir\update-manifest.json" -Value $updatedManifest -NoNewline
    Write-Host "[OK] update-manifest.json checksum updated." -ForegroundColor Green
} else {
    Write-Host "[OK] Checksum is synchronized with update-manifest.json." -ForegroundColor Green
}

Write-Host "`n=================================================================" -ForegroundColor Green
Write-Host "  SUCCESS: Project folder is 100% synchronized with v$phpVersion" -ForegroundColor Green
Write-Host "=================================================================" -ForegroundColor Green
