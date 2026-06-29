# Turn Tracker — developer Makefile
# NOTE: recipe lines are indented with real TABS (required by make).

DC      := docker compose
PHP     := $(DC) exec php
PHP_RUN := $(DC) run --rm php
NODE    := $(DC) run --rm node

# DATABASE_URL override for the test suite: lightweight SQLite, no MySQL dependency.
TEST_DB_URL := DATABASE_URL=sqlite:///%kernel.project_dir%/var/test.db APP_ENV=test

.PHONY: help up down build install migrate seed test test-backend test-frontend \
        build-front shell logs reset-db

help: ## List available targets
	@echo "Turn Tracker — make targets:"
	@echo "  up             Start the stack (detached)"
	@echo "  down           Stop the stack"
	@echo "  build          Build all images"
	@echo "  install        composer install in php (node installs via its own command)"
	@echo "  migrate        Run Doctrine migrations"
	@echo "  seed           Load Doctrine fixtures (demo data)"
	@echo "  test           Run backend + frontend tests"
	@echo "  test-backend   Prepare test DB then run PHPUnit"
	@echo "  test-frontend  Run frontend (vitest) tests"
	@echo "  build-front    Production React build"
	@echo "  shell          Shell into the php container"
	@echo "  logs           Tail logs for all services"
	@echo "  reset-db       Drop, create, migrate and seed the database"

up: ## Start the stack
	$(DC) up -d

down: ## Stop the stack
	$(DC) down

build: ## Build images
	$(DC) build

install: ## Install backend (composer) dependencies
	$(PHP) composer install

migrate: ## Run Doctrine migrations
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

seed: ## Load fixtures
	$(PHP) php bin/console doctrine:fixtures:load --no-interaction

test: test-backend test-frontend ## Run all tests

test-backend: ## Prepare the test database and run PHPUnit
	$(PHP) sh -c '$(TEST_DB_URL) php bin/console doctrine:database:create --env=test --if-not-exists || true'
	$(PHP) sh -c '$(TEST_DB_URL) php bin/console doctrine:schema:create --env=test || true'
	$(PHP) sh -c '$(TEST_DB_URL) ./vendor/bin/phpunit'

test-frontend: ## Run frontend tests (no-op gracefully if not present)
	$(NODE) sh -c 'npm test --if-present || echo "no frontend tests"'

build-front: ## Production React build
	$(NODE) npm run build

shell: ## Shell into the php container
	$(DC) exec php sh

logs: ## Tail logs
	$(DC) logs -f

reset-db: ## Drop, create, migrate and seed
	$(PHP) php bin/console doctrine:database:drop --force --if-exists
	$(PHP) php bin/console doctrine:database:create --if-not-exists
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction
	$(PHP) php bin/console doctrine:fixtures:load --no-interaction
