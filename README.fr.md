# MailPilot

[English](README.md) | **Français**

Application web **locale** (127.0.0.1, un seul utilisateur, sans authentification) pour :

1. importer une base d'entreprises, de n'importe quel pays (CSV / XLSX, jusqu'à 100 000 lignes) ;
2. nettoyer et vérifier les adresses email (syntaxe, MX, domaines jetables, adresses de rôle) ;
3. envoyer une offre de services **par vagues contrôlées** (quotas, créneaux, délais aléatoires, disjoncteurs) ;
4. traiter automatiquement les rebonds, désinscriptions et réponses reçus par IMAP.

Interface en **français et en anglais**, emails en français ou en anglais selon le template, fuseau horaire détecté
au premier lancement. Des règles propres à l'Algérie (wilayas, numéros de téléphone) existent en option.

> **Version de Laravel** : le projet utilise **Laravel 12**. Toutes les versions 11.x sont bloquées par Composer à
> cause d'avis de sécurité non corrigés (dont une injection CRLF dans la règle de validation `email`).

---

## Sommaire

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Démarrage](#démarrage)
- [Premier paramétrage](#premier-paramétrage)
- [Langues et préférences régionales](#langues-et-préférences-régionales)
- [Fonctionnement](#fonctionnement)
- [Configuration DNS du domaine d'envoi (SPF, DKIM, DMARC)](#configuration-dns-du-domaine-denvoi-spf-dkim-dmarc)
- [Montée en charge conseillée](#montée-en-charge-conseillée)
- [Sauvegarde](#sauvegarde)
- [Utiliser MySQL au lieu de SQLite](#utiliser-mysql-au-lieu-de-sqlite)
- [Tests](#tests)
- [Dépannage](#dépannage)
- [Organisation du code](#organisation-du-code)
- [Licence](#licence)

---

## Prérequis

| Outil | Version |
|---|---|
| PHP | 8.3 (extensions : `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `zip`, `xml`, `gd`, `fileinfo`, `intl` conseillée) |
| Composer | 2.x |
| Node.js / npm | Node 18 ou plus (uniquement pour compiler l'interface) |

L'extension PHP `imap` **n'est pas nécessaire** : la relève IMAP utilise `webklex/php-imap`, qui communique
directement avec le serveur.

## Installation

```bash
# 1. Dépendances PHP
composer install

# 2. Fichier d'environnement et clé de chiffrement
cp .env.example .env            # Windows : copy .env.example .env
php artisan key:generate

# 3. Base SQLite, tables et templates d'exemple (français et anglais)
touch database/database.sqlite  # Windows : type nul > database\database.sqlite
php artisan migrate --seed

# 4. Interface (Tailwind via Vite)
npm install
npm run build
```

Les scripts `start.sh` / `start.bat` font les étapes 2 à 4 automatiquement s'ils détectent une première installation,
et règlent `APP_TIMEZONE` sur le fuseau horaire de votre ordinateur (`php artisan mailpilot:configure-env`). Seul
`composer install` doit être lancé à la main. Après une installation manuelle, lancez cette commande vous-même ou
renseignez `APP_TIMEZONE` (ex. `Europe/Paris`, `America/New_York`, `Africa/Algiers`) dans `.env`.

## Démarrage

| Système | Commande |
|---|---|
| macOS | double-clic sur `MailPilot.command` dans le Finder (ou `./start.sh` dans le Terminal) ; Ctrl+C ou fermer la fenêtre arrête tout |
| Linux | `./start.sh` (Ctrl+C arrête tout) |
| Windows | double-clic sur `start.bat` (trois fenêtres s'ouvrent ; fermez-les pour arrêter) |

Les scripts lancent en parallèle :

- `php artisan serve --host=127.0.0.1` : l'interface, sur <http://127.0.0.1:8000>, ou sur le premier port libre
  suivant si 8000 est déjà pris (port de départ réglable avec la variable d'environnement `MAILPILOT_PORT`) ;
- `php artisan queue:work --queue=sends,default` : les tâches de fond (import, vérification, envois, IMAP) ;
- `php artisan schedule:work` : le planificateur (envois chaque minute, relève IMAP toutes les 15 minutes).

Ils chargent aussi `php/mailpilot.ini` (upload de 64 Mo, 512 Mo de mémoire) via `PHP_INI_SCAN_DIR`, sans modifier
votre installation de PHP.

> **Les trois processus sont indispensables.** Sans le worker, les imports restent « En attente » ; sans le
> planificateur, aucune campagne n'envoie.

L'application refuse toute requête qui ne vient pas de la machine locale (middleware `EnsureLocalRequest`).

## Premier paramétrage

1. **Paramètres** : renseignez la raison sociale, l'adresse et le téléphone de l'expéditeur. Ils apparaissent dans le
   pied de page légal ajouté automatiquement à chaque email, avec le lien de désinscription.
2. **Comptes SMTP** : ajoutez un compte d'envoi, puis cliquez sur « Tester la connexion SMTP ». Renseignez aussi la
   boîte **IMAP** et testez-la : elle reçoit les rebonds, désinscriptions et réponses, et son adresse sert de lien de
   désinscription (`mailto`). Les mots de passe sont chiffrés en base avec la clé `APP_KEY`.
3. **Templates** : adaptez un template d'exemple (un en français, un en anglais). Variables disponibles, dans les deux
   langues : `{{entreprise}}` ou `{{company}}`, `{{secteur}}` ou `{{sector}}`, `{{region}}` (ou `{{wilaya}}`),
   `{{email}}`. L'aperçu se fait sur un vrai contact et le bouton « Envoyer un test » envoie le rendu à l'adresse de
   votre choix.
4. **Imports** : choisissez un fichier, vérifiez la correspondance des colonnes (devinée à partir des en-têtes), puis
   lancez l'import. La vérification des emails démarre automatiquement à la fin de l'import.
5. **Campagnes** : l'assistant en 3 étapes définit le segment (avec le compteur de contacts éligibles), le template et
   le compte, puis le planning et les quotas.

### Langues et préférences régionales

- **Interface** : boutons **FR / EN** en bas de la barre latérale (anglais par défaut sur une nouvelle installation).
  Le choix est enregistré et s'applique aussi aux messages produits en arrière-plan (motifs de vérification, arrêts de
  campagne). Les traductions sont dans `lang/en.json`, avec le texte français comme clé ; un test vérifie qu'aucun
  texte n'en manque.
- **Emails** : chaque template a sa **langue** (français ou anglais). Elle fixe la langue du pied de page légal, du
  lien de désinscription et des valeurs par défaut des variables (« votre entreprise » / « your company »).
- **Paramètres → Préférences régionales** :
  - jours d'envoi proposés pour une nouvelle campagne (lundi–vendredi par défaut, dimanche–jeudi en Algérie…) ;
  - **harmonisation des wilayas algériennes** à l'import (désactivée par défaut) : reconnaît les 58 wilayas, leurs
    codes (« 16 » → Alger) et leurs variantes d'écriture. Désactivée, la région est importée telle quelle ;
  - **rétablissement du 0 initial** des numéros à 9 chiffres supprimé par Excel (désactivé par défaut ; utile pour
    l'Algérie, le Maroc, la Tunisie, la France…).
- **Fuseau horaire** : `APP_TIMEZONE` dans `.env`, détecté au premier lancement. Il sert aux créneaux d'envoi et à
  l'affichage des dates. Changez-le de préférence avant les premiers envois : les dates déjà enregistrées ne sont pas
  converties.

## Fonctionnement

### Import et nettoyage

- Lecture par blocs de 5 000 lignes en tâche de fond (`WithChunkReading` + `ShouldQueue`). Pour un classeur Excel,
  seule la première feuille est importée.
- Détection automatique du séparateur CSV (`;` `,` tabulation `|`) et de l'encodage (UTF-8 ou Windows-1252).
- Emails passés en minuscules et sans espaces. Une cellule contenant plusieurs adresses (séparées par `;` `,` `/`,
  espace ou retour à la ligne) produit plusieurs contacts avec les mêmes informations d'entreprise.
- Dédoublonnage par email, dans le fichier comme dans la base.
- La région est importée telle quelle. En option (Préférences régionales), les 58 wilayas algériennes sont
  normalisées : « ALGER », « 16 », « 16 - Alger », « Wilaya de Tizi-Ouzou », « Bejaia », « Bougie »… donnent toutes
  le nom officiel.
- En option, le 0 initial des numéros de téléphone à 9 chiffres supprimé par Excel est restauré.
- Fautes de frappe évidentes corrigées : `gmail,com`, `@gmail`, `gmial.com`, `hotmial.fr`…
- Un email déjà en base n'est pas recréé, mais ses champs vides (raison sociale, secteur, région…) sont complétés.
- Une adresse déjà présente dans la liste noire est importée avec le statut correspondant (désinscrit, rebond, exclu).
- Le rapport indique les lignes lues, les contacts importés, les rejets (email absent ou invalide) et les doublons.

### Vérification des emails

Traitement par lots de 200 contacts :

| Contrôle | Résultat |
|---|---|
| Syntaxe (`filter_var` + expression régulière) | invalide |
| Domaine jetable (`resources/data/disposable_domains.txt`, sous-domaines inclus) | invalide |
| Domaine sans enregistrement MX | invalide |
| Adresse de rôle (`noreply`, `no-reply`, `postmaster`, `abuse`, `mailer-daemon`) | à risque |
| Sinon | valide |

Le résultat DNS de chaque domaine est conservé dans `domain_cache` : **aucune nouvelle requête DNS n'est faite pour
un domaine vérifié il y a moins de 30 jours**.

### Contacts éligibles

Une campagne ne cible que les contacts qui sont à la fois :

- vérifiés **valides** ;
- au statut **actif** ;
- **absents** de la liste noire ;
- **non contactés** depuis 60 jours.

Les envois sont créés au démarrage de la campagne. L'éligibilité est contrôlée une seconde fois juste avant chaque
envoi : un contact désinscrit entre-temps est ignoré.

### Moteur d'envoi

Chaque minute, la commande `mailpilot:dispatch` traite chaque campagne en cours :

1. elle vérifie le jour et l'heure d'envoi, dans le fuseau horaire de l'application (`APP_TIMEZONE`) ;
2. elle calcule les envois autorisés : le plus petit des quotas horaire et journalier de la campagne et de son compte
   SMTP. Le quota horaire est une fenêtre glissante de 60 minutes, et le quota du compte s'applique à toutes ses
   campagnes cumulées ;
3. elle place les jobs dans la file en les espaçant d'un **délai aléatoire** entre `min_delay` et `max_delay`
   secondes.

Chaque job envoie **un email à un seul destinataire** :

- rendu des variables et ajout du pied de page légal ;
- en-têtes `List-Unsubscribe` (mailto vers la boîte IMAP) et `Message-ID` propre, conservé pour reconnaître les
  réponses ;
- journalisation du résultat dans `campaign_sends` ;
- mise à jour de `last_contacted_at`.

Un refus définitif du destinataire (`550 User unknown`) est traité comme un rebond : le contact passe en « Rebond »
et l'adresse est ajoutée à la liste noire. Une erreur temporaire (4xx, connexion) est retentée, jusqu'à 3 tentatives.

### Absence de double envoi et reprise après fermeture

- **Verrou** : à la planification, chaque envoi reçoit un jeton unique. Un job ne peut envoyer que s'il réussit une
  mise à jour atomique `planifié → en cours` avec ce jeton. Exécuter deux fois le même job, ou un job périmé,
  n'envoie donc rien.
- **Reprise** : les jobs sont stockés en base (`jobs`). Après une fermeture de l'application, ceux qui s'exécutent
  avec plus de 5 minutes de retard ne partent pas en rafale : ils remettent leur envoi en file, qui sera replanifié
  avec l'espacement normal. Un job perdu (plus de 15 minutes sans nouvelles) est replanifié avec un nouveau jeton,
  et l'ancien devient inopérant.
- **Envoi interrompu** : un envoi coupé en pleine session SMTP n'est **jamais renvoyé**, puisqu'on ne peut pas savoir
  si le serveur l'a accepté. Il est marqué en échec avec une explication.

### Disjoncteurs

Une campagne passe automatiquement en « Arrêtée », avec la raison affichée, si :

- le **taux de rebond dépasse 5 %**, à partir de 50 envois ;
- **10 échecs SMTP se suivent** (compte bloqué, mot de passe changé, serveur indisponible…).

Après avoir corrigé la cause, le bouton « Reprendre » relance la campagne. Si le taux de rebond dépasse toujours le
seuil, elle est de nouveau arrêtée.

### Relève IMAP (toutes les 15 minutes)

Les messages sont lus **sans être marqués comme lus** : MailPilot retient le dernier UID traité, et vous retrouvez
donc les réponses de vos prospects dans votre messagerie habituelle. Le premier relevé couvre les 14 derniers jours.

| Message détecté | Action |
|---|---|
| Rebond (DSN, `mailer-daemon`, `postmaster`, `X-Failed-Recipients`…) | contact « Rebond », adresse en liste noire, envoi marqué rebond, disjoncteur réévalué |
| Désinscription (« désinscription », « unsubscribe » ou « STOP » dans le sujet ou le texte écrit par l'expéditeur) | contact « Désinscrit », adresse en liste noire |
| Réponse à une campagne (`In-Reply-To` / `References`, ou expéditeur déjà contacté) | `replied_at` renseigné sur le contact et l'envoi |

Le texte cité d'un email d'origine (lignes `>`, « Le … a écrit : ») est ignoré. Sans cette précaution, une simple
réponse qui cite le pied de page (qui contient « STOP » et « Désinscription ») serait prise pour une désinscription.

## Configuration DNS du domaine d'envoi (SPF, DKIM, DMARC)

Sans ces trois enregistrements, vos emails finiront majoritairement en spam, voire seront refusés. Configurez-les
dans la zone DNS du domaine utilisé dans l'adresse d'expéditeur (exemple : `votre-domaine.com`).

### SPF : serveurs autorisés à envoyer pour votre domaine

Un seul enregistrement TXT à la racine du domaine :

```
votre-domaine.com.   TXT   "v=spf1 include:_spf.votre-hebergeur.com ~all"
```

- Remplacez `include:` par la valeur fournie par votre hébergeur email (ou `ip4:1.2.3.4` pour un serveur dédié).
- **Un seul** enregistrement SPF par domaine : fusionnez les `include` si vous utilisez plusieurs services.
- `~all` (échec souple) pour démarrer, puis `-all` une fois la configuration validée.

### DKIM : signature cryptographique des messages

La signature est faite par **votre serveur SMTP**, pas par MailPilot. Activez DKIM dans le panneau de votre
hébergeur, qui vous donne un enregistrement à publier, par exemple :

```
mail._domainkey.votre-domaine.com.   TXT   "v=DKIM1; k=rsa; p=MIIBIjANBgkqh..."
```

### DMARC : politique en cas d'échec SPF/DKIM et rapports

```
_dmarc.votre-domaine.com.   TXT   "v=DMARC1; p=none; rua=mailto:dmarc@votre-domaine.com; adkim=r; aspf=r"
```

Commencez par `p=none` (observation) pendant 2 à 4 semaines, analysez les rapports, puis passez à `p=quarantine`,
et enfin à `p=reject`.

### Vérifications

- Envoyez un test (bouton « Envoyer un test » du template) vers <https://www.mail-tester.com> et visez un score
  d'au moins 9/10.
- Dans Gmail, « Afficher l'original » doit indiquer `SPF: PASS`, `DKIM: PASS` et `DMARC: PASS`.
- Contrôlez que l'IP du serveur SMTP n'est pas sur liste noire (<https://mxtoolbox.com/blacklists.aspx>) et qu'elle
  dispose d'un reverse DNS (PTR) cohérent.
- Conseil : utilisez un **sous-domaine ou un domaine dédié** à la prospection (ex. `contact-votre-domaine.com`) pour
  protéger la réputation de votre domaine principal.

## Montée en charge conseillée

Un domaine ou une adresse qui envoie soudainement beaucoup d'emails est vite classé comme spammeur. Augmentez les
quotas **par compte SMTP** progressivement, et seulement si le taux de rebond reste sous 2 % et que les plaintes sont
quasi nulles :

| Période | Quota journalier par compte | Quota horaire conseillé |
|---|---|---|
| Semaine 1 | **30 / jour** | 5 / h |
| Semaine 2 | 50 / jour | 8 / h |
| Semaine 3 | 80 / jour | 12 / h |
| Semaine 4 | 120 / jour | 18 / h |
| Semaine 5 | 160 / jour | 22 / h |
| Semaine 6 et suivantes | **200 / jour maximum** | 25 / h |

Autres bonnes pratiques :

- gardez un délai de 60 à 180 secondes entre deux emails ;
- envoyez pendant les heures de bureau et les jours ouvrés de vos destinataires ;
- vérifiez la base avant chaque campagne ;
- pour aller au-delà de 200/jour, ajoutez des comptes et des domaines plutôt que d'augmenter un seul compte.

## Sauvegarde

Toutes les données sont dans **`database/database.sqlite`**. La base fonctionne en mode WAL : des fichiers
`database.sqlite-wal` et `database.sqlite-shm` peuvent exister à côté.

**Méthode simple** : arrêtez MailPilot, puis copiez `database/database.sqlite` (ainsi que les fichiers `-wal` et `-shm`
s'ils existent) vers votre support de sauvegarde.

**À chaud**, application démarrée, avec l'outil `sqlite3` :

```bash
sqlite3 database/database.sqlite ".backup 'sauvegardes/mailpilot-$(date +%Y-%m-%d).sqlite'"
```

**Sauvegardez aussi le fichier `.env`.** Il contient `APP_KEY`, qui chiffre les mots de passe SMTP et IMAP : sans
cette clé, une sauvegarde de la base reste lisible, mais les mots de passe des comptes devront être ressaisis.

Pour restaurer : arrêtez l'application, remplacez `database/database.sqlite` (supprimez les éventuels `-wal`/`-shm`),
puis relancez. La commande `php artisan migrate` du script de démarrage met le schéma à jour si besoin.

## Utiliser MySQL au lieu de SQLite

Dans `.env` :

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mailpilot
DB_USERNAME=root
DB_PASSWORD=
```

Puis `php artisan migrate --seed`. MySQL 8 ou plus est requis (la liste des tags utilise `JSON_TABLE`).

## Tests

```bash
php artisan test
```

Les tests Pest (`tests/Feature`) couvrent notamment les quatre points demandés :

- **Quotas horaires** (`CampaignEngineTest`) : quota de la campagne, quota du compte partagé entre campagnes,
  fenêtre glissante, quota journalier, espacement aléatoire, créneaux d'envoi.
- **Exclusion des contacts supprimés** (`CampaignEngineTest`) : à l'activation (liste noire, non valides, inactifs,
  contactés depuis moins de 60 jours) et juste avant l'envoi.
- **Absence de double envoi à la reprise** (`CampaignEngineTest`) : jobs exécutés plusieurs fois, application fermée
  deux heures avec un envoi coupé en pleine session SMTP, jobs en retard.
- **Cache MX** (`EmailVerificationTest`) : une seule requête DNS par domaine, aucune nouvelle requête avant
  30 jours, rafraîchissement ensuite.

Ils couvrent aussi l'import (CSV, XLSX, multi-emails, régions, wilayas en option), les deux langues, les disjoncteurs, la classification IMAP, les
templates, les comptes SMTP, les campagnes, le tableau de bord et les paramètres.

## Dépannage

| Symptôme | Cause probable |
|---|---|
| Import bloqué « En attente » ou « En cours » | Le worker (`queue:work`) ne tourne pas. `start.sh` / `start.bat` le relancent automatiquement s'il s'arrête ; si vous le lancez à la main, ajoutez `--memory=448` (sans cela il s'arrête après 128 Mo, dès le premier gros fichier Excel). |
| Campagne « En cours » mais rien ne part | Le planificateur (`schedule:work`) ne tourne pas, ou vous êtes hors des jours / heures d'envoi, ou le quota du jour est atteint (voir la fiche campagne). |
| « Le fichier ne doit pas dépasser… » / erreur d'upload | Lancez l'application avec `start.sh` / `start.bat`, qui chargent `php/mailpilot.ini`. |
| Tous les emails « Invalide – Domaine sans MX » | La machine n'a pas d'accès DNS (hors ligne, pare-feu). |
| Heures d'envoi décalées de quelques heures | `APP_TIMEZONE` dans `.env` ne correspond pas à votre fuseau horaire. |
| Mots de passe SMTP illisibles après restauration | Le fichier `.env` (clé `APP_KEY`) n'a pas été restauré avec la base. |
| Import lent sous PHP 8.5 | `phpoffice/phpspreadsheet` 1.x émet un avertissement de dépréciation par cellule sous PHP 8.5. La version cible est PHP 8.3, où ce coût n'existe pas. |

Les journaux sont dans `storage/logs/laravel.log`.

## Organisation du code

```
app/
├── Console/Commands/DispatchCampaignSends.php   commande planifiée chaque minute
├── Console/Commands/ConfigureEnvironment.php    fuseau horaire détecté au premier lancement
├── Contracts/                                   DnsResolver, MailTransportFactory (remplaçables en test)
├── Http/Middleware/                             EnsureLocalRequest (accès réservé à 127.0.0.1), SetLocale
├── Imports/ContactsImport.php                   import maatwebsite/excel par blocs
├── Jobs/                                        VerifyContactsJob, SendCampaignEmailJob, ProcessMailboxesJob
├── Livewire/                                    écrans (tableau de bord, contacts, imports, templates, SMTP, campagnes, paramètres)
├── Models/                                      Contact, Import, Template, SmtpAccount, Campaign, CampaignSend, Suppression, DomainCache, Wilaya, Setting
├── Services/
│   ├── Campaigns/                               audience, quotas, créneaux, planification, disjoncteurs, statistiques
│   ├── Inbox/                                   relève IMAP et classification des messages reçus
│   ├── Mail/                                    rendu des templates, envoi Symfony Mailer, client IMAP
│   ├── ContactImporter.php                      nettoyage et dédoublonnage à l'import
│   └── EmailVerifier.php                        vérification + cache MX
└── Support/                                     EmailParser, WilayaNormalizer, ContactFilter, Locale
lang/en.json                                     traductions anglaises (clé = texte français)
config/mailpilot.php                             seuils et réglages métier (60 jours, 30 jours, 5 %, 10 échecs…)
resources/data/disposable_domains.txt            domaines jetables (modifiable)
```

## Licence

MailPilot est un logiciel libre distribué sous licence [MIT](LICENSE) : vous pouvez l'utiliser, le modifier et le
redistribuer librement, y compris à des fins commerciales, à condition de conserver la mention de copyright et le texte
de la licence dans les copies du code.

Copyright (c) 2026 Habib Belayachi.

Utilisez-le dans le respect des lois de votre pays et de celui de vos destinataires sur la prospection par email et la
protection des données personnelles (par exemple le RGPD dans l'Union européenne, CAN-SPAM aux États-Unis, la loi
n° 18-07 en Algérie) : identité de l'expéditeur, désinscription honorée, adresses professionnelles uniquement. Les
règles varient selon les pays, en particulier sur le consentement préalable : renseignez-vous avant d'envoyer.
