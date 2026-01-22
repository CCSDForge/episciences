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
CNTR_USER_ID := 1000:1000

# Paths Configuration  
SOLR_COLLECTION_CONFIG := /opt/configsets/episciences

# =============================================================================
# PHONY Targets
# =============================================================================
.PHONY: help build up down status logs restart clean clean-mysql
.PHONY: collection index dev-setup
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
	@grep -h -E '^(wait-for-db|load-db|shell-mysql|backup-db):.*##' $(MAKEFILE_LIST) 2>/dev/null | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}' || echo "  No database commands found"
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
	@echo "Linting Commands:"
	@grep -h -E '^lint.*:.*##' $(MAKEFILE_LIST) 2>/dev/null | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-25s %s\n", $$1, $$2}' || echo "  No linting commands found"
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
	@echo "Make sure you have the following in /etc/hosts:"
	@echo "127.0.0.1 localhost dev.episciences.org oai-dev.episciences.org data-dev.episciences.org manager-dev.episciences.org"
	@echo ""
	@echo "Available Services:"
	@echo "  Journal     : http://dev.episciences.org/"
	@echo "  Manager     : http://manager-dev.episciences.org/dev/"
	@echo "  OAI-PMH     : http://oai-dev.episciences.org/"
	@echo "  Data        : http://data-dev.episciences.org/"
	@echo "  PhpMyAdmin  : http://localhost:8001/"
	@echo "  Apache Solr : http://localhost:8983/solr"
	@echo "====================================================================="
	@echo "Next Steps:"
	@echo "  1. Import databases: 'make load-db-episciences' and 'make load-db-auth'"
	@echo "  2. Create Solr collection: 'make collection'"
	@echo "  3. Index content: 'make index'"
	@echo "  4. Or run complete setup: 'make dev-setup'"
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
	@until $(DOCKER) exec $(CNTR_NAME_SOLR) curl -s http://localhost:8983/solr >/dev/null 2>&1; do \
		echo "Waiting for Solr..."; \
		sleep 2; \
	done
	@echo "Solr is ready. Creating 'episciences' collection..."
	@$(DOCKER) exec $(CNTR_NAME_SOLR) solr create_collection -c episciences -d $(SOLR_COLLECTION_CONFIG) || \
		echo "Collection may already exist, continuing..."
	@echo "Solr collection setup complete!"

index: ## Index content into Solr
	@echo "Indexing content into Solr..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/solr/solrJob.php -D % -v
	@echo "Indexing complete!"

# =============================================================================
# Development Setup Commands
# =============================================================================
dev-setup: up wait-for-db ## Complete development environment setup
	@echo "Setting up complete development environment..."
	@if [ -f $(SQL_DUMP_DIR)/episciences.sql ]; then \
		echo "Loading episciences.sql ..."; \
		$(MAKE) load-db-episciences; \
	else \
		echo "Warning: $(SQL_DUMP_DIR)/episciences.sql not found, skipping database import"; \
	fi
	@if [ -f $(SQL_DUMP_DIR)/cas_users.sql ]; then \
		echo "Loading cas_users.sql ..."; \
		$(MAKE) load-db-auth; \
	else \
		echo "Warning: $(SQL_DUMP_DIR)/cas_users.sql not found, skipping auth database import"; \
	fi
	@$(MAKE) composer-install
	@$(MAKE) collection
	@$(MAKE) index
	@echo "Development environment setup complete!"


# =============================================================================
# PHP Development Commands
# =============================================================================
composer-install: ## Install composer dependencies
	@echo "Installing Composer dependencies..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer install --no-interaction --prefer-dist --optimize-autoloader

composer-update: ## Update composer dependencies
	@echo "Updating Composer dependencies..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer update --no-interaction --prefer-dist --optimize-autoloader

yarn-encore-production: ## Build frontend assets for production
	@echo "Building frontend assets..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -w $(CNTR_APP_DIR) -e HOME=/tmp $(CNTR_NAME_PHP) yarn install
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -w $(CNTR_APP_DIR) -e HOME=/tmp $(CNTR_NAME_PHP) yarn encore production

enter-container-php: ## Open shell in PHP container
	@$(DOCKER) exec -it $(CNTR_NAME_PHP) sh -c "cd $(CNTR_APP_DIR) && /bin/bash"

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
# =============================================================================
send-mails: ## Send queued emails using the mail queue system
	@echo "Sending queued emails..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/send_mails.php

merge-pdf-volume: ## Merge all PDFs from a volume into one PDF (requires rvcode parameter)
	@if [ -z "$(rvcode)" ]; then \
		echo "Error: rvcode parameter is required"; \
		echo "Usage: make merge-pdf-volume rvcode=JOURNAL_CODE"; \
		echo "Optional: ignorecache=1 removecache=1"; \
		exit 1; \
	fi
	@echo "Merging PDFs for volume $(rvcode)..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		php scripts/mergePdfVol.php \
		--rvcode=$(rvcode) \
		--ignorecache=$(or $(ignorecache),0) \
		--removecache=$(or $(removecache),0)

get-classification-msc: ## Get MSC 2020 Classifications from zbMATH Open
	@echo "Fetching MSC 2020 classifications..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/getClassificationMsc.php

get-classification-jel: ## Get JEL Classifications from OpenAIRE Research Graph
	@echo "Fetching JEL classifications..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/getClassificationJEL.php

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