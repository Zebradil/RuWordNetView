docker-build:
	docker build -t ruwordnetview:dev .

dev: docker-build
	docker run \
		--network ruwordnet_default \
		-v `pwd`:/opt/app \
		-p 8000:8000 \
		-it ruwordnetview:dev

