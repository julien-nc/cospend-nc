# Nextcloud Cospend 💰

Nextcloud Cospend é um gerenciador de orçamento compartilhado/de grupo. Foi inspirado pelo ótimo [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Você pode usá-lo quando dividir uma casa, quando sair de férias com amigos, sempre que dividir despesas com um grupo de pessoas.

Ele permite criar projetos com membros e despesas. Cada membro tem um saldo calculado a partir das faturas do projeto. Os saldos não são uma quantia absoluta de dinheiro à disposição dos membros, mas sim uma informação relativa mostrando se um membro gastou mais para o grupo do que o grupo gastou para ele/ela, independentemente de exatamente quem gastou dinheiro para quem. Desta forma você pode ver quem deve ao grupo e a quem o grupo deve. Em última análise pode pedir um plano de quitação que lhe diga quais os pagamentos a fazer para saldar as dívidas dos membros.

Os membros do projeto são independentes dos usuários do Nextcloud. Os projetos podem ser compartilhados com outros usuários do Nextcloud ou por meio de links públicos.

[<img width="30px" src="https://github.com/helcel-net/cowspent/raw/refs/heads/main/metadata/en-US/images/icon.png" />](https://github.com/helcel-net/cowspent) [Cowspent](https://github.com/helcel-net/cowspent) Android client is [available in F-Droid in the IzzyOnDroid repo](https://apt.izzysoft.de/fdroid/index/apk/net.helcel.cowspent) and as a [downloadable APK file](https://github.com/helcel-net/cowspent/releases/latest).

[<img width="30px" src="https://gitlab.com/uploads/-/system/project/avatar/9981890/ic_launcher.png?width=48" />](https://gitlab.com/eneiluj/moneybuster) [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) (unmaintained) Android client is [available in F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) and on the [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[<img width="30px" src="https://github.com/mayflower/PayForMe/raw/refs/heads/main/PayForMe/Assets.xcassets/AppIcon.appiconset/app_icon-40x40.png" />](https://github.com/mayflower/PayForMe) cliente iOS [PayForMe](https://github.com/mayflower/PayForMe) está atualmente em desenvolvimento!

As APIs privadas e públicas são documentadas usando [o extrator Nextcloud OpenAPI](https://github.com/nextcloud/openapi-extractor/). Esta documentação pode ser acessada diretamente no Nextcloud. Tudo que você precisa é instalar o Cospend (>= v1.6.0) e usar o [o aplicativo OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer) para navegar pela documentação da OpenAPI.

## Funcionalidades

* ✎ Criar/editar/excluir projetos, membros, contas, categorias de contas, moedas
* ⚖ Verifique os saldos dos membros
* 🗠 Exibir estatísticas do projeto
* ♻ Exibir plano de liquidação
* Mover contas de um projeto para outro
* Mova as contas para a lixeira antes de excluí-las
* Arquive projetos antigos antes de excluí-los
* 🎇 Crie automaticamente faturas de reembolso a partir do plano de liquidação
* 🗓 Crie contas recorrentes (dia/semana/mês/ano)
* 📊 Opcionalmente, forneça um valor personalizado para cada membro em novas contas
* 🔗 Vincule arquivos pessoais a contas (foto do recibo físico, por exemplo)
* 👩 Links públicos para pessoas fora do Nextcloud (podem ser protegidos por senha)
* 👫 Compartilhe projetos com usuários/grupos/círculos Nextcloud
* 🖫 Importar/exportar projetos como csv (compatível com arquivos csv de IHateMoney e SplitWise)
* 🔗 Gere link/QRCode para adicionar projetos facilmente no MoneyBuster
* 🗲 Implementar notificações Nextcloud e fluxo de atividades

Este aplicativo geralmente suporta as 2 ou 3 últimas versões principais do Nextcloud.

Este aplicativo está em desenvolvimento.

🌍 Ajude-nos a traduzir esta aplicação no [Nextcloud-Cospend/MoneyBuster Crowdin](https://crowdin.com/project/moneybuster).

⚒ Confira outras maneiras de ajudar nas [diretrizes de contribuição](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentação

* [Documentação do usuário](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentação do administrador](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentação do desenvolvedor](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [REGISTRO DE MUDANÇAS](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTORES](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Problemas conhecidos

* Isso não te deixa rico

Qualquer retorno será apreciado.

