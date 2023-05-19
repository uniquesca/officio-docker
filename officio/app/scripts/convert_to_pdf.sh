#!/bin/bash
export HOME="$2" && libreoffice --headless --convert-to pdf "$1" --outdir "$2"
