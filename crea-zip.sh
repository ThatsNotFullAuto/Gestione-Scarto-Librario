#!/usr/bin/env sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
plugin_dir="$repo_root/gestione-scarto-librario"
releases_dir="$repo_root/releases"

cd "$plugin_dir"
npm ci
npm run test:security:smoke:self
npm run release

version=$(node -p "require('./package.json').version")
zip_name="gestione-scarto-librario-$version.zip"

mkdir -p "$releases_dir"
mv "$repo_root/$zip_name" "$releases_dir/$zip_name"
mv "$repo_root/$zip_name.sha256" "$releases_dir/$zip_name.sha256"

cd "$releases_dir"
sha256sum -c "$zip_name.sha256"
printf 'Release creata: %s\n' "$releases_dir/$zip_name"
