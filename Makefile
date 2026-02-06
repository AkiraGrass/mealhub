SHELL := /bin/bash
ENV_FILE := $(shell [ -f src/mealhub/.env ] && echo "--env-file src/mealhub/.env")
COMPOSE := docker compose $(ENV_FILE)

.PHONY: up down build rebuild init logs sh tinker migrate seed fresh test reset

up:
	@$(COMPOSE) up -d

down:
	@$(COMPOSE) down

build:
	@$(COMPOSE) build

rebuild:
	@$(COMPOSE) build --no-cache

init:
	@$(COMPOSE) up -d
	@sleep 5
	@$(COMPOSE) exec app composer install || true
	@$(COMPOSE) exec app php artisan key:generate || true
	@$(COMPOSE) exec app php artisan migrate --seed || true

logs:
	@$(COMPOSE) logs -f --tail=200

sh:
	@$(COMPOSE) exec app bash

tinker:
	@$(COMPOSE) exec app php artisan tinker

migrate:
	@$(COMPOSE) exec app php artisan migrate

seed:
	@$(COMPOSE) exec app php artisan db:seed

fresh:
	@$(COMPOSE) exec app php artisan migrate:fresh --seed

test:
	@$(COMPOSE) exec app php artisan test --parallel

reset:
	@$(COMPOSE) down -v
	@$(COMPOSE) up -d
	@sleep 5
	@$(COMPOSE) exec app composer install || true
	@$(COMPOSE) exec app php artisan migrate:fresh --seed || true

ps:
	@$(COMPOSE) ps
