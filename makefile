app_name=cospend
app_version=$(version)
project_dir=.
build_dir=/tmp/build
sign_dir=/tmp/sign
cert_dir=$(HOME)/.nextcloud/certificates
webserveruser ?= www-data
occ_dir ?= /var/www/html/dev/server

GITHUB_TOKEN := $(shell cat ~/.nextcloud/secrets/GITHUB_TOKEN | tr -d '\n')
GITHUB_REPO=julien-nc/cospend-nc

build_tools_directory=$(CURDIR)/build/tools
npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

all: build

.PHONY: build
build:
ifneq (,$(wildcard $(CURDIR)/composer.json))
	make composer
endif
ifneq (,$(wildcard $(CURDIR)/package.json))
	make npm
endif

.PHONY: dev
dev:
ifneq (,$(wildcard $(CURDIR)/composer.json))
	make composer
endif
ifneq (,$(wildcard $(CURDIR)/package.json))
	make npm-dev
endif

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
else
	composer install --prefer-dist
endif

.PHONY: composer_release
composer_release:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
else
	composer install --no-dev -a
endif

.PHONY: npm
npm:
	$(npm) ci
	$(npm) run build

.PHONY: npm-dev
npm-dev:
	$(npm) ci
	$(npm) run dev

clean:
	sudo rm -rf $(build_dir)
	sudo rm -rf $(sign_dir)
	rm -rf js/* vendor

build_release: clean composer_release npm
	mkdir -p $(sign_dir)
	mkdir -p $(build_dir)
	@rsync -a \
	--exclude=.git \
	--exclude=appinfo/signature.json \
	--exclude=*.swp \
	--exclude=/.idea \
	--exclude=build \
	--exclude=.gitignore \
	--exclude=.travis.yml \
	--exclude=.scrutinizer.yml \
	--exclude=CONTRIBUTING.md \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=composer.phar \
	--exclude=package.json \
	--exclude=package-lock.json \
	--exclude=js/node_modules \
	--exclude=node_modules \
	--exclude=src \
	--exclude=translationfiles \
	--exclude=webpack.* \
	--exclude=.eslintrc.js \
	--exclude=stylelint.config.js \
	--exclude=.github \
	--exclude=.gitlab-ci.yml \
	--exclude=crowdin.yml \
	--exclude=tools \
	--exclude=l10n/.tx \
	--exclude=l10n/l10n.pl \
	--exclude=l10n/templates \
	--exclude=l10n/*.sh \
	--exclude=l10n/[a-z][a-z] \
	--exclude=l10n/[a-z][a-z]_[A-Z][A-Z] \
	--exclude=l10n/no-php \
	--exclude=makefile \
	--exclude=screenshots \
	--exclude=phpunit*xml \
	--exclude=tests \
	--exclude=/.editorconfig \
	--exclude=ci \
	--exclude=vendor/bin \
	--exclude=/.l10nignore \
	--exclude=/.php* \
	--exclude=/psalm.xml \
	$(project_dir) $(sign_dir)/$(app_name)
	# generate info.xml with translations
	cd $(sign_dir)/$(app_name)/l10n/descriptions && ./gen_info.xml.sh && mv info.xml ../../appinfo/
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		sudo chown $(webserveruser) $(sign_dir)/$(app_name)/appinfo ;\
		sudo -u $(webserveruser) php $(occ_dir)/occ integrity:sign-app --privateKey=$(cert_dir)/$(app_name).key --certificate=$(cert_dir)/$(app_name).crt --path=$(sign_dir)/$(app_name)/ ;\
		sudo chown -R $(USER) $(sign_dir)/$(app_name)/appinfo ;\
	else \
		echo "!!! WARNING signature key not found" ;\
	fi
	tar -czf $(build_dir)/$(app_name)-$(app_version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo NEXTCLOUD------------------------------------------ ;\
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(app_version).tar.gz | openssl base64 | tee $(build_dir)/sign.txt ;\
	fi

publish_release: build_release
	# create release on GitHub
	UPLOAD_URL=`curl -s -H "Authorization: token $(GITHUB_TOKEN)"  \
		-d '{"tag_name": "v$(app_version)", "name": "v$(app_version)", "body": "See CHANGELOG.md for changes."}'  \
		"https://api.github.com/repos/$(GITHUB_REPO)/releases" | jq -r '.upload_url'`; \
	UPLOAD_URL="$${UPLOAD_URL%\{*}"; \
	echo "uploading asset to release to url : $${UPLOAD_URL}"; \
	curl -s -H "Authorization: token $(GITHUB_TOKEN)"  \
		-H "Content-Type: application/gzip" \
		--data-binary  @$(build_dir)/$(app_name)-$(app_version).tar.gz \
		"$${UPLOAD_URL}?name=$(app_name)-$(app_version).tar.gz&label=$(app_name)-$(app_version).tar.gz"
	# publish to appstore
	SIGNATURE=`cat $(build_dir)/sign.txt | tr -d '\n'`; \
	APPSTORE_TOKEN=`cat ~/.nextcloud/secrets/APPSTORE_TOKEN | tr -d '\n'`; \
	DOWNLOAD_URL=https://github.com/$(GITHUB_REPO)/releases/download/v$(app_version)/$(app_name)-$(app_version).tar.gz; \
	NIGHTLY=`echo $(app_version) | grep '\-nightly$$'`; \
	if [ "$$NIGHTLY" = "" ]; then \
		curl -X POST -H "Authorization: Token $${APPSTORE_TOKEN}" https://apps.nextcloud.com/api/v1/apps/releases -H "Content-Type: application/json" -d '{"download":"'$${DOWNLOAD_URL}'", "signature": "'$${SIGNATURE}'"}'; \
	else \
		curl -X POST -H "Authorization: Token $${APPSTORE_TOKEN}" https://apps.nextcloud.com/api/v1/apps/releases -H "Content-Type: application/json" -d '{"download":"'$${DOWNLOAD_URL}'", "signature": "'$${SIGNATURE}'", "nightly": true}'; \
	fi
