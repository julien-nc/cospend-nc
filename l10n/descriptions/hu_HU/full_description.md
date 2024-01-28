# Nextcloud Cospend 💰

A Nextcloud Cospend egy csoportos/megosztott költségkezelő. A nagy [IHateMoney](https://github.com/spiral-project/ihatemoney/) inspirálta.

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

Létre tudsz hozni benne projekteket tagokkal és számlákkal. Minden tagnak van egy egyenlege, melyet a projekt számláiból számítunk ki. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. Így láthatod, ki tartozik a csoportnak és a csoport kinek tartozik. Végül kérhetsz elszámolási tervet, mely megmondja, milyen kifizetésekre van szükség, hogy a tagok tartozásai rendezve legyenek.

A projekttagok függetlenek a Nextcloud felhasználóitól. Projects can be shared with other Nextcloud users or via public links.

A [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) Android kliens elérhető [F-Droid-on](https://f-droid.org/packages/net.eneiluj.moneybuster/) és a [Play store-ban](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## Funkciók

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

Ez az alkalmazás fejlesztés alatt áll.

🌍 Segíts nekünk a fordításban a [Nextcloud-Cospend/MoneyBuster Crowdin projektben](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Dokumentáció

* [Felhasználói dokumentáció](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Rendszergazdai dokumentáció](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Fejlesztői dokumentáció](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [VÁLTOZÁSOK](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [SZERZŐK](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Ismert problémák

* It does not make you rich

Bármilyen visszajelzést nagyra értékelünk.

