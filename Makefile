DOCKER_COMPOSE:=docker compose
APACHE_USER := www-data
SOLR_CONTAINER_NAME := solr
PHP_CONTAINER_NAME := php-fpm
COLLECTION_CONFIG := /opt/configsets/episciences
MYSQL_CONNECT:= mysql -u root -proot -h 127.0.0.1
PHP_VERSION := 8.1

.PHONY: build up down collection index clean help

help: ## Display this help
	@echo "Available targets:"
	@grep -E '^[a-zA-Z_-]+:.*##' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "%-30s %s\n", $$1, $$2}'

build: ## Build the docker containers
	$(DOCKER_COMPOSE) build

up: ## Start the docker containers
	$(DOCKER_COMPOSE) up -d
	@echo "Local Journal: http://dev.episciences.org/ make sure you have [127.0.0.1 dev.episciences.org] in /etc/hosts"
	@echo "Local AOI    : http://oai-dev.episciences.org/ make sure you have [127.0.0.1 oai-dev.episciences.org] in /etc/hosts"
	@echo "Local Data   : http://data-dev.episciences.org/ make sure you have [127.0.0.1 data-dev.episciences.org] in /etc/hosts"
	@echo "PhpMyAdmin: http://localhost:8001/"
	@echo "Apache Solr: http://localhost:8983/solr"

down: ## Stop the docker containers and remove orphans
	$(DOCKER_COMPOSE) down --remove-orphans

collection: up ## Create the Solr collection after starting the containers
	@echo "Waiting for Solr container to be ready..."
	@docker exec $(SOLR_CONTAINER_NAME) bash -c "until curl -s http://localhost:8983/solr; do sleep 1; done"
	@echo "Solr container is ready. Creating collection..."
	@docker exec $(SOLR_CONTAINER_NAME) solr create_collection -c episciences -d $(COLLECTION_CONFIG)

index: ## Index the content into Solr
	@echo "Indexing all content"
	docker compose exec -u www-data -w /var/www/htdocs php-fpm php scripts/solr/solrJob.php -D % -v

clean: down ## Clean up unused docker resources
	#docker stop $(docker ps -a -q)
	docker system prune -f

load-db-episciences: ## Load an SQL dump from ./tmp/episciences.sql
	$(MYSQL_CONNECT) -P 33060 episciences < ./tmp/episciences.sql

load-db-auth: ## Load an SQL dump from ./tmp/cas_users.sql
	$(MYSQL_CONNECT) -P 33062 cas_users < ./tmp/cas_users.sql

send-mails:
	docker compose exec -u www-data -w /var/www/htdocs php-fpm php scripts/send_mails.php

composer-install: ## Install composer dependencies
	docker compose exec -w /var/www/htdocs php-fpm composer install --no-interaction --prefer-dist --optimize-autoloader

composer-update: ## Update composer dependencies
	docker compose exec -w /var/www/htdocs php-fpm composer update --no-interaction --prefer-dist --optimize-autoloader

yarn-encore-production: ## yarn encore production
	docker compose exec -w /var/www/htdocs php-fpm yarn encore production

restart-httpd: ## Restart Apache httpd
	docker compose restart httpd

restart-php: ## Restart PHP-FPM Container
	docker compose restart php-fpm

merge-pdf-volume: ## merge all pdf from a vid into one pdf
	docker compose exec -u www-data -w /var/www/htdocs php-fpm php scripts/mergePdfVol.php --rvcode=$(rvcode) --ignorecache=$(or $(ignorecache),0) --removecache=$(or $(removecache),0)