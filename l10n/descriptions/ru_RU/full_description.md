# Проект Cospend для Nextcloud

Nextcloud Cospend - это менеджер группового/общего бюджета. Он был создан под впечатлением от отличной программы [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Он пригодится, когда вы вскладчину снимаете жильё, или когда едете в отпуск с друзьями, в случаях, когда вы скидываетесь на что-либо.

Он позволяет вам создавать проекты в которых есть участники и счета. Баланс каждого участника вычисляется на основании заведенных в проект счетов. Таки образом сразу видно кто должен всей группе, а кому должна группа. В завершение проекта можно вычислить план расчетов по платежам для обнуления балансов участников.

Участники проекта не связаны с пользователями Nextcloud. Доступ к проектам и к редактированию их данных могут иметь люди и без регистрации в Nextcloud. У каждого проекта есть уникальный идентификатор и пароль для входа с гостевым доступом.

Клиент [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) для Android [ доступен в F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) и в [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

[PayForMe](https://github.com/mayflower/PayForMe) iOS client is currently being developped!

## Возможности

* ✎ create/edit/delete projects, members, bills, bill categories, currencies
* ⚖ проверка балансов участников
* 🗠 отображение статистики проекта
* ♻ план расчета
* 🎇 автоматическое создание счетов на возмещение из плана расчёта
* 🗓 создание повторяющихся счетов (ежедневно/еженедельно/ежемесячно/ежегодно)
* 📊 возможность устрановить произвольную сумму для каждого участника во вводимых счетах
* 🔗 link bills with personal files (picture of physical bill for example)
* 👩 гостевой доступ вне Nextcloud
* 👫 share projects with Nextcloud users/groups/circles
* 🖫 Импорт/экспорт проектов в формате csv (совместим с csv файлами из IHateMoney)
* 🔗 generate link/QRCode to easily import projects in MoneyBuster
* 🗲 implement Nextcloud notifications and activity stream

This app is tested on Nextcloud 18 with Firefox 57+ and Chromium.

This app is under development.

🌍 Help us to translate this app on [Nextcloud-Cospend/MoneyBuster Crowdin project](https://crowdin.com/project/moneybuster).

⚒ Check out other ways to help in the [contribution guidelines](https://gitlab.com/eneiluj/cospend-nc/blob/master/CONTRIBUTING.md).

## Установка

See the [AdminDoc](https://gitlab.com/eneiluj/cospend-nc/wikis/admindoc) for installation details.

Check [CHANGELOG](https://gitlab.com/eneiluj/cospend-nc/blob/master/CHANGELOG.md#change-log) file to see what's new and what's coming in next release.

Check [AUTHORS](https://gitlab.com/eneiluj/cospend-nc/blob/master/AUTHORS.md#authors) file to see complete list of authors.

## Известные проблемы

* оно не сделает вас богатым

Any feedback will be appreciated.