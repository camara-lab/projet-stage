# ─────────────────────────────────────────────────────────────────────────────
# BusGo — Sauvegarde MySQL automatique (Windows / PowerShell)
#
# Usage    : .\scripts\backup-mysql.ps1
# Tâche    : Planifier via "Gestionnaire des tâches" Windows à 2h du matin
# Rétention: 7 jours
# ─────────────────────────────────────────────────────────────────────────────

param(
    [string]$BackupDir   = "C:\Backups\BusGo",
    [int]   $RetentionDays = 7
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ── Lire .env.prod ────────────────────────────────────────────────────────
$envFile = Join-Path $PSScriptRoot "..\symfony\.env.prod"
if (-not (Test-Path $envFile)) {
    Write-Error "Fichier manquant : $envFile"
    exit 1
}

$envVars = @{}
Get-Content $envFile | Where-Object { $_ -match '^\s*([^#=\s]+)\s*=\s*(.*)$' } | ForEach-Object {
    $envVars[$Matches[1]] = $Matches[2].Trim('"')
}

$dbName = $envVars['MYSQL_DATABASE']
$dbUser = $envVars['MYSQL_USER']
$dbPass = $envVars['MYSQL_PASSWORD']

if (-not $dbPass) {
    Write-Error "MYSQL_PASSWORD non défini dans .env.prod"
    exit 1
}

# ── Préparation ───────────────────────────────────────────────────────────
if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir | Out-Null
}

$timestamp  = Get-Date -Format "yyyyMMdd_HHmmss"
$backupFile = Join-Path $BackupDir "busgo_${timestamp}.sql.gz"
$composeFile = Join-Path $PSScriptRoot "..\docker-compose.prod.yml"

Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Démarrage de la sauvegarde → $backupFile"

# ── Dump + compression via Docker ─────────────────────────────────────────
# mysqldump dans le conteneur, résultat compressé par gzip (disponible dans l'image MySQL)
$dumpCmd = "mysqldump --user=$dbUser --password=$dbPass --single-transaction --routines --triggers $dbName | gzip -9"

docker compose -f $composeFile exec -T database sh -c $dumpCmd | Set-Content -AsByteStream $backupFile

$size = (Get-Item $backupFile).Length / 1KB
Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Sauvegarde terminée — taille : $([math]::Round($size, 1)) Ko"

# ── Nettoyage (rétention) ─────────────────────────────────────────────────
$cutoff  = (Get-Date).AddDays(-$RetentionDays)
$deleted = Get-ChildItem -Path $BackupDir -Filter "busgo_*.sql.gz" |
           Where-Object { $_.LastWriteTime -lt $cutoff }

foreach ($f in $deleted) {
    Remove-Item $f.FullName -Force
    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Supprimé : $($f.Name)"
}

Write-Host "[$(Get-Date -Format 'HH:mm:ss')] OK — $backupFile"
