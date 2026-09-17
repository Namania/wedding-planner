#!/bin/sh
set -e

GARAGE_BUCKET="${GARAGE_BUCKET:?GARAGE_BUCKET is required}"
GARAGE_KEY_ID="${GARAGE_KEY_ID:?GARAGE_KEY_ID is required}"
GARAGE_SECRET_KEY="${GARAGE_SECRET_KEY:?GARAGE_SECRET_KEY is required}"

garage server &
SERVER_PID=$!
trap 'kill -TERM "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID"' TERM INT

echo "Waiting for garage RPC to come up..."
until garage layout show >/dev/null 2>&1; do
  sleep 1
done

NODE_ID=$(garage node id -q 2>/dev/null | cut -d'@' -f1)
NODE_ID_SHORT=$(printf '%s' "$NODE_ID" | cut -c1-16)
SHOW_OUTPUT=$(garage layout show 2>/dev/null)

if ! echo "$SHOW_OUTPUT" | grep -q "$NODE_ID_SHORT"; then
  echo "Assigning cluster layout for node ${NODE_ID}..."
  garage layout assign -z "${GARAGE_ZONE:-dc1}" -c "${GARAGE_CAPACITY:-10G}" "$NODE_ID"
  CURRENT_VERSION=$(echo "$SHOW_OUTPUT" | sed -n 's/^Current cluster layout version: //p')
  garage layout apply --version "$((CURRENT_VERSION + 1))"
fi

if ! garage bucket list 2>/dev/null | grep -q "$GARAGE_BUCKET"; then
  echo "Creating bucket ${GARAGE_BUCKET}..."
  garage bucket create "$GARAGE_BUCKET"
fi

if ! garage key info "$GARAGE_KEY_ID" >/dev/null 2>&1; then
  echo "Importing access key ${GARAGE_KEY_ID}..."
  garage key import "$GARAGE_KEY_ID" "$GARAGE_SECRET_KEY" --yes -n app
fi

garage bucket allow --read --write --owner "$GARAGE_BUCKET" --key "$GARAGE_KEY_ID" >/dev/null 2>&1

echo "Garage ready (bucket: ${GARAGE_BUCKET}, key: ${GARAGE_KEY_ID})."
wait "$SERVER_PID"
