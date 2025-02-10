#!/bin/bash

# cd to directory
cd /opt/tile-image-gen

# Pull latest version
git pull

# Restart with updates
systemctl restart tile-image-gen
