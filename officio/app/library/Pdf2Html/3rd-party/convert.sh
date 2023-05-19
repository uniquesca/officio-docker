#!/bin/bash

# Absolute path to this script, e.g. /home/user/bin/foo.sh
SCRIPT=$(readlink -f $0)
# Absolute path this script is in, thus /home/user/bin
SCRIPTPATH=$(dirname $SCRIPT)

# Get path to Java installed
MYJAVAPATH="$(readlink -f $(which java))"

cd $SCRIPTPATH
$MYJAVAPATH -Xmx512M -Dorg.jpedal.pdf2html.viewMode=singlefile -Dorg.jpedal.pdf2html.disableComments=true -Dorg.jpedal.jai=true -cp jpdf2htmlForms.jar org.jpedal.examples.html.PDFtoHTML5Converter "$1" "$2"