# =============================================================================
# Database Operations Makefile
# =============================================================================

# Database Configuration
DB_PORT_EPISCIENCES := 33060
DB_PORT_INDEXING := 33061
DB_PORT_AUTH := 33062
DB_HOST := 127.0.0.1
DB_USER := root
DB_PASS := $(shell echo $$MYSQL_ROOT_PASSWORD)
ifeq ($(DB_PASS),)
    DB_PASS := root
endif

# Paths Configuration  
SQL_DUMP_DIR := ~/tmp

# MySQL Connection Commands
MYSQL_CONNECT_EPISCIENCES := mysql -u $(DB_USER) -p$(DB_PASS) -h $(DB_HOST) -P $(DB_PORT_EPISCIENCES) episciences
MYSQL_CONNECT_INDEXING := mysql -u $(DB_USER) -p$(DB_PASS) -h $(DB_HOST) -P $(DB_PORT_INDEXING) solr_index_queue  
MYSQL_CONNECT_AUTH := mysql -u $(DB_USER) -p$(DB_PASS) -h $(DB_HOST) -P $(DB_PORT_AUTH) cas_users

# Volume Names
VOLUME_MYSQL_EPISCIENCES := episciences-mysql-db-episciences
VOLUME_MYSQL_INDEXING := episciences-mysql-db-indexing
VOLUME_MYSQL_AUTH := episciences-mysql-db-auth

# Development Datasets
DEV_SQL_EPISCIENCES := src/mysql/docker/episciences/dev-episciences.sql
DEV_SQL_AUTH := src/mysql/docker/auth/dev-cas_users.sql

# =============================================================================
# Database Commands
# =============================================================================
.PHONY: wait-for-db load-db-episciences load-db-auth load-dev-db backup-db
.PHONY: shell-mysql-episciences shell-mysql-auth shell-mysql-indexing

wait-for-db: ## Wait for all database containers to be ready
	@echo "Waiting for database containers to be ready..."
	@echo "Checking episciences database..."
	@until $(DOCKER) exec episciences-db-episciences mysqladmin ping -h localhost --silent; do \
		echo "Waiting for episciences database..."; \
		sleep 2; \
	done
	@echo "Checking indexing database..."
	@until $(DOCKER) exec episciences-db-indexing mysqladmin ping -h localhost --silent; do \
		echo "Waiting for indexing database..."; \
		sleep 2; \
	done
	@echo "Checking auth database..."
	@until $(DOCKER) exec episciences-db-auth mysqladmin ping -h localhost --silent; do \
		echo "Waiting for auth database..."; \
		sleep 2; \
	done
	@echo "All databases are ready!"

load-dev-db: wait-for-db ## Load development SQL datasets (src/mysql/docker/...) into databases (with confirmation)
	@echo "This will DROP existing 'episciences' and 'cas_users' databases!"
	@printf "Are you sure you want to proceed and re-import development datasets? (y/N): "; \
	read -r answer; \
	if [ "$$answer" = "y" ] || [ "$$answer" = "Y" ]; then \
		echo "Resetting databases..."; \
		$(DOCKER) exec episciences-db-episciences mysql -u root -p$(DB_PASS) -h localhost -e "DROP DATABASE IF EXISTS episciences; CREATE DATABASE episciences;"; \
		$(DOCKER) exec episciences-db-auth mysql -u root -p$(DB_PASS) -h localhost -e "DROP DATABASE IF EXISTS cas_users; CREATE DATABASE cas_users;"; \
		echo "Loading $(DEV_SQL_EPISCIENCES)..."; \
		$(DOCKER) exec -i episciences-db-episciences mysql -u root -p$(DB_PASS) -h localhost episciences < $(DEV_SQL_EPISCIENCES); \
		echo "Loading $(DEV_SQL_AUTH)..."; \
		$(DOCKER) exec -i episciences-db-auth mysql -u root -p$(DB_PASS) -h localhost cas_users < $(DEV_SQL_AUTH); \
		echo "Development databases reset and loaded successfully!"; \
	else \
		echo "Skipping database re-import."; \
	fi

load-db-episciences: ## Load SQL dump from ~/tmp/episciences.sql into episciences database
	@echo "Loading episciences database..."
	@if [ ! -f $(SQL_DUMP_DIR)/episciences.sql ]; then \
		echo "Error: $(SQL_DUMP_DIR)/episciences.sql not found!"; \
		echo "Please place your SQL dump at $(SQL_DUMP_DIR)/episciences.sql"; \
		exit 1; \
	fi
	@$(MAKE) wait-for-db
	@$(MYSQL_CONNECT_EPISCIENCES) < $(SQL_DUMP_DIR)/episciences.sql
	@echo "Episciences database loaded successfully!"

load-db-auth: ## Load SQL dump from ~/tmp/cas_users.sql into auth database  
	@echo "Loading auth database..."
	@if [ ! -f $(SQL_DUMP_DIR)/cas_users.sql ]; then \
		echo "Error: $(SQL_DUMP_DIR)/cas_users.sql not found!"; \
		echo "Please place your SQL dump at $(SQL_DUMP_DIR)/cas_users.sql"; \
		exit 1; \
	fi
	@$(MAKE) wait-for-db
	@$(MYSQL_CONNECT_AUTH) < $(SQL_DUMP_DIR)/cas_users.sql
	@echo "Auth database loaded successfully!"

backup-db: ## Backup all databases to ~/tmp/ directory
	@echo "Creating database backups..."
	@mkdir -p $(SQL_DUMP_DIR)
	@echo "Backing up episciences database..."
	@mysqldump -u $(DB_USER) -p$(DB_PASS) -h $(DB_HOST) -P $(DB_PORT_EPISCIENCES) episciences > $(SQL_DUMP_DIR)/episciences_backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Backing up auth database..."
	@mysqldump -u $(DB_USER) -p$(DB_PASS) -h $(DB_HOST) -P $(DB_PORT_AUTH) cas_users > $(SQL_DUMP_DIR)/cas_users_backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "Database backups created in $(SQL_DUMP_DIR)/"

shell-mysql-episciences: ## Connect to episciences MySQL database
	@$(MAKE) wait-for-db
	@$(MYSQL_CONNECT_EPISCIENCES)

shell-mysql-auth: ## Connect to auth MySQL database
	@$(MAKE) wait-for-db
	@$(MYSQL_CONNECT_AUTH)

shell-mysql-indexing: ## Connect to indexing MySQL database
	@$(MAKE) wait-for-db  
	@$(MYSQL_CONNECT_INDEXING)
