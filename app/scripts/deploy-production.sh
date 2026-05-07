#!/usr/bin/env bash
set -e
cd "$(dirname "$0")/.."
git pull --ff-only
if [ ! -f config/config.php ]; then
  if [ -f config/config.production.php ]; then
    cp config/config.production.php config/config.php
  elif [ -f config/config.production.php.example ]; then
    cp config/config.production.php.example config/config.php
  fi
fi
php -l index.php >/dev/null
