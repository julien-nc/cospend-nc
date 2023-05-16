#!/bin/bash

rm -rf /tmp/cospend
git clone https://github.com/julien-nc/cospend-nc /tmp/cospend -b l10n_main --single-branch
cp -r /tmp/cospend/l10n/descriptions/[a-z][a-z]_[A-Z][A-Z] ./descriptions/
cp -r /tmp/cospend/translationfiles/[a-z][a-z]_[A-Z][A-Z] ../translationfiles/
rm -rf /tmp/cospend

echo "files copied"
