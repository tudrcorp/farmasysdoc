<#
  Detiene y elimina el servicio. Conserva C:\ProgramData\FarmadocFiscalAgent (configuración, bitácora y logs)
  salvo que se indique -RemoveData. No borre la bitácora si quedan resultados sin entregar al servidor.
#>
param([switch]$RemoveData)

$ErrorActionPreference = 'Stop'
$serviceName = 'FarmadocFiscalAgent'

if (Get-Service -Name $serviceName -ErrorAction SilentlyContinue) {
    Stop-Service -Name $serviceName -Force -ErrorAction SilentlyContinue
    sc.exe delete $serviceName | Out-Null
}

Remove-Item -Recurse -Force (Join-Path ${env:ProgramFiles(x86)} 'Farmadoc\FiscalAgent') -ErrorAction SilentlyContinue

if ($RemoveData) {
    Remove-Item -Recurse -Force (Join-Path $env:ProgramData 'FarmadocFiscalAgent')
}

Write-Host 'Agente desinstalado.'
