include .bootstrap.mk

# Go flags to ignore the PHP composer vendor/ directory until it is removed.
export GOFLAGS := -mod=mod

build:: build-prod build-nginx ## Builds prod and nginx docker images

build-prod:: ## Builds prod docker image
build-nginx:: ## Builds nginx docker image
build-%::
	docker build \
		-t ruwordnetview:$* \
		-f docker/$*/Dockerfile .

dev:: ## Runs the app locally (requires POSTGRES_* env vars exported in the shell)
	go run ./cmd/ruwordnetview

local-stack:: build-prod ## Builds prod image and starts the local Docker stack (app + postgres)
	docker compose -f compose.local.yaml up

test:: ## Runs Go tests
	go test ./...

lint:: ## Runs Go linters
	go vet ./...
	@if gofmt -l . | grep -q .; then \
		echo "gofmt: the following files need formatting:"; \
		gofmt -l .; \
		exit 1; \
	fi

css:: ## Compiles SCSS files to CSS
	sassc --style compressed web/static/css/layout.scss web/static/css/layout.min.css

deploy:: ## Uploads source code to the server
	rsync \
		--archive \
		--verbose \
		--compress \
		--update \
		--delete \
		--progress \
		--human-readable \
		--exclude-from=rsync-exclude \
		./ ruwordnet:/var/www/ruwordnet-view/
