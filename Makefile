DOCKER:= docker
DOCKER_COMPOSE:= docker compose
NPX:= npx
CNTR_NAME_SOLR := solr
CNTR_NAME_PHP := php-fpm
CNTR_APP_DIR := /var/www/htdocs
CNTR_APP_USER := www-data

SOLR_COLLECTION_CONFIG := /opt/configsets/episciences
MYSQL_CONNECT_EPISCIENCES:= mysql -u root -proot -h 127.0.0.1 -P 33060 episciences
MYSQL_CONNECT_AUTH:= mysql -u root -proot -h 127.0.0.1 -P 33062 cas_users

.PHONY: build up down collection index clean help

help: ## Display this help
	@echo "Available targets:"
	@grep -E '^[a-zA-Z_-]+:.*##' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "%-30s %s\n", $$1, $$2}'

build: ## Build the docker containers
	$(DOCKER_COMPOSE) build

up: ## Start all the docker containers
	$(DOCKER_COMPOSE) up -d
	@echo "====================================================================="
	@echo "Make sure you have [127.0.0.1 localhost dev.episciences.org oai-dev.episciences.org data-dev.episciences.org] in /etc/hosts"
	@echo "Journal     : http://dev.episciences.org/"
	@echo "OAI-PMH     : http://oai-dev.episciences.org/"
	@echo "Data        : http://data-dev.episciences.org/"
	@echo "PhpMyAdmin  : http://localhost:8001/"
	@echo "Apache Solr : http://localhost:8983/solr"
	@echo "====================================================================="
	@echo "SQL Place Custom SQL dump files in ~/tmp/"
	@echo "SQL: Import '~/tmp/episciences.sql' with 'make load-db-episciences'"
	@echo "SQL: Import '~/tmp/cas_users.sql'   with 'make load-db-auth'"
	@echo "Solr: Create Solr Collection with           'make collection'"
	@echo "Solr: Index content in Solr Collection with 'make index'"

down: ## Stop the docker containers and remove orphans
	$(DOCKER_COMPOSE) down --remove-orphans

collection: up ## Create the Solr collection after starting the containers
	@echo "Waiting for Solr container to be ready..."
	@docker exec $(CNTR_NAME_SOLR) bash -c "until curl -s http://localhost:8983/solr; do sleep 1; done"
	@echo "Solr container is ready. Creating 'episciences' collection..."
	@docker exec $(CNTR_NAME_SOLR) solr create_collection -c episciences -d $(SOLR_COLLECTION_CONFIG)

index: ## Index the content into Solr
	@echo "Indexing all content"
	$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) php-fpm php scripts/solr/solrJob.php -D % -v

clean: down ## Clean up unused docker resources
	#docker stop $(docker ps -a -q)
	docker system prune -f

load-db-episciences: ## Load an SQL dump from ./tmp/episciences.sql
	$(MYSQL_CONNECT_EPISCIENCES) < ~/tmp/episciences.sql

load-db-auth: ## Load an SQL dump from ./tmp/cas_users.sql
	$(MYSQL_CONNECT_AUTH) < ~/tmp/cas_users.sql

send-mails:
	$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/send_mails.php

composer-install: ## Install composer dependencies
	$(DOCKER_COMPOSE) exec -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer install --no-interaction --prefer-dist --optimize-autoloader

composer-update: ## Update composer dependencies
	$(DOCKER_COMPOSE) exec -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer update --no-interaction --prefer-dist --optimize-autoloader

yarn-encore-production: ## yarn encore production
	$(DOCKER_COMPOSE) exec -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) yarn install; yarn encore production

restart-httpd: ## Restart Apache httpd
	$(DOCKER_COMPOSE) restart httpd

restart-php: ## Restart PHP-FPM Container
	$(DOCKER_COMPOSE) restart $(CNTR_NAME_PHP)


merge-pdf-volume: ## merge all pdf from a vid into one pdf
	$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/mergePdfVol.php --rvcode=$(rvcode) --ignorecache=$(or $(ignorecache),0) --removecache=$(or $(removecache),0)


get-classification-msc: ## Get MSC 2020 Classifications from zbMATH Open
	$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/getClassificationMsc.php

get-classification-jel: ## Get JEL Classifications from OpenAIRE Research Graph
	$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) php scripts/getClassificationJEL.php


can-i-use-update: ## To be launched when Browserslist: caniuse-lite is outdated.
	$(NPX) update-browserslist-db@latest

enter-container-php: ## Open shell on PHP container
	$(DOCKER) exec -it $(CNTR_NAME_PHP) sh -c "cd /var/www/htdocs && /bin/bash"

