#!/bin/bash

git checkout l10n_master
git reset --hard HEAD~200
git pull http l10n_master
rm -rf /tmp/translationfiles ; cp -r ../translationfiles /tmp
git checkout master
cp -r /tmp/translationfiles ../
git commit -a -m "new translations from crowdin"

#git rebase master
#git reset --soft master
#git commit -m "new translations from crowdin"
#git checkout master
#git merge l10n_master
echo "MERGE DONE"
