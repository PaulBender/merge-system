<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Posts extends Converter_Module
{
	public $default_values = array(
		'tid' => 0,
		'replyto' => 0,
		'subject' => '',
		'username' => '',
		'fid' => 0,
		'uid' => 0,
		'import_uid' => 0,
		'dateline' => 0,
		'message' => '',
		'ipaddress' => '',
		'includesig' => 1,
		'smilieoff' => 0,
		'edituid' => 0,
		'edittime' => 0,
		'icon' => 0,
		'visible' => 1,
	);
	
	public $binary_fields = array(
		'ipaddress',
	);

	public $integer_fields = array(
		'tid',
		'replyto',
		'fid',
		'uid',
		'import_uid',
		'dateline',
		'includesig',
		'smilieoff',
		'edituid',
		'edittime',
		'icon',
		'visible',
	);

	/**
	 * Insert post into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		$unconverted_values = $data;

		// Call our currently module's process function
		$data = $converted_values = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data);

		unset($insert_array['import_pid']);
		unset($insert_array['import_uid']);

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("posts", $insert_array);
		$pid = $db->insert_id();

		$db->insert_query("post_trackers", array(
			'pid' => intval($pid),
			'import_pid' => intval($data['import_pid']),
			'import_uid' => intval($data['import_uid'])
		));

		$this->get_import->cache_posts[$data['import_pid']] = $pid;

		$this->after_import($unconverted_values, $converted_values, $pid);

		$this->increment_tracker('posts');

		$output->print_progress("end");

		return $pid;
	}

	/**
	 * Rebuild counters, and lastpost information right after importing posts
	 *
	 */
	public function cleanup()
	{
		global $output, $lang;

		$output->print_header($lang->module_post_rebuilding);

		$this->debug->log->trace0("Rebuilding thread, forum, and statistic counters");

		$output->construct_progress_bar();

		echo $lang->module_post_rebuild_counters;

		flush();

		// Rebuild thread counters, forum counters, user post counters, last post* and thread username
		$this->rebuild_thread_counters();
		$this->rebuild_forum_counters();
		$this->rebuild_user_post_counters();
		$this->rebuild_user_thread_counters();
	}

	private function rebuild_thread_counters()
	{
		global $db, $output, $import_session, $lang;

		$query = $db->simple_select("threads", "COUNT(*) as count", "import_tid > 0");
		$num_imported_threads = $db->fetch_field($query, "count");
		$last_percent = 0;

		if($import_session['counters_threads_start'] >= $num_imported_threads) {
			return;
		}

		$this->debug->log->trace1("Rebuilding thread counters");
		echo $lang->module_post_rebuilding_thread;
		flush();

		$progress = $import_session['counters_threads_start'];
		$query = $db->simple_select("threads", "tid", "import_tid > 0", array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => intval($import_session['counters_threads_start']), 'limit' => 1000));
		while($thread = $db->fetch_array($query))
		{
			// Updates "replies", "unapprovedposts", "deletedposts" and firstpost/lastpost data
			rebuild_thread_counters($thread['tid']);

			++$progress;

			if(($progress % 5) == 0)
			{
				if(($progress % 100) == 0)
				{
					check_memory();
				}
				$percent = round(($progress/$num_imported_threads)*100, 1);
				if($percent != $last_percent)
				{
					$output->update_progress_bar($percent, $lang->sprintf($lang->module_post_thread_counter, $thread['tid']));
				}
				$last_percent = $percent;
			}
		}

		$import_session['counters_threads_start'] += $progress;

		if($import_session['counters_threads_start'] >= $num_imported_threads)
		{
			$this->debug->log->trace1("Finished rebuilding thread counters");
			echo $lang->done;
			flush();
		}

		$this->redirect();
	}

	private function rebuild_forum_counters()
	{
		global $db, $output, $lang, $import_session;

		if(isset($import_session['counters_forum'])) {
			return;
		}

		$this->debug->log->trace1("Rebuilding forum counters");
		echo $lang->module_post_rebuilding_forum;
		flush();

		$query = $db->simple_select("forums", "fid", "import_fid > 0");
		$num_imported_forums = $db->num_rows($query);
		$progress = 0;

		while ($forum = $db->fetch_array($query)) {
			// TODO: From the code this should also update the lastpost data - which isn't done
			rebuild_forum_counters($forum['fid']);
			++$progress;
			$output->update_progress_bar(round((($progress / $num_imported_forums) * 50), 1) + 100, $lang->sprintf($lang->module_post_forum_counter, $forum['fid']));
		}

		echo $lang->done;

		$this->redirect('counters_forum');
	}

	private function rebuild_user_post_counters()
	{
		global $db, $output, $lang, $import_session;

		if(isset($import_session['counters_user_posts'])) {
			return;
		}

		$query = $db->simple_select("forums", "fid", "usepostcounts = 0");
		while($forum = $db->fetch_array($query))
		{
			$fids[] = $forum['fid'];
		}

		if(isset($fids) && is_array($fids))
		{
			$fids = implode(',', $fids);
		}

		if(!empty($fids))
		{
			$fids = " AND fid NOT IN($fids)";
		}
		else
		{
			$fids = "";
		}

		$this->debug->log->trace1("Rebuilding user counters");
		echo $lang->module_post_rebuilding_user_post;
		flush();

		$query = $db->simple_select("users", "uid", "import_uid > 0");
		$num_imported_users = $db->num_rows($query);
		$progress = $last_percent = 0;

		while($user = $db->fetch_array($query))
		{
			$query2 = $db->query("
				SELECT COUNT(p.pid) AS post_count
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
				WHERE p.uid='{$user['uid']}' AND t.visible > 0 AND p.visible > 0{$fids}
			");

			$num_posts = $db->fetch_field($query2, "post_count");
			$db->free_result($query2);
			$db->update_query("users", array("postnum" => (int)$num_posts), "uid='{$user['uid']}'");

			++$progress;
			$percent = round((($progress/$num_imported_users)*50)+150, 1);
			if($percent != $last_percent)
			{
				$output->update_progress_bar($percent, $lang->sprintf($lang->module_post_forum_counter, $user['uid']));
			}
			$last_percent = $percent;
		}
		// TODO: recount user posts doesn't seem to work though it's the same code as in the acp

		$output->update_progress_bar(100, $lang->please_wait);

		echo $lang->done;
		flush();

		$this->redirect('counters_user_posts');
	}

	private function rebuild_user_thread_counters()
	{
		global $db, $output, $lang, $import_session;

		if(isset($import_session['counters_user_threads'])) {
			return;
		}

		$query = $db->simple_select("forums", "fid", "usepostcounts = 0");
		while($forum = $db->fetch_array($query))
		{
			$fids[] = $forum['fid'];
		}

		if(isset($fids) && is_array($fids))
		{
			$fids = implode(',', $fids);
		}

		if(!empty($fids))
		{
			$fids = " AND fid NOT IN($fids)";
		}
		else
		{
			$fids = "";
		}

		$this->debug->log->trace1("Rebuilding user thread counters");
		echo $lang->module_post_rebuilding_user_thread;
		flush();

		$query = $db->simple_select("users", "uid", "import_uid > 0");
		$num_imported_users = $db->num_rows($query);
		$progress = $last_percent = 0;

		while($user = $db->fetch_array($query))
		{
			$query2 = $db->query("
				SELECT COUNT(t.tid) AS thread_count
				FROM ".TABLE_PREFIX."threads t
				WHERE t.uid='{$user['uid']}' AND t.visible > 0 AND t.closed NOT LIKE 'moved|%'{$fids}
			");
			$num_threads = $db->fetch_field($query2, "thread_count");
			$db->free_result($query2);
			$db->update_query("users", array("threadnum" => (int)$num_threads), "uid='{$user['uid']}'");


			++$progress;
			$percent = round((($progress/$num_imported_users)*50)+150, 1);
			if($percent != $last_percent)
			{
				$output->update_progress_bar($percent, $lang->sprintf($lang->module_post_forum_counter, $user['uid']));
			}
			$last_percent = $percent;
		}
		// TODO: recount user posts doesn't seem to work though it's the same code as in the acp

		$output->update_progress_bar(100, $lang->please_wait);

		echo $lang->done;
		flush();

		// Not needed as this is the latest rebuilding so we need to continue the normal code
		// If a new counter function is called after this we'd need to uncomment this
//		$this->redirect('counters_users_threads');
	}

	private function redirect($finished = "")
	{
		if(!empty($finished)) {
			global $import_session;
			$import_session[$finished] = 1;
		}

		if(!headers_sent())
		{
			header("Location: index.php");
		}
		else
		{
			echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";;
		}
	}
}


