<?php

########################################################################
# Extension Manager/Repository config file for ext "solr_feedimport".
#
# Auto generated 27-07-2010 10:32
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Solr RSS and Atom Feed Import',
	'description' => 'Imports RSS and Atom Feeds into Solr',
	'category' => 'misc',
	'author' => 'Markus Goldbach',
	'author_email' => 'markus.goldbach@dkd.de',
	'shy' => '',
	'dependencies' => 'solr',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.0.3',
	'constraints' => array(
		'depends' => array(
			'solr' => '1.2.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
);

?>