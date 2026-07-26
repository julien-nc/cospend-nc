# Проект Cospend для Nextcloud. 💰

Nextcloud Cospend - это менеджер группового/общего бюджета. Он был создан под впечатлением от отличной программы [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Приложение пригодится, когда вы вскладчину снимаете жильё, или когда едете в отпуск с друзьями, или прочих случаях, когда вы скидываетесь на что-либо.

Он позволяет вам создавать проекты в которых есть участники и счета. Баланс каждого участника вычисляется на основании заведенных в проект счетов. Балансы — это не абсолютная сумма денег, которыми располагают участники, а скорее относительная информация, показывающая, потратил ли участник на группу больше, чем группа потратила на него/нее, независимо от того, кто именно потратил деньги на кого. Таким образом сразу видно кто должен всей группе, а кому должна группа. В завершение проекта можно вычислить план расчетов по платежам для обнуления балансов участников.

Участники проекта не связаны с пользователями Nextcloud. Проектами можно делиться с другими пользователями Nextcloud или через публичные ссылки.

[<img width="30px" src="https://github.com/helcel-net/cowspent/raw/refs/heads/main/metadata/en-US/images/icon.png" />](https://github.com/helcel-net/cowspent) [Cowspent](https://github.com/helcel-net/cowspent) Android client is [available in F-Droid in the IzzyOnDroid repo](https://apt.izzysoft.de/fdroid/index/apk/net.helcel.cowspent) and as a [downloadable APK file](https://github.com/helcel-net/cowspent/releases/latest).

[<img width="30px" src="https://gitlab.com/uploads/-/system/project/avatar/9981890/ic_launcher.png?width=48" />](https://gitlab.com/eneiluj/moneybuster) [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) (unmaintained) Android client is [available in F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) and on the [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[<img width="30px" src="https://github.com/mayflower/PayForMe/raw/refs/heads/main/PayForMe/Assets.xcassets/AppIcon.appiconset/app_icon-40x40.png" />](https://github.com/mayflower/PayForMe) Клиент для iOS [PayForMe](https://github.com/mayflower/PayForMe) разрабатывается в настоящее время!

Частные и публичные API документированы с помощью [Nextcloud OpenAPI extractor](https://github.com/nextcloud/openapi-extractor/). Доступ к этой документации можно получить непосредственно в Nextcloud. Все, что вам нужно - это установить Cospend (>= v1.6.0) и используйте [OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer) для просмотра документации OpenAPI.

## Возможности

* ✏️Создание/редактирование/удаление Проектов, участников, счетов, категорий счетов, валют
* ⚖️ Проверка балансов участников
* 🗠 Показать статистику проекта
* ♻ Показать план расчета
* Переместить счета из одного проекта в другой
* Переместить счета в корзину перед их удалением
* Архивировать старые проекты перед их удалением
* 🎇 Автоматическое создание счетов на возмещение расходов из плана расчетов
* 📅 Создание повторяющихся счетов (ежедневно/еженедельно/ежемесячно/ежегодно)
* 📊 Возможность устрановить произвольную сумму для каждого участника в новых счетах
* 🔗 Связать личные файлы со счетами (например, изображение физического чека)
* 👩 Общие ссылки для людей не имеющих аккаунт Nextcloud (могут быть защищены паролем)
* 👫 Публикация проекта для других пользователей/групп/кругов Nextcloud
* 💾 Импорт/экспорт Проектов в формате csv (совместим с csv файлами из IHateMoney)
* 🔗 Создание ссылки/QRCode для легкого импорта проектов в MoneyBuster
* ⚡Реализация уведомлений и активности Nextcloud

Это приложение обычно поддерживает 2 или 3 последние основные версии Nextcloud.

Это приложение находится в стадии разработки.

🌍 Помогите перевести это приложение на [Nextcloud-Cospend/MoneyBuster Crowdin project](https://crowdin.com/project/moneybuster).

⚒️ Ознакомьтесь с другими способами оказания помощи в руководстве по [внесению пожертвований](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Документация

* [Пользовательская документация](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Документация по администрированию](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Документация для разработчиков](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [ИСТОРИЯ ИЗМЕНЕНИЙ](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [АВТОРЫ](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Известные проблемы

* Приложение не сделает вас богатым

Мы будем признательны за любую обратную связь.

