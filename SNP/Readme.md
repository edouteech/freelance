## SNPI API

Centralise tous les besoins métiers du SNPI

### Technologies

- Symfony 4.4
- Doctrine 2
- PHPUnit
- Symfony Server
- Swiftmailer

### CRM

Le CRM intégré par l'api est Eudonet :
- Lexique : http://snpi.eudonet.com/EudoAPI/eudoapidoc/lexique.html
- Swaggeur : http://snpi.eudonet.com/EudoAPI/eudoapidoc/swaggerui/

Le CRM est disponible via une API, des services ont été developpés pour interagir plus rapidement à la maniere de Doctrine, voir `/src/Service/Eudonet`

Exemple : 

```php
$qb = $this->eudonetConnector->createQueryBuilder()
     	->select('id, contact_id')
     	->from('address', 'a')
     	->where('a.email', '=', $email)
     	->andWhere('a.is_home', '=', $is_home)
     	->andWhere('a.is_active', '=', true);
     
return $this->eudonetConnector->execute($qb);
```

### Base de donnée

Utilisation de MySql/MariaDB via Doctrine

#### Tables gérées par le CRM Eudonet

Les tables suivantes sont un cache du CRM, tous les champs ne sont pas importés.

- **Commande** : `php bin/console app:sync:eudonet`
- **Fichier** : `src/Command/SyncEudonetCommand.php`

Liste des tables

- **address** : Adresse postale, email, téléphone. Lie un contact à une société
- **appendix** : Documents administratifs
- **company** : liste des sociétés affiliées ou non au SNPI
- **company_business_card** : Informations sur la carte pro
- **company_representative** : Informations du representant légale d'une société
- **contact** : liste des personnes affiliées au SNPI
- **formation** : Liste les formations
- **formation_course** : Liste les sessions de formation
- **formation_participant** : Liste les participants a une session de formation
- **formation_price** : Prix des formations
- **mail** : Adresse postale d'une société

#### Tables gérées par le CMS

Les tables suivantes sont un cache du CMS, tous les champs ne sont pas importés.

- **Commande** : `php bin/console app:sync:cms`
- **Fichier** : `src/Command/SyncCMSCommand.php`

Liste des tables

- **document** : Documents
- **document_asset** : Fichiers liés à un document
- **menu** : Entrées du menu
- **news** : Actualités
- **option** : Ensemble d'options modifiable via le CMS
- **page** : Pages
- **resource** : Entité abstraite qui englobe document/page/news
- **resource_term** : Table de jointure entre une ressource et une catégorie
- **term** : Catégories

#### Tables gérées par Symfony

- **download** : Lien unique de téléchargement de ressource
- **eudo_entity_metadata** : Informations additionelles liées aux tables du CRM
- **external_formation** : Sessions de formations externes au SNPI d'un contact
- **formation_interest** : 
- **migration_versions** : Table systeme 
- **order** : Commandes
- **order_detail** : Produits d'une commande 
- **order_detail_contact** : Contacts associés a un produit d'une commande 
- **payment** : Status des paiements
- **payment_token** : Tokens de paiement 
- **poll** : Sondage
- **registration** : Etape d'inscription
- **role** : Roles
- **signatory** : Signataire ( voir Contralia )
- **signature** : Signature
- **survey** : Sondage
- **survey_answer** : Réponses possibles d'un sondage
- **survey_comment** : Commentaires d'un sondage
- **survey_question** : Questions d'un sondage
- **survey_question_group** : Groupe de question d'un sondage
- **sync** : Detail d'une syncronisation ( via les commandes SF )
- **user** : Utilisateur SF
- **user_auth_token** : Token d'authentification
- **user_metadata** : Données additionels d'un utilisateur
- **user_role** : Jointure entre un utilisateur et ses roles

### Auto documentation

L'api est auto-documentée via les commentaires PHP grace au bundle [NelmioApiDoc](https://symfony.com/doc/4.x/bundles/NelmioApiDocBundle/index.html)

Pour acceder à la documentation : 
- `/swagger/doc`
- `/swagger/doc.json`

### Gestion des utilisateurs

Les contacts et le sociétés peuvent se connecter à l'api via un email/mot de passe, stocké sur le CRM.
Lors de la premiere connexion un utilisateur symfony est crée.

La connexion est maintenue via un *Bearer Token*

### Google drive

[google drive api](https://developers.google.com/drive/api/v3/about-sdk) est utilisé pour faire des interactions avec l'application et google drive.

#### Pour recuperer le fichier token.json suivre les étapes suivantes:

#### 1er étape:

- Activer google drive api avec la boutton "Enable Drive API" qui existe dans la docs

#### 2em étape:

- Remplir les informations nécessaire pour la configuration OAuth

#### 3em étape:

- Télécharger le fichier credentials.json et mettre le dans le dossier config/google-api
- Créer un ID client OAuth de type "Application de bureau"

#### Dernière étape:

- Executer la commande "php bin/console app:generate-google-api-token", elle retourne une url
- Accédez à l'URL fournie dans votre navigateur Web
- Accepter et copiez le code qui vous est donné, collez-le dans l'invite de ligne de commande et appuyez sur entrée

### Commandes de development

#### Create/update entity

``php bin/console make:entity``

#### Make migration 

``php bin/console make:migration``

#### Run migration

``php bin/console doctrine:migrations:migrate``

#### Prune cache

``php bin/console cache:pool:prune``

### Cron tasks

#### Heartbeat

##### Command

``php bin/console app:heartbeat``

#### Eudonet synchronisation

##### Command

``php bin/console app:sync:eudonet ?scratch ?table ?condition``

##### Examples

``php bin/console app:sync:eudonet``

``php bin/console app:sync:eudonet 1``

``php bin/console app:sync:eudonet 1 address``

``php bin/console app:sync:eudonet 1 address "a.expire_at != ''``

#### CMS synchronisation

##### Command

``php bin/console app:cms:cms ?scratch``

##### Examples

``php bin/console app:sync:cms 1``

#### Database cleanup from date

```sql
set @date = '2020-10-20 12:00:00';
delete from `formation_participant` where created_at > @date;
delete from `formation_price` where created_at > @date;
delete from `formation_course` where created_at > @date;
delete from `formation` where created_at > @date;
delete from `company_representative` where created_at > @date;
delete from `mail` where created_at > @date;
delete from `order_detail_contact` where 1;
delete from `order_detail` where 1;
delete from `appendix` where 1;
delete from `payment` where 1;
delete from `external_formation` where 1;
delete from `order` where 1;
delete from `payment_token` where 1;
delete from `user_auth_token` where 1;
delete from `user_role` where 1;
delete from `user_metadata` where 1;
delete from `user` where 1;
delete from `address` where created_at > @date;
delete from `contact` where created_at > @date;
delete from `company_business_card` where created_at > @date;
delete from `company` where created_at > @date;
```

#### Database cleanup

```sql
delete from `user_auth_token` where 1;
delete from `user_role` where 1;
delete from `user` where 1;
```

#### User cleanup

```sql
select u.* from user u left join contact c on u.contact_id=c.id where u.login like "%@%" and u.contact_id is not null and u.type= "contact" and c.status = 'member';
update user u left join contact c on u.contact_id=c.id set u.login = c.member_id where u.login like "%@%" and u.contact_id is not null and u.type= "contact" and c.status = 'member';
```

#### Database CMS cleanup

```sql
delete from `resource_term` where 1;
delete from `document_asset` where 1;
delete from `document` where 1;
delete from `resource` where 1;
delete from `term` where 1;
delete from `menu` where 1;
```