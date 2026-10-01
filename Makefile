.PHONY: assets deploy linter phpcs phpstan tests clean

ROOT_DIR := $(abspath ../../../)

APP_ENV_BAK   := $(APP_ENV)
APP_DEBUG_BAK := $(APP_DEBUG)
ifneq (,$(wildcard $(ROOT_DIR)/.env))
	include $(ROOT_DIR)/.env
endif
ifneq (,$(wildcard $(ROOT_DIR)/.env.$(APP_ENV)))
	include $(ROOT_DIR)/.env.$(APP_ENV)
endif
ifneq ($(strip $(APP_ENV_BAK)),)
	APP_ENV := $(APP_ENV_BAK)
endif
ifneq ($(strip $(APP_DEBUG_BAK)),)
	APP_DEBUG := $(APP_DEBUG_BAK)
endif
export APP_ENV APP_DEBUG

# One-shot production build, as run by the application's
# `make build-vendor omnibase/admin`. It used to start `yarn run
# watch` whenever APP_DEBUG=1 - which build-vendor passes on a dev machine - and
# a watcher never returns, so the command could only hang there. Build once and
# exit; use `cd assets && yarn run watch` by hand to iterate on the sources.
assets:
	@cd assets && yarn install
	@cd assets && yarn run prod

deploy:
	@composer update
	@yarn install

linter: phpstan phpcs

phpcs:
	../../../bin/php-cs-fixer fix src
phpstan:
	../../vendor/bin/phpstan analyse

tests:
	@vendor/bin/phpunit || ../../../vendor/bin/phpunit -c phpunit.xml.dist

clean:
	@$(RM) -rf composer.lock vendor assets/build assets/node_modules assets/package-lock.json assets/yarn.lock .phpunit.result.cache
