# Nextcloud Cospend 💰

Nextcloud Cospend es un gestor de presupuesto compartido. Fue inspirado por el magnífico [IHateMoney](https://github.com/spiral-project/ihatemoney/).

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

Te permite crear proyectos con miembros y facturas. Cada miembro tiene un saldo calculado a partir de las facturas del proyecto. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. De esta manera se puede ver quién debe al grupo y a quién debe el grupo. En última instancia, puedes pedir un plan de liquidación que indique qué pagos hay que hacer para restablecer los saldos de los miembros.

Los miembros del proyecto son independientes de los usuarios de Nextcloud. Projects can be shared with other Nextcloud users or via public links.

El cliente para Android [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) está [disponible en F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) y en la [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently under developpement!

The private and public APIs are documented using [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). This documentation can be accessed directly in Nextcloud. All you need is to install Cospend (>= v1.6.0) and use the [the OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer) to browse the OpenAPI documentation.

## Funcionalidades

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

Esta aplicación está en desarrollo.

🌍 Ayúdanos a traducir esta aplicación en [el proyecto de Crowdin de Nextcloud Cospend/MoneyBuster](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentación

* [Documentacion para el usuario](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentacion para el administrador](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentación para desarrolladores](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTHORS](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Problemas conocidos

* It does not make you rich

Any feedback will be appreciated.

