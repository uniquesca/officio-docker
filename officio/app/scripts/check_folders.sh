#!/bin/bash
         COUNTER=1
         while [  $COUNTER -lt 80 ]; do
             du "/home/officio/secure/data/$COUNTER/.client_files_other/XFDF" -h | tail -n 1
             let COUNTER=COUNTER+1 
         done