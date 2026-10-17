.PHONY: up secure-up down reset logs seed build-vulnerable build-secure test scan clean

up: build-vulnerable
	docker compose build frontend registry-simulator audit-service
	docker compose up -d
	@echo "AegisDock dashboard: http://localhost:$${AEGISDOCK_HTTP_PORT:-8080}"

secure-up: build-vulnerable build-secure
	docker compose build frontend registry-simulator audit-service
	docker compose -f docker-compose.yml -f docker-compose.secure.yml up -d
	@echo "AegisDock secure profile: http://localhost:$${AEGISDOCK_HTTP_PORT:-8080}"

down:
	docker compose down
	docker compose -f docker-compose.yml -f docker-compose.secure.yml down

reset:
	docker compose down -v --remove-orphans
	docker compose -f docker-compose.yml -f docker-compose.secure.yml down -v --remove-orphans

logs:
	docker compose logs -f --tail=150

seed:
	docker compose exec -T api php artisan migrate --force
	docker compose exec -T api php artisan db:seed --force

build-vulnerable:
	@set -eu; \
	  bash scripts/init-local-secrets.sh; \
	  trap 'rm -f deploy/archive/terminal-gateway.env.bak' EXIT; \
	  docker compose build --no-cache api; \
	  mkdir -p registry-simulator/artifacts; \
	  docker save aegisdock/api:vulnerable -o registry-simulator/artifacts/aegisdock-terminal-api.tar

build-secure:
	@set -eu; \
	  bash scripts/init-local-secrets.sh; \
	  trap 'rm -f deploy/archive/terminal-gateway.env.bak' EXIT; \
	  docker compose -f docker-compose.yml -f docker-compose.secure.yml build --no-cache api

test:
	cd api && composer install --no-interaction --prefer-dist
	cd api && ./vendor/bin/pest -c phpunit.xml

scan:
	@command -v trivy >/dev/null 2>&1 || { echo "Install Trivy to run image scans."; exit 1; }
	trivy image aegisdock/api:vulnerable

clean:
	docker compose down --remove-orphans
	docker compose -f docker-compose.yml -f docker-compose.secure.yml down --remove-orphans
	rm -f registry-simulator/artifacts/aegisdock-terminal-api.tar
