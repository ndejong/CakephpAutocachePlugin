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

App::uses('AutocacheException', 'Autocache.Error/Exception');

class AutocacheBehavior extends ModelBehavior {

	/**
	 * $runtime - stores runtime configuration parameters
	 * 
	 * @var array 
	 */
	public $runtime = array();

	/**
	 * $cachename_prefix
	 * 
	 * @var string 
	 */
	public $cachename_prefix = 'autocache';

	/**
	 * $__cached_results - stores the cached results while the fall through on the 
	 * dummy datasource occurs - enables the return of cached data in afterFind()
	 * 
	 * @var array
	 */
	private $__cached_results = null;

	/**
	 * setup
	 * 
	 * @param Model $model
	 * @param array $config 
	 */
	public function setup(Model $model, $config = array()) {
		
		// catch and adjust old $config parameter names that would have been used rarely and were 
		// confusingly named:-
		//  - the "check_cache" parameter has been renamed to "cache_config_name_check"
		//  - the "default_cache" parameter has been renamed to "cache_config_name_default"
		if(isset($config['check_cache'])) {
			$config['cache_config_name_check'] = $config['check_cache'];
			unset($config['check_cache']);
			CakeLog::warning('Use of deprecated Autocache config parameter detected', 'check_cache');
		}
		if(isset($config['default_cache'])) {
			$config['cache_config_name_default'] = $config['default_cache'];
			unset($config['default_cache']);
			CakeLog::warning('Use of deprecated Autocache config parameter detected', 'default_cache');
		}

		// > cache_config_name_check - determines if we bother checking if the supplied
		// cache configuration name is valid - prevents the developer thinking they are 
		// caching when they are not - will throw a cache expection if fails this check
		//
		// > cache_config_name_default - is the default cache name, which by default is 
		// the string "default" - confused?  You just need to make sure you have an 
		// appropriate Cache::config('default',array(...)) in your bootstrap.php 
		// or core.php
		//
		// > dummy_datasource - name of the dummy data source in the database.php file 
		// should look something like this:-
		// public $autocache = array('datasource' => 'AutocacheSource');

		$this->runtime = array_merge(array(
			
			// check if the named cache config is loaded
			'cache_config_name_check' => ( Configure::read('debug') > 0) ? true : false,
			
			// default cache *config* name if no cache name is provided by developer
			'cache_config_name_default' => 'default',
			
			// name of the autocache dummy datasource *config* name
			'dummy_datasource' => 'autocache',
			
		), (array) $config);
	}

	/**
	 * beforeFind
	 * 
	 * @param Model $model
	 * @param array $query 
	 */
	public function beforeFind(Model $model, $query) {

		// Provides a place in the Model that we can use to find out what 
		// autocache did on the last query
		$model->autocache_is_from = false;
		
		// Determine if we are even going to try using the cache
		if (!isset($query['autocache']) || ($query['autocache'] === false)) {
			return true; // return early as we have nothing to do
		}

		// Do the required cache query setup
		$this->_doCachingRuntimeSetup($model, $query);

		// Load cached results if they are available
		$this->_loadCachedResults($model);

		// Return the cached results if they exist
		if ($this->__cached_results) {

			// Note the original useDbConfig
			$this->runtime['real_datasource'] = $model->useDbConfig;

			// Check if a DATABASE_CONFIG has been made for the dummy_datasource 
			// if not establish one based on standard naming
			$database_config = &ConnectionManager::$config;
			if (!isset($database_config->{$this->runtime['dummy_datasource']})) {
				$datasource_name = (string) $this->runtime['dummy_datasource'];
				$database_config->$datasource_name = array(
					'datasource' => str_replace('Behavior', '', get_class($this)) . '.AutocacheSource',
					'database' => null
				);
			}

			// Use a dummy database connection to prevent any query
			$model->useDbConfig = $this->runtime['dummy_datasource'];
		}

		return $query;
	}

	/**
	 * afterFind
	 * 
	 * @param Model $model
	 * @param array $results
	 */
	public function afterFind(Model $model, $results, $primary = false) {

		// Check if we obtained cached results
		if ($this->__cached_results) {

			// reset the useDbConfig attribute back to what it was
			$model->useDbConfig = $this->runtime['real_datasource'];
			unset($this->runtime['real_datasource']);

			// A flag to indicate in the Model if the last query was from cache
			$model->autocache_is_from = true;

			// return the cached results
			return $this->__cached_results;
		}

		// Cache the result if there is a config defined
		if (isset($this->runtime['config']) && !isset($this->runtime['flush'])) {
			Cache::write($this->runtime['name'], $results, $this->runtime['config']);
		}

		return $results;
	}
	
	/**
	 * afterSave Callback
	 *
	 * Invalidates the cache for this runtime configuration name
	 *
	 * @param Model $model Model the callback is called on
	 * @param boolean $created Whether or not the save created a record.
	 * @return void
	 */
	public function afterSave(Model $model, $created, $options = array()) {
		
		Cache::clear(false, $this->runtime['config']);
		
	}

	/**
	 * afterDelete Callback
	 *
	 * Invalidates the cache for this runtime configuration name
	 *
	 * @param Model $model Model the callback was run on.
	 * @return void
	 */
	public function afterDelete(Model $model) {
		
		Cache::clear(false, $this->runtime['config']);
		
	}

	/**
	 * _doCachingRuntimeSetup
	 * 
	 * @param Model $model
	 * @param array $query 
	 */
	protected function _doCachingRuntimeSetup(Model $model, &$query) {

		// Treat the cache config as a named cache config
		if (is_string($query['autocache'])) {
			$this->runtime['config'] = $query['autocache'];
			$this->runtime['name'] = $this->_generateCacheName($model, $query);
			
		// All other cache setups
		} else {  
		
			// Manage the cache config
			if (isset($query['autocache']['config']) && !empty($query['autocache']['config'])) {
				$this->runtime['config'] = $query['autocache']['config'];
			} else {
				$this->runtime['config'] = $this->runtime['cache_config_name_default'];
			}

			// Manage the cache name
			if (isset($query['autocache']['name']) && !empty($query['autocache']['name'])) {
				$this->runtime['name'] = $query['autocache']['name'];
			} else {
				$this->runtime['name'] = $this->_generateCacheName($model, $query);
			}
		}
		
		// Check the cache config really exists, else caching will silently not occur
		if (
			$this->runtime['cache_config_name_check'] &&
			!Configure::read('Cache.disable') && 
			empty(Cache::settings($this->runtime['config']))
		) {
			throw new AutocacheException('Attempting to use undefined cache configuration named "' 
					. $this->runtime['config']. '"');
		}
		
		// Cache flush control
		if (isset($query['autocache']['flush']) && $query['autocache']['flush'] === true) {
			$this->runtime['flush'] = true;
		}
	}

	/**
	 * _generateCacheName
	 * 
	 * @param Model $model 
	 * @param array $query 
	 */
	protected function _generateCacheName(Model $model, $query) {

		if (isset($query['autocache'])) {
			unset($query['autocache']);
		}

		// NOTE #1: we include the APP as a part of the generated name since it is possible to 
		// have more than one CakePHP site running on the same webserver and thus it possible 
		// to have the same query among them - learnt this the hard way - NdJ
		// 
		// NOTE #2: we use json_encode because it is faster than php serialize()

		return $this->cachename_prefix . '_' . 
				strtolower($model->findQueryType) . '_' .
				strtolower($model->alias) . '_' . 
				md5(APP . json_encode($query));
	}

	/**
	 * _loadCachedResults
	 */
	protected function _loadCachedResults(Model $model) {

		$this->__cached_results = false;
		
		// Flush the cache if required
		if (isset($this->runtime['flush']) && true === $this->runtime['flush']) {
			Cache::delete($this->runtime['name'], $this->runtime['config']);
		} else {
			// Catch the cached result
			$this->__cached_results = Cache::read($this->runtime['name'], $this->runtime['config']);
		}
	}
	
	/**
	 * _generateGroupName
	 * 
	 * @param Model $model 
	 * @param array $query 
	 */
	protected function _generateGroupName(Model $model) {
		
		// Cache grouping - Unfortunately (as at CakePHP 2.5.7) it appears impossible to correctly 
		// define cache groups after an initial call to Cache::config().  While it is possible to 
		// workaround this by implementing per-group configs, however if you do that calls to 
		// Cache::clear() then appear to cause subsequent calls to Cache::write() to not be work as
		// expected - more time required
		
		return $this->cachename_prefix . '_' . 
				$this->runtime['config'] . '_' .
				strtolower(str_replace('/','',APP)) . '_' . 
				strtolower($model->alias) ;
	}

}
