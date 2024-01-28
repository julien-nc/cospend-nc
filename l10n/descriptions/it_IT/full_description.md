# Nextcloud Cospend 💰

Nextcloud Cospend è un budget manager condiviso/di gruppo. Ha tratto ispirazione dal grande [IHateMoney](https://github.com/spiral-project/ihatemoney/).

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

Permette di creare progetti con utenti e spese. Ogni utente ha un saldo derivante dalle spese del progetto. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. Così puoi vedere chi deve al gruppo quanto e quanto deve il gruppo a chi. Infine, puoi visualizzare un piano di rientro dal debito che indichi i pagamenti da effettuare per riportare a zero i saldi degli utenti.

Gli utenti del progetto sono indipendenti dagli utenti Nextcloud. Projects can be shared with other Nextcloud users or via public links.

Il client per Android [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) è [disponibile su F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) e sul [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## Funzioni

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

Questa app è in fase di sviluppo.

🌍 Aiutaci a tradurre questa app su [Nextcloud-Cospend/MoneyBuster progetto Crowdin](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentazione

* [Documentazione utente](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentazione per l'amministratore](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentazione per gli sviluppatori](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTORI](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Problemi noti

* It does not make you rich

Ogni feedback é apprezzato.

