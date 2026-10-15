#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
remote='u184230832@46.17.172.214'
remote_root='/home/u184230832/domains/darkblue-hummingbird-464264.hostingersite.com/public_html/securevote'
key='/Users/jcnino/.ssh/securevote_hostinger'
ssh_command="ssh -i $key -p 65002 -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=yes"

if [[ "${1:-}" == '--dry-run' ]]; then
  dry_run=true
  shift
else
  dry_run=false
fi

if (( $# == 0 )); then
  printf 'Usage: %s [--dry-run] path/to/file [path/to/another/file ...]\n' "$0" >&2
  exit 2
fi

cd "$repo_root"
files=()
for file in "$@"; do
  if [[ "$file" == /* || "$file" == -* || "$file" == *'..'* || "$file" == *'//'*
     || "$file" == .git/* || "$file" == storage/* || "$file" == config/config.local.php
     || ! -f "$file" || -L "$file" ]]; then
    printf 'Refusing unsafe or missing path: %s\n' "$file" >&2
    exit 2
  fi
  files+=("$file")
done

if [[ "$dry_run" == true ]]; then
  rsync -rtv --checksum --relative --dry-run \
    -e "$ssh_command" -- "${files[@]}" "$remote:$remote_root/"
else
  rsync -rtv --checksum --relative \
    -e "$ssh_command" -- "${files[@]}" "$remote:$remote_root/"
fi
