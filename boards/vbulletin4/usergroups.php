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

class VBULLETIN4_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'usergroups',
		'progress_column' => 'usergroupid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get only non-staff groups.
		$query = $this->old_db->simple_select("usergroup", "*", "usergroupid > 8", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$this->insert($group);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 4 values
		$insert_data['import_gid'] = $data['usergroupid'];
		$insert_data['title'] = $data['title'];
		$insert_data['description'] = $data['description'];
		$insert_data['pmquota'] = $data['pmquota'];
		$insert_data['maxpmrecipients'] = $data['pmsendmax'];
		$insert_data['attachquota'] = $data['attachlimit'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("usergroup", "COUNT(*) as count", "usergroupid > 8");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_usergroups'];
	}
}


