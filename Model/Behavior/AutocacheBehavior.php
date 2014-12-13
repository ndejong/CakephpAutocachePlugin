<?php

/* ***************************************************************************
 * Cakephp AutocachePlugin
 * Nicholas de Jong - http://nicholasdejong.com - https://github.com/ndejong
 * 18 December 2011
 * 
 * Very big thanks to Mark Scherer for setting me straight on _queryCount() and 
 * help with refactoring CakephpAutocacheBehaviour into CakephpAutocachePlugin
 *  - https://github.com/dereuromark
 * 
 * @author Nicholas de Jong
 * @copyright Nicholas de Jong
 * ***************************************************************************/

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
		
		// > default_cache - is the default cache name, which by default 
		// is the string "default" - confused?  You just need to make sure 
		// you have an appropriate Cache::config('default',array(...)) in 
		// your bootstrap.php or core.php
		//
		// > check_cache - determines if we bother checking if the supplied
		// cache configuration name is valid - prevents the developer
		// thinking they are caching when they are not - will throw a
		// cache expection if fails this check
		//
		// > dummy_datasource - name of the dummy data source in the 
		// database.php file should look something like this:-
		// public $autocache = array('datasource' => 'AutocacheSource');

		$this->runtime[$model->alias] = array_merge(array(
			
			// check if the named cache config is loaded
			'check_cache' => ( Configure::read('debug') > 0) ? true : false,
			
			// default cache *config* name
			'default_cache' => 'default',
			
			// name of the autocache dummy datasource *config* name
			'dummy_datasource' => 'autocache',
		), (array) $config);

		// Create the new cache config
		$newCacheName = $this->runtime[$model->alias]['default_cache'] . 'Model' . $model->alias;
		$defaultCacheConfig = Cache::config($this->runtime[$model->alias]['default_cache']);
		$modelCacheConfig = array_merge(
			$defaultCacheConfig['settings'],
			array(
				'groups' => array($newCacheName)
			)
		);

		Cache::config($newCacheName, $modelCacheConfig);
		$this->runtime[$model->alias]['default_cache'] = $newCacheName;
	}

	/**
	 * beforeFind
	 * 
	 * @param Model $model
	 * @param array $query 
	 */
	public function beforeFind(Model $model, $query) {
            
		// Determine if we are even going to try using the cache
		if (isset($query['autocache']) && ($query['autocache'] === false)) {
			return true; // return early as we have nothing to do
		}

		// Cache everything by default
		if(!isset($query['autocache'])){
			$query['autocache'] = true;
		}

		// Provides a place in the Model that we can use to find out what 
		// autocache did on the last query
		$model->autocache_is_from = false;

		// Do the required cache query setup
		$this->_doCachingRuntimeSetup($model, $query);

		// Load cached results if they are available
		$this->_loadCachedResults($model);

		// Return the cached results if they exist
		if ($this->__cached_results) {

			// Note the original useDbConfig
			$this->runtime[$model->alias]['useDbConfig'] = $model->useDbConfig;
			
			// Check if a DATABASE_CONFIG has been made for the dummy_datasource 
			// if not establish one based on standard naming
			$database_config = &ConnectionManager::$config;
			if(!isset($database_config->{$this->runtime[$model->alias]['dummy_datasource']})) {
				$datasource_name = (string) $this->runtime[$model->alias]['dummy_datasource'];
				$database_config->$datasource_name = array('datasource' => str_replace('Behavior','',get_class($this)).'.AutocacheSource', 'database'=>null);
			}

			// Use a dummy database connection to prevent any query
			$model->useDbConfig = $this->runtime[$model->alias]['dummy_datasource'];
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

		// debug($model);
		// debug($results);
		// Check if we set useDbConfig in beforeFind above
		if (isset($this->runtime[$model->alias]['useDbConfig'])) {

			// reset the useDbConfig attribute back to what it was
			$model->useDbConfig = $this->runtime[$model->alias]['useDbConfig'];
			unset($this->runtime[$model->alias]['useDbConfig']);

			// A flag to indicate in the Model if the last query was from cache
			if ($this->__cached_results) {
				$model->autocache_is_from = true;
			}

			// return the cached results
			return $this->__cached_results;
		}

		// Cache the result if there is a config defined
		if (isset($this->runtime[$model->alias]['config']) && !isset($this->runtime[$model->alias]['flush'])) {
			Cache::write($this->runtime[$model->alias]['name'], $results, $this->runtime[$model->alias]['config']);
		}

		return $results;
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
			$this->runtime[$model->alias]['config'] = $query['autocache'];
			$this->runtime[$model->alias]['name'] = $this->_generateCacheName($model, $query);

		} else {  // All other cache setups

			// Manage the cache config
			if (isset($query['autocache']['config']) && !empty($query['autocache']['config'])) {
				$this->runtime[$model->alias]['config'] = $query['autocache']['config'];
			} else {
				$this->runtime[$model->alias]['config'] = $this->runtime[$model->alias]['default_cache'];
			}

			// Manage the cache name
			if (isset($query['autocache']['name']) && !empty($query['autocache']['name'])) {
				$this->runtime[$model->alias]['name'] = $query['autocache']['name'];
			} else {
				$this->runtime[$model->alias]['name'] = $this->_generateCacheName($model, $query);
			}
		}

		// Check the cache config really exists, else no caching is going to happen
		if ($this->runtime[$model->alias]['check_cache'] && !Configure::read('Cache.disable') && !Cache::config($this->runtime[$model->alias]['config'])) {
			throw new CacheException('Attempting to use undefined cache configuration named ' . $this->runtime[$model->alias]['config']);
		}

		// Cache flush control
		if (isset($query['autocache']['flush']) && $query['autocache']['flush'] === true) {
			$this->runtime[$model->alias]['flush'] = true;
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

		// NOTE #1: we include the SERVER_NAME as a part of the generated
		// name since it is possible to have more than one CakePHP site
		// running on the same webserver and thus it possible to have
		// the same query among them - learnt this the hard way - NdJ
		// 
		// NOTE #2: we use json_encode because it is faster than php serialize()

		return $this->cachename_prefix . '_' . $model->findQueryType . $model->alias . '_' . md5(env('SERVER_NAME') . json_encode($query));
	}

	/**
	 * _loadCachedResults
	 */
	protected function _loadCachedResults($model) {

		// Flush the cache if required
		if (isset($this->runtime[$model->alias]['flush']) && true === $this->runtime[$model->alias]['flush']) {
			Cache::delete($this->runtime[$model->alias]['name'], $this->runtime[$model->alias]['config']);
			$this->__cached_results = false;
		}
		else {
			// Catch the cached result
			$this->__cached_results = Cache::read($this->runtime[$model->alias]['name'], $this->runtime[$model->alias]['config']);
		}
	}



	/**
	 * afterSave Callback
	 *
	 * Invalidates the cache group for this model.
	 *
	 * @param Model $model Model the callback is called on
	 * @param boolean $created Whether or not the save created a record.
	 * @return void
	 */
	public function afterSave(Model $model, $created, $options = array()) {

		$cacheGroupName = $this->runtime[$model->alias]['default_cache'];
		$cacheName = $cacheGroupName;

		Cache::clearGroup($cacheGroupName, $cacheName);
	}

	/**
	 * afterDelete Callback
	 *
	 * Invalidates the cache group for this model.
	 *
	 * @param Model $model Model the callback was run on.
	 * @return void
	 */
	public function afterDelete(Model $model) {
		$cacheGroupName = $this->runtime[$model->alias]['default_cache'];
		$cacheName = $cacheGroupName;

		Cache::clearGroup($cacheGroupName, $cacheName);
	}

}
