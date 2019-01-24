# this is just a memo
git checkout master
git pull origin master
git checkout l10n_master
git reset --hard HEAD~200
git pull origin l10n_master
git rebase -i master
git checkout master
git merge l10n_master
cd l10n
./write
#cp en_US.js en_GB.js
#cp en_US.json en_GB.json
git commit -a -m "write new translations"
git push origin master
git push origin master:l10n_master -f
