# Minimum de relectures par tour - Guide utilisateur

*[English](user-guide-minimum-reviews.en.md) · [Français](user-guide-minimum-reviews.fr.md)*

## Présentation

Votre revue peut exiger un **nombre minimum de relectures** avant qu'un rédacteur puisse accepter un article, le
publier ou demander des modifications. Ce minimum peut désormais **changer au fil des tours de relecture** : par
exemple deux relectures pour la première version, puis une seule pour les versions révisées.

Un **tour de relecture** correspond à une version de l'article : la première version est le tour 1, la première
version révisée le tour 2, etc. Une **version temporaire** (réponse à une demande de modifications sans nouveau dépôt
dans l'archive) compte aussi comme un tour.

## Régler le minimum

Dans **Paramètres de la revue**, section **Paramètres des rédacteurs** (réservé aux administrateurs, rédacteurs en chef
et secrétaires de rédaction) :

| Réglage | Rôle |
|---|---|
| **Minimum de relectures requis** | minimum des premiers tours |
| **Minimum appliqué aux N premiers tours de relecture** | nombre de premiers tours concernés ; laissez vide pour appliquer le minimum à **toutes** les versions (comportement habituel) |
| **Minimum de relectures aux tours suivants** | minimum à partir du tour suivant ; laissez vide (ou 0) pour n'en exiger aucune |

### Exemples

| Besoin | Minimum requis | N premiers tours | Tours suivants |
|---|---|---|---|
| Même minimum pour toutes les versions (réglage habituel) | 2 | *(vide)* | *(vide)* |
| 2 relectures pour la première version, puis 1 | 2 | 1 | 1 |
| 2 relectures pour les deux premières versions, puis aucune | 2 | 2 | *(vide)* |

Si vous ne touchez pas aux deux nouveaux réglages, rien ne change pour votre revue.

## Ce que le minimum bloque

Tant que le minimum de la version n'est pas atteint :

- les **rédacteurs** et **rédacteurs invités** ne peuvent ni accepter, ni publier, ni demander des modifications : ces
  choix sont grisés dans le menu **Changer le statut de l'article**, avec le minimum de la version en info-bulle ;
- la demande est aussi refusée si elle est envoyée autrement que par le menu ;
- les **rédacteurs en chef**, **secrétaires de rédaction** et **administrateurs** ne sont pas bloqués.

Le minimum sert aussi :

- au statut **évalué - en attente de décision éditoriale** de l'article (atteint quand le minimum de la version
  est atteint) ;
- à la relance **« pas assez de relecteurs »** envoyée aux rédacteurs : elle tient compte du minimum de chaque version.

## Questions fréquentes

**J'ai saisi 0 dans « Minimum de relectures aux tours suivants » et le champ est vide à l'enregistrement : est-ce
normal ?**
Oui : vide et 0 ont le même effet (aucune relecture exigée).

**Une version temporaire acceptée puis remplacée par la version définitive compte-t-elle deux tours ?**
La version temporaire est un tour ; la version définitive déposée ensuite en est un autre, mais elle ne repasse pas
en relecture.
