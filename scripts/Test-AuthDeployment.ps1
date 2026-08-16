[CmdletBinding()]
param(
    [string]$BaseUrl = 'https://novaauth.niuautomations.com'
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
Add-Type -AssemblyName System.Net.Http
$handler = [System.Net.Http.HttpClientHandler]::new()
$handler.AllowAutoRedirect = $false
$client = [System.Net.Http.HttpClient]::new($handler)

function Get-CheckedResponse {
    param(
        [Parameter(Mandatory)][string]$Uri,
        [int[]]$ExpectedStatus = @(200)
    )

    try { $response = $script:client.GetAsync($Uri).GetAwaiter().GetResult() }
    catch { throw "Request failed for $Uri`: $($_.Exception.Message)" }

    $statusCode = [int]$response.StatusCode
    if ($ExpectedStatus -notcontains $statusCode) {
        throw "Unexpected HTTP status $statusCode for $Uri (expected $($ExpectedStatus -join ', '))."
    }

    $headers = @{}
    foreach ($header in $response.Headers) { $headers[$header.Key.ToLowerInvariant()] = ($header.Value -join ', ') }
    foreach ($header in $response.Content.Headers) { $headers[$header.Key.ToLowerInvariant()] = ($header.Value -join ', ') }
    [pscustomobject]@{
        StatusCode = $statusCode
        Headers = $headers
        Content = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    }
}

$http = Get-CheckedResponse -Uri (($BaseUrl -replace '^https://', 'http://') + '/sign-in') -ExpectedStatus @(301, 308)
Write-Verbose "Redirect location: [$($http.Headers['location'])], expected [$BaseUrl/sign-in]"
if (-not ((([string]$http.Headers['location']).TrimEnd('/')) -eq "$BaseUrl/sign-in")) {
    throw "HTTP redirect did not point to the HTTPS sign-in origin."
}

$signIn = Get-CheckedResponse -Uri "$BaseUrl/sign-in"
foreach ($header in @('strict-transport-security', 'x-frame-options', 'x-content-type-options', 'referrer-policy')) {
    if (-not $signIn.Headers.ContainsKey($header)) { throw "Missing security header: $header" }
}

$discovery = Get-CheckedResponse -Uri "$BaseUrl/.well-known/openid-configuration/api/auth"
$metadata = $discovery.Content | ConvertFrom-Json
if ($metadata.issuer -ne "$BaseUrl/api/auth") { throw 'OIDC issuer does not match the public Auth origin.' }
foreach ($property in @('authorization_endpoint', 'token_endpoint', 'jwks_uri')) {
    $endpoint = [string]$metadata.$property
    $parsedEndpoint = $null
    if (-not [uri]::TryCreate($endpoint, [UriKind]::Absolute, [ref]$parsedEndpoint)) {
        throw "OIDC discovery is missing a valid $property."
    }
}
if (@($metadata.code_challenge_methods_supported) -notcontains 'S256') {
    throw 'OIDC discovery does not advertise S256 PKCE.'
}

$bootstrap = Get-CheckedResponse -Uri "$BaseUrl/api/bootstrap" -ExpectedStatus @(404)
if ($bootstrap.Content -notmatch 'Bootstrap is unavailable') {
    throw 'Bootstrap endpoint did not fail closed.'
}

Write-Output "NOVA Auth deployment smoke test passed for $BaseUrl."
