#!/bin/bash

git checkout l10n_master
git reset --hard HEAD~200
git pull origin l10n_master
rm -rf /tmp/translationfiles ; cp -r ../translationfiles /tmp/
rm -rf /tmp/descriptions ; cp -r ./descriptions /tmp/
git checkout master
cp -r /tmp/translationfiles ../
cp -r /tmp/descriptions ./
git commit -a -m "new translations from crowdin"

#git rebase master
#git reset --soft master
#git commit -m "new translations from crowdin"
#git checkout master
#git merge l10n_master
echo "MERGE DONE"
