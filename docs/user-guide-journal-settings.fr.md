# Paramètres de la revue — Guide utilisateur

*[English](user-guide-journal-settings.en.md) · [Français](user-guide-journal-settings.fr.md)*

Ce guide résume la page **Paramètres de la revue** et les pages associées **Licences** et **Paramètres DOI** :
ce que fait chaque réglage et comment l'utiliser.

## En bref

| À retenir | |
|---|---|
| Où trouver la page ? | Menu **Gérer la revue › Revue › Paramètres** (adresse `/review/settings`), ou bouton **Paramètres de la revue** du tableau de bord. |
| Qui peut la modifier ? | Les rédacteurs en chef, administrateurs et secrétaires de rédaction de la revue. |
| Quand les changements s'appliquent-ils ? | Dès l'enregistrement. Certains concernent aussi les articles en cours ou publiés, d'autres seulement les prochaines actions : voir ci-dessous. |
| Comment enregistrer ? | Bouton **Enregistrer les paramètres**, toujours visible en bas de l'écran. Un message signale les modifications non enregistrées ; le navigateur demande confirmation si vous quittez la page. Il en va de même sur les pages **Licences** et **Paramètres DOI**. |
| Et si une valeur est refusée ? | Le message « Le formulaire comporte des erreurs » s'affiche et la page se place sur le premier champ à corriger. Rien n'est enregistré tant qu'une erreur subsiste. |
| Licences et DOI ? | Ils ont leur propre page. La page Paramètres en affiche un résumé en haut, avec un lien. |
| Un réglage est grisé ? | Il dépend d'un autre réglage désactivé (ex. « Ne pas permettre le choix d'un rédacteur en chef » quand l'auteur ne peut pas choisir de rédacteur). Il reste modifiable mais n'a pas d'effet pour l'instant. |

## Quand une modification prend-elle effet ?

Toute modification compte dès l'enregistrement, mais pas de la même façon selon le réglage.
La page Paramètres rappelle ces règles (lien **Quand une modification prend-elle effet ?** sous l'introduction).

| Quand | Réglages concernés | Exemple |
|---|---|---|
| **Tout de suite, pour tous les articles**, y compris ceux en cours ou déjà publiés | Ce que chacun peut faire ou voir : droits et cloisonnement des rédacteurs et des préparateurs de copie ; possibilités des auteurs (version temporaire, abandon, nouvelle version d'un article refusé, messages) ; déclaration de conflit d'intérêts ; affichage sur le site (rapports de relecture, volumes, statistiques, pied de page) ; métadonnées diffusées. | Si vous retirez aux rédacteurs le droit d'accepter, un rédacteur ne peut plus accepter aucun article, même un article qu'il gère depuis des mois. Si vous affichez les rapports de relecture, ceux des articles **déjà publiés** deviennent publics. |
| **À la prochaine action concernée** | Délais de relecture et d'invitation (fixés à l'envoi de chaque invitation) ; formulaire de soumission et assignation automatique des rédacteurs (à chaque nouvelle soumission) ; notifications (aux prochains courriels) ; réassignation des relecteurs et délai de révision (à la prochaine demande de modifications). | Si vous passez le délai de relecture de 2 à 3 mois, seules les invitations envoyées ensuite ont 3 mois. |
| **Jamais rétroactivement** | Ce qui a déjà eu lieu. | Les invitations déjà envoyées gardent leur délai et leur date limite ; les rédacteurs déjà assignés restent assignés ; les courriels déjà envoyés ne sont pas renvoyés ; les DOI déjà attribués ne changent pas. |

## Utiliser la page, pas à pas

| Je veux… | Je fais… |
|---|---|
| aller à un thème | Je clique sur une rubrique du **Sommaire** à gauche ; la rubrique affichée y est mise en évidence. |
| comprendre un réglage | Je lis le texte gris sous le champ. |
| changer un délai | Je saisis un nombre (1 à 999) et je choisis l'unité : jour(s), semaine(s) ou mois. |
| choisir plusieurs archives | Je maintiens **Ctrl** (ou **Cmd** sur Mac) en cliquant dans la liste **Archives disponibles**. |
| modifier les licences | Je clique sur **Modifier les licences** dans l'encadré **Licences**. |
| modifier les DOI | Je clique sur **Modifier les paramètres DOI** dans l'encadré **DOI des articles**. Si le bouton n'apparaît pas, je contacte l'équipe d'Episciences. |
| annuler mes modifications | Je recharge la page sans enregistrer et je confirme la sortie. |

## Les réglages, rubrique par rubrique

### Identité de la revue

Reprise dans le pied de page du site, les exports et les métadonnées diffusées (OAI-PMH, Crossref, DOAJ…).

| Réglage | À quoi il sert | Conseil |
|---|---|---|
| eISSN (online) | ISSN de la version électronique | Format `1234-5678` (le dernier caractère peut être `X`). |
| ISSN (print) | ISSN de la version imprimée | Seulement s'il existe une version imprimée. |
| DOI de la revue | DOI de la revue elle-même | Pas celui des articles. Laisser vide si la revue n'en a pas. |
| Éditeur / Lieu de publication | Éditeur et ville, pays | Le lieu exige que l'éditeur soit renseigné. |
| Année de création | Année sur 4 chiffres | — |
| Description de la revue | Courte présentation (texte brut, 2000 caractères) | Diffusée dans les métadonnées OAI-PMH. |
| Mots-clés | Thèmes de la revue | Séparés par des points-virgules. |

### Contacts et courriels

| Réglage | À quoi il sert | Conseil |
|---|---|---|
| Page de contact de la revue | Lien « Contact » du pied de page | Adresse complète, commençant par `https://`. |
| Page de la notice dans le catalogue | Lien vers la notice de la revue (ex. portail ISSN) | Adresse complète. |
| Courriel de contact de la revue | Adresse affichée dans le pied de page | — |
| Courriel de contact du support technique | Adresse du support affichée dans le pied de page | — |
| Code affiché dans les courriels | Remplace le code de la revue dans l'objet et le texte des courriels automatiques | Vide : code habituel de la revue. 100 caractères maximum. |
| Courriel de retour | Adresse qui reçoit les courriels non distribués | Choisir l'adresse propre à la revue pour suivre ces échecs. |

### Soumission

| Réglage | Choix possibles | Effet |
|---|---|---|
| Archives disponibles | Une ou plusieurs archives | Archives depuis lesquelles on peut soumettre. Au moins une. |
| Lettre d'accompagnement | Désactivée / Facultative / Requise | Fichier joint à la soumission. Le commentaire à la revue reste toujours facultatif. |
| Choix de rubrique | Ne peut pas / Peut / Doit choisir | Rubrique demandée à l'auteur. |
| Permettre aux auteurs de choisir le volume | Oui / Non | L'auteur propose un volume. |
| Choix du rédacteur | Ne peut pas / Peut / Doit / Doit en choisir un seul | Rédacteurs proposés par l'auteur. |
| Ne pas permettre le choix d'un rédacteur en chef | Oui / Non | Utile seulement si l'auteur peut choisir un rédacteur. |
| Suggérer des relecteurs / Indiquer des relecteurs non souhaités | Oui / Non | Champs supplémentaires dans le formulaire de soumission. |
| Partager le mot de passe papier arXiv | Non / Facultatif / Requis | L'auteur délègue à la revue la mise à jour de sa soumission arXiv. |

### Auteurs

| Réglage | Effet quand il est activé |
|---|---|
| Répondre à une demande de modifications par une version temporaire | L'auteur peut déposer un fichier directement sur la revue, en attendant la nouvelle version dans l'archive. |
| Abandonner le processus de publication | L'auteur peut retirer son article du processus. |
| Resoumettre un article déjà refusé | L'auteur peut proposer une nouvelle version d'un article refusé. |
| Communication entre auteurs et rédacteurs | Auteurs et rédacteurs assignés échangent des messages directement. |
| Afficher les noms des rédacteurs aux auteurs | L'auteur voit qui gère son article. |

### Relecture

| Réglage | Effet | Conseil |
|---|---|---|
| Délai avant expiration d'une invitation | Passé ce délai, le relecteur ne peut plus accepter. | Par défaut : 1 mois. |
| Délai de relecture par défaut | Délai pour rendre le rapport, compté depuis l'invitation. | Doit être compris entre le minimum et le maximum. |
| Délai de relecture minimum / maximum | Limites que les rédacteurs peuvent appliquer à chaque relecteur. | Par défaut : 2 mois et 6 mois. |
| Minimum de relectures requis | Nombre de rapports terminés pour que l'article soit « relu » ; en dessous, les rédacteurs sont relancés pour inviter des relecteurs. | 0 : tous les relecteurs ayant accepté doivent rendre leur rapport. |
| Relecteurs : envoyer des messages à l'auteur | Le relecteur peut écrire à l'auteur. | — |
| Afficher les rapports sur la page publique | Les rapports sont visibles sur la page des articles **publiés**, y compris ceux publiés avant l'activation. | À annoncer aux relecteurs. |
| Réassigner les mêmes relecteurs (modifications majeures / mineures) | Case cochée par défaut quand le rédacteur demande des modifications. | Le rédacteur peut la changer à chaque demande. |

### Rédacteurs

| Réglage | Effet quand il est activé |
|---|---|
| Cloisonner les rédacteurs | Un rédacteur ne voit que les articles qui lui sont assignés. |
| Accepter / Refuser / Demander des modifications / Publier | Le rédacteur peut prendre lui-même ces décisions. Sinon, il peut seulement les suggérer. |
| Abandonner le processus de publication | Le rédacteur peut retirer un article du processus. |
| Modifier les templates de mails | Le rédacteur peut adapter les courriels automatiques. |
| Assigner automatiquement les… | À la soumission, les rédacteurs cochés (en chef, de rubrique, de volume, de volume spécial, suggérés par l'auteur) sont assignés à l'article. Aucune case : aucune assignation automatique. |

### Décisions éditoriales

| Réglage | Effet quand il est activé |
|---|---|
| Exiger un délai pour la demande de révision | Le rédacteur doit fixer une date limite à chaque demande de modifications. |
| Permettre la demande de révision | Un article accepté peut encore faire l'objet d'une demande de nouvelle version. |

### Conflits d'intérêts

| Réglage | Effet quand il est activé |
|---|---|
| Activer la déclaration CI | Les rédacteurs doivent déclarer l'absence de conflit d'intérêts avant d'accéder aux informations non publiques d'un article. |
| Commentaires pour les rédacteurs avant la déclaration | Les rédacteurs reçoivent ces commentaires avant de déclarer. N'a d'effet que si la déclaration CI est activée. |

### Notifications

| Réglage | Effet |
|---|---|
| Notifier les rédacteurs en chef / administrateurs / secrétaires de rédaction | Ces rôles sont prévenus (soumission, mise à jour, refus…) en plus des rédacteurs de l'article. Aucune case cochée : les trois rôles sont quand même prévenus d'une nouvelle soumission. |
| Transmettre aux relecteurs le message de refus | En cas de refus, le message expliquant la décision est aussi envoyé aux relecteurs. |

### Volumes et volumes spéciaux

| Réglage | Effet quand il est activé |
|---|---|
| Afficher les volumes vides | Les volumes sans article apparaissent sur le site. |
| Modifier le titre d'un volume contenant des articles publiés | Le titre reste modifiable après publication. |
| Afficher les volumes secondaires | Ils apparaissent sur la page publique de l'article. |
| Code d'accès des volumes spéciaux | Il faut le code du volume spécial pour y soumettre. |
| Réattribuer la gestion de l'article | Dans un volume spécial, un rédacteur de l'article peut le confier à un autre rédacteur du volume. |

### Préparation de copie

| Réglage | Effet quand il est activé |
|---|---|
| Cloisonner les préparateurs de copie | Un préparateur ne voit que les articles qui lui sont assignés. |

### Statistiques

| Réglage | Choix | Effet |
|---|---|---|
| Visibilité des statistiques | Cachée / Publique / Réservée aux administrateurs | Qui voit la page des statistiques. |
| Début des statistiques | Date `AAAA-MM-JJ` (facultatif) | Seules les données postérieures sont comptées. |

## Licences (`/review/licenses`)

| Réglage | Effet | Conseil |
|---|---|---|
| Licence par défaut (obligatoire) | Pré-sélectionnée quand on définit la licence d'un article qui n'en a pas. | Ce n'est pas une contrainte. |
| Licences autorisées (facultatif) | Limite les licences proposées pour un article. | Aucune sélection : toutes les licences recommandées. La licence par défaut fait toujours partie de la liste. |

## Paramètres DOI (`/doi/settings`)

Cette page est gérée par l'équipe d'Episciences ; la page Paramètres en affiche le résumé.

| Réglage | Effet |
|---|---|
| Assignation automatique des DOI | **Automatique** : DOI créé à l'acceptation. **Manuel** : la revue clique sur « Demander un DOI » sur la page de l'article. **Désactivé** : la revue saisit le DOI de chaque article. |
| Agence d'enregistrement | Crossref. |
| Préfixe DOI | Attribué par l'agence (ex. `10.46298`). Sans préfixe, aucun DOI ne peut être créé. |
| Format du DOI | Modèle de la fin du DOI, avec des balises (`%R%` code de la revue, `%P%` numéro de l'article, `%Y%` année…). Un aperçu montre le DOI obtenu. Les DOI déjà attribués ne changent pas. |

## Questions fréquentes

| Question | Réponse |
|---|---|
| J'ai changé un délai : les invitations déjà envoyées sont-elles modifiées ? | Non. Le nouveau délai s'applique aux invitations suivantes. |
| Je ne trouve pas un réglage. | Utilisez le **Sommaire**, ou la recherche du navigateur (Ctrl + F). Les licences et les DOI sont sur leur propre page. |
| L'enregistrement échoue avec « veuillez saisir l'éditeur également ». | Un lieu de publication est renseigné sans éditeur : renseignez l'éditeur ou videz le lieu. |
| L'ISSN est refusé. | Saisissez-le au format `1234-5678`, avec le tiret. |
| Qui peut voir ce que chaque rôle peut faire ? | Tout le monde, sur la page **Permissions**. |
