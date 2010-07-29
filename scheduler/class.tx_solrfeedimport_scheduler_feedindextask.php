<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Markus Goldbach <markus.goldbach@dkd.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('solr_feedimport') . 'lib/magpierss/rss_fetch.inc');


/**
 * Scheduler Task to read feeds and index them into Solr
 *
 * @package TYPO3
 * @subpackage solr_feedimport
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 */
class tx_solrfeedimport_scheduler_FeedIndexTask extends tx_scheduler_Task {

	/**
	 * itemtype of the indexed Solr document
	 *
	 * @var	string
	 */
	const ITEM_TYPE = 'tx_solrfeedimport_feeditem';

	/**
	 * Solr connection
	 *
	 * @var	tx_solr_SolrService
	 */
	protected $solr = NULL;


	/**
	 * Main method to execute the scheduler task.
	 *
	 * @see	typo3/sysext/scheduler/tx_scheduler_Task::execute()
	 * @return	boolean	If task succesful or not.
	 */
	public function execute() {
		$feedsIndexed = array();
		$previousSolrConnection = NULL;

			//deactivate cache
		define('MAGPIE_CACHE_ON', FALSE);
		//TODO copy the magpie cache to typo3temp
		#define('MAGPIE_CACHE_DIR' , 'typo3temp/solr_feedimport/');
		$feeds = $this->getFeeds();

		foreach ($feeds as $feed) {
			$successFullyIndexed = FALSE;
			$this->solr = $this->getSolrConnectionByFeed($feed);

			if ($previousSolrConnection != $this->solr) {
					// clean old index documents, we need to do a full import,
					// incremental imports don't work due to missing identifiers
				$this->solr->deleteByType(self::ITEM_TYPE);
			}

			$successFullyIndexed        = $this->indexFeed($feed);
			$feedsIndexed[$feed['uid']] = $successFullyIndexed;

			$previousSolrConnection     = $this->solr;
		}

		return $this->feedsIndexedSuccessfully($feedsIndexed);
	}

	protected function getSolrConnectionByFeed(array $feedRecord) {
		try {
			$solr = t3lib_div::makeInstance('tx_solr_ConnectionManager')->getConnectionByPageId(
				$feedRecord['pid'],
				$feedRecord['sys_language_uid']
			);
		} catch (tx_solr_NoSolrConnectionFoundException $e) {
				// TODO logging
			continue;
		}

		return $solr;
	}

	protected function getFeeds() {
			// TODO Load feed URls from DB
			// FIXME Use db function to check id delete and not hidden
		$feeds = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_solrfeedimport_feed',
			'deleted = 0 AND hidden = 0'
		);

		return $feeds;
	}

	protected function indexFeed(array $feedRecord) {
		try {
			$solrDocuments = array();

			$feedContent = fetch_rss($feedRecord['url']);
			$itemIndex   = 0;
			foreach ($feedContent->items as $item) {
				$solrDocuments[] = $this->feedItemToDocument($feedRecord, $item, $itemIndex, $feedContent->encoding);
				$itemIndex++;
			}

			$response = $this->solr->addDocuments($solrDocuments);
			if ($response->getHttpStatus() == 200) {
				$successFullIndexed = TRUE;
			}
		} catch (Exception $e) {
				// TODO logging
			continue;
		}

		return $successFullIndexed;
	}

	protected function feedItemToDocument(array $feed, array $feedItem, $itemIndex, $feedEncoding) {
		$document = t3lib_div::makeInstance('Apache_Solr_Document');

			//field mapping
		$document->addField('type',     self::ITEM_TYPE);
		$document->addField('appKey',   'EXT:solr_feedimport');
		$document->addField('id',       tx_solr_Util::getDocumentId(
			'tx_solrfeedimport_feed',
			$feed['pid'],
			$feed['uid'] . '_' . $itemIndex,
			self::ITEM_TYPE
		));
		$document->addField('siteHash', tx_solr_Util::getSiteHash($feed['pid']));
		$document->addField('title',    ($feedEncoding == 'UTF-8') ? $feedItem['title'] : utf8_encode($feedItem['title']));
		$document->addField('content',  ($feedEncoding == 'UTF-8') ? strip_tags($feedItem['description']) : utf8_encode(strip_tags($feedItem['description'])));
		$document->addField('url',      $this->getLink($feedItem));
		$document->addField('language', $feed['sys_language_uid']);

		return $document;
	}

	protected function feedsIndexedSuccessfully(array $feedIndexedStatuses) {
		$success = TRUE;

		foreach ($feedIndexedStatuses as $feedUid => $feedIndexedStatus) {
			if (!$feedIndexedStatus) {
				$success = FALSE;

					// TODO might throw an exception explaining which feed failed

				break;
			}
		}

		return $success;
	}

	protected function getLink(array $feedItem) {
			//MAGPIERSS use link_ oder link when parse feeds
		if (isset($feedItem['link'])) {
			$feedLink = $feedItem['link'];
		} else {
			$feedLink = $feedItem['link_'];
		}

		return $feedLink;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr_feedimport/scheduler/class.tx_solrfeedimport_scheduler_indextask.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr_feedimport/scheduler/class.tx_solrfeedimport_scheduler_indextask.php']);
}
?>