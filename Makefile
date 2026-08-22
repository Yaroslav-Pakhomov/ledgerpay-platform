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

stan:
	./vendor/bin/sail composer phpstan

quality:
	./vendor/bin/sail composer quality
