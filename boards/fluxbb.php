<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class FLUXBB_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "FluxBB 1.5";
	
	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "FluxBB 1";
	
	/**
	 * Whether or not this module requires the loginconvert.php plugin
	 *
	 * @var boolean
	 */
	var $requires_loginconvert = true;
	
	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Database Configuration", "dependencies" => ""),
						 "import_usergroups" => array("name" => "Usergroups", "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Users", "dependencies" => "db_configuration,import_usergroups"),
						 "import_categories" => array("name" => "Categories", "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_categories"),
						 "import_forumperms" => array("name" => "Forum Permissions", "dependencies" => "db_configuration,import_forums"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						 "import_settings" => array("name" => "Settings", "dependencies" => "db_configuration"),
						 "import_avatars" => array("name" => "Avatars", "dependencies" => "db_configuration,import_users"),
						);
	
	/**
	 * The table we check to verify it's "our" database
	 *
	 * @var String
	 */
	var $check_table = "topic_subscriptions";

	/**
	 * The table prefix we suggest to use
	 *
	 * @var String
	 */
	var $prefix_suggestion = "fluxbb_";

	/**
	 * An array of fluxbb -> mybb groups
	 *
	 * @var array
	 */
	var $groups = array(
		1 => MYBB_ADMINS, // Administrators
		2 => MYBB_MODS, // Moderators
		3 => MYBB_GUESTS, // Guests
		4 => MYBB_REGISTERED, // Registered
	);

	/**
	 * Get a user from the FluxBB database
	 *
	 * @param string $username Username
	 * @return array If the uid is 0, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_user($username)
	{
		if(empty($username))
		{
			return array(
				'username' => 'Guest',
				'id' => 0,
			);
		}
	
		$query = $this->old_db->simple_select("users", "id, username", "username = '".$this->old_db->escape_string($username)."'", array('limit' => 1));
		
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);
		
		return $results;
	}
}


