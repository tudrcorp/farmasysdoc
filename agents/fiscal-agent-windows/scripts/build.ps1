<#
  Compila el agente (Release, x86) en .\publish.
  Requisitos: .NET SDK 8+ y lib\TfhkaNet.dll copiado desde
  "C:\Program Files (x86)\The Factory HKA\Fiscalizador (VE)\TfhkaNet.dll".
#>
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

if (-not (Test-Path "$root\lib\TfhkaNet.dll")) {
    $hka = 'C:\Program Files (x86)\The Factory HKA\Fiscalizador (VE)\TfhkaNet.dll'
    if (Test-Path $hka) { Copy-Item $hka "$root\lib\TfhkaNet.dll" }
    else { throw 'Falta lib\TfhkaNet.dll (SDK de The Factory HKA).' }
}

dotnet test "$root\tests\Farmadoc.FiscalAgent.Core.Tests" -c Release
dotnet publish "$root\src\Farmadoc.FiscalAgent.Service" -c Release -o "$root\publish"
Copy-Item "$PSScriptRoot\install.ps1", "$PSScriptRoot\uninstall.ps1" "$root\publish" -Force
Write-Host "Listo: $root\publish"
