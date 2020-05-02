#!/bin/bash

rm -rf /tmp/cospendjs
mv ../js /tmp/cospendjs
rm -rf /tmp/translationfiles ; cp -r ../translationfiles /tmp/

git checkout l10n_master
git reset --hard HEAD~200
git pull origin l10n_master
rm -rf /tmp/descriptions ; mkdir /tmp/descriptions ; cp -r ./descriptions/[a-z][a-z]_[A-Z][A-Z] /tmp/descriptions/
git checkout master
cp -r /tmp/translationfiles ../
cp -r /tmp/descriptions ./
git commit -a -m "new translations from crowdin"

mv /tmp/cospendjs ../js

#git rebase master
#git reset --soft master
#git commit -m "new translations from crowdin"
#git checkout master
#git merge l10n_master
echo "MERGE DONE"
