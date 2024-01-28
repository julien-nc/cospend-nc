# Nextcloud Cospend 💰

Nextcloud Cospend est un gestionnaire de dépenses partagées (de groupe). Il a été inspiré par le génial [IHateMoney](https://github.com/spiral-project/ihatemoney/).

Vous pouvez l'utiliser lorsque vous partagez une maison, quand vous partez en vacances avec des amis, chaque fois que vous partagez des dépenses avec un groupe de personnes.

Cospend vous permet de créer des projets avec des membres et des factures. Chaque membre a un solde calculé à partir des factures du projet. Les soldes ne sont pas un montant absolu d'argent à la disposition des membres, mais plutôt une information relative montrant si un membre a dépensé plus pour le groupe que le groupe n'a dépensé pour lui/elle, indépendamment de qui a dépensé de l'argent pour qui. Comme ça vous pouvez voir qui doit de l'argent au groupe et à qui le groupe doit de l'argent. À la fin, vous pouvez demander un plan de remboursement qui vous indique les paiements à effectuer pour remettre les soldes des membres à zéro.

Les membres du projets sont indépendants des utilisateurs Nextcloud. Les projets peuvent être partagés avec d'autres utilisateurs de Nextcloud ou via des liens publics.

Le client Android [MoneyBuster](https://gitlab.com/eneiluj/moneybuster) est [disponible sur F-Droid](https://f-droid.org/packages/net.eneiluj.moneybuster/) et sur le [Play store](https://play.google.com/store/apps/details?id=net.eneiluj.moneybuster).

Le client iOS [PayForMe](https://github.com/mayflower/PayForMe) est en cours de développement !

Les API privées et publiques sont documentées à l'aide de [l'extracteur OpenAPI Nextcloud](https://github.com/nextcloud/openapi-extractor/). Cette documentation est accessible directement dans Nextcloud. Tout ce dont vous avez besoin est d'installer Cospend (>= v1.6.0) et d'utiliser [l'application OCS API Viewer](https://apps.nextcloud.com/apps/ocs_api_viewer) pour naviguer dans la documentation OpenAPI.

## Fonctionnalités

* ✎ Créer/modifier/supprimer des projets, membres, factures, catégories de factures, devises
* ⚖ Voir les soldes des membres
* 🗠 Afficher les statistiques des projets
* ♻ Afficher un plan de remboursement
* Déplacer les factures d'un projet vers un autre
* Déplacer les factures vers la corbeille avant de les supprimer
* Archiver les anciens projets avant de les supprimer
* 🎇 Créer automatiquement les factures correspondant au plan de remboursement
* 🗓 Créer des factures récurrentes (jour/semaine/mois/année)
* 📊 Entrer un montant personnalisé pour chaque membre dans les nouvelles factures
* 🔗 Lier les fichiers personnels aux factures (photo du reçu physique par exemple)
* 👩 Liens publics pour les personnes en dehors de Nextcloud (peut être protégé par mot de passe)
* 👫 Partager un projet avec des utilisateurs/groupes/cercles Nextcloud
* 🖫 Importer/exporter des projets en csv (compatible avec les fichiers csv d'IHateMoney)
* 🔗 Générez des liens/QRCode pour facilement importer des projets dans MoneyBuster
* 🗲 Implémente les notifications Nextcloud et le flux d'activité

Cette application supporte généralement les 2 ou 3 dernières versions majeures de Nextcloud.

Cette application est en cours de développement.

🌍 Aidez-nous à traduire cette application sur [le project Crowdin Nextcloud-Cospend/MoneyBuster](https://crowdin.com/project/moneybuster).

⚒️ Découvrez d'autres façons d'aider dans le [guide de contribution](https://github.com/julien-nc/cospend-nc/blob/master/CONTRIBUTING.md).

## Documentation

* [Documentation utilisateur](https://github.com/julien-nc/cospend-nc/blob/master/docs/user.md)
* [Documentation administrateur](https://github.com/julien-nc/cospend-nc/blob/master/docs/admin.md)
* [Documentation développeur](https://github.com/julien-nc/cospend-nc/blob/master/docs/dev.md)
* [CHANGELOG](https://github.com/julien-nc/cospend-nc/blob/master/CHANGELOG.md#change-log)
* [AUTEURS](https://github.com/julien-nc/cospend-nc/blob/master/AUTHORS.md#authors)

## Problèmes connus

* Ça ne vous rend pas riche

Tout retour sera apprécié.

