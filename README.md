# Nextcloud Cospend 💰

[![phpunit-mysql](https://github.com/julien-nc/cospend-nc/actions/workflows/phpunit-mysql.yml/badge.svg?branch=main)](https://github.com/julien-nc/cospend-nc/actions/workflows/phpunit-mysql.yml)
[![Crowdin](https://d322cqt584bo4o.cloudfront.net/moneybuster/localized.svg)](https://crowdin.com/project/moneybuster)

Nextcloud Cospend is a group/shared budget manager.
It was inspired by the great [IHateMoney](https://github.com/spiral-project/ihatemoney/).

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

It lets you create projects with members and bills. Each member has a balance computed from the project bills.
Balances are not an absolute amount of money at members disposal but rather a relative information 
showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom.
This way you can see who owes the group and who the group owes.
Ultimately you can ask for a settlement plan telling you which payments to make to reset members balances.

Project members are independent from Nextcloud users.
Projects can be shared with other Nextcloud users or via public links.

[<img width="30px" src="https://github.com/helcel-net/cowspent/raw/refs/heads/main/metadata/en-US/images/icon.png">](https://github.com/helcel-net/cowspent) [Cowspent](https://github.com/helcel-net/cowspent) Android client is [available in F-Droid in the IzzyOnDroid repo](https://apt.izzysoft.de/fdroid/index/apk/net.helcel.cowspent) and as a [downloadable APK file](https://github.com/helcel-net/cowspent/releases/latest).

[<img width="30px" src="https://gitlab.com/uploads/-/system/project/avatar/9981890/ic_launcher.png?width=48">](https://gitlab.com/eneiluj/moneybuster) [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) (unmaintained) Android client is [available in F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) and on the [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[<img width="30px" src="https://github.com/mayflower/PayForMe/raw/refs/heads/main/PayForMe/Assets.xcassets/AppIcon.appiconset/app_icon-40x40.png">](https://github.com/mayflower/PayForMe) [PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/).
This documentation can be accessed directly in Nextcloud.
All you need is to install Cospend (>= v1.6.0) and use the
[the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## Features

* ✎ Create/edit/delete projects, members, bills, bill categories, currencies
* ⚖ Check member balances
* 🗠 Display project statistics
* ♻ Display settlement plan
* Move bills from one project to another
* Move bills to trash before actually deleting them
* Archive old projects before deleting them
* 🎇 Automatically create reimbursement bills from settlement plan
* 🗓 Create recurring bills (day/week/month/year)
* 📊 Optionally provide custom amount for each member in new bills
* 🔗 Link personal files to bills (picture of physical receipt for example)
* 👩 Public links for people outside Nextcloud (can be password protected)
* 👫 Share projects with Nextcloud users/groups/circles
* 🖫 Import/export projects as csv (compatible with csv files from IHateMoney and SplitWise)
* 🔗 Generate link/QRCode to easily add projects in MoneyBuster
* 🗲 Implement Nextcloud notifications and activity stream

This app usually support the 2 or 3 last major versions of Nextcloud.

This app is under development.

🌍 Help us to translate this app on [Nextcloud-Cospend/MoneyBuster Crowdin project](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

Link to Nextcloud application website: https://apps.nextcloud.com/apps/cospend

## Donate

I develop this app during my free time.
If you'd like to support the creation and maintenance of this software, consider donating.

| [<img src="https://img.shields.io/badge/paypal-donate-blue.svg?logo=paypal&style=for-the-badge">](https://www.paypal.me/JulienVeyssier) | [<img src="https://img.shields.io/liberapay/receives/eneiluj.svg?logo=liberapay&style=for-the-badge">](https://liberapay.com/eneiluj/donate) | [<img src="https://img.shields.io/badge/github-sponsors-violet.svg?logo=github&style=for-the-badge">](https://github.com/sponsors/julien-nc) |
| :---: |:---:|:---:|

## Documentation

* [User documentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Admin documentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Developer documentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTHORS](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Known issues

* ...

Any feedback will be appreciated.

## Screenshots

![1](https://github.com/julien-nc/cospend-nc/raw/main/img/screenshots/cospend1.jpg)
![2](https://github.com/julien-nc/cospend-nc/raw/main/img/screenshots/cospend2.jpg)
![3](https://github.com/julien-nc/cospend-nc/raw/main/img/screenshots/cospend3.jpg)
![4](https://github.com/julien-nc/cospend-nc/raw/main/img/screenshots/cospend4.jpg)

## Nightly

:warning: Make sure to only use nightly builds on test Nextcloud instances.

To install the latest nightly, you can either
* download it from https://apps.nextcloud.com/apps/cospend and replace
the app directory manually in `nextcloud/apps`
(make sure you give cospend directory's ownership to your webserver user)
* use this occ command:
```
occ app:update --allow-unstable cospend
```
When you want to get back to stable releases,
just disable and remove the app from app settings and reinstall it. You won't loose any data.
