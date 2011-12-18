
/* ***************************************************************************
 * Cakephp AutocachePlugin
 * Nicholas de Jong - http://nicholasdejong.com - https://github.com/ndejong
 * 18 December 2011
 * 
 * @author Nicholas de Jong
 * @copyright Nicholas de Jong
 * ***************************************************************************/

CakephpAutocachePlugin is a CakePHP 2.0x Plugin that makes query caching as easy 
as adding a 'autocache'=>true condition to your Model query.  This plugin follows 
on from CakephpAutocacheBehavior on Mark Scherer's (https://github.com/dereuromark) 
suggestions, thanks Mark!


Install
=======

Step 1
------
Copy or symlink CakephpAutocachePlugin into a path named Autocache in your Plugin
path like this:-
>  app/Plugin/Autocache

Take careful note of the pathname, the name is "Autocache", not AutocachePlugin
or CakephpAutocachePlugin, it's just Autocache.  I spell this out because it's
an easy thing to trip up on especially if your pulling this down from github or
unpacking from a tarball.

Step 2
------
Make sure you have at least one standard CakePHP cache configuration setup in 
core.php or bootstrap.php.  You can call your first cache configuration 'default' 
and just set it up as a File based cache, like this:-
>  Cache::config('default', array('engine' => 'File'));

Step 3
------
While you have bootstrap.php open, tell Cake to load the plugin like this:-
>  CakePlugin::load('Autocache');

Step 4
------
Tell your model(s) they $actsAs Autocache by adding this to the top part of the 
model definition you want Autocache enabled for.  Alternatively you could just put
this into the AppModel.php thus enabling Autocache for all models
>  public $actsAs = array('Autocache.Autocache');

Note the Behavior settings possible here in the section below.

Step 5
------
Add an 'autocache' condition to your find query, see further below for the various 
options you have here but it can be as simple as just 'autocache' => true.

Step 6
------
Fire up the AutocachePlugin Tests, they should all pass.


Usage
=====

Find Condition Options
----------------------

This is the fun stuff, and where Autocache really shines the with its simplicity 
in usage, there are just three options:-

 - config (string) = the cache name specified by using a standard Cake
   Cache::config('a_cache_name', ... )

 - name (string) = the developer can override an automatically generated cache 
    key name by specifying it as an option, doing this will give you a small 
    performance gain because we don't have to a serialization and md5 to generate 
    a cache key name - see Q&A below for note on cache key name generation.

 - flush (bool) = you can force a reload from the datasource by adding a flush
   option, which will delete any existing cached value and replace it with a
   fresh datastore find result.

Because the 'config' option is the most commonly required option we make it easy
to access it in the following ways.

 - if the 'autocache' option is bool, we use the cache configuration specified when
   the Autocache Behavior was assigned to the Model, ie the "default_cache" parameter

 - if the 'autocache' option is a string, we use this string as the cache 
   configuration name to use.

Find Condition Option Examples
------------------------------

>  $conditions = array('autocache'=>true)

>  $conditions = array('autocache'=>'default')

>  $conditions = array('autocache'=>array('config'=>'default'))

>  $conditions = array('cache'=>array('name'=>'some_name'))

>  $conditions = array('cache'=>array('flush'=>true))

The first three are essentially the same thing expressed differently

The Model->autocache_is_from variable
-------------------------------------
I've erred about the wisdom of this, non-the-less it's there.  After a result is 
handed to you from a Model find(), you can check Model->autocache_is_from 
to determine if the result was from cache or not.  Beware, if you want a portable 
Model code you'll need to do an isset() to test for variable existence because if
the behavior is not there the variable will not be set...

Autocache Behavior Settings
---------------------------

 - default_cache << defines the default cache configuration name to use when a 
   find query contains an 'autocache' condition without an explicit cache 
   configuration name.  By default the name is 'default'

 - check_cache << tells Autocache to check if the cache configuration name that 
   is about to be used has actually been defined, this helps you prevent silly 
   mistakes.  By default this parameter sets itself to true when 
   Configure::read('debug') is greater than 0 and otherwise false.  There may be
   a small speed improvement in setting this to false.

 - dummy_datasource << defines the dummy datastore name that needs to be
   defined in your database.php file. By default it's named 'autocache' and it's 
   pretty unlikely you'd ever need to change this.

Autocache Behavior Setting Examples
-----------------------------------

From the model:-
>  $actsAs = array('Autocache.Autocache')

>  $actsAs = array('Autocache.Autocache',array('default_cache'=>'level_1'));

>  $actsAs = array('Autocache.Autocache',array('default_cache'=>'level_1','check_cache'=>false));

From the Controller through a Behavior "attach":-
>  $this->MyModelName->Behaviors->attach('Autocache.Autocache',array('default_cache'=>'level_2'));


Questions and Answers
=====================

Q: I want more control over cache times, cache locations etc.
A: It's right in front of you :)  The way to achieve this is to specify a new 
   cache configuration for the "stuff" you are wanting to cache.  This allows you
   to get really funky, create a config that caches in APC while others cache to
   File and establish different time frames for each, for example:-

    - Cache::config('default',  array('engine' => 'APC', 'duration'=>'60 seconds'));
    - Cache::config('level_05', array('engine' => 'APC', 'duration'=>'5 second'));
    - Cache::config('level_1',  array('engine' => 'APC', 'duration'=>'1 minute'));
    - Cache::config('level_2',  array('engine' => 'APC', 'duration'=>'5 minute'));
    - Cache::config('level_3',  array('engine' => 'APC', 'duration'=>'30 minute'));
    - Cache::config('level_4',  array('engine' => 'File','duration'=>'4 hour'));
    - Cache::config('level_5',  array('engine' => 'File','duration'=>'1 day'));

Q: How does Autocache name cached data?
A: Take a look at _generateCacheName() the crux of the matter is that we take
   the all query parameters, serialize them and take a hash of the result
   thus ensuring a useful unique name per query - yes, there is overhead in
   doing this but it's still less than doing a database query!

Q: What's AutocacheSource (ie the DummySource) all about?
A: In order to prevent the CakePHP Model class from making a full request to
   the database when we have a cached result we need a way to quickly cause
   the find() query to return with nothing so we can re-inject the result
   in the afterFind() callback - it's unfortunate this behavior requires more
   than one .php file, but that's the way it is - still much tidier than the
   previous approach that involved cutting'n'pasting code into the AppModel.

Q: What's the history?
A: AutocachePlugin follows on from AutocacheBehavior which was an an improvement 
   on "Automatic model data caching for CakePHP" that I wrote a while back.  I
   borrowed from ideas "jamienay" had put forward in his automatic_query_caching
     - nicholasdejong.com/story/automatic-model-data-caching-cakephp
     - github.com/jamienay/automatic_query_caching

Q: What's different about CakephpAutocachePlugin to CakephpAutocacheBehavior?
A: Probably the biggest "thing" is the change in the find condition option name
   from 'cache' to 'autocache' - yes the option name is longer but it is clearer 
   and better aligns with the rest of naming.  Other stuff includes, there is no
   need to specify the autocache datasource configuration anymore, we deal with
   that automatically while it can be overridden via the dummy_datasource parameter.
   ... the the Plugin is just easier to use!
