[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = 'Stop'
$graphFile = Join-Path $ProjectRoot 'graphify-out\graph.json'
$reportFile = Join-Path $ProjectRoot 'graphify-out\GRAPH_REPORT.md'
$htmlFile = Join-Path $ProjectRoot 'graphify-out\graph.html'
$generatedVault = Join-Path $ProjectRoot 'vault\90-generated\graphify'
$ignoreFile = Join-Path $ProjectRoot '.graphifyignore'

$required = @($graphFile, $reportFile, $htmlFile, $generatedVault, $ignoreFile)
$missing = @($required | Where-Object { -not (Test-Path -LiteralPath $_) })
if ($missing.Count -gt 0) {
    throw "Knowledge artifacts are missing:`n$($missing -join "`n")"
}

$ignore = Get-Content -Raw -LiteralPath $ignoreFile
if ($ignore -notmatch '(?m)^graphify-out/$' -or $ignore -notmatch '(?m)^vault/90-generated/$') {
    throw 'Generated Graphify output is not excluded from recursive ingestion.'
}

$graph = Get-Content -Raw -LiteralPath $graphFile | ConvertFrom-Json
if ($graph.nodes.Count -eq 0) { throw 'Knowledge graph contains no nodes.' }

$nodeIds = @{}
foreach ($node in $graph.nodes) { $nodeIds[$node.id] = $true }
$badEdges = @($graph.links | Where-Object {
    -not $nodeIds.ContainsKey($_.source) -or -not $nodeIds.ContainsKey($_.target)
})
if ($badEdges.Count -gt 0) {
    throw "Knowledge graph contains $($badEdges.Count) dangling edge(s)."
}

Write-Output "Knowledge validation passed: $($graph.nodes.Count) nodes, $($graph.links.Count) edges."

