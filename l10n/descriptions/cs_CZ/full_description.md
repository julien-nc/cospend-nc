# Nextcloud Cospend 💰

Nextcloud Cospend je správce skupinových/sdílených rozpočtů. Inspirováno skvělým [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Můžete ji používat když sdílíte dům, jste s příteli na dovolené a nebo kdykoliv máte ze skupinou lidí sdílené výdaje.

Umožňuje vytvářet projekty se členy a účty. Každý člen má zůstek vypočítaný z projektových účtů. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. Tak je možné vidět kdo dluží skupině a naopak komu dluží skupina. V konečném důsledku můžete požádat o plán vypořádání, který vám oznámí, které platby mají být provedeny za účelem vynulování zůstatků členů.

Členové projektu jsou nezávislí na uživatelích z Nextcloud. Projects can be shared with other Nextcloud users or via public links.

Android klient [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) je [k dispozici v repozitáři F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) a v katalogu [Google Play](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## Funkce

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

Na této aplikaci stále ještě probíhá intenzivní vývoj.

🌍 Pomozte nám s překládáním textů v rozhraní této aplikace v rámci [projektu Nextcloud-Cospend/MoneyBuster na službě Crowdin](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Dokumentace

* [Uživatelská dokumentace](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Dokumentace pro správce](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Dokumentace pro vývojáře](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [SEZNAM ZMĚN](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTOŘI](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Známé problémy

* It does not make you rich

Jakákoliv zpětná vazba bude vítána.

