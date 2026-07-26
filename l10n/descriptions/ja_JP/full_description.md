# Nextcloud Cospend 💰

NextcloudのCospendはグループ予算管理ツールです。 [IHateMoney](https://github.com/spiral-project/ihatemoney/)というツールに影響を受け、開発されました。

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

メンバーと請求書でプロジェクトを作成できます。 各メンバーは、プロジェクトの請求書から計算された残高を持っています。 Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. この方法では、グループに借りている人とグループに貸している人を見ることができます。 最終的には、メンバー残高をリセットするために支払いを行う決算プランを求めることができます。

プロジェクトメンバーは NextCloud ユーザーから独立しています。 Projects can be shared with other Nextcloud users or via public links.

[<img width="30px" src="https://github.com/helcel-net/cowspent/raw/refs/heads/main/metadata/en-US/images/icon.png" />](https://github.com/helcel-net/cowspent) [Cowspent](https://github.com/helcel-net/cowspent) Android client is [available in F-Droid in the IzzyOnDroid repo](https://apt.izzysoft.de/fdroid/index/apk/net.helcel.cowspent) and as a [downloadable APK file](https://github.com/helcel-net/cowspent/releases/latest).

[<img width="30px" src="https://gitlab.com/uploads/-/system/project/avatar/9981890/ic_launcher.png?width=48" />](https://gitlab.com/eneiluj/moneybuster) [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) (unmaintained) Android client is [available in F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) and on the [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[<img width="30px" src="https://github.com/mayflower/PayForMe/raw/refs/heads/main/PayForMe/Assets.xcassets/AppIcon.appiconset/app_icon-40x40.png" />](https://github.com/mayflower/PayForMe) [PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## 機能紹介

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

このアプリは開発中です。

🌍 [Nextcloud-Cospend/MoneyBuster Crowdin プロジェクト](https://crowdin.com/project/moneybuster) でこのアプリを翻訳する手助けをしてください。

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## ドキュメント

* [利用者向けドキュメント](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [管理ドキュメント](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [開発者ドキュメント](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [変更](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [作者](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## 既知の問題

* It does not make you rich

ご意見をいただければ幸いです。

