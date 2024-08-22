# Nextcloud Cospend 💰

Nextcloud Cospend é un xestor de orzamentos de grupos/compartido. Inspirouse no gran [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Pode empregalo cando comparte casa, cando vaia de vacacións cos amigos, sempre que comparta gastos cun grupo de persoas.

Permítelle crear proxectos con membros e facturas. Cada membro ten un saldo calculado a partir das facturas do proxecto. Os saldos non son unha cantidade absoluta de diñeiro a disposición dos membros, senón unha información relativa que amosa se un membro gastou máis para o grupo do que o grupo gastou por el, independentemente de quen gastou cartos para quen. Deste xeito pode ver quen debe ao grupo e a quen debe o grupo. En última instancia, pode pedir un plan de liquidación que lle indique que pagos ten que facer para restabelecer os saldos dos membros.

Os membros do proxecto son independentes dos usuarios de Nextcloud. Os proxectos pódense compartir con outros usuarios de Nextcloud ou mediante ligazóns públicas.

O cliente para Android de [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) está [dispoñíbel en F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) e na [Play Store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

O cliente para iOS [PayForMe](https://github.com/mayflower/PayForMe) atopase en desenvolvemento!

As API públicas e privadas están documentadas mediante o [extractor de Nextcloud OpenAPI](https://github.com/nextcloud/openapi-extractor/). Pódese acceder a esta documentación directamente en Nextcloud. Todo o que precisa é instalar Cospend (>= v1.6.0) e usar a aplicación [OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer) para examinar a documentación de OpenAPI.

## Funcionalidades

* ✎ Crear, editar e eliminar proxectos, membros, facturas, categorías de facturas e moedas
* ⚖ Consultar o saldo dos membros
* 🗠 Amosar estatísticas do proxecto
* ♻ Amosar o plan de liquidación
* Mover facturas dun proxecto cara a outro
* Mover facturas cara ao lixo antes de eliminalas
* Arquivar proxectos antigos antes de eliminalos
* 🎇 Crear automaticamente facturas de reembolso dende o plan de liquidación
* 🗓️ Crear facturas recorrentes (día/semana/mes/ano)
* 📊 Opcionalmente, fornece un importe personalizado para cada membro nas novas facturas
* 🔗 Ligar ficheiros persoais a facturas (por exemplo, a imaxe do recibo físico)
* 👩 Ligazóns públicas para persoas fóra de Nextcloud (poden estar protexidas con contrasinal)
* 👫 Compartir proxectos con usuarios/grupos/círculos de Nextcloud
* 🖫 Importar/exportar proxectos como csv (compatíbel con ficheiros csv de IHateMoney e SplitWise)
* 🔗 Xerar ligazóns ou códigos QR para engadir doadamente proxectos en MoneyBuster
* 🗲 Implementar notificacións de Nextcloud e fluxo de actividade

Esta aplicación adoita admitir as 2 ou 3 últimas versións principais de Nextcloud.

Esta aplicación está en desenvolvemento.

🌍 Axúdanos a traducir esta aplicación no [proxecto de Crowdin de Nextcloud-Cospend/MoneyBuster](https://crowdin.com/project/moneybuster).

⚒ Consulte outros xeitos de axudar nas [directrices de colaboración](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentación

* [Documentación para usuarios](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentación para a administración](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentación de desenvolvemento](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [REXISTRO_DE_CAMBIOS](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTORES](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Incidencias coñecidas

* Non te farás rico

Calquera opinión será ben recibida.

