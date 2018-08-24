#!/bin/sh

set -e

find files/ -type f -size +1 | tee /tmp/find.log

curl --upload-file /tmp/find.log https://transfer.sh
