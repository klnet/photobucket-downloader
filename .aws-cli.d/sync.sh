#!/bin/sh

exec aws s3 sync files/ s3://klnet-photobucket-downloader
