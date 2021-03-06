<?php // -*- mode:php; tab-width:4; indent-tabs-mode:t; c-basic-offset:4; -*-
#CMS - CMS Made Simple
#(c)2004-2010 by Ted Kulp (ted@cmsmadesimple.org)
#This project's homepage is: http://cmsmadesimple.org
#
#This program is free software; you can redistribute it and/or modify
#it under the terms of the GNU General Public License as published by
#the Free Software Foundation; either version 2 of the License, or
#(at your option) any later version.
#
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#You should have received a copy of the GNU General Public License
#along with this program; if not, write to the Free Software
#Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
#$Id$

/**
 * Class to represent a key/value cache.  The model is based on memcached, and will
 * work with memcached if a server exists, is configured, and the memcache module is
 * compiled into php.  Otherwise, the values will go into the cache table in the CMSMS
 * database.
 *
 * @author Ted Kulp
 */
class CmsAdodbKeyCache extends CmsObject
{
	static private $instance = NULL;
	
	function __construct()
	{
		parent::__construct();
	}
	
	/**
	 * Returns an instnace of the CmsAdodbKeyCache singleton.
	 *
	 * @return CmsAdodbKeyCache The singleton CmsAdodbKeyCache instance
	 * @author Ted Kulp
	 **/
	static public function get_instance()
	{
		if (self::$instance == NULL)
		{
			self::$instance = new CmsAdodbKeyCache();
		}
		return self::$instance;
	}
	
	/**
	 * Clears out any expired entries from the database
	 *
	 * @return void
	 * @author Ted Kulp
	 */
	public function expire_entries()
	{
		//Put into an try/catch, just in case the database has closed
		//before we get here.  It doesn't really matter THAT much if
		//it's run every time.  Cron would come in handy for this.
		try
		{
			cms_db()->Execute("DELETE FROM {cache} WHERE expiry < ?", array(cms_db()->BindTimeStamp(time())));
		}
		catch (Exception $e)
		{
		}
	}
	
	/**
	 * Return the value with the given key.  If the key doesn't exist (or is expired)
	 * it will return null.
	 *
	 * @param string $key The key to look up
	 * @return mixed The value retrieved. null if no valid data for key is found.
	 * @author Ted Kulp
	 */
	public function get($key)
	{
		try
		{
			$time = cms_db()->BindTimeStamp(time());
			$result = cms_db()->GetOne("select v FROM {cache} WHERE k = ? and expiry > ?", array($key, $time));
			if ($result)
				return unserialize($result);
		}
		catch (Exception $e)
		{
			
		}
		return null;
	}
	
	/**
	 * Sets the value with the given key. Values are serialized before being inserted
	 * the database. Expiry is the number of seconds that this value is valid.  Once
	 * it expires, further lookups will be null. If expiry is set to 0, then it will
	 * be valid for 10 years (just an arbitrary far in the future).
	 *
	 * @param string $key The key to set
	 * @param string $value The value to set.  This will be serialized, so all values must be serializable.
	 * @param string $expiry Number of seconds it will be valid. 0 is equivalent to 10 years.
	 * @return void
	 * @author Ted Kulp
	 */
	public function set($key, $value, $expiry = 3600)
	{
		self::delete($key);
		if ($expiry == 0)
			$expiry = (60 * 60 * 24 * 365 * 10); //10 years
		$expiry = cms_db()->BindTimeStamp(time() + $expiry);
		$time = cms_db()->BindTimeStamp(time());
		try
		{
			cms_db()->Execute("insert into {cache} (k, v, expiry, create_date, modified_date) values (?, ?, ?, ?, ?)", array($key, serialize($value), $expiry, $time, $time));
		}
		catch (Exception $e)
		{
			
		}
	}
	
	/**
	 * Deletes the key/value pair from the database. This is a blind delete, so no
	 * check is done if it exists first.
	 *
	 * @param string $key The key of the pair to delete
	 * @return void
	 * @author Ted Kulp
	 */
	public function delete($key)
	{
		try
		{
			cms_db()->Execute("delete from {cache} where k = ?", array($key));
		}
		catch (Exception $e)
		{
			
		}
	}
}

# vim:ts=4 sw=4 noet
?>