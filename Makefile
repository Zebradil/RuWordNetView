docker-build::
	docker build \
		-t ruwordnetview:dev \
		-f docker/Dockerfile .

dev:: docker-build
	docker run \
		--network ruwordnet_default \
		-v `pwd`:/opt/app \
		-e DEV_UID=1000 \
		-e DEV_GID=1000 \
		-p 8000:8000 \
		-it ruwordnetview:dev

css::
	sassc --style compressed web/static/css/layout.scss web/static/css/layout.min.css
