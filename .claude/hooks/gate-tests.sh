#!/usr/bin/env bash
# .claude/hooks/gate-tests.sh
set -uo pipefail

guard="$(git rev-parse --show-toplevel)/.git/claude-test-gate"

# One retry per turn. Without this you can loop.
if [[ -f "$guard" ]]; then
    rm -f "$guard"
    exit 0
fi

if ! out=$(vendor/bin/pest --compact 2>&1); then
    touch "$guard"
    { echo "The suite is red. Fix it before finishing:"; tail -n 40 <<< "$out"; } >&2
    exit 2
fi

exit 0
