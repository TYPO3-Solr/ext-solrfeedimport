<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

	// adding scheduler tasks
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['tx_solrfeedimport_scheduler_feedindextask'] = array(
	'extension'   => $_EXTKEY,
	'title'       => 'Solr Feed Indexer',
	'description' => 'Indexes RSS and Atom Feeds.'
);

?>