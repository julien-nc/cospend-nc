# Nextcloud Cospend 💰

Nextcloud Cospend jest grupowym/wspólnym menedżerem budżetu. Został on zainspirowany przez wielkiego [IHateMoney](https://github.com/spiral-project/ihatemoney/).

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

Pozwala tworzyć projekty z użytkownikami i rachunkami. Każdy członek ma saldo obliczone na podstawie rachunków projektu. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. W ten sposób możesz zobaczyć, kto jest winny grupy i kto jest jej winny. W końcu możesz poprosić o plan rozrachunkowy informujący o tym, które płatności mają być dokonane w celu zresetowania sald członków.

Członkowie projektu są niezależni od użytkowników Nextcloud. Projects can be shared with other Nextcloud users or via public links.

[Klient MoneyBuster](https://gitlab.com/eneiluj/moneybuster) na Android jest [dostępny w F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) oraz w [Sklepie Play ](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## Funkcje

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

Ta aplikacja jest w trakcie tworzenia.

🌍 Pomóż nam przetłumaczyć tę aplikację na [Projekt Crowdin Nextcloud-Cospend/MoneyBuster](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentation

* [User documentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Admin documentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Developer documentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTHORS](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Znane problemy

* It does not make you rich

Any feedback will be appreciated.

