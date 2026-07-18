# =====================================================================
#  Fabrique un ZIP de PRODUCTION propre, prêt à décompresser sur cPanel.
#  Usage (PowerShell, depuis la racine du projet) :
#      powershell -ExecutionPolicy Bypass -File deploy\build-zip.ps1
#  Résultat :  deploy\dematerialisation-prod-AAAAMMJJ-HHmm.zip
# =====================================================================

$ErrorActionPreference = 'Stop'

# Racine du projet = dossier parent de ce script
$root    = Split-Path -Parent $PSScriptRoot
$stamp   = Get-Date -Format 'yyyyMMdd-HHmm'
$staging = Join-Path $env:TEMP "demat-build-$stamp"
$zipPath = Join-Path $PSScriptRoot "dematerialisation-prod-$stamp.zip"

Write-Host "Projet   : $root"
Write-Host "Staging  : $staging"
Write-Host "ZIP      : $zipPath`n"

if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }

# 1) Copie du projet dans un dossier temporaire, en excluant l'inutile ---------
$excludeDirs = @('.git', 'node_modules', 'tests')
robocopy $root $staging /MIR /XD `
    (Join-Path $root '.git') `
    (Join-Path $root 'node_modules') `
    (Join-Path $root 'tests') `
    /XF (Join-Path $root '.env') `
    /NFL /NDL /NJH /NJS /NP | Out-Null
# robocopy renvoie 0-7 en succès ; on neutralise le code de sortie
if ($LASTEXITCODE -ge 8) { throw "robocopy a échoué (code $LASTEXITCODE)" }

# 2) Nettoyage du staging -------------------------------------------------------
# a) Caches compilés (seront régénérés par *:cache sur le serveur)
Get-ChildItem (Join-Path $staging 'bootstrap\cache') -Filter *.php -ErrorAction SilentlyContinue | Remove-Item -Force
foreach ($d in 'framework\cache\data','framework\sessions','framework\views') {
    $p = Join-Path $staging "storage\$d"
    if (Test-Path $p) { Get-ChildItem $p -Recurse -File | Where-Object Name -ne '.gitignore' | Remove-Item -Force -ErrorAction SilentlyContinue }
}
# b) Journaux
Get-ChildItem (Join-Path $staging 'storage\logs') -Filter *.log -ErrorAction SilentlyContinue | Remove-Item -Force

# c) Fichiers déposés de TEST (on garde la structure de dossiers)
foreach ($d in 'private\candidatures','private\requests','private\signatures','public') {
    $p = Join-Path $staging "storage\app\$d"
    if (Test-Path $p) {
        Get-ChildItem $p -Recurse -File | Where-Object Name -ne '.gitignore' | Remove-Item -Force -ErrorAction SilentlyContinue
        Get-ChildItem $p -Recurse -Directory | Sort-Object FullName -Descending | Where-Object { -not (Get-ChildItem $_.FullName) } | Remove-Item -Force -ErrorAction SilentlyContinue
    }
}

# 3) Compression ----------------------------------------------------------------
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Write-Host "Compression en cours..."
Compress-Archive -Path (Join-Path $staging '*') -DestinationPath $zipPath -CompressionLevel Optimal

Remove-Item $staging -Recurse -Force
$sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host "`nOK -> $zipPath  ($sizeMB Mo)"
Write-Host "Prochaine etape : voir docs\DEPLOIEMENT_CPANEL.md"
