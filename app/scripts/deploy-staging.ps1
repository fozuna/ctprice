Param()
$ErrorActionPreference = "Stop"
Set-Location (Join-Path $PSScriptRoot "..")
git pull --ff-only
if (-not (Test-Path "config\config.php")) {
  if (Test-Path "config\config.staging.php") {
    Copy-Item "config\config.staging.php" "config\config.php"
  } elseif (Test-Path "config\config.staging.php.example") {
    Copy-Item "config\config.staging.php.example" "config\config.php"
  }
}
php -l index.php | Out-Null
