<?php

	/* path to the rdf file you wish to read */
	$path_to_rdf = "";
	/* parsing mode, what are we parsing, 'message', 'topic' or 'user' data */
	$mode = 'message';

/* This is a sample class that will be used to handle the data parsed from the
 * RDF file that contains message information. The data is stored in the following
 * class properties:
 *
 *	subject 	- subject of the message
 *	link		- link to the message on the forum
 *	category_title	- the category this message is in
 *	forum_id	- the forum this message is in
 *	forum_title	- link to the forum
 *	author		- the name of the author
 *	author_id	- id of the poster, 0 for anonymous users
 *	date		- the date message was posted on
 *	attachments	- an array containing information about file attachments
 *				'title' - name of the file
 *				'id' 	- attachment id
 *				'nd' 	- number of times the file was downloaded
 *				'size'	- size of the file (in bytes)
 *	poll_name	- name of a poll inside the message, if one is avaliable
 *	total_votes	- total number of votes in the poll, if one is avaliable
 *	poll_opts	- an array containing the information about poll options
 *				'title'	- name of the option
 *				'votes'	- number of votes for this option
 *	reply_to_id	- If the message is the reply, this is the subject of the original message
 *	reply_to_title	- link to the original message
 *	topic_title	- topic's subject, maybe the same as message subject
 *	topic_id	- link to topic
 */

class fud_forum_rdf_msg_print extends fud_forum_rdf_msg
{
	function handle_fud_data()
	{
		echo $this->category_title . '  ';

		echo '<a href="'.$this->forum_url.'?t=rview&amp;frm_id='.$this->forum_id.'">' . $this->topic_title . '</a> &raquo; ';

		if ($this->topic_title && $this->topic_title != $this->title) {
			echo '<a href="'.$this->forum_url.'?t=rview&amp;th='.$this->topic_id.'"> '.$this->topic_title.'</a> &raquo; ';
		}

		echo '<a href="'.$this->forum_url.'?t=rview&amp;th='.$this->topic_id.'&amp;goto='.$this->message_id.'">'.$this->title.'</a><br />';

		if ($this->author_id) {
			echo '<b>Author: </b><a href="'.$this->forum_url.'?t=usrinfo&amp;id='.$this->author_id.'">'.$this->author.'</a><br />';
		} else {
			echo '<b>Author: </b>'.$this->author.'<br />';
		}

		if (!isset($this->reply_to_id) && $this->reply_to_title != $this->title) {
			echo '<b>In Reply To:</b> <a href="'.$this->forum_url.'?t=rview&amp;th='.$this->topic_id.'&amp;id='.$this->reply_to_id.'"> '.$this->reply_to_title.'</a><br />';
		}

		echo "<b>Message:</b><br /><blockquote>\n".$this->body."\n</blockquote><br />";

		if (@count($this->attachments)) {
			echo '<b>Attachments:</b><br /><blockquote>';
			foreach ($this->attachments as $atd) {
				echo '<a href="'.$this->forum_url.'?t=getfile&amp;id='.$atd['id'].'">'.$atd['title'].'</a> ('.$atd['size'].') bytes, downloaded '.(int)$atd['nd'].' times<br>';
			}
			echo '</blockquote>';
		}
		if (!empty($this->poll_name)) {
			echo '<b>Poll:</b> '.$this->poll_name.' (total votes: '.(int)$this->total_votes.')<br /><blockquote>';
			$i = 0;
			if (@count($this->poll_opts)) {
				foreach ($this->poll_opts as $po) {
					echo ++$i . '. ' . $po['title'] . ' (' . $po['votes'] . ')<br />';
				}
			}
			echo '</blockquote>';
		}
	}
} /* {{{ fud_forum_rdf_msg_print }}} */

/* This is a sample class that will be used to handle the data parsed from the
 * RDF file that contains user information. The data is stored in the following
 * class properties:
 *
 *	user_id		- the id the user
 *	user_login	- login/alias on the forum
 *	user_name	- real name
 *	user_email	- e-mail ('.' & '@' replaced with text equivalents for spam protection)
 *	post_count	- number of messages the user had posted
 *	homepage	- homepage (optional)
 *	bday		- birthdate (optional)
 *	last_visit	- last visit (optional)
 *	reg_date	- registration date (optional)
 *	im_icq		- ICQ uin (optional)
 *	im_yahoo	- Yahoo Messenger (optional)
 *	im_msnm		- MSN Messenger (optional)
 *	im_jabber	- Jabber (optional)
 *	im_affero	- Affero (optional)
 *	-- 	The following are going to be filled only if a user had posted
 *		a message and the permissions allow it to be displayed
 *	--
 *	m_subject	- subject of the message
 *	m_id		- message id
 *	m_thread_id	- thread id
 *	m_forum_id	- forum id
 *	m_forum_title	- forum title
 *	m_cat_title	- category title
 *
 */

class fud_forum_rdf_user_print extends fud_forum_rdf_user
{
	function handle_fud_data()
	{
		echo '<b>User:</b> <a href="'.$this->forum_url.'?t=usrinfo&amp;id='.$this->user_id.'">'.$this->user_login.'</a>	<br />';
		echo '<b>Real Name:</b> '.$this->user_name.'<br />';
		echo '<b>E-mail:</b> <a href="mailto:'.$this->user_email.'">'.$this->user_email.'</a><br />';
		echo '<b>Posted Messages:</b> '.$this->post_count.' <a href="'.$this->forum_url.'?t=showposts&id='.$this->user_id.'">view all messages by this user</a><br />';
		/* draw last post by this user, if one is avaliable */
		if ($this->m_id) {
			echo '<b>Last post:</b> '.$this->m_cat_title.' &raquo; <a href="'.$this->forum_url.'?t=rview&amp;frm_id='.$this->m_forum_id.'">'.$this->m_forum_title.'</a> &raquo; <a href="'.$this->forum_url.'?t=rview&amp;th='.$this->m_thread_id.'&amp;goto='.$this->m_id.'">'.$this->m_subject.'</a> <br />';
		}
		/* all of the following fields are optional, and therefor you should check if they exist before using them */
		if ($this->homepage) {
			echo '<b>Homepage: <a href="'.$this->homepage.'" target="_blank">'.$this->homepage.'</a><br />';
		}
		if ($this->avatar_img) {
			echo '<b>Avatar:</b> '.$this->avatar_img.'<br />';
		}
		if ($this->reg_date) {
			echo '<b>Registered On:</b> '.$this->reg_date.'<br />';
		}
		if ($this->bday) {
			echo '<b>Birthday:</b> '.$this->bday.'<br />';
		}
		if ($this->last_visit) {
			echo '<b>Last Visit:</b> '.$this->last_visit.'<br />';
		}
		if ($this->im_icq) {
			echo '<b>ICQ:</b> '.$this->im_icq.'<br />';
		}
		if ($this->im_aim) {
			echo '<b>AIM:</b> '.$this->im_aim.'<br />';
		}
		if ($this->im_yahoo) {
			echo '<b>Yahoo Messenger:</b> '.$this->im_yahoo.'<br />';
		}
		if ($this->im_msnm) {
			echo '<b>MSN Messenger:</b> '.$this->im_msnm.'<br />';
		}
		if ($this->im_jabber) {
			echo '<b>Jabber:</b> '.$this->im_jabber.'<br />';
		}
		if ($this->im_affero) {
			echo '<b>Affero:</b> '.$this->im_affero.'<br />';
		}
		echo '<hr>';
	}
} /* {{{ fud_forum_rdf_user_print }}} */

/* This is a sample class that will be used to handle the data parsed from the
 * RDF file that contains topic information. The data is stored in the following
 * class properties:
 *
 * topic_id		- the id of the topic
 * topic_title		- subject of the current topic
 * topic_creation_date	- topic creation date
 * forum_id		- forum id
 * forum_title		- forum name
 * category_title	- category title
 * author		- topic author
 * author_id		- author id
 * replies		- number of replies to this topic
 * views		- number of times this topic has been viewed
 * 	-- Only avaliable if topic has replies --
 * last_post_id		- id of the last message in the topic
 * last_post_subj	- subject of the last message in the topic
 * last_post_date	- date when the last message in the topic was posted
 */

class fud_forum_rdf_topic_print extends fud_forum_rdf_topic
{
	function handle_fud_data()
	{
		echo $this->category_title . ' &raquo; <a href="'.$this->forum_url.'?t=rview&amp;frm_id='.$this->forum_id.'">'.$this->forum_title.'</a> &raquo; <a href="'.$this->forum_url.'?t=rview&amp;th='.$this->topic_id.'">'.$this->topic_title.'</a><br />';
		echo '<b>Created By:</b> <a href="'.$this->forum_url.'?t=usrinfo&amp;id='.$this->author_id.'">'.$this->author.'</a> on '.$this->topic_creation_date.'<br />';
		echo 'This topic was <b>viewed '.$this->views.'</b> times and has <b>'.$this->replies.' replies</b><br />';
		/* this means that the thread has >1 replies and this will display the link to the last message
		 * in this thread.
		 */
		if ($this->last_post_id) {
			echo '<b>Last Post:</b> <a href="'.$this->forum_url.'?t=rview&amp;th='.$this->topic_id.'&amp;goto='.$this->last_post_id.'">'.$this->last_post_subj.'</a> posted on '.$this->last_post_date.'<br />';
		}
	}
} /* {{{ fud_forum_rdf_topic_print }}} */

class fud_forum_rdf_msg
{
	var	$parser, $ctag, $ctag_attr, $in_parser=false, $forum_url=null;

	var	$title, $topic_id, $topic_title, $message_id, $reply_to_id, $reply_to_title, $forum_id, $forum_title,
		$category_title, $author, $author_id, $attachments, $poll_name, $total_votes, $poll_opts, $body;

	var	$cur_poll_opt = 0, $cur_attach = 0;

	function parse($url)
	{
		$this->parser = xml_parser_create();
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, 'tag_open', 'tag_close');
		xml_set_character_data_handler($this->parser, 'pdata');
		xml_parse($this->parser, file_get_contents($url)) or die ("XML error: ".xml_error_string(xml_get_error_code($this->parser))." at line ".xml_get_current_line_number($this->parser)."<br />\n");
		xml_parser_free($this->parser);
	}

	function tag_open($parser, $tag, $attributes)
	{
		if (!$this->in_parser && $tag === 'item') {
			$this->in_parser = true;
		}
		$this->ctag = $tag;
		if ($tag === 'content:item') {
			$this->ctag_attr = $attributes['rdf:about'];
		}
	}

	function pdata($parser, $cdata)
	{
		if ($this->in_parser && trim($cdata) !== '') {
			switch ($this->ctag) {
				case 'title':
				case 'topic_id':
				case 'topic_title':
				case 'message_id':
				case 'reply_to_id':
				case 'reply_to_title':
				case 'forum_id':
				case 'forum_title':
				case 'category_title':
				case 'author':
				case 'author_id':
				case 'body':
				case 'poll_name':
				case 'total_votes':
					if (isset($this->{$this->ctag})) {
						$this->{$this->ctag} .= $cdata;
					} else {
						$this->{$this->ctag} = $cdata;
					}
					break;

				case 'a_title':
				case 'a_id':
				case 'a_size':
				case 'a_nd':
					$this->attachments[$this->cur_attach][substr($this->ctag, 2)] = $cdata;
					break;

				case 'opt_title':
				case 'opt_votes':
					$this->poll_opts[$this->cur_poll_opt][substr($this->ctag, 4)] = $cdata;
					break;
			}
		} else if ($this->ctag == 'link' && trim($cdata)) {
			$this->forum_url = $cdata;
		}
	}

	function tag_close($parser, $tag)
	{
		if ($this->in_parser && $tag === 'item') {
			$this->handle_fud_data();
			unset($this->title, $this->topic_id, $this->topic_title, $this->message_id, $this->reply_to_id, $this->reply_to_title, $this->forum_id, $this->forum_title, $this->category_title, $this->author, $this->author_id, $this->attachments, $this->poll_name, $this->total_votes, $this->poll_opts, $this->body);
			$this->cur_poll_opt = $this->cur_attach = 0;
			$this->in_parser = false;
		}
		if ($tag === 'content:item') {
			if ($this->ctag_attr === 'attachments') {
				$this->cur_attach++;
			} else {
				$this->cur_poll_opt++;
			}
			$this->ctag_attr = null;
		}
	}
}

class fud_forum_rdf_user
{
	var	$parser, $ctag, $ctag_attr, $in_parser=false, $forum_url=null;

	var	$user_id, $user_login, $user_name, $user_email, $post_count, $avatar_img, $homepage,
		$bday, $last_visit, $reg_date, $im_icq, $im_aim, $im_yahoo, $im_msnm, $im_jabber, $im_affero,
		$m_subject, $m_id, $m_thread_id, $m_forum_id, $m_forum_title, $m_cat_title;

	function parse($url)
	{
		$this->parser = xml_parser_create();
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, 'tag_open', 'tag_close');
		xml_set_character_data_handler($this->parser, 'pdata');
		xml_parse($this->parser, file_get_contents($url)) or die ("XML error: ".xml_error_string(xml_get_error_code($this->parser))." at line ".xml_get_current_line_number($this->parser)."<br />\n");
		xml_parser_free($this->parser);
	}

	function tag_open($parser, $tag, $attributes)
	{
		if (!$this->in_parser && $tag === 'item') {
			$this->in_parser = true;
		}
		$this->ctag = $tag;
	}

	function pdata($parser, $cdata)
	{
		if ($this->in_parser && trim($cdata) !== '') {
			$this->{$this->ctag} .= $cdata;
		} else if ($this->ctag == 'link' && trim($cdata)) {
			$this->forum_url = $cdata;
		}
	}

	function tag_close($parser, $tag)
	{
		if ($this->in_parser && $tag === 'item') {
			$this->handle_fud_data();
			unset($this->user_id, $this->user_login, $this->user_name, $this->user_email, $this->post_count, $this->avatar_img, $this->homepage, $this->bday, $this->last_visit, $this->reg_date, $this->im_icq, $this->im_aim, $this->im_yahoo, $this->im_msnm, $this->im_jabber, $this->im_affero, $this->m_subject, $this->m_id, $this->m_thread_id, $this->m_forum_id, $this->m_forum_title, $this->m_cat_title);
			$this->in_parser = false;
		}
	}
}

class fud_forum_rdf_topic
{
	var	$parser, $ctag, $ctag_attr, $in_parser=false, $forum_url=null;

	var	$topic_id, $topic_title, $topic_creation_date, $forum_id, $forum_title, $category_title,
		$author, $author_id, $replies, $views, $last_post_id, $last_post_subj, $last_post_date;

	function parse($url)
	{
		$this->parser = xml_parser_create();
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, 'tag_open', 'tag_close');
		xml_set_character_data_handler($this->parser, 'pdata');
		xml_parse($this->parser, file_get_contents($url)) or die ("XML error: ".xml_error_string(xml_get_error_code($this->parser))." at line ".xml_get_current_line_number($this->parser)."<br />\n");
		xml_parser_free($this->parser);
	}

	function tag_open($parser, $tag, $attributes)
	{
		if (!$this->in_parser && $tag === 'item') {
			$this->in_parser = true;
		}
		$this->ctag = $tag;
	}

	function pdata($parser, $cdata)
	{
		if ($this->in_parser && trim($cdata) !== '') {
			$this->{$this->ctag} .= $cdata;
		} else if ($this->ctag == 'link' && trim($cdata)) {
			$this->forum_url = $cdata;
		}
	}

	function tag_close($parser, $tag)
	{
		if ($this->in_parser && $tag === 'item') {
			$this->handle_fud_data();
			unset($this->topic_id, $this->topic_title, $this->topic_creation_date, $this->forum_id, $this->forum_title, $this->category_title, $this->author, $this->author_id, $this->replies, $this->views, $this->last_post_id, $this->last_post_subj, $this->last_post_date);
			$this->in_parser = false;
		}
	}
}
	switch ($mode) {
		case 'message':
			$xml = new fud_forum_rdf_msg_print;
			break;
		case 'user':
			$xml = new fud_forum_rdf_user_print;
			break;
		case 'topic':
			$xml = new fud_forum_rdf_topic_print;
			break;
	}
	$xml->parse($path_to_rdf);
?>
