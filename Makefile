up:
	./vendor/bin/sail up -d

down:
	./vendor/bin/sail down

migrate:
	./vendor/bin/sail artisan migrate

fresh:
	./vendor/bin/sail artisan migrate:fresh --seed

test:
	./vendor/bin/sail artisan test

worker:
	./vendor/bin/sail artisan queue:work redis --queue=transactions,default

dev:
	./vendor/bin/sail npm run dev

vite: dev

build:
	./vendor/bin/sail npm run build

docs:
	@echo "Swagger UI: http://localhost/api/docs"

pint:
	./vendor/bin/sail pint

pint-test:
	./vendor/bin/sail pint --test

stan:
	./vendor/bin/sail php vendor/bin/phpstan analyse --memory-limit=1G

rector:
	./vendor/bin/sail php vendor/bin/rector process

rector-test:
	./vendor/bin/sail php vendor/bin/rector process --dry-run

quality: pint-test stan rector-test

ci: quality test build

fix-perms:
	./vendor/bin/sail exec -u root laravel.test bash -c "chown -R sail:sail storage bootstrap/cache && chmod -R ug+rwx storage/framework/cache/rector"
