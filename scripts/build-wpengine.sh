#!/usr/bin/env bash
#
# Builds the artifact that is deployed to WP Engine: a flat wp-content
# directory in build/wp-content/.
#
# WP Engine provides WordPress core and wp-config.php, so only wp-content is
# deployed. Bedrock keeps wp-content in web/app and Composer's vendor
# directory at the project root, so the build:
#
#   1. installs the locked production dependencies with the paths rewritten
#      to the WP Engine layout (plugins, themes and vendor inside wp-content),
#      which makes Composer generate an autoloader with the right paths;
#   2. copies the code this repository owns (northwind-core and the theme);
#   3. adds a must-use plugin that loads the Composer autoloader, because
#      WP Engine's wp-config.php does not.
#
# Usage: scripts/build-wpengine.sh
# Needs: php, composer, and COMPOSER_AUTH (or auth.json) for the ACF repository.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD="${ROOT}/build"
CONTENT="${BUILD}/wp-content"

echo "Cleaning ${BUILD}"
rm -rf "${BUILD}"
mkdir -p "${CONTENT}"

cp "${ROOT}/composer.json" "${ROOT}/composer.lock" "${BUILD}/"

echo "Rewriting Composer paths for the WP Engine layout"
php -r '
	$file = $argv[1];
	$json = json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );

	$json["config"]["vendor-dir"]           = "wp-content/vendor";
	$json["extra"]["wordpress-install-dir"] = "wp-core-unused";
	$json["extra"]["installer-paths"]       = array(
		"wp-content/mu-plugins/{\$name}/" => array( "type:wordpress-muplugin" ),
		"wp-content/plugins/{\$name}/"    => array( "type:wordpress-plugin" ),
		"wp-content/themes/{\$name}/"     => array( "type:wordpress-theme" ),
	);
	$json["autoload"]["psr-4"] = array(
		"Northwind\\Core\\" => "wp-content/mu-plugins/northwind-core/src/",
	);
	unset( $json["scripts"] );

	file_put_contents( $file, json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
' "${BUILD}/composer.json"

echo "Copying project code"
mkdir -p "${CONTENT}/mu-plugins" "${CONTENT}/plugins" "${CONTENT}/themes"
cp "${ROOT}/web/app/mu-plugins/bedrock-autoloader.php" "${CONTENT}/mu-plugins/"
cp -R "${ROOT}/web/app/mu-plugins/northwind-core" "${CONTENT}/mu-plugins/"
cp -R "${ROOT}/web/app/themes/northwind" "${CONTENT}/themes/"

cat > "${CONTENT}/mu-plugins/00-composer-autoload.php" <<'PHP'
<?php
/**
 * Plugin Name: Composer autoloader
 * Description: Loads the Composer autoloader shipped in wp-content/vendor. Added by scripts/build-wpengine.sh; WP Engine's wp-config.php does not load it.
 */

if ( is_readable( WP_CONTENT_DIR . '/vendor/autoload.php' ) ) {
	require_once WP_CONTENT_DIR . '/vendor/autoload.php';
}
PHP

echo "Installing production dependencies"
# The lock file stays authoritative. Composer warns that the lock file is out
# of date because composer.json was rewritten above; that warning is expected.
(
	cd "${BUILD}"
	composer install \
		--no-dev \
		--no-interaction \
		--no-progress \
		--prefer-dist \
		--optimize-autoloader \
		--classmap-authoritative
)

echo "Removing build-only files"
rm -rf "${BUILD}/wp-core-unused" "${BUILD}/composer.json" "${BUILD}/composer.lock"

echo "Checking PHP syntax"
find "${CONTENT}/mu-plugins/northwind-core" "${CONTENT}/themes/northwind" "${CONTENT}/mu-plugins/00-composer-autoload.php" \
	-name '*.php' -print0 | xargs -0 -n 1 php -l > /dev/null

echo "Checking the autoloader"
php -r '
	require $argv[1];
	foreach ( array( "Northwind\\Core\\Region", "Northwind\\Core\\CLI\\RedirectsCommand", "Roots\\Bedrock\\Autoloader" ) as $class ) {
		if ( ! class_exists( $class ) ) {
			fwrite( STDERR, "Missing class {$class}\n" );
			exit( 1 );
		}
	}
' "${CONTENT}/vendor/autoload.php"

echo
echo "Built ${CONTENT}"
du -sh "${CONTENT}"/* | sed "s|${ROOT}/||"
