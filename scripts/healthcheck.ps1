# ─────────────────────────────────────────────────────────────────────────────
# BusGo — Health check (Windows / PowerShell)
#
# Usage : .\scripts\healthcheck.ps1
#         .\scripts\healthcheck.ps1 -ApiBase "https://busgo.ma"
# Codes : exit 0 = OK | exit 1 = au moins un service KO
# ─────────────────────────────────────────────────────────────────────────────

param(
    [string]$ApiBase = "https://busgo.ma",
    [int]   $Timeout = 10
)

Set-StrictMode -Version Latest
$errors = 0

function Write-Check {
    param([string]$Name, [bool]$Ok, [string]$Detail = "")
    $status = if ($Ok) { "`e[32m✓ OK`e[0m" } else { "`e[31m✗ KO`e[0m" }
    $line   = "  {0,-38} {1}" -f $Name, $status
    if ($Detail) { $line += " ($Detail)" }
    Write-Host $line
}

function Invoke-Check {
    param([string]$Name, [string]$Url, [int]$ExpectedCode = 200)
    try {
        $resp = Invoke-WebRequest -Uri $Url -TimeoutSec $Timeout `
                    -UseBasicParsing -MaximumRedirection 0 `
                    -ErrorAction SilentlyContinue
        $code = $resp.StatusCode
    } catch {
        $code = 0
    }
    $ok = ($code -eq $ExpectedCode)
    Write-Check $Name $ok "HTTP $code"
    if (-not $ok) { $script:errors++ }
}

function Invoke-JsonCheck {
    param([string]$Name, [string]$Url, [string]$Field, [string]$Expected)
    try {
        $resp  = Invoke-RestMethod -Uri $Url -TimeoutSec $Timeout -ErrorAction Stop
        $value = $resp.$Field
    } catch {
        $value = $null
    }
    $ok = ($value -eq $Expected)
    Write-Check $Name $ok $(if (-not $ok) { "got: $value" } else { "" })
    if (-not $ok) { $script:errors++ }
}

# ── Checks ────────────────────────────────────────────────────────────────
Write-Host ""
Write-Host "BusGo Health Check — $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')"
Write-Host "API : $ApiBase"
Write-Host "─────────────────────────────────────────────"

Invoke-JsonCheck "API /health (status)"       "$ApiBase/api/health"  "status"  "ok"
Invoke-Check     "Frontend (homepage)"        "$ApiBase/"             200
Invoke-Check     "HTTP → HTTPS redirect"      "http://busgo.ma/api/health" 301

# ── Conteneurs Docker ─────────────────────────────────────────────────────
$composeFile = Join-Path $PSScriptRoot "..\docker-compose.prod.yml"
if (Test-Path $composeFile) {
    Write-Host ""
    Write-Host "Conteneurs Docker :"

    foreach ($service in @("nginx", "frontend", "symfony", "database")) {
        try {
            $state = docker compose -f $composeFile ps --format json $service 2>$null |
                     ConvertFrom-Json | Select-Object -ExpandProperty State -ErrorAction SilentlyContinue
        } catch {
            $state = "unknown"
        }
        $ok = ($state -eq "running")
        Write-Check $service $ok $(if (-not $ok) { "état: $state" } else { "" })
        if (-not $ok) { $errors++ }
    }
}

# ── Résultat ──────────────────────────────────────────────────────────────
Write-Host "─────────────────────────────────────────────"
if ($errors -eq 0) {
    Write-Host "`e[32m✓ Tous les services sont opérationnels.`e[0m"
    exit 0
} else {
    Write-Host "`e[31m✗ $errors service(s) en erreur.`e[0m"
    exit 1
}
