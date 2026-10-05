#!/bin/sh
set -eu
if [ "$#" -ne 1 ]; then
    printf 'Usage: %s TARGET_WEBROOT\n' "$0" >&2
    exit 2
fi
source_dir=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
target_dir=$1
case "$target_dir" in
    "$source_dir"|"$source_dir"/*)
        printf 'Target must be outside the source directory.\n' >&2
        exit 2
        ;;
esac
mkdir -p "$target_dir/students" "$target_dir/konzultace"
rsync -a --exclude='backup/' --exclude='_private/archive/' --exclude='konzultace/' --exclude='service/' --exclude='scripts/' --exclude='tests/' --exclude='REVIZE.md' --exclude='*.DS_Store' "$source_dir/" "$target_dir/students/"
rsync -a --exclude='*.DS_Store' "$source_dir/konzultace/" "$target_dir/konzultace/"
printf 'Staged /students and /konzultace in %s\n' "$target_dir"
