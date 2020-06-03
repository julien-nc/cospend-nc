#!/bin/bash

cd ..
rm -rf /tmp/cospendjs
mv js /tmp/cospendjs
mv node_modules /tmp/node_modules_cospend
translationtool.phar create-pot-files
mv /tmp/cospendjs js
mv /tmp/node_modules_cospend node_modules
cd -
