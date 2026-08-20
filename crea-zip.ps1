$ErrorActionPreference = 'Stop'

$repoRoot = $PSScriptRoot
$pluginDir = Join-Path $repoRoot 'gestione-scarto-librario'
$releasesDir = Join-Path $repoRoot 'releases'

Push-Location $pluginDir
try {
    npm ci
    npm run test:security:smoke:self
    npm run release
    $package = Get-Content (Join-Path $pluginDir 'package.json') -Raw | ConvertFrom-Json
} finally {
    Pop-Location
}

New-Item -ItemType Directory -Path $releasesDir -Force | Out-Null
$zipName = "gestione-scarto-librario-$($package.version).zip"
$sourceZip = Join-Path $repoRoot $zipName
$sourceHash = "$sourceZip.sha256"
$targetZip = Join-Path $releasesDir $zipName
$targetHash = "$targetZip.sha256"

Move-Item $sourceZip $targetZip -Force
Move-Item $sourceHash $targetHash -Force

$expected = (Get-Content $targetHash -Raw).Split([char[]]" `t`r`n", [System.StringSplitOptions]::RemoveEmptyEntries)[0].ToLowerInvariant()
$actual = (Get-FileHash $targetZip -Algorithm SHA256).Hash.ToLowerInvariant()
if ($actual -ne $expected) {
    throw "Checksum SHA-256 non valido: atteso $expected, rilevato $actual"
}

Write-Host "Release creata: $targetZip"
Write-Host "SHA-256: $actual"
