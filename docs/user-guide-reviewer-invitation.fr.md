# Inviter un relecteur - Guide utilisateur

*[English](user-guide-reviewer-invitation.en.md) · [Français](user-guide-reviewer-invitation.fr.md)*

## Présentation

La page **Inviter un relecteur** (bouton depuis la page de gestion d'un article) vous aide à choisir qui inviter :

- une **seule recherche** retrouve la personne, qu'elle soit relecteur de la revue, titulaire d'un compte Episciences
  ou déjà invitée sans compte ; si elle est introuvable, vous l'invitez comme **nouvelle personne** ;
- pour chaque personne, elle indique ce qui lui est **déjà demandé dans la revue**, pour éviter de solliciter trop
  souvent les mêmes relecteurs ;
- sur une nouvelle version d'un article, elle liste les **relecteurs des versions précédentes** et vous permet d'en
  **réinviter** certains seulement.

Ces informations sont réservées à l'équipe éditoriale qui gère l'article.

## Choisir la personne à inviter

### Rechercher une personne

Saisissez dans **Rechercher une personne** un nom, un prénom ou une adresse e-mail (deux caractères au moins). Les
résultats s'affichent pendant la saisie, en trois groupes :

| Groupe | Contenu | Comment la personne est trouvée |
|---|---|---|
| **Relecteurs de la revue** | les personnes qui ont le rôle de relecteur dans la revue | un mot du nom, du prénom ou de l'identifiant, ou l'adresse complète |
| **Autres comptes Episciences** | les autres comptes (d'autres revues, ou sans rôle) | le **début** du nom, du prénom ou de l'identifiant (au moins un mot de trois lettres), ou l'adresse complète |
| **Déjà invités sans compte** | les personnes invitées par la revue sans avoir de compte | l'adresse complète uniquement |

Majuscules et accents sont ignorés : « muller » trouve « Müller ». Chaque ligne donne le nom, l'adresse, l'identifiant
et les **Sollicitations dans la revue** (voir plus bas). Cliquez sur **Inviter** pour passer au message d'invitation.

Quand une personne ne peut pas être invitée, le motif remplace le bouton : **Déjà invité sur cette version**,
**Auteur de l'article**, **Vous-même** ou **Conflit d'intérêts déclaré**.

Avec les [types de demande](user-guide-review-request-types.fr.md), une personne peut recevoir une demande de chaque
type sur la même version : un relecteur n'est **Déjà invité sur cette version** que s'il a une demande en cours de
**chaque** type proposé par la revue. Sinon, il garde son bouton **Inviter**, et les types qu'il a déjà en cours sont
grisés dans le message d'invitation. Une personne sans compte est **Déjà invitée** dès qu'elle a une invitation en
cours.

### Parcourir les relecteurs de la revue

Sous la recherche, **Parcourir les N relecteurs de la revue** (cliquez pour déplier) liste tous les relecteurs de la
revue, 25 par page. Vous pouvez les trier par **Nom**, **Les moins sollicités d'abord** (pour répartir les demandes) ou
**Dernière invitation**.

### Inviter une nouvelle personne

Si la personne n'a pas été trouvée, cliquez sur **Inviter une nouvelle personne…**, puis saisissez son adresse e-mail,
son nom (obligatoires), son prénom (facultatif) et la langue de l'invitation. **Inviter cette personne…** ouvre le
message d'invitation.

Avant cela, l'adresse est vérifiée : si un compte l'utilise déjà, il vous est proposé à la place, avec son bouton
**Inviter**, ce qui évite de créer un doublon. **Inviter cette personne…** est alors masqué ; pour inviter quelqu'un
d'autre, modifiez l'adresse. Si ce compte ne peut pas être invité (votre propre compte, par exemple), le motif
s'affiche à la place du bouton. La personne invitée sans compte pourra en
créer un en répondant à l'invitation.

## Sollicitations dans la revue

La colonne **Sollicitations dans la revue** apparaît dans les résultats de la recherche, dans la liste des relecteurs
de la revue, et dans le tableau des relecteurs des versions précédentes. Elle indique :

- **Relectures en cours** : relectures acceptées et pas encore rendues ;
- **Invitations en attente** : invitations sans réponse, pas encore expirées ;
- le numéro des articles concernés (lien vers leur page de gestion, dans un nouvel onglet), avec la date limite de la
  relecture ou la date d'envoi de l'invitation ;
- **Dernière invitation** : sa date et la réponse donnée (acceptée, refusée, annulée, expirée, ou sans réponse pour le
  moment).

Bon à savoir :

- seuls les articles **en cours de relecture** sont comptés (pas les articles publiés, refusés ou remplacés par une
  nouvelle version) ; l'article que vous traitez n'est pas compté ;
- vous ne voyez le numéro que des articles que vous pouvez gérer ; les autres sont seulement comptés
  (« Autres articles : 2 ») ;
- le tableau liste au plus trois articles par personne ; la liste complète apparaît à l'étape suivante.

Quand vous choisissez une personne, les mêmes informations s'affichent **au-dessus du message d'invitation** : vous
pouvez adapter votre message, par exemple pour remercier un relecteur déjà très sollicité.

Cela vaut aussi pour une personne **sans compte** déjà invitée par la revue : recherchez son adresse, elle apparaît
dans **Déjà invités sans compte** avec ses invitations précédentes.

## Réinviter un relecteur d'une version précédente

Sur une nouvelle version d'un article, le tableau **Relecteurs des versions précédentes** liste, pour chaque version
précédente, les personnes invitées et ce qu'est devenue leur demande :

| Statut | Signification |
|---|---|
| Rapport rendu | la personne a terminé son rapport |
| Relecture commencée | le rapport est en cours |
| Invitation acceptée | invitation acceptée, rapport non commencé |
| Invitation refusée / expirée / annulée | la personne n'a pas relu cette version |
| Sans réponse | l'invitation est restée sans réponse |

Le type de chaque demande (rapport de relecture, avis rapide…) est indiqué sous son statut.

Pour réinviter une personne :

1. Cliquez sur **Réinviter** sur sa ligne.
2. Le message proposé est celui de la **réassignation à une nouvelle version** (modèle personnalisable dans les
   modèles de mail de la revue) ; il contient un lien vers la version que la personne avait relue. Vérifiez la date
   limite et le message, puis envoyez.

Le bouton n'apparaît qu'une fois par personne. Quand une personne ne peut pas être invitée, le motif s'affiche à la
place : **Déjà invité sur cette version**, **Auteur de l'article**, **Vous-même** ou **Conflit d'intérêts déclaré**.

Le lien **Profil** ouvre la fiche de la personne (comptes uniquement).

> Pour réinviter **tous** les relecteurs automatiquement au dépôt de la nouvelle version, utilisez toujours l'option
> **Réassigner les relecteurs à la nouvelle version de l'article** de la demande de modifications. Le tableau sert à
> n'en réinviter que certains, ou à le faire plus tard.

## Réinviter un relecteur après expiration de son invitation

Sur la page de gestion de l'article, le menu d'un relecteur dont l'invitation a expiré propose **Réinviter ce
relecteur**. Le message proposé dépend de son compte : relecteur de la revue, autre compte, ou personne sans compte.

Si la personne ne peut plus être invitée sur cette version (conflit d'intérêts déclaré, ou demande en cours de chaque
type), la page d'invitation affiche le motif au lieu du message.

L'envoi d'une invitation vérifie de nouveau ces règles : une invitation refusée n'est pas envoyée et le motif s'affiche.

## Renvoyer une invitation sans réponse

Une personne qui n'a pas encore répondu a pu perdre l'e-mail d'invitation, ou le trouver dans ses indésirables. Sur la
page de gestion de l'article, le menu de son invitation en attente propose **Renvoyer l'invitation** : après
confirmation, l'e-mail d'invitation est renvoyé avec **le même lien**, rédigé d'après le modèle d'invitation du type de
demande (les retouches faites au premier envoi ne sont pas reprises). C'est le seul moyen de lui redonner son lien
avant la prochaine relance automatique, en particulier pour une personne qui répond sans compte.

Le renvoi n'est possible que tant que l'invitation attend une réponse (ni acceptée, ni refusée, ni annulée, ni
expirée) et que l'article peut être relu. L'historique de l'article garde le renvoi et l'e-mail envoyé.

## Questions fréquentes

**Je ne trouve pas une personne qui a un compte.**
Pour les comptes qui ne sont pas relecteurs de la revue, saisissez le début de son nom ou de son prénom (au moins trois
lettres), ou son adresse complète : un morceau d'adresse ne suffit pas.

**Pourquoi une personne n'a-t-elle pas de colonne « Sollicitations » remplie ?**
Elle n'a jamais été invitée par la revue (« Jamais invité »).

**Le message de réassignation dit-il que la personne est réinvitée automatiquement ?**
Non : le texte par défaut dit « Nous vous invitons de nouveau à relire cet article ». Si votre revue a personnalisé ce
modèle, c'est votre texte qui est utilisé ; relisez-le avant l'envoi.
