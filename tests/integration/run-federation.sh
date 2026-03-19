#!/usr/bin/env bash

# Federation integration test runner for Cospend
# Sets up two Nextcloud instances (LOCAL + REMOTE) and runs Behat federation tests.
#
# Usage:
#   ./run-federation.sh [behat-scenario-or-feature-path]
#
# Environment:
#   REMOTE_ROOT_DIR  (optional) Path to an existing second Nextcloud installation.
#                   If unset, a fresh instance is created under data/.

set -e

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
OCC="${ROOT_DIR}/occ"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCENARIO_TO_RUN=$1

LOCAL_PORT=8080
REMOTE_PORT=8280

# ---- Verify the main instance is installed ----

INSTALLED=$(php "$OCC" status | grep installed: | cut -d " " -f 5)
if [ "$INSTALLED" != "true" ]; then
	echo "Nextcloud instance needs to be installed first" >&2
	exit 1
fi

# ---- Set up the remote Nextcloud instance ----

if [ -z "$REMOTE_ROOT_DIR" ]; then
	REMOTE_ROOT_DIR="${ROOT_DIR}/data/tests-cospend-federated-server"

	echo "Setting up local federated Nextcloud instance at ${REMOTE_ROOT_DIR}"

	rm -rf "${REMOTE_ROOT_DIR}"
	mkdir -p "${REMOTE_ROOT_DIR}"

	# Symlink all server files except data/ and config/ so we share source code
	for item in "${ROOT_DIR}"/*; do
		name=$(basename "$item")
		if [ "$name" != "data" ] && [ "$name" != "config" ]; then
			ln -sf "$item" "${REMOTE_ROOT_DIR}/${name}"
		fi
	done

	mkdir -p "${REMOTE_ROOT_DIR}/data"
	mkdir -p "${REMOTE_ROOT_DIR}/config"

	REMOTE_OCC="NEXTCLOUD_CONFIG_DIR=${REMOTE_ROOT_DIR}/config php ${REMOTE_ROOT_DIR}/occ"

	eval $REMOTE_OCC maintenance:install \
		--database=sqlite \
		--admin-user=admin \
		--admin-pass=admin \
		--data-dir="${REMOTE_ROOT_DIR}/data"

	eval $REMOTE_OCC config:system:set hashing_default_password --value=true --type=boolean
	eval $REMOTE_OCC app:enable --force cospend
else
	echo "Using external remote Nextcloud instance at ${REMOTE_ROOT_DIR}"
	REMOTE_OCC="NEXTCLOUD_CONFIG_DIR=${REMOTE_ROOT_DIR}/config php ${REMOTE_ROOT_DIR}/occ"
fi

MAIN_SERVER_CONFIG_DIR="${ROOT_DIR}/config"
REMOTE_SERVER_CONFIG_DIR="${REMOTE_ROOT_DIR}/config"

# ---- Install Behat dependencies ----

# Server-level Behat vendor (provides shared step definitions)
(
	cd "${ROOT_DIR}/vendor-bin/behat"
	composer install --no-interaction
)

# App-level integration test dependencies
(
	cd "${APP_DIR}/tests/integration"
	composer install --no-interaction
)

# ---- Configure LOCAL instance ----

php "$OCC" app:enable --force cospend

php "$OCC" config:system:set allow_local_remote_servers --value=true --type=boolean
php "$OCC" config:system:set auth.bruteforce.protection.enabled --value=false --type=boolean
php "$OCC" config:system:set ratelimit.protection.enabled --value=false --type=boolean
php "$OCC" config:system:set debug --value=true --type=boolean
php "$OCC" config:system:set hashing_default_password --value=true --type=boolean
php "$OCC" config:app:set cospend federation_enabled --value=1
php "$OCC" config:app:set cospend federation_incoming_enabled --value=1
php "$OCC" config:app:set cospend federation_outgoing_enabled --value=1
php "$OCC" config:app:set files_sharing outgoing_server2server_share_enabled --value=yes
php "$OCC" config:app:set files_sharing incoming_server2server_share_enabled --value=yes
php "$OCC" config:system:set trusted_domains 0 --value="localhost:${LOCAL_PORT}"

# ---- Configure REMOTE instance ----

eval $REMOTE_OCC config:system:set allow_local_remote_servers --value=true --type=boolean
eval $REMOTE_OCC config:system:set auth.bruteforce.protection.enabled --value=false --type=boolean
eval $REMOTE_OCC config:system:set ratelimit.protection.enabled --value=false --type=boolean
eval $REMOTE_OCC config:system:set debug --value=true --type=boolean
eval $REMOTE_OCC config:app:set cospend federation_enabled --value=1
eval $REMOTE_OCC config:app:set cospend federation_incoming_enabled --value=1
eval $REMOTE_OCC config:app:set cospend federation_outgoing_enabled --value=1
eval $REMOTE_OCC config:app:set files_sharing outgoing_server2server_share_enabled --value=yes
eval $REMOTE_OCC config:app:set files_sharing incoming_server2server_share_enabled --value=yes
eval $REMOTE_OCC config:system:set trusted_domains 0 --value="localhost:${REMOTE_PORT}"

# ---- Start PHP built-in servers ----

echo "Starting LOCAL server on localhost:${LOCAL_PORT}"
PHP_CLI_SERVER_WORKERS=3 php -S "localhost:${LOCAL_PORT}" -t "${ROOT_DIR}" &
LOCAL_PID=$!

echo "Starting REMOTE server on localhost:${REMOTE_PORT}"
NEXTCLOUD_CONFIG_DIR="${REMOTE_ROOT_DIR}/config" PHP_CLI_SERVER_WORKERS=3 php -S "localhost:${REMOTE_PORT}" -t "${ROOT_DIR}" &
REMOTE_PID=$!

sleep 2

if ! curl -sf "http://localhost:${LOCAL_PORT}/status.php" > /dev/null; then
	echo "LOCAL server failed to start" >&2
	kill -9 $LOCAL_PID $REMOTE_PID 2>/dev/null
	exit 1
fi

if ! curl -sf "http://localhost:${REMOTE_PORT}/status.php" > /dev/null; then
	echo "REMOTE server failed to start" >&2
	kill -9 $LOCAL_PID $REMOTE_PID 2>/dev/null
	exit 1
fi

echo "Both servers are running"

# ---- Export environment variables for Behat ----

export TEST_SERVER_URL="http://localhost:${LOCAL_PORT}/"
export TEST_REMOTE_URL="http://localhost:${REMOTE_PORT}/"
export NEXTCLOUD_HOST_ROOT_DIR="${ROOT_DIR}"
export NEXTCLOUD_HOST_CONFIG_DIR="${MAIN_SERVER_CONFIG_DIR}"
export NEXTCLOUD_REMOTE_ROOT_DIR="${REMOTE_ROOT_DIR}"
export NEXTCLOUD_REMOTE_CONFIG_DIR="${REMOTE_SERVER_CONFIG_DIR}"

# ---- Run Behat federation tests ----

cd "${APP_DIR}/tests/integration"

if [ -n "$SCENARIO_TO_RUN" ]; then
	vendor/bin/behat --config config/behat.yml --colors --suite=federation "$SCENARIO_TO_RUN"
else
	vendor/bin/behat --config config/behat.yml --colors --suite=federation
fi
RESULT=$?

# ---- Cleanup ----

kill -9 $LOCAL_PID $REMOTE_PID 2>/dev/null

echo "Federation tests finished with exit code: $RESULT"
exit $RESULT
