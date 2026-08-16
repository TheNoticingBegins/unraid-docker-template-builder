#!/bin/bash
# Build the .txz package for Docker Builder Plugin
# Usage: ./pkg_build.sh [version]
# Default version: 2026.08.17

VERSION="${1:-2026.08.17}"
NAME="docker-builder"
ARCH="x86_64"
BUILD="1"
PKG="${NAME}-${VERSION}-${ARCH}-${BUILD}.txz"

cd "$(dirname "$0")/source"
echo "Building ${PKG}..."
tar -cJf "../${PKG}" .
echo "Done: $(ls -lh "../${PKG}" | awk '{print $5}')"
cd ..