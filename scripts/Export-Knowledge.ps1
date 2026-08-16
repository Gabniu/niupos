[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = 'Stop'
$graphDir = Join-Path $ProjectRoot 'graphify-out'
$pythonFile = Join-Path $graphDir '.graphify_python'
$graphFile = Join-Path $graphDir 'graph.json'
$vaultDir = Join-Path $ProjectRoot 'vault\90-generated\graphify'

if (-not (Test-Path -LiteralPath $pythonFile)) {
    throw "Graphify interpreter record is missing: $pythonFile"
}

if (-not (Test-Path -LiteralPath $graphFile)) {
    throw "Graphify graph is missing: $graphFile. Build or update the graph first."
}

$graphifyPython = (Get-Content -Raw -LiteralPath $pythonFile).Trim()
if (-not (Test-Path -LiteralPath $graphifyPython)) {
    throw "Recorded Graphify interpreter does not exist: $graphifyPython"
}

Push-Location $ProjectRoot
try {
    & $graphifyPython -m graphify export html
    if ($LASTEXITCODE -ne 0) { throw 'Graphify HTML export failed.' }

    & $graphifyPython -m graphify export obsidian --dir $vaultDir
    if ($LASTEXITCODE -ne 0) { throw 'Graphify Obsidian export failed.' }

    & (Join-Path $PSScriptRoot 'Test-Knowledge.ps1') -ProjectRoot $ProjectRoot
    if ($LASTEXITCODE -ne 0) { throw 'Knowledge-system validation failed.' }
}
finally {
    Pop-Location
}

