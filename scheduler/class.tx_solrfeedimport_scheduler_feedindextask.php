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
	 * Type of the indexed Solr document
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
	 * @return	boolean	True if the task executed without errors, false otherwise.
	 */
	public function execute() {
		$indexedFeeds = array();
		$previousSolrConnection = NULL;

			//deactivate cache
		define('MAGPIE_CACHE_ON', FALSE);
			//TODO copy the magpie cache to typo3temp
		$feeds = $this->getFeeds();

		foreach ($feeds as $feed) {
			$this->solr = $this->getSolrConnectionByFeed($feed);

			if ($previousSolrConnection != $this->solr) {
					// clean old index documents, we need to do a full import,
					// incremental imports don't work due to missing identifiers
				$this->solr->deleteByType(self::ITEM_TYPE);
				$this->solr->commit();
			}

			$indexedFeeds[$feed['uid']] = $this->indexFeed($feed);
			$this->solr->commit();

			$previousSolrConnection = $this->solr;
		}

		return $this->feedsIndexedSuccessfully($indexedFeeds);
	}

	/**
	 * Get the solr connection for a feed record
	 *
	 * @param	array	An feed record
	 * @return	tx_solr_SolrService	The solr connection for a feed record.
	 */
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

	/**
	 * Get all active feeds from database
	 *
	 * @return	array	All active feeds
	 */
	protected function getFeeds() {
		$feeds = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_solrfeedimport_feed',
			'deleted = 0 AND hidden = 0'
		);

		return $feeds;
	}

	/**
	 * Index the content of a single feed
	 *
	 * @param	array	A feed record
	 * @return	boolean	status of indexing
	 */
	protected function indexFeed(array $feedRecord) {
		$indexed = FALSE;

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
				$indexed = TRUE;
			}
		} catch (Exception $e) {
				// TODO logging
			continue;
		}

		return $indexed;
	}

	/**
	 * Transforms a feed item into a Solr document
	 *
	 * @param	array	Feed record of the feed item
	 * @param	array	The feed element which you want to index
	 * @param	integer	Iterator number of feed element
	 * @param	string	Encodeing of feed
	 * @return	Apache_Solr_Document a solr document
	 */
	protected function feedItemToDocument(array $feed, array $feedItem, $itemIndex, $feedEncoding) {
		$document = t3lib_div::makeInstance('Apache_Solr_Document');

			// field mapping
		$document->addField('type',     self::ITEM_TYPE);
		$document->addField('appKey',   'EXT:solr_feedimport');
		$document->addField('id',       tx_solr_Util::getDocumentId(
			'tx_solrfeedimport_feed',
			$feed['pid'],
			$feed['uid'] . '_' . $itemIndex
		));
		$document->addField('siteHash', tx_solr_Util::getSiteHash($feed['pid']));
#		$document->addField('title',    $this->getUtf8EncodedString($feedItem['title'], $feedEncoding));
#		$document->addField('content',  $this->getUtf8EncodedString(strip_tags($feedItem['description']), $feedEncoding));
		$document->addField('title',    ($feedEncoding == 'UTF-8') ? $feedItem['title'] : utf8_encode($feedItem['title']));
		$document->addField('content',  ($feedEncoding == 'UTF-8') ? strip_tags($feedItem['description']) : utf8_encode(strip_tags($feedItem['description'])));
		$document->addField('url',      $this->getLink($feedItem));
		$document->addField('group',    '0');
		$document->addField('language', $feed['sys_language_uid']);

		return $document;
	}

	/**
	 * Returns an utf-8 encoded string. If the string is not utf-8 encoded yet,
	 * it is converted to utf-8.
	 *
	 * @param	string	String to be checked and converted on demand.
	 * @param	string	Encoding of the string.
	 * @return	string	Utf-8 encoded string.
	 */
	protected function getUtf8EncodedString($string, $sourceEncoding) {
		$utf8Aliases = array('utf-8', 'utf8', 'UTF-8', 'UTF8');
		$utf8EncodedString = '';

		$charsetConverter = t3lib_div::makeInstance('t3lib_cs');

		if (in_array($sourceEncoding, $utf8Aliases)) {
			$utf8EncodedString = $string;
		} else {
			$utf8EncodedString = $charsetConverter->utf8_encode($string, $sourceEncoding);
		}

		return $utf8EncodedString;
	}

	/**
	 * Checks if all feed items have been indexed
	 *
	 * @param	array	Array of all Solr responses
	 * @return	boolean	Over all status of all indexed items
	 */
	protected function feedsIndexedSuccessfully(array $feedIndexedStatuses) {
		$indexed = TRUE;

		foreach ($feedIndexedStatuses as $feedUid => $feedIndexedStatus) {
			if (!$feedIndexedStatus) {
				$indexed = FALSE;

					// TODO might throw an exception explaining which feed failed

				break;
			}
		}

		return $indexed;
	}

	/**
	 * Gets the link for the feed item.
	 * MAGPIERSS uses link_ or link index when parsing feeds
	 *
	 * @param	array	A feed item from which you want the link
	 * @return	string	The link of a feed item
	 */
	protected function getLink(array $feedItem) {

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