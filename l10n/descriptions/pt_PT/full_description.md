# Cospend do Nextcloud

Nextcloud Cospend é um gestor de orçamento de grupo/partilhado. Foi inspirado pelo grande [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Pode usá-lo quando estiver a partilhar uma casa, quando for de férias com amigos ou sempre que tiver de partilhar despesas com um grupo de pessoas.

Permite-lhe criar planeamentos com membros e despesas. Cada membro tem um saldo calculado a partir das despesas do planeamento. Saldos não são um montante absoluto de dinheiro à disposição dos membros mas antes uma informação relativa que mostra se o membro gastou mais para o grupo do que o grupo gastou para ele/ela, independentemente de quem gastou dinheiro para quem. Desta forma pode ver quem deve ao grupo e a quem o grupo deve. Em última análise pode pedir um plano de liquidação que lhe diga quais os pagamentos a fazer para reiniciar os saldos dos membros.

Os membros do planeamento são independentes dos utilizadores do Nextcloud. Planeamentos podem ser partilhados com outros utilizadores do Nextcloud ou através de ligações públicas.

[MoneyBuster](https://gitlab.com/eneiluj/moneybuster) O cliente Android está [disponível no F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) e na [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) O cliente iOS está neste momento a ser desenvolvido!

As APIs privadas e públicas são documentadas com o [the Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). Esta documentação pode ser acedida no Nextcloud. Tudo o que precisa fazer é instalar o Cospend (>= v1.6.0) e usar a aplicação [OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer) para consultar a documentação do OpenAPI.

## Funcionalidades

* ✎ Criar/editar/eliminar planeamentos, membros, despesas, categorias de despesas, moedas
* ⚖ Verificar saldos dos membros
* 🗠 Mostrar estatísticas do planeamento
* ♻ Mostrar plano de liquidação
* Mover despesas de um planeamento para o outro
* Mover planeamentos para o lixo antes de os eliminar definitivamente
* Arquivar planeamentos antigos antes de os eliminar
* 🎇 Criar notas de reembolso automaticamente a partir do plano de liquidação
* 🗓 Criar despesas recorrentes (dia/semana/mês/ano)
* 📊 Fornecer, opcionalmente, quantias personalizadas para cada membro nas novas despesas
* 🔗 Ligar ficheiros pessoais às despesas (uma fotografia do recibo físico, por exemplo)
* 👩 Ligações públicas para pessoas exteriores ao Nextcloud (podem ser protegidas por uma senha)
* 👫 Partilhar planeamentos com utilizadores/grupos/círculos do Nextcloud
* 🖫 Importar/exportar planeamentos com CSV (compatível com ficheiros CSV do IHateMoney e do SplitWise)
* 🔗 Gerar ligação/código-QR para facilitar a adição de planeamentos no MoneyBuster
* 🗲 Implementar notificações e fluxo de trabalho do Nextcloud

Esta aplicação normalmente suporta as 2 ou 3 versões maiores do Nextcloud.

Esta aplicação está sob desenvolvimento.

🌍 Ajude-nos a traduzir esta aplicação na [tradução no Crowdin do Nextcloud-Cospend/MoneyBuster](https://crowdin.com/project/moneybuster).

⚒ Veja outras formas de ajudar nas [orientações de contribuição](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentação

* [Documentação para o utilizador](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentação para o administrador](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentação para o programador](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG (registo das alterações)](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTHORS (autores)](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Problemas conhecidos

* Não lhe traz riqueza

Qualquer comentário será apreciado.

