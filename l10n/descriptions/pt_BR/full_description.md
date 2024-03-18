# Nextcloud Cospend 💰

Nextcloud Cospend é um gerenciador de orçamento compartilhado/de grupo. Foi inspirado pelo ótimo [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Você pode usá-lo quando dividir uma casa, quando sair de férias com amigos, sempre que dividir despesas com um grupo de pessoas.

Ele permite criar projetos com membros e despesas. Cada membro tem um saldo calculado a partir das faturas do projeto. Os saldos não são uma quantia absoluta de dinheiro à disposição dos membros, mas sim uma informação relativa mostrando se um membro gastou mais para o grupo do que o grupo gastou para ele/ela, independentemente de exatamente quem gastou dinheiro para quem. Desta forma você pode ver quem deve ao grupo e a quem o grupo deve. Em última análise pode pedir um plano de quitação que lhe diga quais os pagamentos a fazer para saldar as dívidas dos membros.

Os membros do projeto são independentes dos usuários do Nextcloud. Os projetos podem ser compartilhados com outros usuários do Nextcloud ou por meio de links públicos.

O cliente Android [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) está [disponível no F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) e na [Play Store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

cliente iOS [PayForMe](https://github.com/mayflower/PayForMe) está atualmente em desenvolvimento!

As APIs privadas e públicas são documentadas usando [o extrator Nextcloud OpenAPI](https://github.com/nextcloud/openapi-extractor/). Esta documentação pode ser acessada diretamente no Nextcloud. Tudo que você precisa é instalar o Cospend (>= v1.6.0) e usar o [o aplicativo OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer) para navegar pela documentação da OpenAPI.

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

