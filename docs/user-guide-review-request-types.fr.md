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
   - le **Libellé** dans chaque langue de la revue ;
   - le **Délai par défaut** (laisser vide pour utiliser le réglage de la revue) ;
   - la **Position** dans la liste ;
   - **Proposer ce type à l'invitation d'un relecteur** (décocher pour le masquer) ;
   - **Compter ses rapports comme des rapports de relecture** (voir « Rapports ») ;
   - **Transmettre ses rapports à l'auteur** (voir « Rapports transmis à l'auteur ») ;
   - **Réponse sans compte, depuis le lien de l'invitation** (voir plus bas), et sa **Consigne** dans chaque langue
     (affichée seulement quand l'option est cochée ; elle est conservée si l'option est décochée).
3. Cliquez sur **Enregistrer**.

Les deux types par défaut restent toujours disponibles : ils ne peuvent pas être supprimés, seulement désactivés, afin
que chaque revue choisisse les types qu'elle propose. Un type créé par votre revue peut être supprimé tant qu'il n'a pas
servi ; ensuite, désactivez-le. Le rapport de relecture est toujours proposé et exige toujours un compte.

Pour d'autres besoins (relecture d'une version révisée, phases de relecture…), créez vos propres types, chacun avec sa
grille si besoin.

## Inviter un relecteur

1. Ouvrez **Inviter un relecteur** depuis la page de gestion de l'article et choisissez la personne (voir
   [Inviter un relecteur](user-guide-reviewer-invitation.fr.md)).
2. À l'étape du message d'invitation, choisissez le **Type de demande**.
3. Le message et la date limite changent selon le type. Vous pouvez toujours modifier le message avant l'envoi.

Les types que le relecteur a déjà en cours pour cet article sont grisés. Un relecteur qui a une demande en cours de
chaque type ne peut plus être invité sur cette version : **Déjà invité sur cette version** remplace son bouton.

## Demander des modifications : type de la version suivante

Dans le formulaire de demande de modifications (mineures ou majeures), la liste **Type de la nouvelle demande aux
relecteurs** indique ce qui sera demandé aux relecteurs sur la version que l'auteur va déposer (par exemple un avis
rapide après un premier rapport).

- **Même type que la demande en cours** (choix par défaut) : rien ne change. Si **Réassigner les relecteurs à la
  nouvelle version de l'article** est coché, chaque relecteur est réinvité avec le type de chacune de ses demandes, et
  le délai de la revue.
- **Un type choisi** : si la case de réassignation est cochée, chaque relecteur reçoit **une** invitation de ce type sur
  la nouvelle version (ou la version temporaire), avec le délai par défaut du type (sinon celui de la revue).
- Dans les deux cas, en invitant un relecteur sur la nouvelle version, le type choisi est proposé en premier.

Les messages de réinvitation peuvent afficher le type avec la balise `%%REVIEW_REQUEST_TYPE%%`.

Sur la page de gestion de l'article, chaque demande du panneau **Demandes de modifications** rappelle ce choix :
**Nouvelle demande aux relecteurs** (le type, ou « Même type que la demande en cours ») et **Réassignation automatique
des relecteurs** (oui ou non). L'historique de l'article l'indique aussi pour les demandes faites depuis cette
version. Ces informations sont réservées à l'équipe éditoriale : l'auteur ne les voit pas.

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
grille du volume ou la grille par défaut ; un type à réponse sans compte le signale aussi. **Créer une grille**, dans le bloc d'un type, crée sa grille.
Au-delà de cinq grilles de volume, elles sont repliées et un clic sur le titre d'un volume affiche sa grille.
Un nouveau rapport utilise, dans l'ordre :

1. la grille de son type de demande ;
2. sinon, la grille du volume de l'article (sauf pour un avis rapide) ;
3. sinon, la grille par défaut.

Un avis rapide n'utilise donc jamais la grille d'un volume, faite pour les rapports de relecture : tant que la revue ne
lui a pas créé de grille, il utilise la grille par défaut.

Un rapport garde la grille avec laquelle il a été créé.

Un type à réponse sans compte utilise sa grille de la même façon : la personne invitée la remplit depuis le lien de
l'invitation (voir « Réponse sans compte »).

## Rapports

Chaque demande a son propre rapport. Sur la page de gestion de l'article :
- la section **Relecteurs** indique le type de chaque invitation ;
- la section **Évaluations** indique le type des rapports autres que les rapports de relecture.

Seuls les **rapports de relecture**, et ceux des types où **Compter ses rapports comme des rapports de relecture** est
coché, comptent pour le statut « relu » de l'article, le nombre minimum de relectures, la note moyenne et la relance
« pas assez de relecteurs ». Les autres types sont affichés à titre d'information. Cochez cette option, par exemple, pour
les phases de relecture que votre revue crée comme types (« Phase 2 », « Relecture d'une version révisée »…). Elle est
sans effet pour un type à réponse sans compte, qui ne produit pas de rapport. Sur la page des grilles, ces types portent
le badge **Compte comme rapport de relecture**.

La même règle vaut pour les statistiques de la page **Statistiques** : les **évaluations demandées** ne comptent que les
invitations pour un rapport de relecture ou pour un type qui compte comme tel, et les **évaluations reçues** et le
nombre médian d'évaluations par article ne comptent que les rapports de ces types. Les avis rapides n'y figurent pas.

## Rapports transmis à l'auteur

L'option **Transmettre ses rapports à l'auteur** décide si les rapports d'un type sortent de la rédaction :

- **cochée** : ils sont transmis comme les rapports de relecture, selon la visibilité des critères de leur grille
  (« public », « contributeur ») : page de l'article de l'auteur, e-mails de décision (tag `%%PAPER_RATINGS%%`, où le
  type est indiqué à côté du relecteur), pièces jointes, et page publique de l'article publié si la revue affiche les
  rapports ;
- **décochée** : ils restent réservés à la rédaction, quelle que soit la visibilité des critères de leur grille.

Les **rapports de relecture** sont toujours transmis : l'option est cochée et ne peut pas être modifiée. L'**avis
rapide** et les types créés par la revue ne sont pas transmis tant que la revue ne coche pas l'option. Une réponse sans
compte reste toujours réservée à la rédaction. Le relecteur voit toujours son propre rapport.

Sur la page des grilles, chaque type porte le badge **Transmis à l'auteur** ou **Réservé à la rédaction**.

## Réponse sans compte

Quand l'option est cochée sur un type, le lien de l'invitation ouvre une page où la personne invitée :
- lit la consigne du type (par défaut, l'avis rapide demande quelques lignes sur l'intérêt de l'article) ;
- remplit la grille de la demande (choix, commentaires et fichiers selon les critères), choisie comme pour un rapport :
  grille du type, sinon du volume (sauf pour un avis rapide), sinon grille par défaut ;
- ou refuse la demande.

Si aucune grille n'est configurée, la page l'indique à la personne invitée, qui ne peut pas encore répondre, et les
rédacteurs de l'article sont prévenus par mail (une fois par invitation) ; l'historique de l'article le garde. Créez
alors une grille pour le type, ou une grille par défaut : le lien de l'invitation reste valable.

Aucun compte n'est nécessaire. Le premier envoi vaut acceptation de l'invitation et prévient les rédacteurs par mail.
La personne peut modifier sa réponse depuis le même lien jusqu'à la date limite ; un nouveau fichier remplace celui
déjà envoyé pour le même critère.

La réponse n'est visible que par l'équipe éditoriale, dans la section **Réponses données sans compte** de la page de gestion de
l'article (et sous l'invitation, dans la section **Relecteurs**). Les auteurs ne la voient jamais.

Sans compte, la personne invitée ne voit pas ses rapports précédents, et sa réponse ne compte pas comme rapport de
relecture. Le formulaire du type le rappelle
quand l'option est cochée sur un autre type que l'avis rapide. Pour un type de relecture d'une version révisée, gardez
la réponse avec compte si le relecteur doit s'appuyer sur son rapport précédent.

## Notifications par mail

| Événement | Destinataires |
|---|---|
| Invitation | la personne invitée (modèle du type de demande) |
| Réponse donnée sans compte | les rédacteurs de l'article (même mail que pour une relecture terminée, avec la grille remplie et ses fichiers) |
| Réponse sans compte impossible : aucune grille configurée | les rédacteurs de l'article (une fois par invitation) |

## Historique de l'article

L'historique de l'article indique le type de demande de chaque invitation.
