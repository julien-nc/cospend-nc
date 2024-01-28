# Nextcloud Cospend 💰

Nextcloud Cospend est un gestionnaire de dépenses partagées (de groupe). Il a été inspiré par le génial [IHateMoney](https://github.com/spiral-project/ihatemoney/).

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

Cospend vous permet de créer des projets avec des membres et des factures. Chaque membre a un solde calculé à partir des factures du projet. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. Comme ça vous pouvez voir qui doit de l'argent au groupe et à qui le groupe doit de l'argent. À la fin, vous pouvez demander un plan de remboursement qui vous indique les paiements à effectuer pour remettre les soldes des membres à zéro.

Les membres du projets sont indépendants des utilisateurs Nextcloud. Projects can be shared with other Nextcloud users or via public links.

Le client Android [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) est [disponible sur F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) et sur le [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## Fonctionnalités

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

Cette application est en cours de développement.

🌍 Aidez-nous à traduire cette application sur [le project Crowdin Nextcloud-Cospend/MoneyBuster](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentation

* [Documentation utilisateur](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentation administrateur](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentation développeur](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTEURS](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Problèmes connus

* It does not make you rich

Tout retour sera apprécié.

