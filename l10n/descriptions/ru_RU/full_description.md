# Проект Cospend для Nextcloud

Nextcloud Cospend - это менеджер группового/общего бюджета. Он был создан под впечатлением от отличной программы [IHateMoney](https://github.com/spiral-project/ihatemoney/).

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

Он позволяет вам создавать проекты в которых есть участники и счета. Баланс каждого участника вычисляется на основании заведенных в проект счетов. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. Таки образом сразу видно кто должен всей группе, а кому должна группа. В завершение проекта можно вычислить план расчетов по платежам для обнуления балансов участников.

Участники проекта не связаны с пользователями Nextcloud. Projects can be shared with other Nextcloud users or via public links.

Клиент [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) для Android [ доступен в F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) и в [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

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

Это приложение находится в стадии разработки.

🌍 Помогите перевести это приложение на [PhoneTrack Crowdin project](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Документация

* [Пользовательская документация](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Документация по админу](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Документация для разработчиков](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [АВТОРЫ](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Известные проблемы

* It does not make you rich

Мы будем признательны за любую обратную связь.

