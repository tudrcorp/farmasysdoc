<#
  Instala o actualiza el agente fiscal como servicio de Windows. Ejecutar como Administrador
  desde la carpeta publish:

    .\install.ps1 -ServerUrl https://farmadoc.example.com -Token fd_fp_xxx -ComPort COM5

  Cierre el sistema de facturación actual (p. ej. Valery) durante la instalación: el diagnóstico lee la
  máquina fiscal una vez y guarda sus datos (registro, alícuotas, reloj) para la lista de chequeo de Farmaadmin.
  El modo, el registro esperado y los medios de pago se configuran por máquina en Farmaadmin.

  En una actualización basta con .\install.ps1 (conserva agent.json y la bitácora).
  Si agent.json ya existe, -ServerUrl, -Token, -ComPort y -Registry que se indiquen lo sobrescriben.
#>
param(
    [string]$ServerUrl,
    [string]$Token,
    [string]$ComPort = 'COM5',
    [string]$Registry,
    [switch]$SkipUsbPowerSettings
)

$ErrorActionPreference = 'Stop'

# Recibe la URL de Farmadoc tal como la pegue el usuario y devuelve la raíz del sitio:
# quita espacios, barras finales y rutas del panel o de la API (el agente agrega /api/fiscal-agent/v1/).
function ConvertTo-ServerRootUrl([string]$url) {
    $clean = $url.Trim().TrimEnd('/')
    $uri = $null
    if (-not [Uri]::TryCreate($clean, [UriKind]::Absolute, [ref]$uri) -or $uri.Scheme -notin @('http', 'https')) {
        throw "-ServerUrl no es una URL válida: '$url'. Ejemplo: https://farmasysdoc.farmadoc.net"
    }

    $path = $uri.AbsolutePath.TrimEnd('/')
    $root = $uri.GetLeftPart([UriPartial]::Authority)
    if ($path -match '^(.*?)/(farmaadmin|business-partners|api)(/.*)?$') {
        $root = $root + $Matches[1]
        Write-Warning "-ServerUrl incluía una ruta del sistema; se usará la raíz del sitio: $root"
    } elseif ($path) {
        $root = $root + $path
    }

    return $root
}

function Test-PlaceholderValue([string]$value) {
    return -not $value -or $value -match 'TU-DOMINIO|example\.com|PEGAR_TOKEN'
}

function Set-ConfigValue($config, [string]$name, $value) {
    $config | Add-Member -NotePropertyName $name -NotePropertyValue $value -Force
}

if ($ServerUrl) { $ServerUrl = ConvertTo-ServerRootUrl $ServerUrl }
if ($Token) {
    $Token = $Token.Trim()
    if ($Token -notmatch '^fd_fp_[0-9a-f]+$') {
        Write-Warning 'El token no tiene el formato esperado (fd_fp_…). Cópielo de nuevo desde Farmaadmin.'
    }
}

$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Ejecute PowerShell como Administrador.'
}

$serviceName = 'FarmadocFiscalAgent'
$installDir  = Join-Path ${env:ProgramFiles(x86)} 'Farmadoc\FiscalAgent'
$dataDir     = Join-Path $env:ProgramData 'FarmadocFiscalAgent'
$configPath  = Join-Path $dataDir 'agent.json'

$existing = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if ($existing -and $existing.Status -ne 'Stopped') {
    Write-Host 'Deteniendo servicio…'
    Stop-Service -Name $serviceName -Force
    $existing.WaitForStatus('Stopped', [TimeSpan]::FromSeconds(90))
}

Write-Host "Copiando archivos a $installDir"
New-Item -ItemType Directory -Force -Path $installDir | Out-Null
Copy-Item -Path (Join-Path $PSScriptRoot '*') -Destination $installDir -Recurse -Force -Exclude 'install.ps1', 'uninstall.ps1'

New-Item -ItemType Directory -Force -Path $dataDir | Out-Null

if (-not (Test-Path $configPath)) {
    if (-not $ServerUrl -or -not $Token) {
        throw 'Primera instalación: indique -ServerUrl y -Token.'
    }

    $config = Get-Content (Join-Path $installDir 'agent.example.json') -Raw | ConvertFrom-Json
    $config.server_url = $ServerUrl
    $config.agent_token = $Token
    $config.com_port = $ComPort
    if ($Registry) { $config.expected_registry = $Registry }
    $config | ConvertTo-Json -Depth 5 | Set-Content -Path $configPath -Encoding UTF8
    Write-Host "Configuración creada: $configPath"
} else {
    $config = Get-Content $configPath -Raw | ConvertFrom-Json
    $changed = @()

    if ($ServerUrl) { Set-ConfigValue $config 'server_url' $ServerUrl; $changed += 'server_url' }
    if ($Token) { Set-ConfigValue $config 'agent_token' $Token; $changed += 'agent_token' }
    if ($PSBoundParameters.ContainsKey('ComPort')) { Set-ConfigValue $config 'com_port' $ComPort; $changed += 'com_port' }
    if ($Registry) { Set-ConfigValue $config 'expected_registry' $Registry; $changed += 'expected_registry' }

    if ($changed.Count -gt 0) {
        Copy-Item $configPath "$configPath.bak" -Force
        $config | ConvertTo-Json -Depth 5 | Set-Content -Path $configPath -Encoding UTF8
        Write-Host "Configuración actualizada ($($changed -join ', ')): $configPath (respaldo en agent.json.bak)"
    } else {
        Write-Host "Se conserva la configuración existente: $configPath"
    }

    if ((Test-PlaceholderValue $config.server_url) -or (Test-PlaceholderValue $config.agent_token)) {
        throw "agent.json todavía tiene valores de ejemplo (server_url: '$($config.server_url)'). Ejecute de nuevo con -ServerUrl y -Token."
    }
}

# El token solo lo pueden leer SYSTEM y Administradores.
# SIDs: S-1-5-18 = SYSTEM, S-1-5-32-544 = Administradores (independiente del idioma de Windows).
icacls $dataDir /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)F' '*S-1-5-32-544:(OI)(CI)F' | Out-Null

$exe = Join-Path $installDir 'FarmadocFiscalAgent.exe'

if (-not $existing) {
    sc.exe create $serviceName binPath= "`"$exe`"" start= delayed-auto DisplayName= 'Farmadoc - Agente de máquina fiscal' | Out-Null
    sc.exe description $serviceName 'Imprime en la máquina fiscal HKA las facturas encoladas en Farmadoc.' | Out-Null
}

# Reinicio automático ante fallos: 5 s, 5 s y luego 30 s.
sc.exe failure $serviceName reset= 86400 actions= restart/5000/restart/5000/restart/30000 | Out-Null

if (-not $SkipUsbPowerSettings) {
    # Evita que Windows suspenda el USB de la máquina fiscal.
    powercfg /setacvalueindex SCHEME_CURRENT 2a737441-1930-4402-8d77-b2bebba308a3 48e6b7a6-50f5-4782-a5d4-53bb8f07e226 0
    powercfg /setdcvalueindex SCHEME_CURRENT 2a737441-1930-4402-8d77-b2bebba308a3 48e6b7a6-50f5-4782-a5d4-53bb8f07e226 0
    powercfg /setactive SCHEME_CURRENT
}

Write-Host 'Diagnóstico:'
& $exe --check
if ($LASTEXITCODE -ne 0) {
    Write-Warning 'El diagnóstico reportó errores; el servicio NO se inició. Corrija y ejecute: Start-Service FarmadocFiscalAgent'
    exit 1
}

Start-Service -Name $serviceName
Write-Host 'Servicio iniciado. Logs en' (Join-Path $dataDir 'logs')
