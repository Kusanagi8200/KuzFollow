# KuzFollow

KuzFollow analyse les relations d'un compte GitHub : abonnés, abonnements,
relations réciproques et comptes à suivre ou à ne plus suivre.

Le dépôt contient deux applications indépendantes qui utilisent la même API
GitHub :

| Version | Point d'entrée | Usage |
| --- | --- | --- |
| Terminal Bash | `KuzFollow.sh` | Analyse interactive depuis un terminal |
| Web PHP | `public/index.php` | Tableau de bord accessible depuis un navigateur |

La version terminal reste entièrement écrite en Bash. La version web existante
utilise PHP, HTML, CSS et un petit script JavaScript intégré à la page.

## Fonctionnalités communes

- Récupération paginée des followers et des comptes suivis.
- Détection des relations non réciproques.
- Sélection des comptes à suivre ou à ne plus suivre.
- Consultation des dépôts publics et de l'activité publique récente.
- Utilisation d'un token GitHub conservé hors du dépôt.

## Version terminal Bash

### Interface

Le logo ASCII historique est affiché dans les terminaux d'au moins 80 colonnes.
Un en-tête compact prend automatiquement le relais dans un terminal plus étroit.
Le tableau de bord affiche ensuite les statistiques alignées, les listes
d'actions et le menu interactif.

```text
███████╗ ██████╗ ██╗     ██╗      ██████╗ ██╗    ██╗███████╗██████╗ ███████╗
██╔════╝██╔═══██╗██║     ██║     ██╔═══██╗██║    ██║██╔════╝██╔══██╗██╔════╝
█████╗  ██║   ██║██║     ██║     ██║   ██║██║ █╗ ██║█████╗  ██████╔╝███████╗
██╔══╝  ██║   ██║██║     ██║     ██║   ██║██║███╗██║██╔══╝  ██╔══██╗╚════██║
██║     ╚██████╔╝███████╗███████╗╚██████╔╝╚███╔███╔╝███████╗██║  ██║███████║
╚═╝      ╚═════╝ ╚══════╝╚══════╝ ╚═════╝  ╚══╝╚══╝ ╚══════╝╚═╝  ╚═╝╚══════╝
```

Le menu conserve les améliorations suivantes :

- statistiques des relations réciproques ;
- listes séparées avec compteurs et états vides explicites ;
- retour au menu après une saisie invalide, une consultation ou une annulation ;
- confirmation exacte `YES` avant une action groupée ;
- arrêt sans action avec `4`, `q` ou Entrée ;
- arrêt immédiat si les données de relations sont incomplètes ou invalides ;
- rapport sans action lorsque l'entrée ou la sortie n'est pas un terminal ;
- désactivation des couleurs avec `NO_COLOR=1`, `TERM=dumb` ou une redirection.

### Prérequis

- Bash 4 ou plus récent ;
- `curl` ;
- `jq` ;
- les utilitaires système `head`, `date` et `sleep`.

Sur macOS, installez une version récente de Bash au lieu d'utiliser Bash 3
fourni par défaut.

### Installation et lancement

```bash
git clone https://github.com/Kusanagi8200/KuzFollow.git
cd KuzFollow

export GITHUB_USER="your_username"
read -r -s -p 'GitHub token: ' GITHUB_TOKEN
printf '\n'
export GITHUB_TOKEN

bash KuzFollow.sh
unset GITHUB_TOKEN
```

Le token doit appartenir au compte géré et autoriser les actions follow et
unfollow. Pour un token classique, utilisez la permission `user:follow`.

Commandes utiles :

```bash
# Aide sans appel à l'API
bash KuzFollow.sh --help

# Affichage sans couleurs
NO_COLOR=1 bash KuzFollow.sh

# Rapport non interactif, sans action sur les relations
bash KuzFollow.sh > report.txt
```

Après une action groupée, relancez le script pour actualiser les statistiques.
Les requêtes d'action sont espacées d'une seconde. Le script n'effectue pas de
nouvelle tentative automatique en cas de limite API ou d'erreur réseau.

## Version web PHP

### Fonctionnalités

Le tableau de bord web fournit :

- les nombres de followers, abonnements et dépôts ;
- les 10 premiers followers renvoyés par l'API ;
- les dépôts du propriétaire triés par mise à jour ;
- les 30 événements publics récents dans une fenêtre dédiée ;
- la sélection individuelle des comptes à suivre ;
- la sélection individuelle des comptes non réciproques à ne plus suivre ;
- un mode `DRY-RUN` qui affiche l'action et les cibles sans appeler l'action API ;
- un historique quotidien limité à 180 points ;
- un graphique SVG généré par `public/graph.svg.php`.

La version web est indépendante du script Bash. Elle utilise
`src/GitHubClient.php` pour appeler l'API GitHub et écrit l'historique dans
`data/followers_history.json`.

### Prérequis

- PHP 7.4 ou plus récent ;
- extension PHP cURL ;
- extension PHP JSON ;
- sessions PHP ;
- serveur web Apache avec `mod_rewrite`, ou une configuration équivalente ;
- accès HTTPS recommandé ;
- droit d'écriture du processus PHP sur le dossier `data/`.

Le document root du site doit pointer vers le dossier `public/`. Le fichier
`public/.htaccess` redirige les routes vers `public/index.php` sous Apache.

### Configuration

La version PHP lit sa configuration dans
`/etc/kuzfollow/config.php`. Ce fichier reste hors du dépôt et hors du document
root.

```php
<?php
return [
    'github_user' => 'your_username',
    'github_token' => 'your_token',
];
```

Exemple de préparation sur un serveur Linux :

```bash
sudo install -d -m 750 /etc/kuzfollow
sudo editor /etc/kuzfollow/config.php
sudo chown -R www-data:www-data data
sudo chmod 750 data
```

Adaptez `www-data` à l'utilisateur réel de PHP-FPM ou du serveur web.
Ne publiez jamais le fichier de configuration et ne placez jamais un vrai token
dans le dépôt.

Pour un test local avec le serveur intégré de PHP :

```bash
php -S 127.0.0.1:8080 -t public
```

Ouvrez ensuite `http://127.0.0.1:8080/`. La configuration absolue
`/etc/kuzfollow/config.php` doit déjà exister.

### Historique et graphique

À chaque chargement du tableau de bord, `public/index.php` ajoute au maximum un
point par jour à l'historique et conserve les 180 derniers points.

`public/seed_history.php` est un outil d'initialisation facultatif. Il remplace
l'historique par 30 points synthétiques se terminant par le nombre réel de
followers du jour :

```bash
php public/seed_history.php
```

N'exécutez cette commande que si vous souhaitez réinitialiser volontairement le
fichier d'historique.

### Protection de l'interface

L'interface PHP peut modifier les relations GitHub et ne contient pas de système
de connexion intégré. Placez-la derrière une authentification du serveur web ou
du proxy inverse et limitez son accès aux personnes autorisées. Utilisez
`DRY-RUN` pour contrôler les cibles avant une action réelle.

## Tests

### Bash

La suite hors ligne intercepte toutes les requêtes HTTP. Elle ne suit et ne
retire aucun compte réel.

```bash
bash -n KuzFollow.sh
bash -n tests/test_ui.sh
bash tests/test_ui.sh
```

Elle couvre notamment la pagination, les erreurs API, les rapports non
interactifs, les largeurs de terminal, le logo ASCII, les couleurs, le menu, les
annulations et les confirmations.

### PHP

Vérification de syntaxe des fichiers PHP :

```bash
php -l public/index.php
php -l public/graph.svg.php
php -l public/seed_history.php
php -l src/GitHubClient.php
```

Les tests fonctionnels PHP nécessitent un fichier de configuration valide, un
accès réseau à l'API GitHub et un dossier `data/` accessible en écriture.

## Structure du dépôt

```text
KuzFollow.sh                 Application terminal Bash
tests/test_ui.sh             Tests hors ligne de la version Bash
public/index.php             Tableau de bord web PHP
public/graph.svg.php         Graphique SVG de l'historique
public/seed_history.php      Initialisation facultative de l'historique
public/assets/style.css      Design de l'interface web
public/assets/bg.jpg         Image de fond de l'interface web
public/.htaccess             Réécriture des routes Apache
src/GitHubClient.php         Client de l'API GitHub pour PHP
data/followers_history.json  Historique utilisé par le graphique
```

Les fichiers `.bk` présents dans le dépôt sont des copies historiques de
certains fichiers PHP, CSS et de données.
