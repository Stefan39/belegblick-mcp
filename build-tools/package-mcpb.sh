#!/bin/sh
# Packs the standalone binaries into a Claude Desktop extension (.mcpb).
# Usage:
#   build-tools/package-mcpb.sh darwin <output.mcpb> <arm64 binary> <x86_64 binary>
#   build-tools/package-mcpb.sh win32 <output.mcpb> <x86_64 .exe>
# Needs jq and npx (Node.js).
set -eu

# renovate: datasource=npm depName=@anthropic-ai/mcpb
MCPB_VERSION=2.1.2

usage() {
    sed -n '3,5p' "$0" | sed 's/^# //' >&2
    exit 2
}

[ "$#" -ge 3 ] || usage
platform=$1
output=$2
root=$(cd "$(dirname "$0")/.." && pwd)
staging=$(mktemp -d)
trap 'rm -rf "$staging"' EXIT
mkdir -p "$staging/server"

case "$platform" in
    darwin)
        [ "$#" -eq 4 ] || usage
        # One extension for Apple silicon and Intel Macs; the launcher picks the binary.
        cp "$3" "$staging/server/lexware-php-mcp-arm64"
        cp "$4" "$staging/server/lexware-php-mcp-x86_64"
        cp "$root/build-tools/mcpb/macos-launcher.sh" "$staging/server/lexware-php-mcp"
        chmod 0755 "$staging/server/"*
        ;;
    win32)
        [ "$#" -eq 3 ] || usage
        cp "$3" "$staging/server/lexware-php-mcp.exe"
        ;;
    *) usage ;;
esac

cp "$root/LICENSE" "$staging/LICENSE"
jq --arg platform "$platform" '.compatibility.platforms = [$platform]' \
    "$root/build-tools/mcpb/manifest.json" > "$staging/manifest.json"

npx --yes "@anthropic-ai/mcpb@$MCPB_VERSION" validate "$staging/manifest.json"
npx --yes "@anthropic-ai/mcpb@$MCPB_VERSION" pack "$staging" "$output"
