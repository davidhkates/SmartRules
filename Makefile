AWS_REGION := eu-west-2
AWS_ACCOUNT_ID := $(shell aws sts get-caller-identity | jq -r .Account)
REGISTRY := ${AWS_ACCOUNT_ID}.dkr.ecr.${AWS_REGION}.amazonaws.com

VERSION_NO_METADATA := 0.10.1
VERSION := ${VERSION_NO_METADATA}_${GIT_SHA}
IMAGE_NAME := upn-reference-preprocessor
REPO_NAME := ${REGISTRY}/${IMAGE_NAME}


.PHONY: help
help: ## Print this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: test
test: ## Run tests
	./vendor/bin/phpunit --colors tests

.PHONY: analyse
analyse: composer ## Run static code analysis
	vendor/bin/phpstan analyse -c phpstan.neon

.PHONY: composer
composer: ## Install composer dependencies
	composer install

.PHONY: build
build: ## Build docker image
	docker build --build-arg GIT_COMMIT=${VERSION} -t ${REPO_NAME}:${VERSION_NO_METADATA} .
	docker tag ${REPO_NAME}:${VERSION_NO_METADATA} ${REPO_NAME}:latest

.PHONY: create_repository
create_repository: ## Create ECR repository
	aws ecr describe-repositories --repository-names ${IMAGE_NAME} || aws ecr create-repository --repository-name ${IMAGE_NAME}

.PHONY: push
push: build create_repository ## Push Docker image to ECR
	aws ecr get-login-password --region ${AWS_REGION} | docker login --username AWS --password-stdin ${REGISTRY}
	docker push ${REPO_NAME}:${VERSION_NO_METADATA}
	docker push ${REPO_NAME}:latest

.PHONY: upload-all-images
upload-all-images: ## Upload all Docker images
upload-all-images: export REGISTRY := ${REGISTRY}
upload-all-images: export AWS_REGION := ${AWS_REGION}
upload-all-images: push
	$(MAKE) -e -C athena push
