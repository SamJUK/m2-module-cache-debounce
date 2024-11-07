#!/usr/bin/env bash
CWD="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
trap "docker rm -f magento-project-community-edition" SIGINT
set -e

export VENDOR="samjuk"
export MODULE="m2-module-cache-debounce"
export MODULE_DIR_BASE="/data/extensions"
export MODULE_DIR="${MODULE_DIR_BASE}/${MODULE}"
export RELATIVE_PROJECT_DIR="../.."

echo "[i] Executing Unit Tests Locally, using Github Matrix Configuration"
VERSIONS=$(yq '.jobs.tests.strategy.matrix.include | map(.PHP_VERSION + ":" + .MAGENTO_VERSION)' .github/workflows/ci.yml -o shell | awk -F= '{print $2}' | tr -d "'")
while IFS= read -r VER; do
  export PHP_VER=$(echo "${VER}" | awk -F: '{print $1}')
  export MAGE_VER=$(echo "${VER}" | awk -F: '{print $2}')
  echo "[i] Testing ${MAGE_VER} on ${PHP_VER}"

  docker rm -f magento-project-community-edition
  docker run --detach --name magento-project-community-edition \
    -e MODULE_DIR=${MODULE_DIR} -e RELATIVE_PROJECT_DIR=${RELATIVE_PROJECT_DIR} \
    michielgerritsen/magento-project-community-edition:${PHP_VER}-magento${MAGE_VER}

  docker cp ${CWD}/ magento-project-community-edition:${MODULE_DIR}/
  docker exec magento-project-community-edition composer require ${VENDOR}/${MODULE}:@dev
  docker exec magento-project-community-edition make -C ${MODULE_DIR} test-composer
  docker exec magento-project-community-edition make -C ${MODULE_DIR} test-phpstan
  docker exec magento-project-community-edition make -C ${MODULE_DIR} test-phpcs
  docker exec magento-project-community-edition make -C ${MODULE_DIR} test-unit
  docker exec magento-project-community-edition make -C ${MODULE_DIR} test-compile
  docker exec magento-project-community-edition make -C ${MODULE_DIR} test-integration
  docker rm -f magento-project-community-edition
done <<< "${VERSIONS}"

