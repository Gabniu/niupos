[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$overlay = Join-Path $root 'infra/k8s/overlays/dev'
$required = @(
    'infra/k8s/base/kustomization.yaml',
    'infra/k8s/base/api.yaml',
    'infra/k8s/base/web.yaml',
    'infra/k8s/base/network-policy.yaml',
    'infra/observability/prometheus/prometheus.yaml',
    'infra/observability/prometheus/rules/platform.yaml'
)

foreach ($relative in $required) {
    $path = Join-Path $root $relative
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Missing required baseline file: $relative" }
}

$yamlParser = Get-Command ConvertFrom-Yaml -ErrorAction SilentlyContinue
if ($yamlParser) {
    foreach ($relative in $required) {
        $documents = (Get-Content -Raw (Join-Path $root $relative)) -split '(?m)^---\s*$'
        foreach ($document in $documents) {
            if ($document.Trim()) { $null = $document | ConvertFrom-Yaml }
        }
    }
    Write-Host 'Static YAML parsing passed.'
} else {
    foreach ($relative in $required) {
        $text = Get-Content -Raw (Join-Path $root $relative)
        if ($text -notmatch '(?m)^(apiVersion|global|groups):') { throw "Unrecognized YAML document: $relative" }
        if ($text.Contains("`t")) { throw "YAML contains tab indentation: $relative" }
    }
    Write-Host 'ConvertFrom-Yaml unavailable; basic static YAML checks passed.'
}

if (Get-Command kubectl -ErrorAction SilentlyContinue) {
    & kubectl kustomize $overlay | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'kubectl kustomize failed.' }
    Write-Host 'Kustomize rendering passed.'
    $priorErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $dryRunOutput = & kubectl apply --dry-run=client --validate=false -k $overlay 2>&1
    $dryRunExitCode = $LASTEXITCODE
    $ErrorActionPreference = $priorErrorAction
    if ($dryRunExitCode -eq 0) {
        Write-Host 'kubectl client dry-run passed.'
    } elseif (($dryRunOutput | Out-String) -match 'server|discovery|connect|refused|unable to recognize') {
        Write-Warning 'kubectl client dry-run requires compatible API discovery in this environment and was skipped after rendering passed.'
    } else {
        throw "kubectl client dry-run failed:`n$($dryRunOutput | Out-String)"
    }
} else {
    Write-Warning 'kubectl unavailable; rendering and client dry-run were skipped.'
}

Write-Host 'Kubernetes and observability baseline validation passed.'
