PHP ?=
TEST ?=
FILTER ?= .

docs:
	docker run --rm -v $$(pwd)/docs:/data/docs -w /data php:cli bash -c "\
		apt-get update && apt-get install -y wget git zip unzip;\
		docker-php-ext-install zip;\
		wget -q -O - https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer;\
		wget -q https://phpdoc.org/phpDocumentor.phar;\
		TEMPLATE=\$$(grep -o "{{#TAGS}}.*{{/TAGS}}" docs/index.html);\
		HTML='';\
		git clone https://github.com/matthiasmullie/router.git code && cd code;\
		while read TAG; do\
			git clean -fx;\
			git checkout \$$TAG;\
			git reset --hard;\
			composer install --ignore-platform-reqs;\
			php ../phpDocumentor.phar --directory=src --target=../docs/\$$TAG --visibility=public --defaultpackagename=Router --title='Router';\
			HTML=\$$HTML\$$(echo \$$TEMPLATE | sed -e \"s/{{[#/]TAGS}}//g\" | sed -e \"s/{{\.}}/\$$TAG/g\");\
		done <<< \$$(git rev-parse --abbrev-ref HEAD && git tag --sort=-v:refname | cat);\
		sed -i \"s|\$$TEMPLATE|\$$HTML|g\" ../docs/index.html"

test:
	VERSION=$$(echo "$(PHP)-cli" | sed "s/^-//");\
	test $$(docker images -q matthiasmullie/router:$$VERSION) || docker build -t matthiasmullie/router:$$VERSION . --build-arg VERSION=$$VERSION;\
	docker run -v $$(pwd)/src:/var/www/src -v $$(pwd)/tests:/var/www/tests -v $$(pwd)/phpunit.xml:/var/www/phpunit.xml matthiasmullie/router:$$VERSION env XDEBUG_MODE=off vendor/bin/phpunit $(TEST) --filter $(FILTER)

coverage:
	VERSION=$$(echo "$(PHP)-cli" | sed "s/^-//");\
	test $$(docker images -q matthiasmullie/router:$$VERSION) || docker build -t matthiasmullie/router:$$VERSION . --build-arg VERSION=$$VERSION;\
	docker run -v $$(pwd)/src:/var/www/src -v $$(pwd)/tests:/var/www/tests -v $$(pwd)/build:/var/www/build -v $$(pwd)/phpunit.xml:/var/www/phpunit.xml matthiasmullie/router:$$VERSION env XDEBUG_MODE=off php -d pcov.enabled=1 -d pcov.directory="src" vendor/bin/phpunit $(TEST) --filter $(FILTER) --coverage-clover build/coverage-$(PHP).clover

profile:
	VERSION=$$(echo "$(PHP)-cli" | sed "s/^-//");\
	test $$(docker images -q matthiasmullie/router:$$VERSION) || docker build -t matthiasmullie/router:$$VERSION . --build-arg VERSION=$$VERSION;\
	docker run -v $$(pwd)/src:/var/www/src -v $$(pwd)/tests:/var/www/tests -v $$(pwd)/build:/var/www/build -v $$(pwd)/phpunit.xml:/var/www/phpunit.xml matthiasmullie/router:$$VERSION env XDEBUG_MODE=off php -d xdebug.mode=profile -d xdebug.profiler_output_name=cachegrind.out -d xdebug.output_dir=build vendor/bin/phpunit $(TEST) --filter $(FILTER)

format:
	VERSION=$$(echo "$(PHP)-cli" | sed "s/^-//");\
	test $$(docker images -q matthiasmullie/router:$$VERSION) || docker build -t matthiasmullie/router:$$VERSION . --build-arg VERSION=$$VERSION;\
	docker run -v $$(pwd)/src:/var/www/src -v $$(pwd)/tests:/var/www/tests -v $$(pwd)/.php-cs-fixer.php:/var/www/.php-cs-fixer.php matthiasmullie/router:$$VERSION vendor/bin/php-cs-fixer fix

composer_update:
	VERSION=$$(echo "$(PHP)-cli" | sed "s/^-//");\
	docker image rm -f matthiasmullie/router:$$VERSION;\
	echo '{}' > composer.lock;\
	docker build -t matthiasmullie/router:$$VERSION . --build-arg VERSION=$$VERSION;\
	docker run -v $$(pwd)/src:/var/www/src -v $$(pwd)/tests:/var/www/tests -v $$(pwd)/composer.json:/var/www/composer.json -v $$(pwd)/composer.lock:/var/www/composer.lock -v $$(pwd)/vendor:/var/www/vendor matthiasmullie/router:$$VERSION composer update

.PHONY: docs
