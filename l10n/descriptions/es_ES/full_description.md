# Nextcloud Cospend 💰

Nextcloud Cospend es un gestor de presupuesto compartido. Fue inspirado por el magnífico [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Puedes utilizarlo cuando compartes casa, cuando vas de vacaciones con amigos, o siempre que compartas gastos con un grupo de personas.

Te permite crear proyectos con miembros y facturas. Cada miembro tiene un saldo calculado a partir de las facturas del proyecto. Balances are not an absolute amount of money at members disposal but rather a relative information showing if a member has spent more for the group than the group has spent for her/him, independently of exactly who spent money for whom. De esta manera se puede ver quién debe al grupo y a quién debe el grupo. En última instancia, puedes pedir un plan de liquidación que indique qué pagos hay que hacer para restablecer los saldos de los miembros.

Los miembros del proyecto son independientes de los usuarios de Nextcloud. Los proyectos pueden compartirse con otros usuarios de Nextcloud o a través de enlaces públicos.

El cliente para Android [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) está [disponible en F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) y en la [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

¡El cliente de iOS [PayForMe](https://github.com/mayflower/PayForMe) está en desarrollo actualmente!

Las APIs privadas y públicas están documentadas usando el extractor [Nextcloud OpenAPI ](https://github.com/nextcloud/openapi-extractor/). Esta documentación puede ser accedida directamente en Nextcloud. Todo lo que necesitas es instalar Cospend (>= v1.6.0) y utilizar la la aplicación [OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer) para navegar por la documentación de OpenAPI.

## Funcionalidades

* ✎ Crear, editar y eliminar proyectos, miembros, facturas, categorías y monedas
* ⚖ Verificar el saldo de los miembros
* 🗠 Mostrar estadísticas del proyecto
* ♻️ Mostrar plan de liquidación
* Mover facturas de un proyecto a otro
* Mover facturas a la papelera antes de eliminarlas
* Archivar proyectos antiguos antes de eliminarlos
* 🎇 Crear automáticamente facturas de reembolso a partir del plan de pago
* 🗓️ Crear facturas recurrentes (día/semana/mes/año)
* 📊 Proporcionar opcionalmente una cantidad personalizada para cada miembro en nuevas facturas
* 🔗 Enlazar archivos personales a facturas (imagen de recibo físico por ejemplo)
* 👩 Enlaces públicos para personas fuera de Nextcloud (pueden estar protegidos por contraseña)
* 👫 Compartir proyectos con usuarios/grupos/círculos de Nextcloud
* ► Importar/exportar proyectos como csv (compatible con archivos csv de IHateMoney y SplitWise)
* 🔗 Generar enlaces o códigos QRs para agregar proyectos fácilmente en MoneyBuster
* 🗲 Implement Nextcloud notifications and activity stream

Esta aplicación normalmente soporta las 2 o 3 últimas versiones mayores de Nextcloud.

Esta aplicación está en desarrollo.

🌍 Ayúdanos a traducir esta aplicación en [el proyecto de Crowdin de Nextcloud Cospend/MoneyBuster](https://crowdin.com/project/moneybuster).

⚒️ Échale un vistazo a otras formas de ayudar en las [directrices de contribución](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentación

* [Documentacion para el usuario](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentacion para el administrador](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentación para desarrolladores](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTORES](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Problemas conocidos

* No te hace rico

Cualquier comentario o crítica será apreciado.

