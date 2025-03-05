<?php

namespace ST;

use SMW\DataValueFactory;
use SMW\Query\PrintRequest;
use SMWQueryProcessor;

class Query {

	/**
	 * This function returns the results of a certain query.
	 * Thank you Yaron Koren for advice concerning this code.
	 *
	 * @param string $query_string The query
	 * @param array(String) $properties_to_display Array of property names to display
	 * @param bool $display_title Add the page title in the result
	 * @return \SMWQueryResult
	 */
	public static function getQueryResults( $query_string, array $properties_to_display, $display_title ) {
		// We use the Semantic MediaWiki Processor
		$params = [];
		$inline = true;
		$printouts = [];

		// add the page name to the printouts
		if ( $display_title ) {
			SMWQueryProcessor::addThisPrintout( $printouts, $params );
		}

		// Push the properties to display in the printout array.
		foreach ( $properties_to_display as $property ) {
			$to_push = new PrintRequest(
				PrintRequest::PRINT_PROP,
				$property,
				DataValueFactory::getInstance()->newPropertyValueByLabel( $property )
			);
			array_push( $printouts, $to_push );
		}

		$params = SMWQueryProcessor::getProcessedParams( $params, $printouts );

		$query = SMWQueryProcessor::createQuery( $query_string, $params, $inline, null, $printouts );
		$results = smwfGetStore()->getQueryResult( $query );

		return $results;
	}
}
