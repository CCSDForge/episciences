# =============================================================================
# Deployment Makefile
# =============================================================================

# Deployment Configuration
DEPLOY_SCRIPT := deployVersion.sh
CURRENT_BRANCH := $(shell git branch --show-current 2>/dev/null || echo "unknown")
LATEST_TAG := $(shell git describe --tags --abbrev=0 2>/dev/null || echo "no-tags")

# =============================================================================
# Deployment Commands
# =============================================================================
.PHONY: deploy deploy-staging deploy-current deploy-latest deploy-prod deploy-force deploy-check deploy-dry-run

deploy: ## Deploy branch or tag (usage: make deploy TARGET=staging)
ifndef TARGET
	@echo "Error: TARGET parameter is required"
	@echo "Usage: make deploy TARGET=staging"
	@echo "       make deploy TARGET=v1.2.3"
	@echo ""
	@echo "Quick options:"
	@echo "  make deploy-staging    # Deploy staging branch"
	@echo "  make deploy-current    # Deploy current branch ($(CURRENT_BRANCH))"
	@echo "  make deploy-latest     # Deploy latest tag ($(LATEST_TAG))"
	@exit 1
endif
	@echo "Deploying $(TARGET)..."
	@bash $(DEPLOY_SCRIPT) $(TARGET)

deploy-staging: ## Deploy staging branch
	@bash $(DEPLOY_SCRIPT) staging

deploy-current: ## Deploy current branch ($(CURRENT_BRANCH))
	@if [ "$(CURRENT_BRANCH)" = "unknown" ]; then \
		echo "Error: Could not determine current branch"; \
		exit 1; \
	fi
	@echo "Deploying current branch: $(CURRENT_BRANCH)"
	@bash $(DEPLOY_SCRIPT) $(CURRENT_BRANCH)

deploy-latest: ## Deploy latest tag ($(LATEST_TAG))
	@if [ "$(LATEST_TAG)" = "no-tags" ]; then \
		echo "Error: No tags found in repository"; \
		exit 1; \
	fi
	@echo "Deploying latest tag: $(LATEST_TAG)"
	@bash $(DEPLOY_SCRIPT) $(LATEST_TAG)

deploy-prod: ## Deploy production tag (usage: make deploy-prod TAG=v1.2.3)
ifndef TAG
	@echo "Error: TAG parameter is required"
	@echo "Usage: make deploy-prod TAG=v1.2.3"
	@echo "Available tags:"
	@git tag -l | tail -10
	@exit 1
endif
	@echo "Deploying production tag $(TAG)..."
	@bash $(DEPLOY_SCRIPT) $(TAG)

deploy-force: ## Force deploy branch/tag, discarding local changes (usage: make deploy-force TARGET=staging)
ifndef TARGET
	@echo "Error: TARGET parameter is required"
	@echo "Usage: make deploy-force TARGET=staging"
	@echo "       make deploy-force TARGET=v1.2.3"
	@echo ""
	@echo "    Don't underestimate the Force."
	@echo ""
	@echo "⚠️  WARNING: This will discard ALL local changes!"
	@exit 1
endif
	@echo "⚠️  Force deploying $(TARGET) - this will discard local changes!"
	@echo "Continuing in 7 seconds... (Ctrl+C to cancel)"
	@sleep 7
	@git reset --hard HEAD
	@git clean -fd
	@bash $(DEPLOY_SCRIPT) $(TARGET)

deploy-check: ## Check deployment script syntax and git status
	@echo "Checking deployment script syntax..."
	@bash -n $(DEPLOY_SCRIPT)
	@echo "✓ Deployment script syntax OK"
	@echo ""
	@echo "Git Status:"
	@echo "  Current branch: $(CURRENT_BRANCH)"
	@echo "  Latest tag: $(LATEST_TAG)"
	@echo "  Uncommitted changes: $$(git status --porcelain | wc -l)"

deploy-dry-run: ## Show what would be deployed without actually deploying
	@echo "=== DEPLOYMENT DRY RUN ==="
	@echo "Current branch: $(CURRENT_BRANCH)"
	@echo "Latest tag: $(LATEST_TAG)"
	@echo ""
	@echo "Recent branches:"
	@git branch -r --sort=-committerdate | head -5
	@echo ""
	@echo "Recent tags:"
	@git tag -l --sort=-version:refname | head -5
	@echo ""
	@echo "Use one of:"
	@echo "  make deploy TARGET=staging"
	@echo "  make deploy-current    # $(CURRENT_BRANCH)"  
	@echo "  make deploy-latest     # $(LATEST_TAG)"