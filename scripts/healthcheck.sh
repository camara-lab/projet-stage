#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# BusGo — Health check de l'API et des services
#
# Usage    : ./scripts/healthcheck.sh [--quiet]
# Codes    : 0 = tout OK | 1 = au moins un service KO
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────
API_BASE="${API_BASE:-https://busgo.ma}"
TIMEOUT="${TIMEOUT:-10}"
QUIET="${1:-}"

# ── Couleurs ──────────────────────────────────────────────────────────────
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

OK="${GREEN}✓ OK${NC}"
FAIL="${RED}✗ KO${NC}"
WARN="${YELLOW}⚠ WARN${NC}"

ERRORS=0

check() {
    local name="$1"
    local url="$2"
    local expected_status="${3:-200}"

    local http_code
    http_code=$(curl -s -o /dev/null -w "%{http_code}" \
        --max-time "$TIMEOUT" \
        --connect-timeout 5 \
        "$url" 2>/dev/null || echo "000")

    if [ "$http_code" = "$expected_status" ]; then
        [ -z "$QUIET" ] && printf "  %-35s %b\n" "$name" "$OK"
        return 0
    else
        [ -z "$QUIET" ] && printf "  %-35s %b (HTTP %s)\n" "$name" "$FAIL" "$http_code"
        ERRORS=$((ERRORS + 1))
        return 1
    fi
}

check_json() {
    local name="$1"
    local url="$2"
    local jq_filter="$3"
    local expected="$4"

    local body
    body=$(curl -s --max-time "$TIMEOUT" --connect-timeout 5 "$url" 2>/dev/null || echo "{}")

    local value
    value=$(echo "$body" | jq -r "$jq_filter" 2>/dev/null || echo "")

    if [ "$value" = "$expected" ]; then
        [ -z "$QUIET" ] && printf "  %-35s %b\n" "$name" "$OK"
        return 0
    else
        [ -z "$QUIET" ] && printf "  %-35s %b (got: %s)\n" "$name" "$FAIL" "$value"
        ERRORS=$((ERRORS + 1))
        return 1
    fi
}

# ── Checks ────────────────────────────────────────────────────────────────
[ -z "$QUIET" ] && echo ""
[ -z "$QUIET" ] && echo "BusGo Health Check — $(date)"
[ -z "$QUIET" ] && echo "API : ${API_BASE}"
[ -z "$QUIET" ] && echo "─────────────────────────────────────────"

# API Health endpoint
check_json "API /health (status)" "${API_BASE}/api/health" ".status" "ok"

# HTTP→HTTPS redirect
REDIRECT_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    --max-time "$TIMEOUT" \
    --no-follow-redirect \
    "http://busgo.ma/api/health" 2>/dev/null || echo "000")
if [ "$REDIRECT_CODE" = "301" ]; then
    [ -z "$QUIET" ] && printf "  %-35s %b\n" "HTTP→HTTPS redirect" "$OK"
else
    [ -z "$QUIET" ] && printf "  %-35s %b (HTTP %s)\n" "HTTP→HTTPS redirect" "$WARN" "$REDIRECT_CODE"
fi

# Frontend
check "Frontend (homepage)" "${API_BASE}/" "200"

# Conteneurs Docker (si disponible en local)
if command -v docker &>/dev/null; then
    [ -z "$QUIET" ] && echo ""
    [ -z "$QUIET" ] && echo "Conteneurs Docker :"
    for service in nginx frontend symfony database; do
        state=$(docker compose -f "$(dirname "$0")/../docker-compose.prod.yml" \
            ps --format json "$service" 2>/dev/null \
            | jq -r '.State // "unknown"' 2>/dev/null || echo "unknown")
        if [ "$state" = "running" ]; then
            [ -z "$QUIET" ] && printf "  %-35s %b\n" "$service" "$OK"
        else
            [ -z "$QUIET" ] && printf "  %-35s %b (état: %s)\n" "$service" "$FAIL" "$state"
            ERRORS=$((ERRORS + 1))
        fi
    done
fi

# ── Résultat ──────────────────────────────────────────────────────────────
[ -z "$QUIET" ] && echo "─────────────────────────────────────────"
if [ "$ERRORS" -eq 0 ]; then
    [ -z "$QUIET" ] && printf "%b Tous les services sont opérationnels.\n\n" "$OK"
    exit 0
else
    [ -z "$QUIET" ] && printf "%b %d service(s) en erreur.\n\n" "$FAIL" "$ERRORS"
    exit 1
fi
