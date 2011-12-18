<?php

/* ***************************************************************************
 * Cakephp AutocachePlugin
 * Nicholas de Jong - http://nicholasdejong.com - https://github.com/ndejong
 * 18 December 2011
 * 
 * Very big thanks to Mark Scherer for setting me straight on _queryCount() and 
 * help with refactoring AutocacheBehaviour into AutocachePlugin
 *  - https://github.com/dereuromark
 * 
 * @author Nicholas de Jong
 * @copyright Nicholas de Jong
 * ***************************************************************************/

/**
 * AutocacheSource
 */
class AutocacheSource extends DataSource {

	/**
	 * __construct
	 * 
	 * @param array $config 
	 */
	public function __construct($config = array()) {
		parent::__construct($config);
	}

	/**
	 * isConnected
	 * 
	 * @return bool 
	 */
	function isConnected() {
		return true;
	}

}
