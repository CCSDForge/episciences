DOCKER_COMPOSE = docker-compose
SOLR_CONTAINER_NAME := solr
COLLECTION_CONFIG := /opt/configsets/episciences

.PHONY: build up down collection index clean

build:
	$(DOCKER_COMPOSE) build

up:
	$(DOCKER_COMPOSE) up -d

down:
	$(DOCKER_COMPOSE) down --remove-orphans

collection: up
	@echo "Waiting for Solr container to be ready..."
	@docker exec $(SOLR_CONTAINER_NAME) bash -c "until curl -s http://localhost:8983/solr; do sleep 1; done"
	@echo "Solr container is ready. Creating collection..."
	@docker exec $(SOLR_CONTAINER_NAME) solr create_collection -c dev-episciences -d $(COLLECTION_CONFIG)

index: collection
	@echo "Indexing all content"
	php8.1 scripts/solr/solrJob.php -D % -v -d

clean: down
	#docker stop $(docker ps -a -q)
	docker system prune -f
