DOCKER_COMPOSE:=docker compose
SOLR_CONTAINER_NAME := solr
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
	php$(PHP_VERSION) scripts/solr/solrJob.php -D % -v -d

clean: down ## Clean up unused docker resources
	#docker stop $(docker ps -a -q)
	docker system prune -f

load-db-episciences: ## Load an SQL dump from ./tmp/episciences.sql
	$(MYSQL_CONNECT) -P 33060 episciences < ./tmp/episciences.sql

load-db-auth: ## Load an SQL dump from ./tmp/cas_users.sql
	$(MYSQL_CONNECT) -P 33062 cas_users < ./tmp/cas_users.sql

