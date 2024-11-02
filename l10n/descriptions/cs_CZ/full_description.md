# Nextcloud Cospend 💰

Nextcloud Cospend je správce skupinových/sdílených rozpočtů. Inspirováno skvělým [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Můžete ji používat když sdílíte dům, jste s příteli na dovolené a nebo kdykoliv máte ze skupinou lidí sdílené výdaje.

Umožňuje vytvářet projekty se členy a účty. Každý člen má zůstek vypočítaný z projektových účtů. Zůstatky nejsou absolutní částkou peněz, které mají členové k dispozici, ale spíše relativní informací uvádějící, zda člen za skupinu utratil více, než skupina utratila za něj, nezávisle na tom, kdo za koho co utratil. Tak je možné vidět kdo dluží skupině a naopak komu dluží skupina. V konečném důsledku můžete požádat o plán vypořádání, který vám oznámí, které platby mají být provedeny za účelem vynulování zůstatků členů.

Členové projektu jsou nezávislí na uživatelích z Nextcloud. Projekty lze sdílet s jinými uživateli služby Nextcloud nebo pomocí veřejných odkazů.

Android klient [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) je [k dispozici v repozitáři F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) a v katalogu [Google Play](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

Ve vývoji je současně také iOS klient [PayForMe](https://github.com/mayflower/PayForMe)!

Soukromé a veřejné API jsou zdokumentováne pomocí [Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). K této dokumentaci lze přistupovat přímo v službě Nextcloud. Vše, co potřebujete, je nainstalovat Cospend (>= v1.6.0) a použít [aplikaci OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer) k procházení OpenAPI dokumentace.

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

