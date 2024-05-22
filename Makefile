DOCKER_COMPOSE = docker-compose
SOLR_CONTAINER_NAME := solr
COLLECTION_CONFIG := /opt/configsets/episciences

.PHONY: build up down collection clean

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
	@docker exec $(SOLR_CONTAINER_NAME) solr create_collection -c episciences -d $(COLLECTION_CONFIG)

clean: down
	#docker stop $(docker ps -a -q)
	docker system prune -f
