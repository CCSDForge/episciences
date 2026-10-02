# Changer le contributeur - Guide utilisateur

## Aperçu

La fonctionnalité "Changer le contributeur" permet aux utilisateurs autorisés de transférer la propriété d'un article d'un contributeur à un autre.

## Qui peut changer le contributeur ?

Seuls les rôles suivants peuvent changer le contributeur d'un article :
- **Administrateurs**
- **Rédacteurs en chef**

## Comment changer le contributeur

1. Accédez à la page d'administration de l'article
2. Dans le panneau **Contributeur**, cliquez sur le bouton **Changer le contributeur**
3. Une fenêtre modale s'ouvre :
   - Recherchez le nouveau contributeur par nom ou email
   - Sélectionnez l'utilisateur dans les résultats de l'autocomplétion
   - Cochez éventuellement **"Ajouter l'ancien contributeur comme co-auteur"** (coché par défaut)
4. Cliquez sur **Confirmer** pour appliquer le changement

## Option "Ajouter l'ancien contributeur comme co-auteur"

Lorsque cette option est **cochée** :
- L'ancien contributeur est ajouté comme co-auteur de l'article
- Il continuera à recevoir les notifications concernant l'article
- Il pourra toujours voir l'article dans son espace auteur

Lorsque cette option est **décochée** :
- L'ancien contributeur n'est pas ajouté comme co-auteur
- L'ancien contributeur reçoit une notification du changement, mais ne recevra plus de notifications concernant cet article par la suite.

## Un co-auteur devient contributeur

Un co-auteur peut être sélectionné comme nouveau contributeur. Dans ce cas :
- Son rôle de co-auteur est automatiquement supprimé
- Il devient le contributeur principal (propriétaire) de l'article

## Notifications par email

Deux notifications par email sont envoyées lors du changement de contributeur :

### 1. Notification au nouveau contributeur
**Modèle** : `paper_new_contributor_notification`

Envoyée au **nouveau contributeur** pour l'informer qu'il est désormais responsable de l'article.

**Contenu inclus** :
- Titre de l'article
- Lien pour gérer l'article dans son espace auteur

### 2. Notification à l'ancien contributeur
**Modèle** : `paper_former_contributor_notification`

Envoyée à l'**ancien contributeur** pour l'informer qu'il n'est plus le propriétaire de l'article.

**Contenu inclus** :
- Titre de l'article
- Nom du nouveau contributeur
- Message sur le statut de co-auteur (s'il a été ajouté comme co-auteur ou non)
- Lien pour consulter l'article (uniquement s'il a été ajouté comme co-auteur)

## Journal d'activité

L'action est enregistrée dans l'historique de l'article avec les détails suivants :
- **Action** : Contributeur changé
- **Effectuée par** : Utilisateur qui a effectué le changement
- **Ancien contributeur** : Nom de l'ancien contributeur
- **Nouveau contributeur** : Nom du nouveau contributeur
- **Statut co-auteur** : Si l'ancien contributeur a été ajouté comme co-auteur

## Affichage dans la chronologie

Dans la chronologie de l'article (panneau historique), le changement de contributeur apparaît comme :

```
Contributeur changé    [Ancien nom] → [Nouveau nom]    [Date]
```

En cliquant sur l'entrée, une fenêtre modale s'ouvre avec tous les détails :
- Date et heure
- Utilisateur qui a effectué l'action
- Nom de l'ancien contributeur
- Nom du nouveau contributeur
- Statut co-auteur (le cas échéant)