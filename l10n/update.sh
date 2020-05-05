#!/bin/bash

rm -rf /tmp/cospend
git clone https://gitlab.com/eneiluj/cospend-nc /tmp/cospend -b l10n_master
cp -r /tmp/cospend/l10n/descriptions/[a-z][a-z]_[A-Z][A-Z] ./descriptions/
cp -r /tmp/cospend/translationfiles/[a-z][a-z]_[A-Z][A-Z] ../translationfiles/
rm -rf /tmp/cospend

echo "files copied"
