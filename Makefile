.PHONY: up down reset logs seed test scan clean help build-vulnerable build-secure

# AegisDock Makefile

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Start all services
	docker compose up -d

down: ## Stop all services
	docker compose down

reset: down up ## Reset all services

logs: ## Show logs
	docker compose logs -f

seed: ## Run database migrations and seeders
	docker compose exec api php artisan migrate --force
	docker compose exec api php artisan db:seed --force

build-vulnerable: ## Build the vulnerable Docker image
	docker build -t aegisdock/api:vulnerable -f docker/vulnerable/Dockerfile .

build-secure: ## Build the secure Docker image
	docker build -t aegisdock/api:secure -f docker/secure/Dockerfile .

test: ## Run tests
	cd api && ./vendor/bin/phpunit

scan: ## Scan vulnerable image for secrets
	docker history aegisdock/api:vulnerable --no-trunc

lint: ## Run code linting and static analysis
	cd api && ./vendor/bin/phpunit --filter=skip
	@composer --working-dir=api lint 2>/dev/null || echo "No dedicated lint script"

clean: ## Stop and remove all containers/volumes
	docker compose down -v
	docker rmi aegisdock/api:vulnerable 2>/dev/null || true
	docker rmi aegisdock/api:secure 2>/dev/null || true