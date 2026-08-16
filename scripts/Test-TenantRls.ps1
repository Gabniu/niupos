[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = 'Stop'
$composeFile = Join-Path $ProjectRoot 'infra\compose.yaml'
$sqlFile = Join-Path $ProjectRoot 'tests\integration\postgres\tenant_rls.sql'

docker compose -f $composeFile up -d --wait postgres
if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL failed to become ready.' }

Get-Content -Raw -LiteralPath $sqlFile |
    docker compose -f $composeFile exec -T postgres psql -U nova -d nova

if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL tenant RLS proof failed.' }

Write-Output 'PostgreSQL tenant RLS proof passed.'
