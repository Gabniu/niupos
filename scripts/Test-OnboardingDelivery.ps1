[CmdletBinding()]
param(
    [string]$BaseUrl = 'https://pos.niuautomations.com',
    [string]$CookieHeader,
    [string]$DeliveryId,
    [switch]$Send
)

$ErrorActionPreference = 'Stop'
$BaseUrl = $BaseUrl.TrimEnd('/')
if ([string]::IsNullOrWhiteSpace($CookieHeader)) {
    throw 'Provide an authenticated browser Cookie header. The script never prints or stores it.'
}
if ($Send -and [string]::IsNullOrWhiteSpace($DeliveryId)) {
    throw 'The -Send switch requires -DeliveryId and performs a real email delivery attempt.'
}

Add-Type -AssemblyName System.Net.Http
$client = [System.Net.Http.HttpClient]::new()

function Invoke-OnboardingApi {
    param(
        [Parameter(Mandatory)][ValidateSet('GET', 'POST')][string]$Method,
        [Parameter(Mandatory)][string]$Path
    )

    $httpMethod = if ($Method -eq 'GET') { [System.Net.Http.HttpMethod]::Get } else { [System.Net.Http.HttpMethod]::Post }
    $request = [System.Net.Http.HttpRequestMessage]::new($httpMethod, "$script:BaseUrl$Path")
    [void]$request.Headers.TryAddWithoutValidation('Cookie', $script:CookieHeader)
    [void]$request.Headers.TryAddWithoutValidation('Origin', $script:BaseUrl)
    [void]$request.Headers.TryAddWithoutValidation('Accept', 'application/json')
    if ($Method -eq 'POST') {
        $request.Content = [System.Net.Http.StringContent]::new('{}', [Text.Encoding]::UTF8, 'application/json')
    }

    try { $response = $script:client.SendAsync($request).GetAwaiter().GetResult() }
    catch { throw "Request failed for $Path`: $($_.Exception.Message)" }

    $status = [int]$response.StatusCode
    $content = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    if ($status -lt 200 -or $status -ge 300) {
        throw "Onboarding API returned HTTP $status for $Path."
    }

    if ([string]::IsNullOrWhiteSpace($content)) { return $null }
    return $content | ConvertFrom-Json
}

$preferences = Invoke-OnboardingApi -Method GET -Path '/api/v1/onboarding/notification-preferences'
if ($null -eq $preferences.data) { throw 'Preferences response did not contain data.' }
Write-Output "External email delivery available: $($preferences.data.externalDeliveryAvailable)"

$deliveries = Invoke-OnboardingApi -Method GET -Path '/api/v1/onboarding/notification-deliveries'
$items = @($deliveries.data)
Write-Output "Tenant-scoped delivery records returned: $($items.Count)"
foreach ($item in $items | Select-Object -First 10) {
    Write-Output ("Delivery {0}: channel={1}, status={2}, attempts={3}" -f $item.id, $item.channel, $item.status, $item.attempts)
}

if ($Send) {
    $result = Invoke-OnboardingApi -Method POST -Path "/api/v1/onboarding/notification-deliveries/$DeliveryId/send"
    Write-Output "Explicit delivery result: $($result.data.status)"
}
