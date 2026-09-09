<?php

use MediaWiki\MediaWikiServices;
use ST\SemanticTasksMessages;

return [
	'SemanticTasksMessages' => static function ( MediaWikiServices $services ): SemanticTasksMessages {
		$wanCache = $services->getMainWANObjectCache();
		$revisionLookup = $services->getRevisionLookup();
		$dbProvider = $services->getConnectionProvider();
		$srvCache = $services->getObjectCacheFactory()->getLocalServerInstance( CACHE_ANYTHING );
		return new SemanticTasksMessages( $dbProvider, $wanCache, $revisionLookup, $srvCache );
	},
];
