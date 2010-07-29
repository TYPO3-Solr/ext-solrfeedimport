<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_solrfeedimport_feed'] = array(
	'ctrl' => array(
		'title'                    => 'LLL:EXT:solr_feedimport/locallang_db.xml:tx_solrfeedimport_feed',
		'label'                    => 'description',
		'tstamp'                   => 'tstamp',
		'crdate'                   => 'crdate',
		'cruser_id'                => 'cruser_id',
		'languageField'            => 'sys_language_uid',
		'transOrigPointerField'    => 'l18n_parent',
		'transOrigDiffSourceField' => 'l18n_diffsource',
		'default_sortby'           => 'ORDER BY crdate',
		'delete'                   => 'deleted',
		'enablecolumns'            => array(
			'disabled' => 'hidden',
		),
		'dynamicConfigFile'        => t3lib_extMgm::extPath($_EXTKEY) . 'tca.php',
		'iconfile'                 => t3lib_extMgm::extRelPath($_EXTKEY) . 'icon_tx_solrfeedimport_feed.gif',
	),
	'feInterface' => array()
);
?>