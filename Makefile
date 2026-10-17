.PHONY: up down reset logs seed build-vulnerable build-secure test scan clean

up: build-vulnerable
	docker compose build frontend registry-simulator audit-service
	docker compose up -d
	@echo "AegisDock dashboard: http://localhost:$${AEGISDOCK_HTTP_PORT:-8080}"

down:
	docker compose down

reset:
	docker compose down -v --remove-orphans

logs:
	docker compose logs -f --tail=150

seed:
	docker compose exec -T api php artisan migrate --force
	docker compose exec -T api php artisan db:seed --force

build-vulnerable:
	docker compose build --no-cache api
	mkdir -p registry-simulator/artifacts
	docker save aegisdock/api:vulnerable -o registry-simulator/artifacts/aegisdock-terminal-api.tar

build-secure:
	@echo "Secure Docker build is added in the secure-remediation phase."

test:
	cd api && composer install --no-interaction --prefer-dist
	cd api && ./vendor/bin/pest -c phpunit.xml

scan:
	@command -v trivy >/dev/null 2>&1 || { echo "Install Trivy to run image scans."; exit 1; }
	trivy image aegisdock/api:vulnerable
	trivy image aegisdock-frontend

clean:
	docker compose down --remove-orphans
	rm -f registry-simulator/artifacts/aegisdock-terminal-api.tar
