# =============================================================================
# Episciences GPL - Development Makefile
# =============================================================================

# Suppress directory change messages
MAKEFLAGS += --no-print-directory

# Include sub-makefiles
include makefiles/deploy.mk
include makefiles/database.mk
include makefiles/testing.mk

# Configuration Variables
DOCKER := docker
DOCKER_COMPOSE := docker compose
NPX := npx
PROJECT_NAME := episciences

# Container Configuration
CNTR_NAME_SOLR := solr
CNTR_NAME_PHP := php-fpm
CNTR_NAME_HTTPD := httpd
CNTR_APP_DIR := /var/www/htdocs
CNTR_APP_USER := www-data
# Override with 0:0 if composer-install fails (rootless Docker or uid mismatch):
#   make dev-setup CNTR_USER_ID=0:0
CNTR_USER_ID := 1000:1000

# Paths Configuration  
SOLR_COLLECTION_CONFIG := /opt/configsets/episciences

# =============================================================================
# PHONY Targets
# =============================================================================
.PHONY: help build up down status logs restart clean clean-mysql
.PHONY: collection index dev-setup setup-logs copy-config generate-users init-dev-users create-bot-user init-data-dir yarn-encore-dev
.PHONY: send-mails composer-install composer-update yarn-encore-production
.PHONY: restart-httpd restart-php merge-pdf-volume
.PHONY: get-classification-msc get-classification-jel can-i-use-update
.PHONY: enter-container-php
.PHONY: format format-check format-tests format-file

# =============================================================================
# Help & Information
# =============================================================================
help: ## Display this help message
	@echo "Episciences GPL - Development Environment"
	@echo "========================================"
	@echo ""
	@echo "Core Docker Commands:"
	@grep -E '^(build|up|down|status|logs|restart|clean|clean-mysql):.*##' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}'
	@echo ""
	@echo "Database Commands:"
	@grep -h -E '^(wait-for-db|load-db.*|generate-users|shell-mysql.*|backup-db):.*##' $(MAKEFILE_LIST) 2>/dev/null | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}' || echo "  No database commands found"
	@echo ""
	@echo "Solr Commands:"
	@grep -E '^(collection|index):.*##' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}'
	@echo ""
	@echo "Development Commands:"
	@grep -E '^(dev-setup|composer|yarn|enter):.*##' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}'
	@echo ""
	@echo "Testing Commands:"
	@grep -h -E '^test.*:.*##' $(MAKEFILE_LIST) 2>/dev/null | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}' || echo "  No testing commands found"
	@echo ""
	@echo "Linting & Quality Commands:"
	@grep -h -E '^(phpstan|rector).*:.*##' $(MAKEFILE_LIST) 2>/dev/null | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}' || echo "  No quality commands found"
	@echo ""
	@echo "Formatting Commands:"
	@grep -E '^format.*:.*##' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}'
	@echo ""
	@echo "Deployment Commands:"
	@grep -h -E '^deploy.*:.*##' $(MAKEFILE_LIST) 2>/dev/null | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}' || echo "  No deployment commands found"
	@echo ""
	@echo "Other Commands:"
	@grep -E '^(send-mails|merge-pdf|get-classification|can-i-use):.*##' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}'

# =============================================================================
# Core Docker Commands
# =============================================================================
build: ## Build all docker containers
	@echo "Building Docker containers..."
	$(DOCKER_COMPOSE) build

up: ## Start all docker containers
	@echo "Starting Docker containers..."
	$(DOCKER_COMPOSE) up -d
	@echo "====================================================================="
	@echo "Development Environment Started Successfully!"
	@echo "====================================================================="
	@echo "📝 Make sure you have the following in /etc/hosts:"
	@echo "127.0.0.1 localhost dev.episciences.org oai-dev.episciences.org data-dev.episciences.org manager-dev.episciences.org"
	@echo ""
	@echo "🌐 Available Services:"
	@echo "  Journal     : http://dev.episciences.org/"
	@echo "  Manager     : http://manager-dev.episciences.org/dev/"
	@echo "  OAI-PMH     : http://oai-dev.episciences.org/"
	@echo "  Data        : http://data-dev.episciences.org/"
	@echo "  PhpMyAdmin  : http://localhost:8001/"
	@echo "  Apache Solr : http://localhost:8983/solr"
	@echo "====================================================================="

down: ## Stop all docker containers and remove orphans
	@echo "Stopping Docker containers..."
	$(DOCKER_COMPOSE) down --remove-orphans

status: ## Show status of all containers
	@echo "Container Status:"
	@echo "=================="
	@$(DOCKER) ps -a --filter "name=$(PROJECT_NAME)" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

logs: ## Show logs from all containers (use CONTAINER=name for specific container)
ifdef CONTAINER
	@echo "Showing logs for container: $(CONTAINER)"
	@$(DOCKER) logs -f $(CONTAINER)
else
	@echo "Showing logs for all containers (press Ctrl+C to stop):"
	@$(DOCKER_COMPOSE) logs -f
endif

restart: down up ## Restart all containers

# =============================================================================
# Cleanup Commands  
# =============================================================================
clean: down ## Clean up unused docker resources
	@echo "Cleaning up Docker resources..."
	@$(DOCKER) system prune -f
	@echo "Removing episciences network..."
	@$(DOCKER) network rm epi-network 2>/dev/null || true

clean-mysql: down ## Remove all MySQL volumes (WARNING: This will delete all database data!)
	@echo "WARNING: This will permanently delete all MySQL database data!"
	@echo "This may be needed when downgrading from MySQL 8.4 to 8.0"
	@echo ""
	@echo "Volumes to be removed:"
	@echo "  - $(VOLUME_MYSQL_EPISCIENCES)"
	@echo "  - $(VOLUME_MYSQL_INDEXING)" 
	@echo "  - $(VOLUME_MYSQL_AUTH)"
	@echo ""
	@printf "Type 'Yes delete database volumes' to confirm: "; \
	read -r answer; \
	if [ "$$answer" = "Yes delete database volumes" ]; then \
		echo "Removing MySQL volumes..."; \
		$(DOCKER) volume rm $(VOLUME_MYSQL_EPISCIENCES) $(VOLUME_MYSQL_INDEXING) $(VOLUME_MYSQL_AUTH) 2>/dev/null || true; \
		echo "MySQL volumes removed successfully."; \
	else \
		echo "Operation cancelled."; \
	fi

# =============================================================================
# Solr Commands
# =============================================================================
collection: up ## Create Solr collection after starting containers
	@echo "Setting up Solr collection..."
	@echo "Waiting for Solr container to be ready..."
	@until $(DOCKER_COMPOSE) exec $(CNTR_NAME_SOLR) curl -s http://localhost:8983/solr >/dev/null 2>&1; do \
		echo "Waiting for Solr..."; \
		sleep 2; \
	done
	@echo "Solr is ready. Creating 'episciences' collection..."
	@$(DOCKER_COMPOSE) exec $(CNTR_NAME_SOLR) solr create_collection -c episciences -d $(SOLR_COLLECTION_CONFIG) -s http://localhost:8983 >/dev/null 2>&1 || \
		echo "Collection may already exist, continuing..."
	@echo "Solr collection setup complete!"

index: ## Index content into Solr
	@echo "Indexing content into Solr..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/solr/solrJob.php -D % -v
	@echo "Indexing complete!"

# =============================================================================
# Development Setup Commands
# =============================================================================
dev-setup: build copy-config setup-logs up wait-for-db init-data-dir ## Complete development environment setup with 30 generated users
	@echo "Setting up complete development environment..."
	@$(MAKE) composer-install
	@$(MAKE) yarn-encore-dev
	@$(MAKE) load-dev-db
	@$(MAKE) init-dev-users
	@$(MAKE) create-bot-user
	@$(MAKE) collection
	@$(MAKE) index
	@echo "Development environment setup complete!"
	@echo ""
	@echo "====================================================================="
	@echo "🔑 TEST USER CREDENTIALS"
	@echo "====================================================================="
	@echo "30 users have been created for the 'dev' journal (RVID 1)."
	@echo "Default password for all: password123"
	@echo "Available roles: 1 Chief Editor, 2 Administrators, 5 Editors, 22 Members"
	@echo "====================================================================="

setup-logs: ## Setup log directory and files with correct permissions
	@echo "Setting up logs..."
	@./scripts/setup-logs.sh

copy-config: ## Copy dist-dev.pwd.json to config/pwd.json if it doesn't exist
	@if [ -f config/pwd.json ]; then \
		echo "config/pwd.json already exists."; \
		printf "Overwrite with dist-dev.pwd.json? (y/N): "; \
		read -r answer; \
		if [ "$$answer" = "y" ] || [ "$$answer" = "Y" ]; then \
			cp config/dist-dev.pwd.json config/pwd.json; \
			echo "config/pwd.json overwritten."; \
		else \
			echo "Keeping existing config/pwd.json."; \
		fi; \
	else \
		cp config/dist-dev.pwd.json config/pwd.json; \
		echo "config/pwd.json created from dist-dev.pwd.json."; \
	fi

init-data-dir: ## Create data/dev directory with correct permissions for the journal
	@echo "Initializing data/dev directory..."
	@$(DOCKER_COMPOSE) exec -u 0:0 -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		sh -c "chown $(CNTR_APP_USER):$(CNTR_APP_USER) data && chmod 775 data \
		       && cp -rn src/data/default data/ 2>/dev/null || true \
		       && mkdir -p data/dev/config data/dev/files data/dev/languages data/dev/layout data/dev/public data/dev/tmp \
		       && cp -n data/default/config/navigation.json data/dev/config/navigation.json 2>/dev/null || true \
		       && chown -R $(CNTR_APP_USER):$(CNTR_APP_USER) data/dev \
		       && chmod -R 775 data/dev \
		       && find /tmp -maxdepth 1 -name 'zend_cache---*' ! -user $(CNTR_APP_USER) -delete 2>/dev/null || true"
	@echo "data/dev directory ready."

generate-users: ## Generate random test users (usage: make generate-users COUNT=10 ROLE=editor)
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/console.php app:generate-users --count=$(or $(COUNT),5) --role=$(or $(ROLE),member) --rvcode=dev

init-dev-users: ## Initialize journal 'dev' with 30 users (1 chief, 2 admins, 5 editors, 22 members)
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/console.php app:init-dev-users

create-bot-user: ## Create the fixed episciences-bot user
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/console.php app:create-bot-user


# =============================================================================
# PHP Development Commands
# =============================================================================
composer-install: ## Install composer dependencies
	@echo "Installing Composer dependencies..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer install --no-interaction --prefer-dist --optimize-autoloader

composer-update: ## Update composer dependencies
	@echo "Updating Composer dependencies..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer update --no-interaction --prefer-dist --optimize-autoloader

yarn-encore-dev: ## Build frontend assets for development
	@echo "Building frontend assets (dev)..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -e HOME=/tmp -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) yarn install
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -e HOME=/tmp -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) yarn encore dev

yarn-encore-production: ## Build frontend assets for production
	@echo "Building frontend assets..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -e HOME=/tmp -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) yarn install
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -e HOME=/tmp -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) yarn encore production

enter-container-php: ## Open shell in PHP container
	@$(DOCKER_COMPOSE) exec $(CNTR_NAME_PHP) sh -c "cd $(CNTR_APP_DIR) && /bin/bash"

# =============================================================================
# Service Management Commands
# =============================================================================
restart-httpd: ## Restart Apache httpd container
	@echo "Restarting Apache httpd..."
	@$(DOCKER_COMPOSE) restart $(CNTR_NAME_HTTPD)

restart-php: ## Restart PHP-FPM container
	@echo "Restarting PHP-FPM..."
	@$(DOCKER_COMPOSE) restart $(CNTR_NAME_PHP)

# =============================================================================
# Application Specific Commands
#
# These targets are development shortcuts that run Episciences CLI commands
# inside the PHP container as $(CNTR_APP_USER).
#
# In production, commands are run directly on the server (no Make, no Docker):
#   sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php <command> [options]
#
# All console commands accept --dry-run (simulate without writing) and -q (quiet/cron mode).
# Run `php scripts/console.php list` for the full list of available commands.
# =============================================================================

# --- Mail -----------------------------------------------------------------------

send-mails: ## Send queued emails using the mail queue system
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/send_mails.php
	@echo "Sending queued emails..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/send_mails.php

# --- Enrichment -----------------------------------------------------------------

enrich-citations: ## Enrich citation data from the Episciences API
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php enrichment:citations [-q] [--dry-run]
	@echo "Enriching citation data..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php enrichment:citations

enrich-creators: ## Enrich author ORCID data from OpenAIRE Research Graph and HAL TEI
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php enrichment:creators [-q] [--dry-run]
	@echo "Enriching author (creator) data..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php enrichment:creators

enrich-licences: ## Enrich licence data from the Episciences API
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php enrichment:licences [-q] [--dry-run]
	@echo "Enriching licence data..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php enrichment:licences

enrich-links: ## Enrich link data from the Episciences API
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php enrichment:links [-q] [--dry-run]
	@echo "Enriching link data..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php enrichment:links

enrich-funding: ## Enrich funding data from OpenAIRE Research Graph
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php enrichment:funding [-q] [--dry-run]
	@echo "Enriching funding data..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php enrichment:funding

get-classification-jel: ## Enrich JEL classification data from OpenAIRE Research Graph
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php enrichment:classifications-jel [-q] [--dry-run]
	@echo "Enriching JEL classification data..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php enrichment:classifications-jel

get-classification-msc: ## Enrich MSC 2020 classification data from zbMATH Open
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php enrichment:classifications-msc [-q] [--dry-run]
	@echo "Enriching MSC 2020 classification data..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php enrichment:classifications-msc

enrich-zb-reviews: ## Enrich zbMATH review data
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php enrichment:zb-reviews [-q] [--dry-run]
	@echo "Enriching zbMATH review data..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php enrichment:zb-reviews

# --- Sitemap --------------------------------------------------------------------

generate-sitemap: ## Generate XML sitemap for a journal (requires rvcode=JOURNAL_CODE; optional: pretty=1)
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php sitemap:generate RVCODE [--pretty] [-q]
	@if [ -z "$(rvcode)" ]; then \
		echo "Error: rvcode parameter is required"; \
		echo "Usage: make generate-sitemap rvcode=JOURNAL_CODE [pretty=1]"; \
		exit 1; \
	fi
	@echo "Generating sitemap for journal '$(rvcode)'..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php sitemap:generate $(rvcode) \
		$(if $(filter 1,$(pretty)),--pretty)

# --- Volume / DOAJ --------------------------------------------------------------

merge-pdf-volume: ## Merge PDFs for all articles in a journal volume into one file (requires rvcode=JOURNAL_CODE; optional: ignore-cache=1 remove-cache=1 dry-run=1)
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php volume:merge-pdf --rvcode=RVCODE [--ignore-cache] [--remove-cache] [--dry-run] [-q]
	@if [ -z "$(rvcode)" ]; then \
		echo "Error: rvcode parameter is required"; \
		echo "Usage: make merge-pdf-volume rvcode=JOURNAL_CODE [ignore-cache=1] [remove-cache=1] [dry-run=1]"; \
		exit 1; \
	fi
	@echo "Merging PDFs for journal '$(rvcode)'..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php volume:merge-pdf \
		--rvcode=$(rvcode) \
		$(if $(filter 1,$(ignore-cache)),--ignore-cache) \
		$(if $(filter 1,$(remove-cache)),--remove-cache) \
		$(if $(filter 1,$(dry-run)),--dry-run)

doaj-export-volumes: ## Create DOAJ XML volume exports (requires rvcode=JOURNAL_CODE or rvcode=allJournals; optional: ignore-cache=1 remove-cache=1 dry-run=1)
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php doaj:export-volumes --rvcode=RVCODE|allJournals [--ignore-cache] [--remove-cache] [--dry-run] [-q]
	@if [ -z "$(rvcode)" ]; then \
		echo "Error: rvcode parameter is required"; \
		echo "Usage: make doaj-export-volumes rvcode=JOURNAL_CODE|allJournals [ignore-cache=1] [remove-cache=1] [dry-run=1]"; \
		exit 1; \
	fi
	@echo "Creating DOAJ volume exports for '$(rvcode)'..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php doaj:export-volumes \
		--rvcode=$(rvcode) \
		$(if $(filter 1,$(ignore-cache)),--ignore-cache) \
		$(if $(filter 1,$(remove-cache)),--remove-cache) \
		$(if $(filter 1,$(dry-run)),--dry-run)

# --- Import ---------------------------------------------------------------------

import-sections: ## Import journal sections from a CSV file (requires csv-file=PATH; optional: dry-run=1)
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php import:sections --csv-file=PATH [--dry-run] [-q]
	@if [ -z "$(csv-file)" ]; then \
		echo "Error: csv-file parameter is required"; \
		echo "Usage: make import-sections csv-file=PATH/TO/FILE.csv [dry-run=1]"; \
		exit 1; \
	fi
	@echo "Importing sections from '$(csv-file)'..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php import:sections \
		--csv-file=$(csv-file) \
		$(if $(filter 1,$(dry-run)),--dry-run)

import-volumes: ## Import journal volumes from a CSV file (requires rvid=JOURNAL_RVID csv-file=PATH; optional: dry-run=1)
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php import:volumes --rvid=RVID --csv-file=PATH [--dry-run] [-q]
	@if [ -z "$(rvid)" ] || [ -z "$(csv-file)" ]; then \
		echo "Error: rvid and csv-file parameters are required"; \
		echo "Usage: make import-volumes rvid=JOURNAL_RVID csv-file=PATH/TO/FILE.csv [dry-run=1]"; \
		exit 1; \
	fi
	@echo "Importing volumes for RVID $(rvid) from '$(csv-file)'..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php import:volumes \
		--rvid=$(rvid) \
		--csv-file=$(csv-file) \
		$(if $(filter 1,$(dry-run)),--dry-run)

# --- zbJATS ---------------------------------------------------------------------

zbjats-zip: ## Download PDF + zbJATS XML per volume and create a ZIP archive (requires rvid=JOURNAL_RVID; optional: zip-prefix=PREFIX dry-run=1)
	# Prod: sudo -u $(CNTR_APP_USER) php $(CNTR_APP_DIR)/scripts/console.php zbjats:zip --rvid=RVID [--zip-prefix=PREFIX] [--dry-run] [-q]
	@if [ -z "$(rvid)" ]; then \
		echo "Error: rvid parameter is required"; \
		echo "Usage: make zbjats-zip rvid=JOURNAL_RVID [zip-prefix=PREFIX] [dry-run=1]"; \
		exit 1; \
	fi
	@echo "Creating zbJATS ZIP for RVID $(rvid)..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/console.php zbjats:zip \
		--rvid=$(rvid) \
		$(if $(zip-prefix),--zip-prefix=$(zip-prefix)) \
		$(if $(filter 1,$(dry-run)),--dry-run)

can-i-use-update: ## Update browserslist database when caniuse-lite is outdated
	@echo "Updating browserslist database..."
	@$(NPX) update-browserslist-db@latest

# =============================================================================
# Code Formatting Commands (Prettier)
# =============================================================================
format: ## Format all JavaScript files with Prettier
	@echo "Formatting JavaScript files..."
	@yarn format

format-check: ## Check JavaScript formatting without modifying files
	@echo "Checking JavaScript formatting..."
	@yarn format:check

format-tests: ## Format JavaScript test files
	@echo "Formatting JavaScript test files..."
	@yarn format:tests

format-file: ## Format a specific file with Prettier (usage: make format-file FILE=path/to/file.js)
	@if [ -z "$(FILE)" ]; then \
		echo "Error: FILE parameter is required"; \
		echo "Usage: make format-file FILE=path/to/file.js"; \
		exit 1; \
	fi
	@echo "Formatting $(FILE)..."
	@yarn prettier --write "$(FILE)"

# =============================================================================
# Default Target
# =============================================================================
.DEFAULT_GOAL := help