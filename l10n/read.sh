#!/bin/bash

cd ..
rm -rf /tmp/cospendjs
mv js /tmp/cospendjs
translationtool.phar create-pot-files
mv /tmp/cospendjs js
cd -
