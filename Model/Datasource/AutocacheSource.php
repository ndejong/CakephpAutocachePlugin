<?php

/****************************************************************************
 * Cakephp AutocachePlugin
 * Nicholas de Jong - http://nicholasdejong.com - https://github.com/ndejong
 * 
 * Very big thanks to Mark Scherer for setting me straight on _queryCount() and 
 * help with refactoring CakephpAutocacheBehaviour into CakephpAutocachePlugin
 *  - https://github.com/dereuromark
 * 
 * @author Nicholas de Jong
 * @link https://github.com/ndejong/CakephpAutocachePlugin
 ****************************************************************************/

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
