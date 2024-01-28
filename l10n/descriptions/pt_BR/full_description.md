# Nextcloud Cospend 💰

Nextcloud Cospend é um gerenciador de orçamento compartilhado/de grupo. Foi inspirado pelo ótimo [IHateMoney](https://github.com/spiral-project/ihatemoney/).

You can use it when you share a house, when you go on vacation with friends, whenever you share expenses with a group of people.

Ele permite criar projetos com membros e despesas. Cada membro tem um saldo calculado a partir das faturas do projeto. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. Desta forma você pode ver quem deve ao grupo e a quem o grupo deve. Em última análise pode pedir um plano de quitação que lhe diga quais os pagamentos a fazer para saldar as dívidas dos membros.

Os membros do projeto são independentes dos usuários do Nextcloud. Projects can be shared with other Nextcloud users or via public links.

[MoneyBuster](https://gitlab.com/eneiluj/moneybuster) é um cliente Android que está [disponível no F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) e na [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

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

Este aplicativo está em desenvolvimento.

🌍 Ajude-nos a traduzir esta aplicação no [Nextcloud-Cospend/MoneyBuster Crowdin](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentação

* [Documentação do usuário](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentação do administrador](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentação do desenvolvedor](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [REGISTRO DE MUDANÇAS](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTORES](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Problemas conhecidos

* It does not make you rich

Qualquer retorno será apreciado.

