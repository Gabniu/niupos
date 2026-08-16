[CmdletBinding()]
param(
    [switch] $ApiOnly
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$phpCiImage = 'nova-pos/php-ci:8.5.3'
$phpDockerfile = Join-Path $projectRoot 'infra/docker/php-ci/Dockerfile'
$phpBuildContext = Join-Path $projectRoot 'infra/docker/php-ci'
$apiSource = Join-Path $projectRoot 'apps/api'

function Invoke-Checked {
    param(
        [Parameter(Mandatory)]
        [string] $Description,

        [Parameter(Mandatory)]
        [scriptblock] $Command
    )

    Write-Host "==> $Description"
    & $Command
    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed with exit code $LASTEXITCODE."
    }
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker is required for CI verification.'
}

if (-not $ApiOnly) {
    if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
        throw 'Node.js and npm are required for architecture and web verification.'
    }

    Push-Location $projectRoot
    try {
        Invoke-Checked 'Installing locked Node dependencies' { npm ci }
        Invoke-Checked 'Running architecture tests' { npm run test:architecture }
        Invoke-Checked 'Linting the Next.js application' { npm --workspace '@nova/web' run lint }
        Invoke-Checked 'Building the Next.js application' { npm --workspace '@nova/web' run build }
        Invoke-Checked 'Validating the Docker Compose topology' { docker compose -f infra/compose.yaml config --quiet }
    }
    finally {
        Pop-Location
    }
}

Invoke-Checked 'Building the pinned PHP 8.5 CI image' {
    docker build --pull --tag $phpCiImage --file $phpDockerfile $phpBuildContext
}

Invoke-Checked 'Validating Composer metadata and running Laravel tests' {
    docker run --rm `
        --mount "type=bind,source=$apiSource,target=/source,readonly" `
        --tmpfs '/workspace:exec' `
        --workdir /workspace `
        $phpCiImage `
        sh -lc 'cp -a /source/. /workspace/ && mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions storage/logs && composer validate --strict --no-interaction && composer install --no-interaction --prefer-dist --no-progress && php artisan test'
}

Write-Host 'CI verification passed.'
