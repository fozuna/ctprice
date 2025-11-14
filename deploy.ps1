param(
    [string]$OutputDir = "dist",
    [string]$PackageName = "ctprice",
    [switch]$SkipBuildCss
)

$ErrorActionPreference = "Stop"
$repoRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $repoRoot

Write-Host "== Empacotamento CTPrice ==" -ForegroundColor Cyan
Write-Host "Root:" $repoRoot

# 1) Build CSS do Tailwind (produção)
if (-not $SkipBuildCss) {
  Write-Host "Compilando Tailwind (npm run build:css)..." -ForegroundColor Yellow
  $npm = Get-Command npm -ErrorAction SilentlyContinue
  if ($null -eq $npm) {
    Write-Warning "npm não encontrado. Pulando build do CSS. Certifique-se de que public/assets/tailwind.css existe."
  } else {
    $proc = Start-Process -FilePath $npm.Source -ArgumentList "run", "build:css" -NoNewWindow -Wait -PassThru
    if ($proc.ExitCode -ne 0) {
      throw "Falha ao compilar Tailwind (ExitCode=$($proc.ExitCode))."
    }
  }
}

# Verificar se CSS existe
$cssPath = Join-Path $repoRoot "public/assets/tailwind.css"
if (-not (Test-Path $cssPath)) {
  throw "Arquivo CSS não encontrado: $cssPath"
}

# 2) Preparar diretórios
$destRoot = Join-Path $repoRoot $OutputDir
$destApp = Join-Path $destRoot $PackageName
if (Test-Path $destApp) { Remove-Item $destApp -Recurse -Force }
New-Item -ItemType Directory -Path $destApp | Out-Null

# 3) Copiar pastas essenciais
Write-Host "Copiando app/ e public/..." -ForegroundColor Yellow
Copy-Item -Path (Join-Path $repoRoot "app") -Destination $destApp -Recurse -Force
Copy-Item -Path (Join-Path $repoRoot "public") -Destination $destApp -Recurse -Force

# 4) Remover artefatos de desenvolvimento do pacote
$devFiles = @(
  "tailwind.config.js",
  "package.json",
  "package-lock.json",
  "router.php",
  "deploy.ps1",
  "README.md",
  "assets"
)
foreach ($f in $devFiles) {
  $p = Join-Path $destApp $f
  if (Test-Path $p) { Remove-Item $p -Recurse -Force }
}

# 5) Incluir eventuais scripts SQL (se existirem)
Get-ChildItem -Path $repoRoot -Filter *.sql -ErrorAction SilentlyContinue | ForEach-Object {
  Copy-Item -Path $_.FullName -Destination $destApp -Force
}

# 6) Compactar em ZIP
$zipPath = Join-Path $destRoot ("$PackageName.zip")
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Write-Host "Gerando ZIP: $zipPath" -ForegroundColor Yellow
Compress-Archive -Path (Join-Path $destApp "*") -DestinationPath $zipPath -Force

Write-Host "Pacote pronto: $zipPath" -ForegroundColor Green
Write-Host "Finalize ajustando app/core/Config.php na VPS (base_url, DB, env)." -ForegroundColor Green