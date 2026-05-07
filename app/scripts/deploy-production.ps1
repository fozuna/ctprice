Param()
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")
git pull --ff-only
if (-not (Test-Path "config\config.php")) {
  if (Test-Path "config\config.production.php") {
    Copy-Item "config\config.production.php" "config\config.php"
  } elseif (Test-Path "config\config.production.php.example") {
    Copy-Item "config\config.production.php.example" "config\config.php"
  }
}
php -l index.php | Out-Null
