SHELL := /bin/bash

.PHONY: up down build rebuild init logs sh tinker migrate seed fresh test reset

up:
	@docker compose up -d

down:
	@docker compose down

build:
	@docker compose build

rebuild:
	@docker compose build --no-cache

init:
	@docker compose up -d
	@sleep 5
	@docker compose exec app composer install || true
	@docker compose exec app php artisan key:generate || true
	@docker compose exec app php artisan migrate --seed || true

logs:
	@docker compose logs -f --tail=200

sh:
	@docker compose exec app bash

tinker:
	@docker compose exec app php artisan tinker

migrate:
	@docker compose exec app php artisan migrate

seed:
	@docker compose exec app php artisan db:seed

fresh:
	@docker compose exec app php artisan migrate:fresh --seed

test:
	@docker compose exec app php artisan test --parallel

reset:
	@docker compose down -v
	@docker compose up -d
	@sleep 5
	@docker compose exec app composer install || true
	@docker compose exec app php artisan migrate:fresh --seed || true

ps:
	@docker compose ps
