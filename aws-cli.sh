#!/bin/sh

exec docker run --rm -it \
  -v "$PWD:$PWD" \
  -w "$PWD" \
  --entrypoint=sh \
  mesosphere/aws-cli
