# =============================================================================
# Testing Makefile
# =============================================================================

# Testing Configuration
PHPUNIT_CONFIG := phpunit.xml
JEST_CONFIG := jest.config.js

# =============================================================================
# Test Commands
# =============================================================================
.PHONY: test test-all test-php test-js test-php-unit test-js-unit test-js-watch test-js-coverage test-coverage lint-php

test: test-all ## Run all tests (PHP + JavaScript)

test-all: ## Run all tests (PHP + JavaScript)
	@echo "Running all tests (PHP + JavaScript)..."
	@echo "========================================"
	@$(MAKE) test-php
	@echo ""
	@$(MAKE) test-js
	@echo ""
	@echo "✅ All tests completed!"

test-php: ## Run PHP tests (PHPUnit)
	@echo "Running PHP tests..."
	@if [ -f $(PHPUNIT_CONFIG) ]; then \
		echo "Using PHPUnit configuration: $(PHPUNIT_CONFIG)"; \
		$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) ./vendor/bin/phpunit; \
	else \
		echo "❌ No $(PHPUNIT_CONFIG) found, skipping PHP tests"; \
	fi

test-php-unit: test-php ## Alias for test-php (for clarity)

test-js: ## Run JavaScript tests (Jest)  
	@echo "Running JavaScript tests..."
	@if [ -f package.json ]; then \
		yarn test; \
	else \
		echo "❌ No package.json found, skipping JavaScript tests"; \
	fi

test-js-unit: test-js ## Alias for test-js (for clarity)

test-js-watch: ## Run JavaScript tests in watch mode
	@echo "Running JavaScript tests in watch mode..."
	@yarn test:watch

test-js-coverage: ## Run JavaScript tests with coverage report
	@echo "Running JavaScript tests with coverage..."
	@yarn test:coverage

test-coverage: ## Run all tests with coverage (where available)
	@echo "Running tests with coverage..."
	@echo "================================"
	@echo "PHP Tests:"
	@if [ -f $(PHPUNIT_CONFIG) ]; then \
		$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) ./vendor/bin/phpunit --coverage-text; \
	else \
		echo "❌ No $(PHPUNIT_CONFIG) found, skipping PHP coverage"; \
	fi
	@echo ""
	@echo "JavaScript Tests:"
	@if [ -f package.json ]; then \
		yarn test:coverage; \
	else \
		echo "❌ No package.json found, skipping JavaScript coverage"; \
	fi

# =============================================================================
# Test Environment Setup
# =============================================================================
.PHONY: test-setup test-setup-php test-setup-js

test-setup: test-setup-php test-setup-js ## Setup test environment for both PHP and JS

test-setup-php: ## Setup PHP test environment
	@echo "Setting up PHP test environment..."
	@if [ ! -f $(PHPUNIT_CONFIG) ]; then \
		echo "⚠️ Warning: $(PHPUNIT_CONFIG) not found"; \
		echo "Please ensure PHPUnit is properly configured"; \
	else \
		echo "✅ PHPUnit configuration found"; \
	fi
	@$(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) composer install --dev

test-setup-js: ## Setup JavaScript test environment
	@echo "Setting up JavaScript test environment..."
	@yarn install
	@echo "✅ JavaScript test environment ready"

# =============================================================================
# Test Utilities
# =============================================================================
.PHONY: test-clean test-status

test-clean: ## Clean test artifacts and caches
	@echo "Cleaning test artifacts..."
	@rm -rf coverage/
	@rm -rf tests/coverage/
	@rm -rf .phpunit.result.cache
	@echo "✅ Test artifacts cleaned"

test-status: ## Show testing setup status
	@echo "Testing Environment Status"
	@echo "=========================="
	@echo "PHP Tests:"
	@if [ -f $(PHPUNIT_CONFIG) ]; then \
		echo "  ✅ PHPUnit config: $(PHPUNIT_CONFIG)"; \
	else \
		echo "  ❌ PHPUnit config: $(PHPUNIT_CONFIG) not found"; \
	fi
	@if $(DOCKER_COMPOSE) exec -u $(CNTR_APP_USER) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) test -f ./vendor/bin/phpunit 2>/dev/null; then \
		echo "  ✅ PHPUnit binary available"; \
	else \
		echo "  ❌ PHPUnit binary not found"; \
	fi
	@echo ""
	@echo "JavaScript Tests:"
	@if [ -f package.json ]; then \
		echo "  ✅ package.json found"; \
	else \
		echo "  ❌ package.json not found"; \
	fi
	@if command -v yarn >/dev/null 2>&1; then \
		echo "  ✅ Yarn available"; \
	else \
		echo "  ❌ Yarn not found"; \
	fi
	@if [ -d node_modules/jest ]; then \
		echo "  ✅ Jest installed"; \
	else \
		echo "  ❌ Jest not installed"; \
	fi

# =============================================================================
# Linting & Refactoring Commands
# =============================================================================
.PHONY: phpstan rector

phpstan: ## Run PHPStan static analysis (usage: make phpstan [TARGET=path/to/file] [LEVEL=X])
	@echo "Ensuring PHPStan cache directory exists and is writable..."
	@$(DOCKER_COMPOSE) exec -u 0:0 $(CNTR_NAME_PHP) mkdir -p /tmp/phpstan
	@$(DOCKER_COMPOSE) exec -u 0:0 $(CNTR_NAME_PHP) chmod -R 777 /tmp/phpstan
	@echo "Running PHPStan static analysis..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		./vendor/bin/phpstan analyse --memory-limit=1G $(if $(LEVEL),--level $(LEVEL)) $(TARGET)

rector: ## Run Rector refactoring tool (usage: make rector [TARGET=path/to/file] [DRY_RUN=1])
	@echo "Ensuring Rector cache directories exist and are writable..."
	@$(DOCKER_COMPOSE) exec -u 0:0 $(CNTR_NAME_PHP) mkdir -p $(CNTR_APP_DIR)/cache/rector /tmp/cache
	@$(DOCKER_COMPOSE) exec -u 0:0 $(CNTR_NAME_PHP) chmod -R 777 $(CNTR_APP_DIR)/cache/rector /tmp/cache
	@echo "Running Rector..."
	@$(DOCKER_COMPOSE) exec -u $(CNTR_USER_ID) -w $(CNTR_APP_DIR) $(CNTR_NAME_PHP) \
		./vendor/bin/rector process $(TARGET) $(if $(DRY_RUN),--dry-run)