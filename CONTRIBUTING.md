# Translate Payback in your language

Translation is done in the [Nextcloud Payback/MoneyBuster Crowdin project](https://crowdin.com/project/moneybuster).

If your language is not present in the project, send me a private message in Crowdin or an e-mail and i'll add it.

# Report a bug

[Here](https://gitlab.com/eneiluj/payback-nc/issues/new?issue%5Bassignee_id%5D=&issue%5Bmilestone_id%5D=) is the link to submit a new issue.

Please check if the issue has already been fixed or if it is already currently discussed in an existing issue.

Don't forget to mention :

* your Nextcloud version
* your Payback version : release version or commit ID (if you're using a git working copy)
* your database type
* your browser name and version
* a more or less precise protocol to reproduce the bug

# Suggest a feature

You can also submit a [new issue](https://gitlab.com/eneiluj/payback-nc/issues/new?issue%5Bassignee_id%5D=&issue%5Bmilestone_id%5D=) to suggest a change or to make a feature request.

Please make sure the feature you ask for is not too specific to your use case and make sense in the project.

# Submit your own changes

Feel free to fork Payback to make your own changes.

## Workflow

Here is a brief description of the `fork and merge request` workflow (or at least my interpretation of it) :

* Fork the project to get a copy of which you are the owner
* Don't push commits in your master branch, it is easier to use your master branch to stay up to date with original project
* Create a branch from your up-to-date master to make a bunch of commits **related to one single topic**. Name the branch explicitly.
* Create a merge request from this branch to master branch of the original project

Here is a memo of git commands to run after having forked the project on gitlab.com :
``` bash
git clone https://gitlab.com/yourlogin/payback-nc payback
cd payback

# on your local master branch, to get changes from original project's master branch :
git pull https://gitlab.com/eneiluj/payback-nc master

# create a branch to work on a future merge request
git checkout -b new_feature1
# make changes then commit
git commit -a -m "beginning to implement my new feature"
# continue developing
git commit -a -m "new feature is now ready"
# push it to your repo
git push origin new_feature1
# now you can make your merge request ^^ !

# you want to update your master branch
git checkout master
git pull https://gitlab.com/eneiluj/payback-nc master

# optional expert git trick ;-) :
# you've started to work on new_feature1 and in the meantime,
# the master branch of original project integrated some new stuff.
# If you want to get the new stuff in your new_feature1 branch :
git checkout master
git pull https://gitlab.com/eneiluj/payback-nc master
git checkout new_feature1
# rebasing a branch means trying to put the commits of local branch on top of requested branch
# in this example : remove your changes, get new stuff from master, put your changes on top !
git rebase master
# if there is no conflict between your changes
# and the new stuff in master branch of original project
# the rebase will go just fine.
# You can then continue developing on your new_feature1 branch
```

## Tests

If you want to trigger Continuous Integration tests on Gitlab, just push to your branch `test`

``` bash
# from any branch, for example from branch 'new_feature1'
git push origin new_feature1:test -f
```

Those tests only concern controller part. If someone could show me the way and just start to implement front-end (JS) tests with Karma, i'll be more than grateful !

## Recomandations

* Try not to make changes to libraries. Any css can be overriden in `css/payback.css`
* Try to use explicit variable names
* Try not to change HTML structure too much
* Try to comment your code if what it does it not obvious
