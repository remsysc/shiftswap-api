#!/usr/bin/env bash
set -euo pipefail

if git rev-parse --show-toplevel >/dev/null 2>&1; then
    ROOT_DIR="$(git rev-parse --show-toplevel)"
    cd "$ROOT_DIR"

    changed_files="$({
        git diff --name-only
        git diff --cached --name-only
        git ls-files --others --exclude-standard
    } | sort -u)"
else
    ROOT_DIR="$(pwd)"
    changed_files="<non-git working tree>"
    echo "Git metadata not found; running full feature validation."
fi

if [[ -z "$changed_files" ]]; then
    exit 0
fi

if [[ "$changed_files" == "<non-git working tree>" ]]; then
    feature_changes="$changed_files"
else
    feature_changes="$(printf '%s\n' "$changed_files" | grep -E '^(app|bootstrap|config|database|resources|routes|tests)/|^(artisan|composer\.json|package\.json|phpunit\.xml|vite\.config\.)' || true)"
fi

if [[ -z "$feature_changes" ]]; then
    echo "No application or test changes detected; skipping feature validation."
    exit 0
fi

echo "Feature changes detected:"
printf '  %s\n' "$feature_changes"

test_files=()
while IFS= read -r file; do
    if [[ -n "$file" && -f "$file" ]]; then
        test_files+=("$file")
    fi
done < <(printf '%s\n' "$feature_changes" | grep -E '^tests/(Feature|Unit)/.*Test\.php$' || true)

if ((${#test_files[@]} > 0)); then
    echo "Running isolated tests for changed test files..."
    php artisan test --compact "${test_files[@]}"
else
    echo "No changed test file detected; running the full Pest suite..."
    php artisan test --compact
fi

echo "Running application checks..."
composer ci:check

echo "Feature validation passed."
