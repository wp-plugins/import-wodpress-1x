<?php

$msg = '';

class WP1x_Import {

var $blog_url = '';
var $posts_feed = '';
var $comments_feed = '';
var $posts = array();
var $comments = array();
var $summary = '';
var $cats_before = array();
var $cats_after = array();

  function WP1x_Import() {
	$this->cats_before = array();
	$cats = get_categories();
	  foreach($cats as $ctgr) $this->cats_before[] = $ctgr->cat_name;
	$this->cats_after = $this->cats_before;
	}

  function header() { 
  ?>
  <div class="wrap" id="wrap_iwp1x"> 
  <h2><?php _e('Import WordPress 1.x', 'import1x'); ?></h2>
  <?php 
  }
  
	function show_msg() {
  global $msg;
    if(!empty($msg)) { 
    ?>
		<script type="text/javascript">
		var els = document.getElementsByTagName('*');
			for(var i = 0; i < els.length; i++) {
			  if(els[i].className == 'tohide') els[i].parentNode.removeChild(els[i]);
			}
		var msgel = document.createElement('div');
		msgel.id = 'message';
		msgel.className = 'updated fade';
		msgel.innerHTML = '<p><strong><?php print $msg; ?></strong></p>';
		var refel = document.getElementById('wrap_iwp1x');
		refel.parentNode.insertBefore(msgel, refel);
		</script>
    <?php 
    }
  }
	
  function footer() { ?></div><?php }

  function greet() {
  ?>
  <div class="narrow">
  <p><?php _e('This importer allows you to import data (posts and comments) from installations of  WordPress 1.x into your blog.', 'import1x'); ?></p>
  <p><?php _e('ATENTION', 'import1x'); ?>:
	<ul>
	<li><?php _e('The permalinks of this blog must have the same structure of the blog that you are importing, otherwise you will have problems with the links.', 'import1x'); ?></li>
	<li><?php _e('In your WordPress installation, the folder .../wp-admin/import must have write permissions to anyone (666 or 777).', 'import1x'); ?></li>
	</ul>
	</p>
  <div style="margin:3px; padding:10px; background-color:#F1F1F1;">
  <p><?php _e('Type the URL to the WordPress blog you want to import', 'import1x'); ?></p>
  <form method="post" action="<?php 
	  print get_bloginfo('wpurl'); ?>/wp-admin/admin.php?import=wordpress1x&amp;step=1">
  <p><label for="blog_url"><?php _e('Blog URL', 'import1x'); ?>:</label>
  <input type="text" style="width:70%" name="blog_url" id="blog_url" value="http://" />
  </p>
  <p>
  <input type="checkbox" value="1" name="create_cat" id="create_cat" checked="checked" />
  <label for="create_cat"><?php _e('Create categories', 'import1x'); ?></label><br />
  <input type="checkbox" value="1" name="inc_comments" id="inc_comments" checked="checked" />
  <label for="inc_comments"><?php _e('Import comments', 'import1x'); ?></label><br />
  <input type="checkbox" value="1" name="moretime" id="moretime" />
  <label for="moretime"><?php 
	  _e('Increase time limit (for large amounts of articles)', 'import1x'); ?></label>
  </p>
  <p class="submit">
  <input type="submit" value="<?php _e('Import WordPress 1.x', 'import1x'); ?> &raquo;" />
  </p>
	<script type="text/javascript">document.getElementById('blog_url').focus();</script>
  </form></div>
  </div>
  <?php
  }
  
	function report($message, $prin = true) {
	$this->summary .= $message."\n";
	  if($prin) { print $message."\n"; flush(); }
	}

  function dispatch() {
  global $msg;
    if(empty($_GET['step'])) $step = 0;
    else $step = (int)$_GET['step'];
  $this->header();
    if($step == 0) $this->greet();
    else {
      if(empty($_POST['blog_url'])) {
			$msg = __('The blog URL must be filled', 'import1x');
      $this->greet();
			} else {
			  if(isset($_POST['moretime'])) set_time_limit(180);
      $this->blog_url = $_POST['blog_url'];
			$this->started = date('H:i:s');
      $this->import(isset($_POST['inc_comments']), isset($_POST['create_cat']));
      }
    }
  $this->footer();
	$this->show_msg();
  }
  
  function import($comments = false, $create_cat = false) {
  global $wpdb;
	$this->report('<div class="tohide"><strong>'.
	              __('Started at', 'import1x').": {$this->started}</strong></div>");
	$this->report('<div class="tohide">'.__('Reading feeds...', 'import1x'));
  $this->get_feeds($this->blog_url, $comments);
	$this->report(' &nbsp; '.__('...done.', 'import1x').'</div>');
    if(empty($this->posts_feed)) {
		$this->greet();
    return false;
    }
  preg_match_all("/<item>(.*?)<\/item>/is", $this->posts_feed, $items);
  $posts = $items[1];

	$this->report('<div>'.__('Proccessing data...', 'import1x'));
    foreach ($posts as $post) {
    preg_match('|<guid[^>]*>(.*?)</guid>|is', $post, $guid);
      if(!empty($guid[1])) $guid = trim($guid[1]);
      else {
    preg_match('|<link[^>]*>(.*?)</link>|is', $post, $guid);
        if(!empty($guid[1])) $guid = trim($guid[1]);
        else continue;
      }
    $this->posts[$guid] = array();
    $this->posts[$guid]['guid'] = $guid;
    preg_match('|<title>(.*?)</title>|is', $post, $title);
    $this->posts[$guid]['post_title'] = str_replace(array('<![CDATA[', ']]>'), '', 
                                                    $wpdb->escape(trim($title[1])));
    preg_match('|<pubdate>(.*?)</pubdate>|is', $post, $post_date_gmt);
    $this->posts[$guid]['post_date_gmt'] = gmdate('Y-m-d H:i:s', strtotime($post_date_gmt[1]));
    $this->posts[$guid]['post_date'] = get_date_from_gmt($this->posts[$guid]['post_date_gmt']);
    preg_match_all('|<category>(.*?)</category>|is', $post, $categories);
    $cats = array();
      foreach($categories[1] as $cat) $cats[] = $wpdb->escape(trim($cat));
    $this->posts[$guid]['post_category'] = $cats;
    preg_match('|<content:encoded>(.*?)</content:encoded>|is', $post, $post_content);
		$post_content[1] = preg_replace("/<a id=\"more\-\d+\"><\/a>/i", 
		                                "<!--more-->", $post_content[1]);
    $this->posts[$guid]['post_content'] = str_replace(array ('<![CDATA[', ']]>'), '', 
                                                      $wpdb->escape(trim($post_content[1])));
    preg_match('|<description>(.*?)</description>|is', $post, $post_excerpt);
      if(!empty($post_excerpt[1])) 
        $this->posts[$guid]['post_excerpt'] = $wpdb->escape(trim(preg_replace("/<\!\[CDATA\[|\]\]>/", "", $post_excerpt[1])));
    $this->posts[$guid]['post_author'] = 1;
    $this->posts[$guid]['post_status'] = 'publish';
    $this->posts[$guid]['comment_status'] = 'open';
    }
	$this->report(' &nbsp; '.__('...done.', 'import1x').'</div>');

  $this->report("<h4>".__('Importing posts...', 'import1x')."</h4>");
  $this->report("<ul>");
  $this->add_posts($create_cat);
  $this->report("</ul>");

    if($comments and !empty($this->comments_feed)) {
    preg_match_all("/<item>(.*?)<\/item>/is", $this->comments_feed, $items);
    $commts = $items[1];

      foreach ($commts as $comm) {
      preg_match('|<guid[^>]*>(.*?)</guid>|is', $comm, $guid);
        if(!empty($guid[1])) $guid = trim($guid[1]);
        else {
      preg_match('|<link[^>]*>(.*?)</link>|is', $comm, $guid);
          if(!empty($guid[1])) $guid = trim($guid[1]);
          else continue;
        }
      $arr = array();
      $arr['comment_post_ID'] = $this->posts[preg_replace("/#.+$/", "", trim($guid))]['ID'];
      $arr['comment_author_email'] = '';
      $arr['comment_author_url'] = '';
      $arr['comment_type'] = '';
      $arr['comment_agent'] = $_SERVER['HTTP_USER_AGENT'];
      preg_match('|<title>(.*?)</title>|is', $comm, $title);
      $arr['comment_author'] = str_replace(array('<![CDATA[', ']]>', 'by: '), '', 
                                           $wpdb->escape(trim($title[1])));
      preg_match('|<pubdate>(.*?)</pubdate>|is', $comm, $date_gmt);
      $arr['comment_date_gmt'] = gmdate('Y-m-d H:i:s', strtotime($date_gmt[1]));
      $arr['comment_date'] = get_date_from_gmt($arr['comment_date_gmt']);
      preg_match('|<content:encoded>(.*?)</content:encoded>|is', $comm, $cont);
      $arr['comment_content'] = str_replace(array ('<![CDATA[', ']]>'), '', 
                                            $wpdb->escape(trim($cont[1])));
      $this->comments[] = $arr;
      }
    $this->report("<h4>".__('Imported comments', 'import1x')."</h4>");
    $commsnum = $this->add_comments();
    $this->report("<ul><li>$commsnum ".__('comments imported.', 'import1x')."</li></ul>");
    }
	$this->report("<h4>".__('Created categories', 'import1x')."</h4>");
	$catsnum = count($this->cats_after) - count($this->cats_before);
	$this->report("<ul><li>$catsnum ".__('categories created.', 'import1x')."</li></ul>");
	$this->finished = date('H:i:s');
	$this->report("<div><strong>".__('Finished at', 'import1x').": {$this->finished}</strong></div>");
	$this->show_msg();
  }
  
  function add_posts($create_cat = false) {
    foreach($this->posts as $gui => $post) {
    $post_id = wp_insert_post($post);
    $this->posts[$gui]['ID'] = (int)$post_id;
      if($create_cat and count($this->posts[$gui]['post_category']) > 0) {
      wp_create_categories($this->posts[$gui]['post_category'], $post_id);
			  foreach($this->posts[$gui]['post_category'] as $ct) {
				  if(!in_array($ct, $this->cats_after)) $this->cats_after[] = $ct;
				}
			}
    $this->report("<li>{$post['post_title']}</li>");
    }
  }
  
  function add_comments() {
  $ret = 0;
    foreach($this->comments as $i => $comm) {
    $comment_id = wp_insert_comment($comm);
    $this->comments[$i]['ID'] = $comment_id;
    $ret++;
    }
  return $ret;
  }
  
  function get_feeds($url, $comments = false) {
	global $msg;
 // $url = preg_replace("/^([a-z]+\:\/\/.+)\/[^\/]+$/", "$1", $url);
  $url = preg_replace("/\/*$/", "", $url);
    if(empty($url)) {
    $this->posts_feed = '';
    $this->comments_feed = '';
    return false;
    }
    
  $fil = join('', file($url.'/wp-rss2.php'));
    if($this->is_feed($fil)) {
    $this->posts_feed = preg_replace("/\r\n|\n\r/", "\n", $fil);
		  if(!preg_match("/(generator=\"|<generator>http:\/\/)wordpress[\/\.]/", $fil)) {
			$msg = __('We get something, but it was not generated by WordPress...', 'import1x');
			$this->posts_feed = ''; $this->comments_feed = ''; return false;
			}
    } else {
		$msg = __('The posts could not be imported...', 'import1x');
    $this->posts_feed = ''; $this->comments_feed = ''; return false;
    }
  
		if($comments) {
		$fil = @file($url.'/wp-commentsrss2.php');
			if($this->is_feed($fil)) {
			$this->comments_feed = preg_replace("/\r\n|\n\r/", "\n", join('', $fil));
			} else {
			$msg = __('The comments could not be imported...', 'import1x');
			$this->comments_feed = '';
			}
		}
  return true;
  }
  
  function is_feed($txt) {
    if(gettype($txt) == 'array') $txt = join('', $txt);
    if(preg_match("/<?xml /", $txt) and 
       preg_match("/<rss [^>]*version=[\'|\"]2\.0[\'|\"]/", $txt)) return true;
  return false;
  }
}

$wp1x_import = new WP1x_Import();

register_importer('wordpress1x', __('WordPress 1.x', 'import1x'), 
                  __('Import posts, categories and comments from WordPress 1.x remote installations', 
									   'import1x'), array ($wp1x_import, 'dispatch'));

?>