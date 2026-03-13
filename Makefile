# Moodle-BTCPayServer development commands
# Run from the moodle-btcpayserver directory. Copy env.example to env and customize.

REPO_ROOT := $(CURDIR)
DOCKER_DIR := $(REPO_ROOT)/.dev/moodle-docker
COMPOSE := $(DOCKER_DIR)/bin/moodle-docker-compose

.PHONY: bootstrap up start down stop reset reset-full restart logs shell purge-caches upgrade help

help:
	@echo "Moodle-BTCPayServer development targets:"
	@echo "  make bootstrap     - Initial setup (clone Moodle, moodle-docker, symlink, start containers)"
	@echo "  make up / start    - Start Docker containers"
	@echo "  make down / stop   - Stop Docker containers"
	@echo "  make reset         - Stop containers, remove volumes (run 'make bootstrap' to start again)"
	@echo "  make reset-full    - Same as reset + delete .moodle and .dev/moodle-docker (full wipe)"
	@echo "  make restart       - Restart webserver container"
	@echo "  make logs          - Follow webserver logs"
	@echo "  make shell         - Open bash in webserver container"
	@echo "  make purge-caches  - Purge Moodle caches"
	@echo "  make upgrade       - Run Moodle upgrade (install/upgrade plugins)"
	@echo ""
	@echo "Config: copy env.example to env and edit. Env is at repo root."

bootstrap:
	@REPO_ROOT="$(REPO_ROOT)"; \
	if [ ! -f "$$REPO_ROOT/env" ]; then cp "$$REPO_ROOT/env.example" "$$REPO_ROOT/env"; echo "Created env from env.example. Edit env if needed."; fi; \
	. "$$REPO_ROOT/env" && export REPO_ROOT PLUGIN_PATH MOODLE_DOCKER_DB MOODLE_BRANCH MOODLE_DOCKER_WEB_HOST MOODLE_DOCKER_WEB_PORT MOODLE_DOCKER_WEB_SCHEME; \
	if [ -z "$$MOODLE_DOCKER_DB" ] || [ -z "$$PLUGIN_PATH" ] || [ -z "$$MOODLE_BRANCH" ]; then echo "Error: MOODLE_DOCKER_DB, PLUGIN_PATH, MOODLE_BRANCH must be set in env" >&2; exit 1; fi; \
	if ! command -v git >/dev/null 2>&1; then echo "Error: git required" >&2; exit 1; fi; \
	if ! command -v docker >/dev/null 2>&1; then echo "Error: docker required" >&2; exit 1; fi; \
	if ! docker compose version >/dev/null 2>&1 && ! command -v docker-compose >/dev/null 2>&1; then echo "Error: docker compose required" >&2; exit 1; fi; \
	echo "Cloning Moodle core (branch: $$MOODLE_BRANCH)..."; \
	if [ ! -d "$$REPO_ROOT/.moodle" ]; then git clone -b "$$MOODLE_BRANCH" git://git.moodle.org/moodle.git "$$REPO_ROOT/.moodle"; else echo "Moodle core already exists at .moodle/"; fi; \
	echo "Cloning moodlehq/moodle-docker..."; \
	mkdir -p "$$REPO_ROOT/.dev"; \
	if [ ! -d "$$REPO_ROOT/.dev/moodle-docker" ]; then git clone https://github.com/moodlehq/moodle-docker.git "$$REPO_ROOT/.dev/moodle-docker"; else echo "moodle-docker already exists at .dev/moodle-docker/"; fi; \
	export MOODLE_DOCKER_WWWROOT="$$REPO_ROOT/.moodle"; \
	if [ ! -f "$$REPO_ROOT/.moodle/config.php" ]; then echo "Creating config.php..."; cp "$$REPO_ROOT/.dev/moodle-docker/config.docker-template.php" "$$REPO_ROOT/.moodle/config.php"; else echo "config.php already exists"; fi; \
	PLUGIN_TARGET="$$REPO_ROOT/.moodle/$$PLUGIN_PATH"; PLUGIN_SOURCE="$$REPO_ROOT/$$PLUGIN_PATH"; PLUGIN_PARENT="$$(dirname "$$PLUGIN_TARGET")"; \
	if [ ! -d "$$PLUGIN_SOURCE" ]; then echo "Error: Plugin not found at $$PLUGIN_SOURCE" >&2; exit 1; fi; \
	mkdir -p "$$PLUGIN_PARENT"; \
	if [ -L "$$PLUGIN_TARGET" ]; then \
	  LINK_TO="$$(readlink -f "$$PLUGIN_TARGET")"; WANT="$$(cd -P "$$PLUGIN_SOURCE" && pwd)"; \
	  if [ "$$LINK_TO" != "$$WANT" ]; then rm "$$PLUGIN_TARGET"; ln -s "$$PLUGIN_SOURCE" "$$PLUGIN_TARGET"; echo "Created symlink: $$PLUGIN_TARGET -> $$PLUGIN_SOURCE"; fi; \
	elif [ -e "$$PLUGIN_TARGET" ]; then rm -rf "$$PLUGIN_TARGET"; ln -s "$$PLUGIN_SOURCE" "$$PLUGIN_TARGET"; echo "Created symlink: $$PLUGIN_TARGET -> $$PLUGIN_SOURCE"; \
	else ln -s "$$PLUGIN_SOURCE" "$$PLUGIN_TARGET"; echo "Created symlink: $$PLUGIN_TARGET -> $$PLUGIN_SOURCE"; fi; \
	{ printf 'version: "3"\nservices:\n  webserver:\n    environment:\n'; \
	  printf '      - MOODLE_CONFIG_PATH=/var/www/html/config.php\n'; \
	  [ -n "$${MOODLE_DOCKER_WEB_HOST:-}" ] && printf '      - MOODLE_DOCKER_WEB_HOST=%s\n' "$$MOODLE_DOCKER_WEB_HOST"; \
	  [ -n "$${MOODLE_DOCKER_WEB_PORT:-}" ] && printf '      - MOODLE_DOCKER_WEB_PORT=%s\n' "$$MOODLE_DOCKER_WEB_PORT"; \
	  [ -n "$${MOODLE_DOCKER_WEB_SCHEME:-}" ] && printf '      - MOODLE_DOCKER_WEB_SCHEME=%s\n' "$$MOODLE_DOCKER_WEB_SCHEME"; \
	  printf '    volumes:\n      - "$${REPO_ROOT}/$${PLUGIN_PATH}:/var/www/html/$${PLUGIN_PATH}"\n'; \
	} > "$$REPO_ROOT/.dev/moodle-docker/local.yml"; \
	echo "Created/updated .dev/moodle-docker/local.yml"; \
	echo "Starting Docker containers..."; \
	cd "$$REPO_ROOT/.dev/moodle-docker" && ./bin/moodle-docker-compose up -d && ./bin/moodle-docker-wait-for-db; \
	echo ""; echo "=========================================="; echo "Moodle development environment is ready!"; echo "=========================================="; \
	echo ""; echo "Next: Open http://localhost:8000 → Site administration → Notifications to install the plugin."; echo "Use: make logs, make shell, make purge-caches, make upgrade"; echo ""

# Helper: export vars needed by moodle-docker-compose (for down/reset targets)
COMPOSE_ENV = . $(REPO_ROOT)/env && export REPO_ROOT="$(REPO_ROOT)" MOODLE_DOCKER_WWWROOT="$(REPO_ROOT)/.moodle" MOODLE_DOCKER_DB MOODLE_DOCKER_WEB_HOST MOODLE_DOCKER_WEB_PORT MOODLE_DOCKER_WEB_SCHEME

up start:
	@. $(REPO_ROOT)/env && export REPO_ROOT="$(REPO_ROOT)" MOODLE_DOCKER_WWWROOT="$(REPO_ROOT)/.moodle" MOODLE_DOCKER_DB MOODLE_DOCKER_WEB_HOST MOODLE_DOCKER_WEB_PORT MOODLE_DOCKER_WEB_SCHEME && cd $(DOCKER_DIR) && ./bin/moodle-docker-compose up -d

down stop:
	@$(COMPOSE_ENV) && cd $(DOCKER_DIR) && ./bin/moodle-docker-compose down

reset:
	@$(COMPOSE_ENV) && cd $(DOCKER_DIR) && ./bin/moodle-docker-compose down -v
	@echo "Containers and volumes removed. Run 'make bootstrap' to start again."

reset-full: reset
	@echo "Removing .moodle and .dev/moodle-docker..."
	@rm -rf $(REPO_ROOT)/.moodle $(REPO_ROOT)/.dev/moodle-docker
	@echo "Run 'make bootstrap' to start from scratch."

restart:
	@$(COMPOSE_ENV) && cd $(DOCKER_DIR) && ./bin/moodle-docker-compose restart webserver

logs:
	@$(COMPOSE_ENV) && cd $(DOCKER_DIR) && ./bin/moodle-docker-compose logs -f webserver

shell:
	@$(COMPOSE_ENV) && cd $(DOCKER_DIR) && ./bin/moodle-docker-compose exec webserver bash

purge-caches:
	@$(COMPOSE_ENV) && cd $(DOCKER_DIR) && ./bin/moodle-docker-compose exec webserver php admin/cli/purge_caches.php

upgrade:
	@$(COMPOSE_ENV) && cd $(DOCKER_DIR) && ./bin/moodle-docker-compose exec webserver php admin/cli/upgrade.php
