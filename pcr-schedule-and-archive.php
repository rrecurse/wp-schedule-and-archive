<?php
/*
Plugin Name: PCR Content Scheduler & Archiver
Description: Schedule posts and pages to expire. Sets expired content to new Archive status.
Version: 1.0
Author: cdebellis
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly.

  class pcr_sched_archive {
    /**
     * Instance of this class.
     *
     * @var object
     *
     * @since 1.0.0
     */
    protected static $instance = null;

    /**
     * Slug.
     *
     * @var string
     *
     * @since 1.0.0
     */
    protected static $text_domain = 'pcr-sched-post';

    /**
     * Initialize the plugin
     *
     * @since 1.0.0
     */
    private function __construct() {

      // # load css/js assets
      // # enqueue date picker for meta date feild
      add_action('admin_enqueue_scripts', array($this, 'enqueue_date_picker'));

      // # add custom meta feilds:
      add_action( 'add_meta_boxes', array($this, 'add_schedule_post'));

      // # add after-save admin notices
      add_action('admin_notices', array( $this, 'admin_notices' ) );

      // # save the custom fields
      add_action('save_post', array($this, 'pcr_save_schedule_meta'), 1, 2);

      // # register wp cron schedule event
      add_filter('cron_schedules', array($this,'per_minute_schedule'));

      // # the per-minute check to see if posts are to be archived
      add_action('pcr_sched_post_per_minute', array($this, 'per_minute_action_event'));

      // # filter expired posts from WP $query object
      add_action('pre_get_posts', array($this, 'pcr_filter_expired_posts'));

      // # filter archived posts from menus
      add_filter('wp_nav_menu_objects', array($this, 'pcr_filter_archived_from_menu'), 10, 2);

      // # register a custom post STATUS
      add_action('init', array($this, 'pcr_custom_post_status_init'), 10, 2);

      // # append the new status to status list
      add_action('admin_footer-post.php', array($this, 'pcr_append_post_status_list'));

      add_action('in_admin_footer', array($this, 'pcr_append_post_statuses'));
    }

    /**
    * Return an instance of this class.
    *
    *
    * @since 1.0.0
    *
    * @return object A single instance of this class.
    */
    public static function get_instance() {
      // # If the single instance hasn't been set, set it now.
      if ( null == self::$instance ) {
        self::$instance = new self;
      }

      return self::$instance;
    }

    /**
     * Load the plugin text domain for translation.
     *
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function load_plugin_textdomain() {
      load_plugin_textdomain( self::$text_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    // # Add the Security Images Details Meta Boxes
    public function add_schedule_post() {
      //array('id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $callback_args);
      $screens = array('page', 'post', 'alerts');
      add_meta_box('pcr_schedule_post', (get_post_type(get_the_ID()) == 'alerts' ? 'Alert Schedule' : 'Post Schedule'), array($this,'pcr_schedule_post'), $screens, 'side', 'low');
    }
    
    // # enqueue datetime picker for ADMIN meta date feilds
    public function enqueue_date_picker(){

      wp_enqueue_style('pcr-sched-post-styles', plugins_url('assets/style.css', __FILE__), array(), null, 'all');

      // # only register and queue certain scripts & styles on needed admin page
      // # get the current post type by post ID using the wp function get_the_ID() & get_post_type()
      $current_page = get_post_type(get_the_ID());

      if($current_page == 'post' || $current_page == 'page' || $current_page == 'alerts') {

        // # main plugin styles
        if(strpos($_SERVER['REQUEST_URI'], 'edit.php') === FALSE) {

          wp_enqueue_script('expire-dates', plugins_url('assets/admin-datepicker-selector.js', __FILE__), array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker'), time(), true);
        }
        
        wp_enqueue_script('datetimepicker', plugins_url('assets/jquery-ui-timepicker-addon.min.js', __FILE__), array('jquery', 'jquery-ui-datepicker', 'jquery-ui-slider'), '1.9.1', true);
     
        wp_enqueue_style('jquery-ui-datepicker', plugins_url('assets/jquery-ui.min.css', __FILE__), array(), '1.11.2', 'all');
        wp_enqueue_style('datetimepicker', plugins_url('assets/jquery-ui-timepicker-addon.css', __FILE__), array( 'jquery-ui-datepicker' ), '1.6.0', 'all');

      } else { // # if not on post page, deregister and dequeue styles & scripts
        wp_deregister_script('expire-dates');
        wp_dequeue_script('datetimepicker');
        wp_dequeue_style('jquery-ui-datepicker');
        wp_deregister_style('datetimepicker');
      }
    }

    // # The Post Scheduler Metabox
    public function pcr_schedule_post($post) {

      // Noncename needed to verify where the data originated
      echo '<input type="hidden" name="sched_noncename" id="sched_noncename" value="'.wp_create_nonce( plugin_basename(__FILE__)).'"/>';
      
      // Get the location data if its already been entered
      $start_date = get_post_meta($post->ID, self::$text_domain . '_startdate', true);
      $end_date = get_post_meta($post->ID, self::$text_domain . '_enddate', true);
      $select_status = get_post_meta($post->ID, self::$text_domain . '_select_status', true);

      if(!empty($start_date)) {
        $start_date = date('m/d/Y g:ia',(int)$start_date);
      }

      if(!empty($end_date)) {
        $end_date = date('m/d/Y g:ia',(int)$end_date);
      }
      
      // # Echo out the field
      echo '<label for "' . self::$text_domain . '_startdate">'. __('Start Date:', self::$text_domain) .'</label>';
      echo '<input type="text" name="' . self::$text_domain . '_startdate" value="' . $start_date  . '" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" readonly class="widefat" />';
      echo '<p>Expire Date:</p>';
      echo '<input type="text" name="' . self::$text_domain . '_enddate" value="' . $end_date  . '" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" readonly class="widefat" />';
      echo '<p>Expiration Status:</p>';
      echo '<select name="' . self::$text_domain . '_select_status" id="' . self::$text_domain . '_select_status" class="' . self::$text_domain . '_select_status" disabled="disabled">';
      // # select multiple roles from new dropdown_statuses() function
      self::dropdown_statuses($select_status);
      echo '</select>';

      echo '<button id="' . self::$text_domain . '_clearDates" onclick="return false;">Clear Dates</button>';

    }

    /**
     * Pre-select specified roles - supports multple
     *
     *
     * @since 1.0.0
     *
     * @param $selected The role array.
     *
     * function based on wp_dropdown_roles() found in /wp-admin/includes/template.php
     *
     */

    public function dropdown_statuses($select_status) {
      $y = '';
      $n = '';

      $statuses = get_post_stati(null, 'objects');

      $status_array = array();

      foreach ($statuses as $key => $value) {
        $status_array[$key] = $value->label;
      }

      // # remove the statuses not used:
      unset($status_array['inherit'], 
            $status_array['auto-draft'],
            $status_array['future'],
            $status_array['publish']
            );

      asort($status_array);

      $y .= '<option value="">Select Status</option>';

      foreach ($status_array as $status_key => $status ) {

        // # preselect specified status
        if($status_key == $select_status) { 
          $y .= "\n\t<option selected='selected' value='" . esc_attr($status_key) . "'>$status</option>";
        } else {
          $n .= "\n\t<option value='" . esc_attr($status_key) . "'>$status</option>";
        }
      }
      
      echo $y . $n;
    }


    // # Save the Metabox Data
    public function pcr_save_schedule_meta($post_id, $post) {
      
      global $wpdb;

      // # verify this came from the our screen and with proper authorization,
      // # because save_post can be triggered at other times
      if ( !wp_verify_nonce( $_POST['sched_noncename'], plugin_basename(__FILE__))) {
        return $post->ID;
      }

      // # Is the user allowed to edit the post or page?
      if ( !current_user_can( 'edit_post', $post->ID )) return $post->ID;

      // # Assign values to our $sched_meta array
      $sched_meta[self::$text_domain . '_startdate'] = strtotime($_POST[self::$text_domain . '_startdate']);
      $startdate = (int)$sched_meta[self::$text_domain . '_startdate'];
      $sched_meta[self::$text_domain . '_enddate'] = strtotime($_POST[self::$text_domain . '_enddate']);
      $enddate = (int)$sched_meta[self::$text_domain . '_enddate'];

      $sched_meta[self::$text_domain . '_select_status'] = $_POST[self::$text_domain . '_select_status'];
      $select_status = $sched_meta[self::$text_domain . '_select_status'];

      $post_status = $_POST['post_status'];

      // # check for status if expire date is set!
      if($enddate > 0 && $startdate > $enddate) {

        // # remove the trouble postmeta entries
        // # we had to use a direct sql delete due to delete_post_meta() function no cooperating.
        $wpdb->query("DELETE FROM ".$wpdb->postmeta." 
                      WHERE post_id = '".$post_id."' 
                      AND (meta_key = 'pcr-sched-post_enddate' 
                        OR meta_key = 'pcr-sched-post_select_status'
                      )
                    ");

        // # strangely, these did not remove the post_meta - we use the abive query instead.
        delete_post_meta($post->ID, 'pcr-sched-post_enddate');
        delete_post_meta($post->ID, 'pcr-sched-post_select_status');

        // # the following get_post_meta() function does work, which is also odd considering delete_post_meta() did not.
//error_log(print_r(get_post_meta($post->ID, self::$text_domain . '_enddate', true),1));

        // # add admin error message.
        add_filter('redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );

      }

      // # format _startdate for SQL
      if($startdate > 0) {
        $post_date = date('Y-m-d H:i:s', $startdate);
      } else {
        $post_date = date('Y-m-d H:i:s', time());
      }

      // # check value for start date and update post start date if date is in future
      if($startdate > time() && $post_status != 'draft' && $post_status != 'archive' && $post_status != 'pending') {

        $post_args = array(
          'ID' => $post->ID,
          'post_date'     => $post_date,
          'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($post_date)), // # convert to gmt
          'post_status'   => 'future',
          'post_modified' => $post_date,
          'post_modified_gmt' => gmdate('Y-m-d H:i:s', strtotime($post_date)), // # convert to gmt
        );

        // # Update the scheduled date in the database to future status
        $wpdb->update($wpdb->posts, $post_args, array('ID' => $post->ID));

      } else if($startdate < time() && $post_status == 'future') {

        $post_args = array(
          'ID' => $post->ID,
          'post_date'     => $post_date,
          'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime($post_date)), // # convert to gmt
          'post_status'   => 'publish',
          'post_modified' => $post_date,
          'post_modified_gmt' => gmdate('Y-m-d H:i:s', strtotime($post_date)), // # convert to gmt
        );

        // # Update the scheduled date in the database to publish status
        $wpdb->update($wpdb->posts, $post_args, array('ID' => $post->ID));
        //delete_post_meta($post->ID, self::$text_domain . '_startdate');
      }

      // # iterate through the $sched_meta array
      foreach ($sched_meta as $key => $meta) {

        // # Don't store custom data twice
        if($post->post_type == 'revision' ) return;

        // # If $value is an array, make it a CSV (unlikely)
        $meta = implode(',', (array)$meta);

        // # If the custom field already has a value
        if(get_post_meta($post->ID, $key, FALSE)) {
          update_post_meta($post->ID, $key, $meta);
        } else { // If the custom field doesn't have a meta value
          add_post_meta($post->ID, $key, $meta);
        }

        // # Delete if $meta is blank
        if(!$meta) {
          delete_post_meta($post->ID, $key);
        }
      }

    }

    public function pcr_custom_post_status_init(){

      if(!post_type_exists('archive')) {

        $args = array('label' => _x( 'Archive', 'post', 'alerts'),
                      'public' => true,
                      'internal' => false,
                      'private' => false,
                      'exclude_from_search' => true,
                      'show_in_admin_all_list' => false,
                      'show_in_admin_status_list' => true,
                      'label_count' => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>')
                      );

        register_post_status('archive', $args);
      }
    }

    public function pcr_append_post_status_list(){
      global $post;
      $complete = '';

      if($post->post_type == 'post' || $post->post_type == 'page' || $post->post_type == 'alerts') {

        if($post->post_status == 'archive'){
          $complete = ' selected="selected"';
          echo '
            <script>
              jQuery(document).ready( function($) {
                $(\'#post-status-display\').text(\''. __('Archived', 'self::$text_domain') .'\');
              });
            </script>';
        }

        if($post->post_status !== 'draft' && $post->post_status !== 'pending') {
          
          // # add archive status to admin post status pulldowns:
          echo '
            <script>
              jQuery(document).ready(function($){
                  $(\'select#post_status\').append(\'<option value="archive"'.$complete.'>'.__('Archive', self::$text_domain).'</option>\');
              });
            </script>';
        }
      }
    }

    public function pcr_append_post_statuses(){
      global $wpdb, $post;

      $archive_query = "SELECT COUNT(ID) 
                        FROM $wpdb->posts 
                        WHERE $wpdb->posts.post_status = 'archive' 
                        AND (post_type = 'page' 
                          OR post_type = 'post' 
                        )";

      $archive_count = (int)$wpdb->get_var($archive_query);

      $label = ($archive_count > 0 ? '<li class="np-archive-links"> | <a href="edit.php?post_status=archive&amp;post_type='.$post->post_type.'">Archive ('.$archive_count .') </a></li> ' : '');

      if($post->post_type == 'post' || $post->post_type == 'page' || $post->post_type == 'alerts' ) {
          
        // # add archive status to admin post status pulldowns:
        echo '<script>
                jQuery(document).ready(function($){

                  if(!$(\'ul.subsubsub:contains("Archive")\').length) {
                    $(\''.$label.'\').insertBefore(".np-trash-links");
                  } else {
                    // # if Archive is found, move to earlier in list:
                    $(\'ul.subsubsub\').find(\'li.archive\').insertBefore(\'.draft\').after(\' | \');
                  }

                  $(\'[name="_status"]\').append(\'<option value="archive">'. __('Archive', self::$text_domain) .'</option>\');

                });
              </script>';
      }
    }

   /**
   * Check for per-minute wp_cron schedule - add one if not found
   *
   * @since 1.0.0
   *
   * @param $schedules The schedules array.
   *
   *
   */
    public function per_minute_schedule($schedules) {

      // # double check for existing schedule names that may match per-minute
      if(!array_key_exists('pcr_sched_post_per_minute', $schedules)) {
        // # now add a 'per-minute' schedule to the existing set if none found.
        $schedules['pcr_sched_post_per_minute'] = array(
            'interval' => 60, 
            'display' => __('Every 1 Minute')
          );
      }

      return $schedules;
    }

    public function pcr_sched_archive_init() {

      if(empty(wp_next_scheduled('pcr_sched_post_per_minute'))) {
        // # wp_schedule_event ( $timestamp, $recurrence, $action, $args );
        wp_schedule_event(time(), 'pcr_sched_post_per_minute', 'pcr_sched_post_per_minute');
      }
      return;
    }

    public function pcr_sched_archive_deactivation() {

      // # remove single instance of pcr_sched_post_per_minute event
      wp_unschedule_event(wp_next_scheduled('pcr_sched_post_per_minute'), 'pcr_sched_post_per_minute');

      // # Unschedule all cron jobs attached to pcr_sched_post_per_minute hook
      wp_clear_scheduled_hook('pcr_sched_post_per_minute');
      
      // # uninstall hook filter from cron_schedules
      remove_filter('cron_schedules', array($this,'per_minute_schedule'));
    }

    public function per_minute_action_event() {

      $posts = array();

      // # get all page objects with post_status of publish or future (scheduled)
      $pages_array = get_pages(array('post_status' => 'publish,future'));

      // # iterate through pages_array and build secondary array
      foreach ($pages_array as $page) {

        // # secondary array containing page ID as key
        $posts[$page->ID] = array('post_status' => $page->post_status, 
                                  'post_type'   => $page->post_type
                                  );
      }

      $post_args = array(
        'post_type' => 'post',
        'post_status' => 'publish,future'
      );

      // # get all post objects with post_status of publish or future (scheduled)
      $posts_array = get_posts($post_args);

      foreach ($posts_array as $pst) {

        // # secondary array containing post ID as key
        $posts[$pst->ID] = array('post_status' => $pst->post_status, 
                                 'post_type'   => $pst->post_type
                                 );
      }

      // # if perp-alert plugin is active, grab alerts
      if(is_plugin_active('pcr-perp-images/pcr-perp-images.php')) {

        $alerts_args = array(
          'post_type' => 'alerts',
          'post_status' => 'publish,future',
        );

        $alerts_array = get_posts($alerts_args);

        foreach ($alerts_array as $alert) {

        // # secondary array containing post ID as key
        $posts[$alert->ID] = array('post_status' => $alert->post_status, 
                                   'post_type'   => $alert->post_type
                                 );
        }
      }
      // # scrub any duplicates from $posts[]
      array_unique($posts);

//error_log('per_minute_action_event time run = ' . print_r(date('H:i:s', time()),1));

      foreach($posts as $post_id => $post) {

        if($post_id > 0) {

          $post_status = $post['post_status'];
          $post_type = $post['post_type'];

          $start_time = get_post_meta($post_id, self::$text_domain . '_startdate', true);
          $expire_time = get_post_meta($post_id, self::$text_domain . '_enddate', true);
          $selected_status = get_post_meta($post_id, self::$text_domain . '_select_status', true);

          // # activate post if start time is set and is less than current time!
          // # wordpress does NOT automatically set a scheduled post to publish!

          if(!empty($start_time)) {

            $start_time = (int)$start_time;

            if(time() > $start_time) {

              // # check if post is scheduled
              if($post_status == 'future') {

                $post_args = array(
                  'ID' => $post_id,
                  'post_modified'     => date('Y-m-d H:i:s', time()),
                  'post_modified_gmt' => gmdate('Y-m-d H:i:s', time()), // # convert to gmt
                  'post_status'   => 'publish',
                );

                // # Update the scheduled date in the database to publish status
                wp_update_post($post_args);

              }
            }
          }

          if(!empty($expire_time)) {

            $expire_time = (int)$expire_time;

            if(time() > $expire_time) {

              // # check if post is published 
              if($post_status == 'publish') {

                // # setup the args for archiving status
                $post_args = array(
                  'ID'  => $post_id,
                  'post_status' => $selected_status,
                );

                // # avoid infinite loop noted by codex
                remove_action('save_post', 'per_minute_action_event');

                // # finally update the post status
                wp_update_post($post_args);
                
                // # and remove the postmeta keys
                delete_post_meta($post_id, self::$text_domain . '_enddate');
                delete_post_meta($post_id, self::$text_domain . '_select_status');

                // # and readd our save_post action we had to remove
                add_action('save_post', 'per_minute_action_event');

                // # check if post has children
                $children = get_posts( array('post_parent' => $post_id, 'post_type' => $post_type) );

                if(!empty($children)) {
                  foreach ($children as $postchild) {

                    // # setup the args for archiving status
                    $postchild_args = array(
                      'ID' => $postchild->ID,
                      'post_parent' => $post_id,
                      'post_status' => $selected_status,
                    );

                    // # if child, mark post_status the same as parent
                    wp_update_post($postchild_args);

                    // # clean the post cache (optional)
                    clean_post_cache($postchild->ID);
                  }
                }
                
                // # clean the post cache (optional)
                clean_post_cache($post_id);

              }
            }
          }
        }
      }
    }

    public function pcr_filter_expired_posts($query) {

      $admin_files = scandir(ABSPATH.'/wp-admin');


      $remove = array('.', '..', 'admin-ajax.php');
      $admin_array = array_diff($admin_files, $remove);
      foreach ($admin_array as $k => $page) $admin_array[] = '/wp-admin/' . $page;
      $is_admin = (in_array($_SERVER['PHP_SELF'], $admin_array) ? true : false);


      if($is_admin) return $query;

      // # if is_search or doing ajax (for auto suggest search)
      if($query->is_search() && $is_admin !== true) {

        // # only show published
        $query->set('post_status', 'publish');
        //$query->set('post_status', 'attachment');
      }

      // # check for main query (on every page load) 
      if($query->is_main_query() && $is_admin !== true) {

        // # archived posts are not publish status / show only publish
        // # secondary logic using meta_query is prob unnecessary 
        // # make sure ending key=>value pair does NOT have ending comma , 
        $query->set('post_status', 'publish',
                    'meta_query', array(
                                    'key' => 'archive',
                                    'value' => time(),
                                    'compare' => '<',
                                    'type' => 'DATE'
                                    )
                    );
      }

      return $query;
    }

    // # filter items from menus
    public function pcr_filter_archived_from_menu($items, $args) {

      if(is_admin()) return;

      foreach ($items as $ix => $item) {
        $post_status = get_post_status($item->object_id);
        if($post_status == 'archive') {
          unset($items[$ix]);
        }
      }
      return $items;
    }

    public function add_notice_query_var( $location ) {
      remove_filter('redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
      return add_query_arg( array( 'startdate-enddate-error' => 'ID' ), $location);
    }

    public function admin_notices() {
        if ( ! isset( $_GET['startdate-enddate-error'] ) ) {
          return;
        }
      ?>
      <div class="error"><p><?php esc_html_e('Start Date & Time cannot be greater than Expiration Date & Time. The incorrect Expiration Date & Time has been removed. Please review your dates and times carefully and try again.', self::$text_domain); ?></p></div>
      <?php
    }

}
  // # now trigger the class instance
  pcr_sched_archive::get_instance();

  // # plugin activation hook - call the pcr_sched_archive_init() function on activation of the plugin
  register_activation_hook(__FILE__, array('pcr_sched_archive','pcr_sched_archive_init'));

  // # plugin deactivation hook
  register_deactivation_hook(__FILE__, array('pcr_sched_archive', 'pcr_sched_archive_deactivation'));