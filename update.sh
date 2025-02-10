#!/bin/bash

# Pull latest version
git pull

# Restart with updates
systemctl restart tile-image-gen
