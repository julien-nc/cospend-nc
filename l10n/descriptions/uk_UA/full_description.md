# Nextcloud Cospend 💰

Nextcloud Cospend is a group/shared budget manager. It was inspired by the great [IHateMoney](https://github.com/spiral-project/ihatemoney/).

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

It lets you create projects with members and bills. Each member has a balance computed from the project bills. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. This way you can see who owes the group and who the group owes. Ultimately you can ask for a settlement plan telling you which payments to make to reset members balances.

Project members are independent from Nextcloud users. Projects can be shared with other Nextcloud users or via public links.

[MoneyBuster](https://gitlab.com/eneiluj/moneybuster) Android client is [available in F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) and on the [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). Ця документація доступна безпосередньо в Nextcloud. Все, що вам потрібно — встановити Cospend (>= v1.6.0) і використовувати [OCS API Viewer додатків](https://apps.nextcloud.com/apps/ocs_api_viewer) для перегляду документації OpenAPI.

## Можливості

* ✎ Create/edit/delete projects, members, bills, bill categories, currencies
* ⚖ Check member balances
* 🗠Переглядати статистику проекту
* ♻ Display settlement plan
* Move bills from one project to another
* Move bills to trash before actually deleting them
* Archive old projects before deleting them
* 🎇 Automatically create reimbursement bills from settlement plan
* 🗓 Create recurring bills (day/week/month/year)
* 📊 Optionally provide custom amount for each member in new bills
* 🔗 Link personal files to bills (picture of physical receipt for example)
* 👩 Публічні посилання для людей поза Nextcloud (можуть бути захищені паролем)
* 👫 Ділитися проєктами з користувачами/групами/колами Nextcloud
* 🖫 Import/export projects as csv (compatible with csv files from IHateMoney and SplitWise)
* 🔗 Generate link/QRCode to easily add projects in MoneyBuster
* 🗲 Implement Nextcloud notifications and activity stream

Додаток зазвичай підтримує 2 або 3 останні основні версії Nextcloud.

Цей додаток в стадії розробки.

🌍 Допоможіть нам перекласти цей додаток на [PhoneTrack Crowdin](https://crowdin. com/project/phonetrack).

⚒️ Перегляньте інші способи допомогти в [інструкціях з внеску оголошень](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Документація

* [Документація користувача](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Документація для адміністратора](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Документація для розробника](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [АВТОРИ](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Відомі проблеми

* Це не зробить вас багатим

Будемо вдячні за будь-який відгук.

