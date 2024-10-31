<?php
/*
Plugin Name: MyBB Latest Posts
Plugin URI: 
Description: Enables a widget that displays latest threads or posts from a MyBB forum.
Author: Rosario Milone
Version: 1.1
Author URI: 
*/

define('DEFAULT_ENTRY_COUNT', 10);
define('DEFAULT_MYBB_DB_PREFIX', 'mybb_');

$show_types = array('Latest Threads','Latest Posts','Threads w/ Recent Activity');

// show type helpers
define('ST_THREADS', 0);
define('ST_POSTS', 1);
define('ST_THREADS_LASTPOST', 2);

class MyBBLatest_Widget extends WP_Widget
{
	protected $mydb;
	
	function MyBBLatest_Widget()
	{
		parent::WP_Widget('mybb-latest', 'MyBB Latest Posts', array('description' => 'Enables a widget that displays latest threads or posts from a MyBB forum.'));
	}

	function widget($args, $instance)
	{
		global $wpdb;
	
		extract($args, EXTR_SKIP);

		$title = empty($instance['title']) ? '&nbsp;' : apply_filters('widget_title', $instance['title']);
		$count = empty($instance['count']) ? DEFAULT_ENTRY_COUNT : intval($instance['count']);
		$exclude = empty($instance['exclude']) ? '' : $instance['exclude'];
		$show_type = empty($instance['show_type']) ? ST_THREADS : intval($instance['show_type']);
		$show_avatars = empty($instance['show_avatars']) ? true : intval($instance['show_avatars']);

		$mybb_db_user = $instance['mybb_db_user'];
		$mybb_db_pass = $instance['mybb_db_pass'];
		$mybb_db_name = $instance['mybb_db_name'];
		$mybb_db_prefix = $instance['mybb_db_prefix'];
		$mybb_url = $instance['mybb_url'];

		if(!empty($mybb_db_user) && !empty($mybb_db_pass) && !empty($mybb_db_name)) {
			$this->mydb = new wpdb($mybb_db_user,$mybb_db_pass,$mybb_db_name,$wpdb->dbhost);
		} else {
			$this->mydb = $wpdb;
		}

		// get entries
		$results = $this->get_entries($instance,$show_type,$exclude,$count);

		if(!empty($results)) :
			echo $before_widget;

			if(!empty($title)) {
				echo $before_title . $title . $after_title;
			}
?>
		<ul class="threads">
<?php foreach($results as $entry) : ?>
		<li><?php $this->display_entry($instance,$entry,$show_type) ?></li>
<?php endforeach ?>
		</ul>
<?php
			echo $after_widget;

		endif;
	}
	
	function get_entries($instance,$show_type,$exclude,$count) {
		$mybb_db_prefix = $instance['mybb_db_prefix'];
		
		$where = '';

		if(!empty($exclude)) {
			$exclude = $this->filter_exclude($exclude);

			if(!empty($exclude)) {
				$where = "fid NOT IN ({$exclude}) AND";
			}
		}
		
		switch($show_type) {
		case ST_THREADS:
		case ST_THREADS_LASTPOST:
			{
				$order_field = $show_type == ST_THREADS ? 'dateline' : 'lastpost';
				
				$query = $this->mydb->prepare("SELECT tid, fid, subject, " .
					"uid, username, dateline, lastpost, lastposter, lastposteruid " .
					"FROM {$mybb_db_prefix}threads " .
					"WHERE $where visible = 1 ORDER BY {$order_field} DESC LIMIT %d",$count);
				$results = $this->mydb->get_results($query);
			}
			break;
			
		case ST_POSTS:
			{
				$query = $this->mydb->prepare("SELECT pid, tid, replyto, fid, " .
					"subject, uid, username, dateline, message, ipaddress " .
					"FROM {$mybb_db_prefix}posts " .
					"WHERE $where visible = 1 ORDER BY dateline DESC LIMIT %d",$count);
				$results = $this->mydb->get_results($query);
			}
			break;
		}

		return $results;
	}
	
	function display_entry($instance,$entry,$show_type) {
		$mybb_url = $instance['mybb_url'];
		$show_avatars = $instance['show_avatars'];

		switch($show_type) {
		case ST_THREADS:
		case ST_THREADS_LASTPOST:
			{
				$username = $entry->lastposter;
				$uid = $entry->lastposteruid;	

				$verb = $show_type == ST_THREADS ? 'created' : 'posted on';
				$url = $mybb_url.'/showthread.php?tid='.$entry->tid;
				
				if($show_type == ST_THREADS_LASTPOST) {
					$url .= '&action=lastpost';
				}
			}
			break;

		case ST_POSTS:
			{
				$username = $entry->username;
				$uid = $entry->uid;

				$verb = 'posted on';
				$url = $mybb_url.'/showthread.php?tid='.$entry->tid.'&pid='.$entry->pid.'#pid'.$entry->pid;
			}
			break;
		}
?>

<?php if($show_avatars) : ?>
	<div class="avatar">
		<a href="<?php echo $mybb_url ?>/member.php?action=profile&amp;uid=<?php echo $uid ?>" title="<?php echo $username ?>"><?php $this->display_avatar($instance,$username,35) ?></a>
	</div>
<?php endif ?>
	
	<div class="entry">
		<a class="entry-user" href="<?php echo $mybb_url ?>/member.php?action=profile&amp;uid=<?php echo $uid ?>" title="<?php echo $username ?>"><?php echo $username ?></a> <?php echo $verb ?> <a class="entry-link" href="<?php echo $url ?>"><?php echo $entry->subject ?></a>
	</div>
<?php
	}
	
	function display_avatar($instance,$username,$size=48,$gravatar=false) {
		$mybb_db_prefix = $instance['mybb_db_prefix'];
		$mybb_url = $instance['mybb_url'];
		
		if($gravatar) {
			echo get_avatar(get_comment_author_email(), $size, $default_avatar );
		} else {
			$avatar_info = $this->mydb->get_row("SELECT avatar,avatartype FROM {$mybb_db_prefix}users WHERE username='{$username}' LIMIT 1");
			$avatar = $avatar_info->avatar;

			if(!empty($avatar)) {
				$avatartype = $avatar_info->avatartype;
				$avatar_url = $avatar;

				if($avatartype != 'remote') {
					$avatar_url = $mybb_url.'/'.$avatar;			
				}
			} else {
				$avatar_url = $instance['default_avatar'];				
			}
	?>
		<img src="<?php echo $avatar_url ?>" alt="" class="avatar photo" width="<?php echo $size ?>" height="<?php echo $size ?>" />
	<?php
		}
	}
	
	function filter_exclude($exclude) {
		$exclude = rtrim($exclude,',');
		$exclude = explode(',',$exclude);

		$exclude_fids = array();
		foreach($exclude as $fid) {
			$fid = intval(trim($fid));
			if($fid > 0) {
				$exclude_fids[] = $fid;					
			}
		}

		$exclude = implode(',',$exclude_fids);
		
		return $exclude;
	}

	function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['count'] = intval($new_instance['count']);
		$instance['exclude'] = strip_tags($new_instance['exclude']);
		$instance['exclude'] = $this->filter_exclude($new_instance['exclude']);
		$instance['show_type'] = intval($new_instance['show_type']);
		$instance['show_avatars'] = isset($new_instance['show_avatars']);

		$instance['mybb_db_user'] = strip_tags($new_instance['mybb_db_user']);
		$instance['mybb_db_pass'] = strip_tags($new_instance['mybb_db_pass']);
		$instance['mybb_db_name'] = strip_tags($new_instance['mybb_db_name']);
		$instance['mybb_db_prefix'] = strip_tags($new_instance['mybb_db_prefix']);
		$instance['mybb_url'] = strip_tags($new_instance['mybb_url']);
		$instance['mybb_url'] = rtrim($new_instance['mybb_url'],'/');

		$instance['default_avatar'] = strip_tags($new_instance['default_avatar']);

		if(empty($instance['count'])) {
			$instance['count'] = DEFAULT_ENTRY_COUNT;
		}
		
		if(empty($instance['mybb_db_prefix'])) {
			$instance['mybb_db_prefix'] = DEFAULT_MYBB_DB_PREFIX;
		}

		return $instance;
	}

	function form($instance)
	{
		global $show_types;
		
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );

		$title = isset($instance['title']) ? strip_tags($instance['title']) : '';
		$count = isset($instance['count']) ? intval($instance['count']) : '';
		$exclude = isset($instance['exclude']) ? strip_tags($instance['exclude']) : '';
		$show_type = isset($instance['show_type']) ? intval($instance['show_type']) : '';
		$show_avatars = intval($instance['show_avatars']);
		
		$mybb_db_user = isset($instance['mybb_db_user']) ? strip_tags($instance['mybb_db_user']) : '';
		$mybb_db_pass = isset($instance['mybb_db_pass']) ? strip_tags($instance['mybb_db_pass']) : '';
		$mybb_db_name = isset($instance['mybb_db_name']) ? strip_tags($instance['mybb_db_name']) : '';
		$mybb_db_prefix = isset($instance['mybb_db_prefix']) ? strip_tags($instance['mybb_db_prefix']) : '';
		$mybb_url = isset($instance['mybb_url']) ? strip_tags($instance['mybb_url']) : '';
		$default_avatar = isset($instance['default_avatar']) ? strip_tags($instance['default_avatar']) : '';
		
?>
			<p><label for="<?php echo $this->get_field_id('title'); ?>">Title: <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('count'); ?>">Count: <input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="text" value="<?php echo esc_attr($count); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('exclude'); ?>">Exclude: <input class="widefat" id="<?php echo $this->get_field_id('exclude'); ?>" name="<?php echo $this->get_field_name('exclude'); ?>" type="text" value="<?php echo esc_attr($exclude); ?>" /></label></p>
			
			<p><label for="<?php echo $this->get_field_id('show_type'); ?>">Show Type: 
				<select class="widefat" id="<?php echo $this->get_field_id('show_type'); ?>" name="<?php echo $this->get_field_name('show_type'); ?>">
					<?php foreach($show_types as $value => $type) : ?>
					<option value="<?php echo $value ?>"<?php if($value == $show_type) echo ' selected="selected"' ?>><?php echo $type ?></option>
					<?php endforeach ?>
				</select>
			</label></p>
			
			<p><label for="<?php echo $this->get_field_id('show_avatars'); ?>">Show Avatars: <input class="widefat" id="<?php echo $this->get_field_id('show_avatars'); ?>" name="<?php echo $this->get_field_name('show_avatars'); ?>" type="checkbox" value="<?php echo esc_attr($show_avatars); ?>"<?php if($show_avatars) echo ' checked="checked"' ?> /></label></p>

			<p><label for="<?php echo $this->get_field_id('mybb_db_user'); ?>">MyBB DB User: <input class="widefat" id="<?php echo $this->get_field_id('mybb_db_user'); ?>" name="<?php echo $this->get_field_name('mybb_db_user'); ?>" type="text" value="<?php echo esc_attr($mybb_db_user); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('mybb_db_pass'); ?>">MyBB DB Pass: <input class="widefat" id="<?php echo $this->get_field_id('mybb_db_pass'); ?>" name="<?php echo $this->get_field_name('mybb_db_pass'); ?>" type="password" value="<?php echo esc_attr($mybb_db_pass); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('mybb_db_name'); ?>">MyBB DB Name: <input class="widefat" id="<?php echo $this->get_field_id('mybb_db_name'); ?>" name="<?php echo $this->get_field_name('mybb_db_name'); ?>" type="text" value="<?php echo esc_attr($mybb_db_name); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('mybb_db_prefix'); ?>">MyBB DB Prefix: <input class="widefat" id="<?php echo $this->get_field_id('mybb_db_prefix'); ?>" name="<?php echo $this->get_field_name('mybb_db_prefix'); ?>" type="text" value="<?php echo esc_attr($mybb_db_prefix); ?>" /></label></p>
			<p><label for="<?php echo $this->get_field_id('mybb_url'); ?>">MyBB URL: <input class="widefat" id="<?php echo $this->get_field_id('mybb_url'); ?>" name="<?php echo $this->get_field_name('mybb_url'); ?>" type="text" value="<?php echo esc_attr($mybb_url); ?>" /></label></p>

			<p><label for="<?php echo $this->get_field_id('default_avatar'); ?>">Default Avatar URL: <input class="widefat" id="<?php echo $this->get_field_id('default_avatar'); ?>" name="<?php echo $this->get_field_name('default_avatar'); ?>" type="text" value="<?php echo esc_attr($default_avatar); ?>" /></label></p>
<?php
	}
}

// register widget
function mybblatest_register_widget() { register_widget('MyBBLatest_Widget'); }
add_action('widgets_init','mybblatest_register_widget');

?>