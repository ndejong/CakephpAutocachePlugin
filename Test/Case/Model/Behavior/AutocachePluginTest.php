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
 * ***************************************************************************/

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

		$this->cache_path = CACHE . 'models' . DS;

		Cache::config('default', array(
			'prefix' => 'cake_',
			'engine' => 'File',
			'path' => $this->cache_path,
			'duration' => '+1 hour'
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
	 * testGetCachedTrue
	 *
	 * @return void
	 */
	public function testGetCachedTrue() {
		$autocache = true;
		$this->_cacheTest($autocache);
	}

	/**
	 * testGetCachedNamedConfigString
	 *
	 * @return void
	 */
	public function testGetCachedNamedConfigString() {
		$autocache = 'default';
		$this->_cacheTest($autocache);
	}

	/**
	 * testGetCachedNamedConfig
	 *
	 * @return void
	 */
	public function testGetCachedNamedConfig() {
		$autocache = array('config' => 'default');
		$this->_cacheTest($autocache);
	}

	/**
	 * testGetCachedNamedConfigName
	 *
	 * @return void
	 */
	public function testGetCachedNamedConfigName() {
		$autocache = array('name' => 'a_name_for_a_cache');
		$this->_cacheTest($autocache);
	}

	/**
	 * testGetCachedNamedConfigNameAndConfig
	 *
	 * @return void
	 */
	public function testGetCachedNamedConfigNameAndConfig() {
		$autocache = array('config' => 'default', 'name' => 'a_name_for_a_cache');
		$this->_cacheTest($autocache);
	}

	/**
	 * testFlushedCache
	 *
	 * @return void
	 */
	public function testFlushedCache() {
		$autocache = array('flush' => true);
		$this->_cacheTest($autocache);
	}

	/**
	 * testFlushedCache
	 *
	 * @return void
	 */
	public function testFlushedWithConfig() {
		$autocache = array('config' => 'default', 'flush' => true);
		$this->_cacheTest($autocache);
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
            $this->assertTrue(!empty( $db->name('count') ));
            $this->assertTrue(is_object( $db->identifier('foobar') ));
            
	}
	
	/**
	 * _cacheTest
	 * 
	 * @param mixed $autocache 
	 * @return void
	 */
	protected function _cacheTest($autocache) {

		Cache::clear();

		// First query gets cached
		$result_1 = $this->Article->find('first', array('autocache' => $autocache));
		//debug($result_1);
		//ob_flush();
		
		$this->assertTrue(!empty($result_1));
		$this->assertFalse($this->Article->autocache_is_from);

		# check if filename starting with "cake_autocache_first_article_" exists
		$files = $this->_glob_recursive($this->cache_path . 'cake_autocache_first_article_*');
		if (is_array($autocache) && isset($autocache['name'])) {
			$files = $this->_glob_recursive($this->cache_path . 'cake_' . $autocache['name'] . '*');
		}
                
		if(isset($autocache['flush']) && $autocache['flush'] !== true) {
                    $this->assertTrue((1 === count($files))); // always 1 because Cache::clear(); is used above
                }

		// Count number of queries before second query for the same data
		$query_count_before = $this->_queryCount();
		
		# Second query result should equal first query
		$result_2 = $this->Article->find('first', array('autocache' => $autocache));
		$this->assertTrue(!empty($result_2));

		// Count number of queries before second query for the same data
		$query_count_after = $this->_queryCount();
		
		// If flushed we do expect the query to be run so we must compensate
		if(isset($autocache['flush']) && $autocache['flush'] === true) {
			$this->assertSame($query_count_before, $query_count_after-1);
		} else {
			// Check we still have the same query count and thus did not touch the database
			$this->assertSame($query_count_before, $query_count_after);
		}
		
		// Test result does not come from cache if flushed
		if (isset($autocache['flush']) && true === $autocache['flush']) {
			$this->assertFalse($this->Article->autocache_is_from);
		} else {
			$this->assertTrue($this->Article->autocache_is_from);
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
			if (strpos($row['query'], 'DESCRIBE') === 0) {
				continue;
			}
			$return[] = $row['query'];
		}
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