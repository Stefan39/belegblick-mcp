#!/bin/sh
# Entry point of the macOS extension: starts the binary that matches this Mac's processor.
exec "$(dirname "$0")/lexware-php-mcp-$(uname -m)" "$@"
