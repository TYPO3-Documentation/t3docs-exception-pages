SHELL := /bin/bash

BRANCH ?= $$(git rev-parse --abbrev-ref HEAD 2> /dev/null)

.PHONY: env build start stop clean

env:
	@echo "UID=$$(id -u)" > .env
	@echo "GID=$$(id -g)" >> .env

build: env
	@echo "Build TYPO3 Exception Page creation environment [branch: $(BRANCH)]"
	@docker-compose build --force-rm
	@docker-compose -f admin.yml run --rm composer-install

start: env
	@echo "Start TYPO3 Exception Page creation environment [branch: $(BRANCH)]"
	@docker-compose up

stop:
	@echo "Stop TYPO3 Exception Page creation environment [branch: $(BRANCH)]"
	@docker-compose down

clean:
	@echo "Remove TYPO3 Exception Page creation environment"
	@docker-compose down --rmi all --volumes
