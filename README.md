# Noho Conciergerie

Boutique Sylius pour la location de propriétés haut de gamme (catalogue, réservation, paiements). Le projet repose sur **Sylius 2.x**, **Symfony** et **Doctrine**.

## Prérequis

- PHP **8.4+** (contrainte Composer du projet) avec les extensions Symfony usuelles (`intl`, `pdo_mysql` ou `pdo_pgsql`, etc.)
- Composer 2
- Node.js **20+** et npm (ou yarn) pour Webpack Encore
- Une base **MySQL 8** ou **PostgreSQL** (selon votre `DATABASE_URL`)

## Installation

1. **Cloner et installer les dépendances PHP**

   ```bash
   composer install
   ```

2. **Variables d’environnement**

   Copier `.env` vers `.env.local` et renseigner au minimum `DATABASE_URL` et `APP_SECRET`.

3. **Schéma de base de données**

   ```bash
   php bin/console doctrine:database:create --if-not-exists
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

4. **Assets front (admin + boutique)**

   ```bash
   npm install
   npm run build
   ```

   En développement : `npm run watch`.

5. **Données de démo (suite `noho`)**

   ```bash
   php bin/console sylius:fixtures:load noho -n
   ```

   Cette commande purge la base (ORM) puis charge taxons, canal, produits, images, etc.

## Comptes de démonstration

À utiliser **uniquement en développement / démo**. Changez ces mots de passe en production.

| Contexte | URL (exemple local) | Identifiant | Mot de passe |
|----------|---------------------|-------------|--------------|
| **Back-office (BO)** | `/admin` | **Email ou identifiant :** `admin@noho-conciergerie.com` ou `admin` | `noho2024` |
| **Front-office (FO)** | `/` puis lien « Connexion » | **Email :** `customer@noho-conciergerie.com` | `noho2024` |

Les comptes sont définis dans `config/packages/sylius_fixtures.yaml` (fixtures `admin_user` et `shop_user`).

## Commandes utiles

| Action | Commande |
|--------|----------|
| Serveur Symfony local | `symfony server:start` ou `php -S localhost:8000 -t public` |
| Vider le cache | `php bin/console cache:clear` |
| Traductions | `php bin/console translation:extract` (selon besoin) |

## Structure notable

- `config/packages/sylius_fixtures.yaml` — suite de fixtures **noho** (canal `NOHO_WEB`, propriétés, taxons, comptes démo).
- `templates/bundles/SyliusShopBundle/` — surcouches Twig boutique.
- `assets/shop/` — JS/CSS boutique (Encore + overrides).
- `src/` — code métier et fixtures personnalisées (ex. images de taxons).

## Documentation Sylius

[docs.sylius.com](https://docs.sylius.com)

## Licence

Le cœur **Sylius** est sous licence MIT. Voir les fichiers `LICENSE` du dépôt Sylius et les licences des dépendances du projet.
