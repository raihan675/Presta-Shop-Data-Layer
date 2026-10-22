# Automated Pre-Push Safety Crosscheck and Release Packaging Pipeline
$ErrorActionPreference = "Stop"

$moduleDir = Resolve-Path (Join-Path $PSScriptRoot "..")
$zipOutput = Join-Path (Split-Path $moduleDir) "ps_gtm_datalayer.zip"
$manifestPath = Join-Path $moduleDir "update-manifest.json"

Write-Host "=========================================================="
Write-Host "   STEP 1: RUNNING 126-POINT SYSTEM SAFETY CROSSCHECK"
Write-Host "=========================================================="
node (Join-Path $moduleDir "tests/systemSafetyCrosscheck.js")
if ($LASTEXITCODE -ne 0) {
    Write-Error "Safety checks failed! Release packaging aborted."
    exit 1
}

Write-Host "`n=========================================================="
Write-Host "   STEP 2: PACKAGING ZIP WITH STRICT FORWARD SLASHES"
Write-Host "=========================================================="
if (Test-Path $zipOutput) { Remove-Item -Force $zipOutput }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipOutput, [System.IO.Compression.ZipArchiveMode]::Create)

$allFiles = Get-ChildItem -Path $moduleDir -Recurse -File | Where-Object {
    $rel = $_.FullName.Substring($moduleDir.Path.Length).TrimStart('\', '/')
    -not (
        $rel.StartsWith('.git') -or 
        $rel.StartsWith('tests') -or 
        $rel.StartsWith('scripts') -or 
        $rel -eq 'update-manifest.json' -or
        $_.Extension -eq '.ps1' -or
        $_.Extension -eq '.zip'
    )
}

foreach ($file in $allFiles) {
    $relPath = $file.FullName.Substring($moduleDir.Path.Length).TrimStart('\', '/').Replace('\', '/')
    $entryName = "ps_gtm_datalayer/" + $relPath
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
}

$zip.Dispose()

$check = [System.IO.Compression.ZipFile]::OpenRead($zipOutput)
$entries = $check.Entries
$backslashCount = ($entries | Where-Object { $_.FullName -like "*\*" }).Count
Write-Host "Packaged $($entries.Count) files into ps_gtm_datalayer.zip (Backslashes: $backslashCount)"
$check.Dispose()

if ($backslashCount -gt 0) {
    Write-Error "CRITICAL: Backslashes detected in zip archive! Linux extraction will fail."
    exit 1
}

$sha256 = (Get-FileHash -Path $zipOutput -Algorithm SHA256).Hash.ToLower()
Write-Host "SHA-256 Checksum: $sha256"

Write-Host "`n=========================================================="
Write-Host "   STEP 3: UPDATING UPDATE-MANIFEST.JSON"
Write-Host "=========================================================="
$manifestContent = Get-Content $manifestPath -Raw | ConvertFrom-Json
$manifestContent.checksum = $sha256
$jsonString = $manifestContent | ConvertTo-Json -Depth 5
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($manifestPath, $jsonString, $utf8NoBom)
Write-Host "Updated update-manifest.json with checksum $sha256 (UTF-8 No-BOM)"

Write-Host "`nRelease packaging complete!"
