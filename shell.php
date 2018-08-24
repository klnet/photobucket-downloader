#!/bin/sh

exec docker run --rm -it \
  -v "$PWD:$PWD" \
  -w "$PWD" \
  xfrocks/xenforo:php-apache-7.2.8c \
  bash
