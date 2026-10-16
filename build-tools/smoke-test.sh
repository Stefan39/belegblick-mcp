#!/bin/sh
# Smoke test for a built server (PHAR or static binary): speaks MCP over STDIO and checks that
# initialize and tools/list answer. Needs no PHP, only a POSIX shell.
# With SMOKE_TEST_TLS=1 it also calls open_items with an invalid key against the real Lexware
# API and expects HTTP 401. That proves HTTPS works, i.e. the CA certificates were found.
# Usage: [SMOKE_TEST_TLS=1] build-tools/smoke-test.sh <command> [args...]
set -eu

if [ "$#" -eq 0 ]; then
    echo "Usage: [SMOKE_TEST_TLS=1] $0 <command> [args...]" >&2
    exit 2
fi

initialize='{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"smoke-test","version":"1"}}}'
initialized='{"jsonrpc":"2.0","method":"notifications/initialized"}'
tools_list='{"jsonrpc":"2.0","id":2,"method":"tools/list"}'
tools_call='{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"open_items","arguments":{}}}'

expect() {
    case "$output" in
        *"$1"*) ;;
        *)
            echo "Smoke test failed: '$1' missing in server output:" >&2
            echo "$output" >&2
            exit 1
            ;;
    esac
}

output=$(printf '%s\n' "$initialize" "$initialized" "$tools_list" | LEXWARE_API_KEY=smoke-test "$@")
expect '"serverInfo":{"name":"lexware-php-mcp"'
expect '"name":"open_items"'

if [ "${SMOKE_TEST_TLS:-0}" = 1 ]; then
    output=$(printf '%s\n' "$initialize" "$initialized" "$tools_call" | LEXWARE_API_KEY=invalid-smoke-test-key "$@")
    expect 'returned HTTP 401'
fi

echo "Smoke test passed: $*"
