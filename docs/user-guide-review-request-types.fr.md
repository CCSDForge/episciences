# Types de demande de relecture - Guide utilisateur

*[English](user-guide-review-request-types.en.md) · [Français](user-guide-review-request-types.fr.md)*

## Présentation

Quand vous invitez un relecteur, vous choisissez désormais le **type de demande** : un rapport de relecture complet,
un avis rapide, ou un type créé par votre revue (par exemple la relecture d'une version révisée). Le type détermine :

- le message d'invitation envoyé au relecteur ;
- la date limite proposée ;
- la grille utilisée pour le rapport (si votre revue en a créé une pour ce type) ;
- la possibilité de répondre **sans compte**, directement depuis le lien de l'invitation.

Un même relecteur peut recevoir une demande de chaque type pour une même version d'un article, par exemple d'abord un
avis rapide, puis un rapport complet.

## Qui peut gérer les types ?

Les types se gèrent dans la section **Types de demande** de la page **Relecture : grilles et types de demande**
(paramètres de la revue). Les actions sur les types sont réservées aux :
- **Administrateurs**
- **Rédacteurs en chef**
- **Secrétaires de rédaction**

## Types par défaut

Chaque revue commence avec deux types :

| Type | Délai par défaut | Usage |
|---|---|---|
| Rapport de relecture | réglage de la revue | relecture détaillée avec la grille d'évaluation (la relecture habituelle) |
| Avis rapide | 2 semaines | quelques lignes sur l'intérêt et la pertinence de l'article |

## Gérer les types

Dans la section **Types de demande**, chaque type est un bloc qui indique son délai par défaut, son état et la grille
qu'il utilise :

1. Cliquez sur **Ajouter un type de demande**, ou sur **Modifier ce type de demande** dans le bloc d'un type.
2. Renseignez :
   - le **Code** : identifiant stable (lettres minuscules, chiffres, tirets bas), qui ne peut plus être modifié ensuite ;
   - le **Libellé** dans chaque langue de la revue ;
   - le **Délai par défaut** (laisser vide pour utiliser le réglage de la revue) ;
   - la **Position** dans la liste ;
   - **Proposer ce type à l'invitation d'un relecteur** (décocher pour le masquer) ;
   - **Réponse sans compte, depuis le lien de l'invitation** (voir plus bas), et sa **Consigne** dans chaque langue
     (affichée seulement quand l'option est cochée ; elle est conservée si l'option est décochée).
3. Cliquez sur **Enregistrer**.

Les deux types par défaut restent toujours disponibles : ils ne peuvent pas être supprimés, seulement désactivés, afin
que chaque revue choisisse les types qu'elle propose. Un type créé par votre revue peut être supprimé tant qu'il n'a pas
servi ; ensuite, désactivez-le. Le rapport de relecture est toujours proposé et exige toujours un compte.

Pour d'autres besoins (relecture d'une version révisée, phases de relecture…), créez vos propres types, chacun avec sa
grille si besoin.

## Inviter un relecteur

1. Ouvrez **Inviter un relecteur** depuis la page de gestion de l'article et choisissez le relecteur comme d'habitude.
2. À l'étape du message d'invitation, choisissez le **Type de demande**.
3. Le message et la date limite changent selon le type. Vous pouvez toujours modifier le message avant l'envoi.

Les types que le relecteur a déjà en cours pour cet article sont grisés.

## Messages d'invitation

Chaque type a son propre modèle de message, que vous pouvez personnaliser dans l'écran des modèles de mail, comme
n'importe quel autre modèle. Un type créé par votre revue reçoit son propre modèle à sa création, copié d'un modèle
générique. Les modèles des types que vous avez désactivés ne sont plus listés.

Le paragraphe qui aide le relecteur à se connecter dépend de son compte (relecteur connu, compte existant, pas de
compte, réponse sans compte). Il est inséré par le tag `%%INVITATION_ACCOUNT_HINT%%` ; ses quatre textes sont eux aussi
des modèles de mail personnalisables.

Le **rapport de relecture** garde les trois modèles d'invitation existants : vos personnalisations actuelles ne
changent pas.

## Grilles de relecture

La page **Relecture : grilles et types de demande** montre la grille par défaut, puis un bloc par type de demande, puis
les grilles par volume. Le bloc d'un type affiche sa grille s'il en a une ; sinon, il indique que le type utilise la
grille du volume ou la grille par défaut, ou qu'il se répond sans compte. **Créer une grille**, dans le bloc d'un type, crée sa grille.
Au-delà de cinq grilles de volume, elles sont repliées et un clic sur le titre d'un volume affiche sa grille.
Un nouveau rapport utilise, dans l'ordre :

1. la grille de son type de demande ;
2. sinon, la grille du volume de l'article (sauf pour un avis rapide) ;
3. sinon, la grille par défaut.

Un avis rapide n'utilise donc jamais la grille d'un volume, faite pour les rapports de relecture : tant que la revue ne
lui a pas créé de grille, il utilise la grille par défaut.

Un rapport garde la grille avec laquelle il a été créé.

Un type à réponse sans compte n'utilise pas de grille : on ne peut pas lui en créer une, et une grille créée avant de
cocher l'option n'est pas utilisée tant que l'option reste cochée.

## Rapports

Chaque demande a son propre rapport. Sur la page de gestion de l'article :
- la section **Relecteurs** indique le type de chaque invitation ;
- la section **Évaluations** indique le type des rapports autres que les rapports de relecture.

Seuls les **rapports de relecture** comptent pour le statut « relu » de l'article, le nombre de relecteurs requis et la
note moyenne. Les autres types sont affichés à titre d'information.

## Réponse sans compte

Quand l'option est cochée sur un type, le lien de l'invitation ouvre une page où la personne invitée :
- lit la consigne du type (par défaut, l'avis rapide demande quelques lignes sur l'intérêt de l'article) ;
- répond : un texte, un fichier (facultatif), ou les deux ;
- ou refuse la demande.

Aucun compte n'est nécessaire. Le premier envoi vaut acceptation de l'invitation et prévient les rédacteurs par mail.
La personne peut modifier sa réponse depuis le même lien jusqu'à la date limite.

La réponse n'est visible que par l'équipe éditoriale, dans la section **Réponses données sans compte** de la page de gestion de
l'article (et sous l'invitation, dans la section **Relecteurs**). Les auteurs ne la voient jamais.

Sans compte, la personne invitée ne voit ni la grille ni ses rapports précédents. Le formulaire du type le rappelle
quand l'option est cochée sur un autre type que l'avis rapide. Pour un type de relecture d'une version révisée, gardez
la réponse avec compte si le relecteur doit s'appuyer sur son rapport précédent.

## Notifications par mail

| Événement | Destinataires |
|---|---|
| Invitation | la personne invitée (modèle du type de demande) |
| Réponse donnée sans compte | les rédacteurs de l'article (même mail que pour une relecture terminée) |

## Historique de l'article

L'historique de l'article indique le type de demande de chaque invitation.
