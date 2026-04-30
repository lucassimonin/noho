EXEC_PHP = docker compose exec php
SYMFONY = $(EXEC_PHP) php bin/console
YARN = $(EXEC_PHP) yarn

.PHONY: help start stop open shell install db-diff db-migrate db-fixture reset-db reset-test consume watch reset-test tests test-unit test-func qa fix cs-check rector-check stan deploy

## ——— PROJECT ———
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

start: ## Start the Docker containers
	docker compose up -d --remove-orphans

stop: ## Stop the Docker containers
		docker compose stop

open:
	open http://localhost

shell: ## Enter the PHP container
	$(EXEC_PHP) bash

install: start ## Install the entire project (Vendor + Dev database + Test database)
	$(EXEC_PHP) composer install
	$(SYMFONY) sylius:install --no-interaction
	@$(MAKE) build-assets
	@$(MAKE) reset-test
	@echo "✅ Projet installé et prêt !"

## ——— BDD ———

db-diff: ## Generates a new migration by comparing Entities <-> Database
	$(SYMFONY) doctrine:migrations:diff --no-interaction

db-migrate: ## Apply pending migrations to the database
	$(SYMFONY) doctrine:migrations:migrate --no-interaction

db-fixture: ## Load fixtures
	$(SYMFONY) sylius:fixtures:load noho --no-interaction

## ——— BDD (DEV) ———
reset-db: ## Complete reset of the Dev database (Drop -> Create -> Migrate)
	$(SYMFONY) doctrine:database:drop --force --if-exists
	$(SYMFONY) doctrine:database:create
	$(SYMFONY) doctrine:migrations:migrate --no-interaction
	@$(MAKE) db-fixture

## ——— Messenger ———
consume: ## Cosume message async
	$(SYMFONY) messenger:consume async

## ——— FRONTEND———

build-assets: ## Build all frontend assets (Encore + Tailwind)
	$(YARN) install --pure-lockfile
	$(YARN) encore production
	@$(MAKE) build-tailwind

build-tailwind: ## Build Tailwind CSS
	$(SYMFONY) tailwind:build
	$(EXEC_PHP) mkdir -p /app/public/css
	$(EXEC_PHP) cp /app/var/tailwind/tailwind.built.css /app/public/css/tailwind.css

watch-tailwind: ## Watch Tailwind CSS for changes
	$(SYMFONY) tailwind:build --watch

## ——— TESTS ———

reset-test: ## Reset the Test database (Drop -> Create -> Schema)
	$(SYMFONY) doctrine:database:drop --env=test --force --if-exists -n
	$(SYMFONY) doctrine:database:create --env=test -n
	$(SYMFONY) doctrine:schema:create --env=test -n
	$(SYMFONY) sylius:fixtures:load --env=test -n
	$(SYMFONY) cache:clear --env=test
	$(SYMFONY) doctrine:cache:clear-metadata --env=test

tests: reset-test test-unit test-func ## Run the tests

test-unit: reset-test ## Run only unit tests
	$(EXEC_PHP) sh -c 'APP_ENV=test bin/phpunit --testsuite=unit'

test-func: reset-test ## Runs only functional tests
	$(EXEC_PHP) sh -c 'APP_ENV=test bin/phpunit --testsuite=functional'

## ——— QUALITY ———

qa: cs-check rector-check stan ## Launch all quality tools (CS, Stan, REctor)

fix: .php-cs-fixer.php ## Corrects style (CS-Fixer) and quality (Rector)
	$(EXEC_PHP) ./vendor/bin/rector process
	$(EXEC_PHP) ./vendor/bin/php-cs-fixer fix

cs-check: .php-cs-fixer.php ## Check the style without modifying it (for CI)
	$(EXEC_PHP) ./vendor/bin/php-cs-fixer fix --dry-run --diff

rector-check: ## Check quality without modifying it (for CI)
	$(EXEC_PHP) ./vendor/bin/rector process --dry-run

stan: ## Launch the PHPStan static analysis
	$(EXEC_PHP) ./vendor/bin/phpstan analyse --memory-limit=1G

.php-cs-fixer.php: .php-cs-fixer.dist.php
	cp .php-cs-fixer.dist.php .php-cs-fixer.php

## ——— DEPLOY ———
deploy:
	@echo "🚀 Démarrage du déploiement de Noho..."

	echo "🛑 Arrêt des workers..."
    # On arrête Supervisor pour être sûr que rien ne tourne pendant la mise à jour
	#sudo supervisorctl stop noho-worker:*

	@echo "📥 Récupération du code..."
	git pull origin main

	@echo "⚙️ Installation des dépendances (Production)..."
	$(EXEC_PHP) composer install --no-dev --optimize-autoloader --no-interaction

	@echo "🎨 Compilation des assets (Tailwind & AssetMapper)..."
	$(MAKE) build-assets
	$(SYMFONY) tailwind:build --minify
	$(SYMFONY) asset-map:compile

	@echo "🗄️ Exécution des migrations..."
	$(SYMFONY) doctrine:migrations:migrate --no-interaction

	@echo "🧹 Nettoyage final du cache..."
	$(SYMFONY) cache:clear

	@echo "Rechargement container..."
	docker compose -f compose.yaml -f compose.prod.yaml stop php
	docker compose -f compose.yaml -f compose.prod.yaml up -d php

	echo "✅ Redémarrage des workers..."
    # On relance la machine !
	#sudo supervisorctl start noho-worker:*

	@echo "✅ Noho est en ligne et à jour !"
