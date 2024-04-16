# Nextcloud Cospend 💰

Nextcloud Cospend управлява бюджет за група/споделен бюджет. Вдъхновен е от великолепния [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Можете да го използвате, когато споделят къща, когато отивате на почивка с приятели, винаги когато споделяте разходи с група хора.

Дава ви възможност да създавате проекти с членове и сметки. За всеки член се изчислява салдо от сметките по проекта. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. This way you can see who owes the group and who the group owes. Ultimately you can ask for a settlement plan telling you which payments to make to reset members balances.

Project members are independent from Nextcloud users. Projects can be shared with other Nextcloud users or via public links.

[MoneyBuster](https://gitlab.com/eneiluj/moneybuster) за Android се [предлага за F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) и в [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## Функции

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

Приложението се разработва.

🌍 Помогнете ни да преведем това приложение в Crowdin проекта [Nextcloud-Cospend/MoneyBuster ](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Документация

* [Документация за потребителя](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Документация за администратора](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Документация за разработчиците](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [ПРОМЕНИ](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [АВТОРИ](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Известни проблеми

* It does not make you rich

Всяка обратна връзка ще бъде оценена.

