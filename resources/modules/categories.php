<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Categories extends Converter_Module
{
	public $default_values = array(
		'import_fid' => 0,
		'name' => '',
		'description' => '',
		'import_pid' => 0,
		'disporder' => 0,
		'linkto' => '',
		'lastpost' => 0,
		'parentlist' => '',
		'lastposter' => '',
		'lastposttid' => 0,
		'lastposteruid' => 0,
		'lastpostsubject' => '',
		'threads' => 0,
		'posts' => 0,
		'type' => 'c',
		'active' => 1,
		'open' => 1,
		'allowhtml' => 0,
		'allowmycode' => 1,
		'allowsmilies' => 1,
		'allowimgcode' => 1,
		'allowpicons' => 1,
		'allowtratings' => 1,
		'usepostcounts' => 1,
		'usethreadcounts' => 1,
		'password' => '',
		'showinjump' => 1,
		'style' => 0,
		'overridestyle' => 0,
		'rulestype' => 0,
		'rules' => '',
		'unapprovedthreads' => 0,
		'unapprovedposts' => 0,
		'defaultdatecut' => 0,
		'defaultsortby' => '',
		'defaultsortorder' => '',
	);

	public $integer_fields = array(
		'import_fid',
		'import_pid',
		'disporder',
		'lastpost',
		'lastposttid',
		'lastposteruid',
		'threads',
		'posts',
		'active',
		'open',
		'allowhtml',
		'allowmycode',
		'allowsmilies',
		'allowimgcode',
		'allowpicons',
		'allowtratings',
		'usepostcounts',
		'usethreadcounts',
		'showinjump',
		'style',
		'overridestyle',
		'rulestype',
		'unapprovedthreads',
		'unapprovedposts',
		'defaultdatecut',
	);

	/**
	 * Insert forum into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		// Call our currently module's process function
		$data = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'forums');

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("forums", $insert_array);
		$fid = $db->insert_id();

		// Update internal array caches
		$this->get_import->cache_fids[$insert_array['import_fid']] = $fid; // TODO: Fix?

		if($insert_array['type'] == "f")
		{
			$this->get_import->cache_fids_f[$insert_array['import_fid']] = $fid; // TODO: Fix?
		}

		$this->increment_tracker('categories');

		$output->print_progress("end");

		return $fid;
	}

	function fix_ampersand($text)
	{
		return str_replace('&amp;', '&', $text);
	}
}


