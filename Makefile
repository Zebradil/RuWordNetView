include .bootstrap.mk

build:: build-dev build-prod build-nginx ## Builds all docker images

build-dev:: ## Builds dev docker image
build-prod:: ## Builds prod docker image
build-nginx:: ## Builds nginx docker image
build-%::
	docker build \
		-t ruwordnetview:$* \
		-f docker/$*/Dockerfile .

dev:: build-dev ## Runs docker container for development
	docker run \
		--network ruwordnet_default \
		-v `pwd`:/opt/app \
		-e DEV_UID=1000 \
		-e DEV_GID=1000 \
		-p 8000:8000 \
		-it ruwordnetview:dev

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
