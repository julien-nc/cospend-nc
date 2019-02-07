app_name=payback
app_version=$(version)
project_dir=$(CURDIR)/../$(app_name)
build_dir=/tmp/build
sign_dir=/tmp/sign
cert_dir=$(HOME)/.nextcloud/certificates
webserveruser ?= www-data
occ_dir ?= /var/www/html/n15

all: appstore

clean:
	sudo rm -rf $(build_dir)
	sudo rm -rf $(sign_dir)

appstore: clean
	mkdir -p $(sign_dir)
	mkdir -p $(build_dir)
	rsync -a \
	--exclude=.git \
	--exclude=appinfo/signature.json \
	--exclude=*.swp \
	--exclude=build \
	--exclude=README.md \
	--exclude=.gitignore \
	--exclude=.travis.yml \
	--exclude=.scrutinizer.yml \
	--exclude=CONTRIBUTING.md \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=composer.phar \
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
	--exclude=ci \
	--exclude=vendor/bin \
	$(project_dir) $(sign_dir)
	# give the webserver user the right to create signature file
	sudo chown $(webserveruser) $(sign_dir)/$(app_name)/appinfo
	sudo -u $(webserveruser) php $(occ_dir)/occ integrity:sign-app --privateKey=$(cert_dir)/$(app_name).key --certificate=$(cert_dir)/$(app_name).crt --path=$(sign_dir)/$(app_name)/
	sudo chown -R $(USER) $(sign_dir)/$(app_name)/appinfo
	tar -czf $(build_dir)/$(app_name)-$(app_version).tar.gz \
		-C $(sign_dir) $(app_name)
	echo NEXTCLOUD------------------------------------------
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(app_version).tar.gz | openssl base64
