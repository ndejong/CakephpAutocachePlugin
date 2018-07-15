<?php

/****************************************************************************
 * Cakephp AutocachePlugin
 * Verb Networks Pty Ltd - http://verbnetworks.com - https://github.com/verbnetworks
 * 
 * @author Nicholas de Jong
 * @link https://github.com/verbnetworks/CakephpAutocachePlugin
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
