#!/bin/sh

PROJECT_NAME=briardene-books

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
SRC_DIR="$ROOT_DIR/src"
BUILD_DIR="$ROOT_DIR/build"
DIST_DIR="$ROOT_DIR/dist"

if [ -n "$GITHUB_SHA" ]; then
  IDENTIFIER="$(git -C "$ROOT_DIR" rev-parse --short "$GITHUB_SHA")"
else
  IDENTIFIER="$(hostname)"
fi
VERSION="$(TZ=UTC date +"%Y.%m.%d.%H%M%S")+$IDENTIFIER"

SAFE_VERSION=$(echo "$VERSION" | tr '+.' '--')
DIST_FILE="$DIST_DIR/$PROJECT_NAME-$SAFE_VERSION.zip"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/$PROJECT_NAME"

rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

echo "Copying from $SRC_DIR to $BUILD_DIR..."
rsync -a "$SRC_DIR/" "$BUILD_DIR/$PROJECT_NAME"

echo "Setting version to $VERSION..."
find "$BUILD_DIR/$PROJECT_NAME" -type f -print0 | xargs -0 sed -i "s/{{ VERSION }}/$VERSION/g"

echo "Making zip file:"
( cd "$BUILD_DIR" && zip -r "$DIST_FILE" . )

echo "Created zip file at:"
echo "  $DIST_FILE"

if [ -n "$GITHUB_OUTPUT" ]; then
  echo "version=$VERSION" >> "$GITHUB_OUTPUT"
  echo "dist_file=$DIST_FILE" >> "$GITHUB_OUTPUT"
fi