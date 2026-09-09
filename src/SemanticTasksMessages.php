<?php

namespace ST;

use MediaWiki\Content\TextContent;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IConnectionProvider;

class SemanticTasksMessages {
	private const CACHE_VERSION = 4;
	private const CACHE_TTL = 86400; // 1 day
	private const MESSAGES_CLASS_VERSION = 1;

	/** @var array|null */
	private $definitions;

	private IConnectionProvider $dbProvider;
	private WANObjectCache $wanCache;
	private RevisionLookup $revLookup;
	private BagOStuff $srvCache;
	private string $messagesArticle;

	public function __construct(
		IConnectionProvider $dbProvider,
		WANObjectCache $wanCache,
		RevisionLookup $revLookup,
		BagOStuff $srvCache
	) {
		global $stgMessagesArticle;

		$this->dbProvider = $dbProvider;
		$this->wanCache = $wanCache;
		$this->revLookup = $revLookup;
		$this->srvCache = $srvCache;
		$this->messagesArticle = $stgMessagesArticle;
	}

	/**
	 * Handle page updates to purge cache
	 *
	 * @param LinkTarget $target
	 */
	public function handlePageUpdate( LinkTarget $target ): void {
		if (
			$target->isKnown() &&
			$target->getFullText() === $this->messagesArticle
		) {
			$this->purgeDefinitionCache();
		}
	}

	/**
	 * Purge the definitions cache
	 */
	private function purgeDefinitionCache(): void {
		$key = $this->makeDefinitionCacheKey( $this->wanCache );

		$this->wanCache->delete( $key );
		$this->srvCache->delete( $key );
		$this->definitions = null;
	}

	/**
	 * Make cache key
	 *
	 * @param WANObjectCache $cache
	 * @return string
	 */
	private function makeDefinitionCacheKey( WANObjectCache $cache ): string {
		return $cache->makeKey(
			'semantictasks-messages-definition',
			self::MESSAGES_CLASS_VERSION,
			self::CACHE_VERSION
		);
	}

	/**
	 * Load messages from cache or database
	 *
	 * @return array
	 */
	protected function getContents(): array {
		if ( defined( 'MW_PHPUNIT_TEST' ) && MediaWikiServices::getInstance()->isStorageDisabled() ) {
			return [];
		}

		if ( $this->definitions === null ) {
			$key = $this->makeDefinitionCacheKey( $this->wanCache );
			$this->definitions = $this->srvCache->getWithSetCallback(
				$key,
				mt_rand( 7, 15 ),
				function () use ( $key ) {
					return $this->wanCache->getWithSetCallback(
						$key,
						self::CACHE_TTL,
						function ( $old, &$ttl, &$setOpts ) {
							$setOpts += Database::getCacheSetOptions(
								$this->dbProvider->getReplicaDatabase()
							);
							return $this->fetchStructuredList();
						},
						[
							'version' => 2,
							'lockTSE' => 300,
						]
					);
				}
			);
		}
		return $this->definitions;
	}

	/**
	 * Fetch messages from the wiki page
	 *
	 * @return array
	 */
	public function fetchStructuredList(): array {
		$title = Title::newFromText( $this->messagesArticle );
		$revRecord = $this->revLookup->getRevisionByTitle( $title );

		if ( !$revRecord ) {
			return [];
		}

		$content = $revRecord->getContent( SlotRecord::MAIN );
		if ( !$content || !( $content instanceof TextContent ) || $content->isEmpty() ) {
			return [];
		}

		$text = $content->getText();
		$messages = $this->parseContents( $text );

		wfDebug( __METHOD__ . ": SemanticTasksMessages parsed, cache entry should be updated\n" );

		return $messages;
	}

	/**
	 * Parse translation file format into key-value array
	 * 
	 * Format expected:
	 * == Section Name ==
	 * key|value
	 * key|value
	 * 
	 * @param string $content File content
	 * @return array Associative array with message keys and values
	 */
	private function parseContents( string $content ): array {
		$result = [];
		$lines = explode( "\n", $content );
		$inSection = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( $line === '' ) {
				continue;
			}

			// discard section header
			if ( preg_match( '/^==\s*(.+?)\s*==$/', $line, $matches ) ) {
				$inSection = true;
				continue;
			}

			// Parse key|value lines
			if ( $inSection && strpos( $line, '|' ) !== false ) {
				$parts = explode( '|', $line, 2 );
				$key = trim( $parts[0] );
				$value = isset( $parts[1] ) ? trim( $parts[1] ) : '';
				
				if ( $key !== '' ) {
					$result[$key] = $value;
				}
			}
		}
		
		return $result;
	}

	/**
	 * @param string $key Message key
	 * @param mixed ...$args parameters
	 * @return string|null
	 */
	public function getMessage( string $key, ...$args ): ?string {
		$messages = $this->getContents();

		if ( !isset( $messages[$key] ) ) {
			return null;
		}
		
		$message = $messages[$key];

		// Replace parameters
		if ( !empty( $args ) ) {
			$i = 1;
			foreach ( $args as $arg ) {
				$message = str_replace( '$' . $i, (string)$arg, $message );
				$i++;
			}
		}

		return $message;
	}
}
