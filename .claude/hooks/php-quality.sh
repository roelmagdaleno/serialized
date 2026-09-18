#!/usr/bin/env bash
# .claude/hooks/php-quality.sh
set -uo pipefail

input=$(cat)
file=$(jq -r '.tool_input.file_path // empty' <<< "$input")

[[ "$file" == *.php && -f "$file" ]] || exit 0

# Save a hash before Pint formatting
before=$(md5sum "$file")
vendor/bin/pint "$file" > /dev/null 2>&1
after=$(md5sum "$file")

if ! report=$(vendor/bin/phpstan analyse --no-progress --error-format=raw "$file" 2>&1); then
    {
        echo "PHPStan rejected ${file}:"
        echo "$report"
    } >&2
    exit 2
fi

# If Pint formatted the file, tell Claude via stdout
if [[ "$before" != "$after" ]]; then
    jq -n \
        --arg ctx "Pint reformatted this file after the edit, so the contents on disk differ from what was written." \
        '{hookSpecificOutput: {hookEventName: "PostToolUse", additionalContext: $ctx}}'
fi

exit 0
