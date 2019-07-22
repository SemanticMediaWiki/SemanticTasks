<?php
if ( PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' ) {
	die( 'Not an entry point' );
}
if ( !is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
	die( 'You need to install this package with Composer before you can run the tests' );
}
print sprintf( "\n%-20s%s\n", "Semantic Tasks: ", SEMANTIC_TASKS );
$autoloader = require __DIR__ . '/../vendor/autoload.php';
$autoloader->addPsr4( 'ST\\Tests\\', __DIR__ . '/phpunit' );
$autoloader->addPsr4( 'SMW\\Test\\', __DIR__ . '/../../SemanticMediaWiki/tests/phpunit' );
$autoloader->addPsr4( 'SMW\\Tests\\', __DIR__ . '/../../SemanticMediaWiki/tests/phpunit' );
