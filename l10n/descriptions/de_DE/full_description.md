# Nextcloud Cospend 💰

Nextcloud Cospend ist ein gemeinsamer Budget-Manager für Gruppen. [IHateMoney](https://github.com/spiral-project/ihatemoney/) diente hierbei als Vorbild.

Immer wenn du Geld mit anderen teilst, z. B. in einer WG oder im Urlaub mit Freunden, kannst du diese App verwenden.

Du kannst Projekte erstellen sowie Mitglieder und Ausgaben hinzufügen. Anhand der Ausgaben im Projekt wird für jedes Mitglied eine Bilanz erstellt. Bilanzen sind keine absolute Summe an Geld, die den Mitgliedern zur Verfügung steht, sondern eine relative Information, die anzeigt, ob ein Mitglied mehr für die Gruppe ausgegeben hat als die Gruppe für sie/ihn (unabhängig davon wer genau gezahlt hat). Somit ist ersichtlich, wer wem etwas schuldet. Letztendlich kannst du dir dann in der Abrechnung anzeigen lassen, welche Zahlungen zu leisten sind, um die Bilanzen der Mitglieder auszugleichen.

Projektmitglieder sind unabhängig von Nextcloud Benutzern. Projekte können mit anderen Nextcloud-Nutzern oder mit öffentlichen Links geteilt werden.

Der Android Client [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) ist [erhältlich auf F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) und im [Play Store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

Der iOS-Client [PayForMe](https://github.com/mayflower/PayForMe) wird aktuell noch entwickelt!

Die privaten und öffentlichen APIs sind mit [der Nextcloud OpenAPI Extractor App](https://github.com/nextcloud/openapi-extractor/) dokumentiert. Diese Dokumentation kann direkt in Nextcloud aufgerufen werden. Installiere einfach Cospend (>= v1.6.0) und benutze die [OCS API Viewer App](https://apps.nextcloud.com/apps/ocs_api_viewer) um die OpenAPI-Dokumentation zu sehen.

## Funktionen

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

Diese App unterstützt in der Regel die 2 oder 3 letzten Hauptversionen von Nextcloud.

Diese App ist in Entwicklung.

🌍 Hilf uns bei der Übersetzung dieser App auf [Nextcloud-Cospend/MoneyBuster Crowdin project](https://crowdin.com/project/moneybuster).

⚒️ Weitere Möglichkeiten zu Helfen findest du in der [Contribution-Richtlinie](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Dokumentation

* [Benutzer-Dokumentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Admin-Dokumentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Entwickler-Dokumentation](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG / Änderungen](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTHORS / Ersteller](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Bekannte Probleme

* Es macht dich nicht reich

Jedes Feedback ist willkommen.

