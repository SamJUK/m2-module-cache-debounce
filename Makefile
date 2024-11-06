MAKEFLAGS += --always-make
.DEFAULT_GOAL := help


# Assuming the project is located within app/code/<vendor>/<module>
RELATIVE_PROJECT_DIR ?= ../../..
MODULE_DIR ?= .


## 
# Local Commands
##

local-tests:
	bash _local_test.sh


##
# Individual Tests
##
test-phpcs:
	${RELATIVE_PROJECT_DIR}/vendor/bin/phpcs --standard=Magento2 --extensions=php,phtml -p ${MODULE_DIR} --severity=6

test-composer:
	composer validate 2>/dev/null

test-compile:
	php ${RELATIVE_PROJECT_DIR}/bin/magento setup:di:compile

test-phpstan:
	${RELATIVE_PROJECT_DIR}/vendor/bin/phpstan analyse -c ${MODULE_DIR}/phpstan.neon ${MODULE_DIR}

test-unit:
	${RELATIVE_PROJECT_DIR}/vendor/bin/phpunit ${MODULE_DIR}/Test/Unit

test-integration:
	echo "!!! NOT IMPLEMENTED !!!"






##
# HELP
##
help:  ## Prints the help document
	@egrep -h '\s##\s' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m  %-30s\033[0m %s\n", $$1, $$2}'