# Résumé de session — 27 Juin 2026 (suite)

## Objectif
1. Ajouter un accès rapide vers la recherche avancée depuis le champ "🔍 Rechercher patient" de l'en-tête.
2. Construire le chantier "Recherche avancée" (prévu en fin de session précédente).

## 1. Double-clic sur le champ de recherche d'en-tête
Sur 8 pages, un double-clic sur "🔍 Rechercher patient" envoie directement vers `recherche.php` (en conservant le texte déjà tapé, le cas échéant) :
`jours_feries.php`, `dossier.php`, `biologie.php`, `index.php`, `agenda.php`, `grille_semaine.php`, `nouveau_bilan_clinique.php`, `recherche.php`.

`planning.php` est volontairement exclu — son champ de recherche filtre par date/jour dans la grille, pas par patient.

## 2. Recherche avancée combinée — `recherche.php`
Refonte complète du formulaire de recherche. Plutôt qu'un menu déroulant à un seul critère, **5 groupes de critères affichés simultanément sur une ligne**, reliés par des connecteurs **et / ou**, lus strictement de gauche à droite (pas de priorité mathématique du "et" sur le "ou") :

| Groupe | Modalités | Détail |
|---|---|---|
| Identité | Égal à / Commence par / Contient / Se termine par | Détecte automatiquement N° patient (si chiffres) ou Nom/Prénom (si texte) |
| Date de consultation | À cette date / Avant le / Après le / Entre deux dates | Cherche dans `ORD`, combine `[DATE REDEZ VOUS]` et `Date_Rdv`, passé ou futur |
| Âge ou naissance | Égal à / Plus de / Moins de / Entre | Un seul champ : détecte si c'est un nombre (âge) ou une date jj/mm/aaaa (DDN) |
| Téléphone | Contient / Égal à | — |
| CIN | Égal à / Contient | — |

Tout groupe laissé vide est ignoré. Les anciens liens `recherche.php?q=...` (recherche rapide d'en-tête, double-clic) restent compatibles — ils remplissent simplement le groupe Identité.

### Bug rencontré et corrigé
**PDO + SQL Server + opérateur unaire moins sur paramètre** : `DATEADD(YEAR, -?, ...)` provoque l'erreur *"Le type de données de l'opérande nvarchar n'est pas valide pour l'opérateur minus"*. PDO/ODBC envoie les paramètres en nvarchar par défaut, et SQL Server refuse d'appliquer `-` directement dessus. **Correctif : toujours écrire `-CAST(? AS INT)` au lieu de `-?`** dès qu'un paramètre lié doit être utilisé dans un calcul arithmétique (et pas seulement comparé). À garder en tête pour tout futur calcul similaire (âge, durées, etc.).

## Pages encore non traitées
Aucune — toutes les pages prévues pour l'harmonisation des boutons/thème sont faites, et la recherche avancée est livrée.

## En attente — prochaine session
1. Tâche 12 — Valider `print_lettre.php`
2. Tâche 13 — Révision contenu listes bilan clinique
3. Bug FDR vide dans `print_rapport.php`
4. `index.php` — liens vers `recherche.php?action=...` à vérifier/compléter
5. Doublon du compteur RDV sur `index.php` (logo + bandeau bas) — à trancher si gênant
6. Tester la recherche avancée en conditions réelles avec plusieurs combinaisons (3-4 critères à la fois) et remonter tout cas où le résultat ne correspond pas à l'attente

## Git
```bash
cd C:\xampp\htdocs\logycab
git add jours_feries.php dossier.php biologie.php index.php agenda.php grille_semaine.php nouveau_bilan_clinique.php recherche.php
git commit -m "Double-clic recherche depuis l'entete + recherche avancee combinee (5 criteres, et/ou) sur recherche.php"
git push
```

Sur l'autre machine, au prochain démarrage : `git pull`.
