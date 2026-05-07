#!/usr/bin/env bash
set -e
cd "$(dirname "$0")/.."
git pull --ff-only
if [ ! -f config/config.php ]; then
  if [ -f config/config.staging.php ]; then
    cp config/config.staging.php config/config.php
  elif [ -f config/config.staging.php.example ]; then
    cp config/config.staging.php.example config/config.php
  fi
fi
php -l index.php >/dev/null
