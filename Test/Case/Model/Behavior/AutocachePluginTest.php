<?php

/****************************************************************************
 * Cakephp AutocachePlugin
 * Nicholas de Jong - http://nicholasdejong.com - https://github.com/ndejong
 * 
 * @author Nicholas de Jong
 * @link https://github.com/ndejong/CakephpAutocachePlugin
 ****************************************************************************/

/**
 * Test Case by Mark Scherer
 * testing ndejong's Behavior
 */
App::uses('Model', 'Model');
App::uses('ModelBehavior', 'Model');

/**
 * AutocacheTestCase
 *
 * @package search
 * @subpackage search.tests.cases.behaviors
 */
class AutocacheTestCase extends CakeTestCase {

	/**
	 * Fixtures used in the SessionTest
	 *
	 * @var array
	 */
	var $fixtures = array('core.Article', 'core.User');

	/**
	 * $autocache_path - path location of the cache files
	 * 
	 * @var string
	 */
	var $autocache_path = null;

	/**
	 * startTest
	 *
	 * @return void
	 */
	public function startTest($method) {

		$this->cache_path = CACHE;

		Cache::config('default', array(
			//'prefix' => 'ac_',
			'engine' => 'File',
			'path' => $this->cache_path,
			'duration' => '+10 seconds'
		));

		$this->Article = ClassRegistry::init('Article');
		$this->User = ClassRegistry::init('User');
	}

	/**
	 * endTest
	 *
	 * @return void
	 */
	public function endTest($method) {
		unset($this->Article);
	}

	/**
	 * testGetCachedTrueFirst
	 *
	 * @return void
	 */
	public function testGetCachedTrueFirst() {
		$autocache = true;
		$this->_cacheTest($autocache, __FUNCTION__);
	}

	/**
	 * testGetCachedTrueSecond
	 *
	 * @return void
	 */
	public function testGetCachedTrueSecond() {
		$autocache = true;
		$this->_cacheTest($autocache, __FUNCTION__);
	}

	/**
	 * testGetCachedNamedConfigString
	 *
	 * @return void
	 */
	public function testGetCachedNamedConfigString() {
		$autocache = 'default';
		$this->_cacheTest($autocache, __FUNCTION__);
	}

	/**
	 * testGetCachedNamedConfig
	 *
	 * @return void
	 */
	public function testGetCachedNamedConfig() {
		$autocache = array('config' => 'default');
		$this->_cacheTest($autocache, __FUNCTION__);
	}

	/**
	 * testGetCachedNamedConfigName
	 *
	 * @return void
	 */
	public function testGetCachedNamedConfigName() {
		$autocache = array('name' => 'a_name_for_a_cache_1');
		$this->_cacheTest($autocache, __FUNCTION__);
	}

	/**
	 * testGetCachedNamedConfigNameAndConfig
	 *
	 * @return void
	 */
	public function testGetCachedNamedConfigNameAndConfig() {
		$autocache = array('config' => 'default', 'name' => 'a_name_for_a_cache_2');
		$this->_cacheTest($autocache, __FUNCTION__);
	}

	/**
	 * testFlushedCache
	 *
	 * @return void
	 */
	public function testFlushedCache() {
		$autocache = array('flush' => true);
		$this->_cacheTest($autocache, __FUNCTION__);
	}

	/**
	 * testFlushedCache
	 *
	 * @return void
	 */
	public function testFlushedWithConfig() {
		$autocache = array('config' => 'default', 'flush' => true);
		$this->_cacheTest($autocache, __FUNCTION__);
	}

	/**
	 * testGetCachedWithContainable
	 * - Article JOIN User
	 *
	 * @return void
	 */
	public function testGetCachedWithContainable() {

		Cache::clear();

		$this->User->Behaviors->attach('Containable');

		$conditions = array(
			'contain' => array('Article'),
			'autocache' => true
		);

		$result_1 = $this->User->find('first', $conditions);

		$this->assertTrue(!empty($result_1));
		$this->assertFalse($this->User->autocache_is_from);

		# check if filename starting with "cake_autocache_first_article_" exists
		$files = $this->_glob_recursive($this->cache_path . 'cake_autocache_first_user_*');
		$this->assertTrue((1 === count($files))); // always 1 because Cache::clear(); is used above
		# Second query result should equal first query
		$result_2 = $this->User->find('first', $conditions);
		$this->assertTrue(!empty($result_2));

		$this->assertTrue($this->User->autocache_is_from);

		// Check the first query result is the same as the second
		$this->assertSame($result_1, $result_2);
	}

	/**
	 * testDatasourceFunctions
	 *
	 * @return void
	 */
	public function testDatasourceFunctions() {

		Cache::clear();

		$this->User->Behaviors->attach('Containable');

		$conditions = array(
			'contain' => array('Article'),
			'autocache' => true
		);

		$result_1 = $this->User->find('first', $conditions);
		$this->assertTrue(!empty($result_1));

		$db = $this->User->getDataSource();
		$this->assertTrue(!empty($db->name('count')));
		$this->assertTrue(is_object($db->identifier('foobar')));
	}
	
	/**
	 * testCallbacks
	 *
	 * @return void
	 */
	public function testCallbacks() {
		
		Cache::clear();
		
		$autocache = array('config' => 'default');
		$user_id = 3;
		$conditions = array('id'=>$user_id);
		
		$result_3 = $this->User->find('all',array(
			'conditions' => $conditions,
			'autocache' => $autocache
		));
		debug($result_3);
		$this->assertFalse($this->User->autocache_is_from);
		
		$this->User->id = $user_id;
		$username = md5(time(true));
		$this->User->saveField('user', $username);
		
		$result_4 = $this->User->find('all',array(
			'conditions' => $conditions,
			'autocache' => $autocache
		));
		debug($result_4);
		$this->assertFalse($this->User->autocache_is_from);
		
		$result_5 = $this->User->find('all',array(
			'conditions' => $conditions,
			'autocache' => $autocache
		));
		debug($result_5);
		$this->assertTrue($this->User->autocache_is_from);
		
	}

	/**
	 * _cacheTest
	 * 
	 * @param mixed $autocache 
	 * @return void
	 */
	protected function _cacheTest($autocache, $test_name='unknown') {
		
		Cache::clear();

		// First query gets cached
		$result_1 = $this->Article->find('first', array('autocache' => $autocache));

		$this->assertTrue(!empty($result_1));
		$this->assertFalse($this->Article->autocache_is_from);

		# check if filename starting with "cake_autocache_first_article_" exists
		$files = $this->_glob_recursive($this->cache_path . 'cake_autocache*');
		if (is_array($autocache) && isset($autocache['name'])) {
			$files = $this->_glob_recursive($this->cache_path . 'cake_' . $autocache['name'] . '*');
		}

		if (isset($autocache['flush']) && $autocache['flush'] !== true) {
			$this->assertTrue((1 === count($files))); // always 1 because Cache::clear(); is used above
		}

		// Count number of queries before second query for the same data
		$query_count_1 = $this->_queryCount();

		# Second query result should equal first query
		$result_2 = $this->Article->find('first', array('autocache' => $autocache));
		$this->assertTrue(!empty($result_2));

		// Count number of queries before second query for the same data
		$query_count_2 = $this->_queryCount();

		// If flushed we do expect the query to be run so we must compensate
		if (isset($autocache['flush']) && true === $autocache['flush'] ) {
			$this->assertFalse($this->Article->autocache_is_from);
			$this->assertSame($query_count_1, $query_count_2 - 1);
		} else {
			// Check we still have the same query count and thus did not touch the database
			$this->assertTrue($this->Article->autocache_is_from);
			$this->assertSame($query_count_1, $query_count_2 );
		}

		// Check the first query result is the same as the second
		$this->assertSame($result_1, $result_2);
		
	}

	/**
	 * return all queries
	 *
	 * @return array
	 * @access protected
	 */
	protected function _queries() {
		$res = $this->db->getLog(false, false);
		$queries = $res['log'];
		$return = array();
		foreach ($queries as $row) {
			if (strpos($row['query'], 'DESCRIBE') === 0 || strpos($row['query'], 'BEGIN') === 0 || strpos($row['query'], 'COMMIT') === 0) {
				continue;
			}
			$return[] = $row['query'];
		}
		debug($return);
		return $return;
	}

	/**
	 * return number of queries executed
	 *
	 * @return int
	 * @access protected
	 */
	protected function _queryCount() {
		return count($this->_queries());
	}

	/**
	 * _glob_recursive
	 * @param type $pattern
	 * @param type $flags
	 * @return type
	 */
	protected function _glob_recursive($pattern, $flags = 0) {
		$files = glob($pattern, $flags);

		foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
			$files = array_merge($files, $this->_glob_recursive($dir . '/' . basename($pattern), $flags));
		}
		return $files;
	}

}

/**
 * User model
 *
 */
class User extends CakeTestModel {

	/**
	 * Behaviors
	 *
	 * @var array
	 */
	public $actsAs = array('Autocache.Autocache');

	/**
	 * hasMany associations
	 *
	 * @var array
	 */
	public $hasMany = array(
		'Article' => array(
			'className' => 'Article',
			'foreignKey' => 'user_id',
		)
	);

}

/**
 * Article model
 *
 */
class Article extends CakeTestModel {

	/**
	 * Behaviors
	 *
	 * @var array
	 */
	public $actsAs = array('Autocache.Autocache');

}
