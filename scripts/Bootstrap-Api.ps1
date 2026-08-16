[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = 'Stop'
$apiPath = Join-Path $ProjectRoot 'apps\api'

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker CLI is required to bootstrap the Laravel API.'
}

docker info *> $null
if ($LASTEXITCODE -ne 0) {
    throw 'Docker engine is not running. Start Docker Desktop and retry.'
}

$existing = @(Get-ChildItem -LiteralPath $apiPath -Force -ErrorAction SilentlyContinue)
$appsPath = Join-Path $ProjectRoot 'apps'

if (Test-Path -LiteralPath (Join-Path $apiPath 'composer.json')) {
    docker run --rm `
        --mount "type=bind,source=$apiPath,target=/app" `
        --workdir /app `
        composer:2.8.12 `
        install --no-interaction
}
elseif ($existing.Count -eq 0) {
    docker run --rm `
        --mount "type=bind,source=$appsPath,target=/workspace" `
        --workdir /workspace `
        composer:2.8.12 `
        create-project 'laravel/laravel:^13.0' api --no-interaction
}
else {
    throw "API directory is non-empty but has no composer.json; refusing to overwrite: $apiPath"
}

if ($LASTEXITCODE -ne 0) {
    throw 'Laravel project generation failed.'
}

Write-Output 'Laravel API dependencies are ready in apps/api.'
