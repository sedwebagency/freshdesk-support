<?php

/**
  Plugin Name: Freshdesk Official
  Plugin URI:
  Description: Quickly embed the Freshdesk help widget, convert WordPress comments to tickets and seamlessly log your WordPress users in to your suppport portal.
  Author: Freshworks
  Version: 3.0.0
  Author URI: http://freshdesk.com/
 */


if (!class_exists('Fresh_Desk_Official')) {
	/**
	 * Class Name: Fresh_Desk_Official
	 */
	class Fresh_Desk_Official
	{
		protected $freshdesk_options = [];
		protected $freshdesk_feedback_options = [];
		
		/**
		 * Create a new instance.
		 *
		 * @return void
		 */
		public function __construct()
		{
			if (!defined('ABSPATH')) {
				die(); //Die if accessed directly.
			}

			//oauth
			require_once(dirname(__FILE__) . "/oauth/wp-oauth-server.php");
			require_once(dirname(__FILE__) . "/oauth/includes/admin/pages/add-new-client.php");
			require_once(dirname(__FILE__) . "/oauth/includes/admin/pages/manage-clients.php");
			require_once(dirname(__FILE__) . "/oauth/includes/admin/pages/edit-client.php");

			require_once(dirname(__FILE__) . "/oauth/includes/admin/page-server-options.php");

			// Define global constants
			defined('FDO_PLUGIN_URL') or define('FDO_PLUGIN_URL', plugin_dir_url(__FILE__));
			defined('FDO_PAGE_BASENAME') or define('FDO_PAGE_BASENAME', 'freshdesk-menu-handle');
			defined('FDO_DOMAIN_REGEX') or define('FDO_DOMAIN_REGEX', '/\bhttps?:\/\/([-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|])/i');

			// include freshdesk api class.
			//Sso handler and comment action handler
			add_action('init', [$this, 'fdo_login']);
			//Plugin Menu
			add_action('admin_menu', [$this, 'fdo_plugin_menu']);
			// This adds the comment action menu.
			add_filter('comment_row_actions', [$this, 'fdo_comment_action'], 10, 2);

			// This is used to redirect to Freshdesk on SSO.
			add_filter('login_redirect', [$this, 'fdo_login_redirect'], 10, 3);
			add_filter('login_message', [$this, 'fdo_show_login_message']);
			// admin messages hook!
			add_action('admin_notices', [$this, 'fdo_admin_msgs']);

			//Get Freshdesk Options from DB
			$this->freshdesk_options = (get_option('freshdesk_options') !== false) ? get_option('freshdesk_options') : [];
			$this->freshdesk_feedback_options = (get_option('freshdesk_feedback_options') !== false) ? get_option('freshdesk_feedback_options') : [];

			if (isset($this->freshdesk_options['freshdesk_enable_tickets']) && $this->freshdesk_options['freshdesk_enable_tickets'] == 'checked') {
				// This is the comment post hook which executes after the comment creation
				add_action('comment_post', [$this, 'fdo_action_callback'], 10, 2);
				add_action('wp_ajax_fd_ticket_action', [$this, 'fdo_action_ajax_callback']);
			}
			register_activation_hook(__FILE__, 'wpoauth_server_activation');
			register_activation_hook(__FILE__, array(new WO_Server, 'setup'));
			register_activation_hook(__FILE__, array(new WO_Server, 'upgrade'));
			register_deactivation_hook(__FILE__, 'wpoauth_server_deactivation');
		}

		/**
		 * Function Name: fdo_show_login_message
		 * @return void
		 */
		public function fdo_show_login_message($message)
		{
			$_GET['action'] = isset($_GET['action'])?$_GET['action']:null;

			if (!empty($this->freshdesk_options) && 
			$this->freshdesk_options['freshdesk_enable_sso'] != 'checked' && 
			$this->freshdesk_options['sso_type'] != 'freshdesk' && 
			sanitize_text_field($_GET['action']) != null && 
			sanitize_text_field($_GET['action']) == 'freshdesk-login') {
				//ToDO change the login message
				// Modify the login message and append it to the existing message
				$message .= "<div style='padding:12px;border-left:4px solid #feba11; background:#fff; margin-bottom:4px'>We are not able to log you in, please contact your Wordpress administrator to enable SSO in your account.</div>";				
			}

			return $message;
		}

		/**
		 * Function Name: fdo_login_redirect
		 * @return void
		 */
		public function fdo_login_redirect($url, $request, $user)
		{
			parse_str($request, $params);
			if (!isset($params['fd_redirect_to'])) {
				return $url;
			}
			$fd_redirect_to = $params['fd_redirect_to'];
			if (!$fd_redirect_to) {
				return $url;
			}
			$redirect_url = $this->fdo_get_redirect_url($fd_redirect_to);

			// For handling Redirect to Freshdesk on login.
			if (sanitize_text_field($_REQUEST['wp-submit']) != null && sanitize_text_field($_REQUEST['wp-submit']) == "Log In" && is_a($user, 'WP_User') && $redirect_url) {

				$user_name = $user->data->display_name;
				$user_email = $user->data->user_email;				
				$secret = $this->freshdesk_options['freshdesk_sso_key'];
				$ssl_url = $this->fdo_sso_login_url($redirect_url, $user_name, $user_email, $secret);
				sleep(1); // holding a bit so that it falls within FD 30 mins bar.
				header("Location: " . $ssl_url);
				die();
			}
			return  $request;
		}

		/**
		 * Function Name: fdo_get_redirect_url
		 * @return string
		 */
		public function fdo_get_redirect_url($host_url)
		{

			//Stripping protocols from urls to match the host url correctly.
			$host_url = preg_replace(FDO_DOMAIN_REGEX, "$1", trim($host_url));
			$domains = explode(",", $this->freshdesk_options['freshdesk_cname']);
			array_push($domains, $this->freshdesk_options['freshdesk_domain_url']);

			//Checking the host url against the provided helpdesk/portal url to avoid Open-redirect vulnerability
			foreach ($domains as $domain) { // start of the $domains loop
				$domain = trim($domain);
				$url = preg_replace(FDO_DOMAIN_REGEX, "$1", $domain);
				if ($url == $host_url) {
					return $domain;
				}
			} // end of the $domains loop
		}

		/**
		 * Function Name: fdo_plugin_menu
		 * @return void
		 */
		public function fdo_plugin_menu()
		{
			add_menu_page('Freshdesk settings', 'Freshdesk', 'manage_options', 'freshdesk-menu-handle', array($this, 'fdo_settings_page'));
			add_submenu_page('freshdesk-menu-handle', 'Freshdesk', __('Basic Settings', 'wp-oauth'), 'manage_options', 'freshdesk-menu-handle', array($this, 'fdo_settings_page'));
			//oauth
			add_submenu_page('freshdesk-menu-handle', 'Clients', __('SSO Integration', 'wp-oauth'), 'manage_options', 'wo_manage_clients', 'wo_admin_manage_clients_page');
			add_submenu_page(null, 'Settings', __('Settings', 'wp-oauth'), 'manage_options', 'wo_settings', "wo_server_options_page");
			//oauth client
			add_submenu_page(null, 'Add Client', 'Add Client', 'manage_options', 'wo_add_client', 'wo_add_client_page');
			add_submenu_page(null, 'Edit Client', 'Edit Clients', 'manage_options', 'wo_edit_client', 'wo_admin_edit_client_page');

			add_action('admin_init', array($this, 'fdo_settings_init'));
		}

		/**
		 * Function Name: fdo_settings_page
		 * @return void
		 */
		public function fdo_settings_page()
		{

			echo '<div class="wrap">
						<h2>' . esc_html__("Basic Settings") . '</h2>
						<form class="form-table" method="post" action="options.php">';
			echo esc_attr(settings_fields('freshdesk_options_group')); //setting fields group
			echo do_settings_sections('freshdesk-menu-handle');
			echo '<p class="submit"><input class="wp-core-ui button-primary" name="Submit" type="submit" value="Save changes" /></p>
						</form>
					</div>';
		}

		/**
		 * Function Name: fdo_settings_init
		 * @return void
		 */
		public function fdo_settings_init()
		{
			register_setting('freshdesk_options_group', 'freshdesk_options', array($this, 'validate_fdo_settings'));
			add_settings_section('freshdesk_settings_section', '', '', 'freshdesk-menu-handle');

			//Enable/disable freshdesk widget code.
			register_setting('freshdesk_options_group', 'freshdesk_feedback_options', array($this, 'validate_fdo_fb_settings'));
			add_settings_field('freshdesk_enable_feedback', '', array($this, 'fdo_enable_fb_callback'), 'freshdesk-menu-handle', 'freshdesk_settings_section');

			//Convert WP Comments to Freshdesk Tickets
			add_settings_field('freshdesk_convert_wp_comments_to_fd_tickets', '', array($this, 'fdo_convert_tickets_callback'), 'freshdesk-menu-handle', 'freshdesk_settings_section');
		}

		/**
		 * Function Name: fdo_convert_tickets_callback
		 * @return void
		 */
		public function fdo_convert_tickets_callback()
		{
			echo '<tr><td colspan="2"><ul class="fd-form-table"><li><div><label><input class="fd_button" type="checkbox" name="freshdesk_options[freshdesk_enable_tickets]" id="freshdesk_enable_tickets" ' . (isset($this->freshdesk_options["freshdesk_enable_tickets"]) ? esc_attr($this->freshdesk_options["freshdesk_enable_tickets"]) : '') . ' /><span class="fd_ui_element fd-bold">Convert WordPress comments to tickets in Freshdesk </span></label><div><div class="info-data fd_lmargin">When this is checked, every comment on WordPress will get created as a ticket in Freshdesk.</div></li>';
			//API Key
			echo '<div id="freshdesk_tickets_options" style="display: none;padding-left:45px">';
			$this->fdo_tickets_callback();

			//HelpDesk URL
			echo '<ul class="fd-content freshdesk_sso_settings">';
			$this->fdo_helpdesk_url_callback();
			echo '</ul>';

			echo '</div>';
		}

		/**
		 * Function Name: fdo_helpdesk_url_callback
		 * @return void
		 */
		public function fdo_helpdesk_url_callback()
		{
			echo '<li><div class="info-title">' . esc_html__('Freshdesk URL') . '</div>';
			$freshdesk_domain_url = '';
			if (isset($this->freshdesk_options['freshdesk_domain_url'])) {
				$freshdesk_domain_url = $this->freshdesk_options['freshdesk_domain_url'];
			}
			echo "<input class='fd-code freshdesk_domain_url' name='freshdesk_options[freshdesk_domain_url]' size='72' type='text' value='" . esc_attr($freshdesk_domain_url) . "' />";
			echo '<div class="info-data freshdesk_helpdesk_url">E.g.: https://yourcompany.freshdesk.com</div></li></ul>';
		}

		/**
		 * Function Name: fdo_tickets_callback
		 * @return void
		 */
		public function fdo_tickets_callback()
		{
			$freshdesk_api_key = '';
			if (isset($this->freshdesk_options['freshdesk_api_key'])) {
				$freshdesk_api_key = $this->freshdesk_options['freshdesk_api_key'];
			}
			echo "<div class='freshdesk_sso_settings'><div class='info-title'>" . esc_html__("API key") . "</div><input class='fd_ui_element' id='freshdesk_api_key' name='freshdesk_options[freshdesk_api_key]' size='72' type='text' value='" . esc_attr($freshdesk_api_key) . "' />";
			echo '<div class="info-data fd_ui_element freshdesk_helpdesk_url">' . esc_html__("Your API key can be found in your profile settings. <a href='https://support.freshdesk.com/support/solutions/articles/215517-how-to-find-your-api-key' target='_blank'>Learn more.</a>") . '</div>';
		}

		/**
		 * Function Name: fdo_enable_fb_callback
		 * @return void
		 */
		public function fdo_enable_fb_callback()
		{			
			//if (isset($this->freshdesk_feedback_options["freshdesk_enable_feedback"])) {
				echo '<tr><td colspan="2"><ul class="fd-form-table"><li><div><label><input class="fd_button" type="checkbox" name="freshdesk_feedback_options[freshdesk_enable_feedback]" id="freshdesk_enable_feedback" ' . (isset($this->freshdesk_feedback_options["freshdesk_enable_feedback"]) ? esc_attr($this->freshdesk_feedback_options["freshdesk_enable_feedback"]) : '') . ' /><span class="fd_ui_element fd-bold">Show help widget on this WordPress site</span></label><div><div class="info-data fd_lmargin">This widget will appear on all pages in your WordPress site, so you customers can reach out to you for help.</div></li>';
			//}
			$this->fdo_fb_widget_callback();
		}

		/**
		 * Function Name: fdo_fb_widget_callback
		 * @return void
		 */
		public function fdo_fb_widget_callback()
		{
			$freshdesk_domain_url = '';
			if (isset($this->freshdesk_options["freshdesk_domain_url"])) {
				$freshdesk_domain_url = $this->freshdesk_options["freshdesk_domain_url"];
			}
			echo '<li><div id="freshdesk_feedback_widget_id" style="display: none;"><div class="info-data  fd_text fd_ui_element freshdesk_widget_url"><a href="' . esc_url($freshdesk_domain_url) . '/a/admin/widgets" target="_blank">Copy the embed code</a> from your account and paste it below.</div>';
			$freshdesk_fb_widget_code = '';
			if (isset($this->freshdesk_feedback_options["freshdesk_fb_widget_code"])) {
				$freshdesk_fb_widget_code = $this->freshdesk_feedback_options["freshdesk_fb_widget_code"];
			}
			echo '<textarea class="fd_ui_element fd_text" name="freshdesk_feedback_options[freshdesk_fb_widget_code]" id="freshdesk_fb_widget_code" rows="7">' . esc_textarea($freshdesk_fb_widget_code) . '</textarea></div></li></ul></td></tr>';
		}

		/**
		 * Function Name: validate_fdo_settings
		 * This is the validation(db before_save) callback
		 * @param $input
		 * @return array
		 */
		public function validate_fdo_settings($input)
		{

			$error = 0;
			$url = trim($input['freshdesk_domain_url']);

			if ($url && !preg_match(FDO_DOMAIN_REGEX, $url)) {
				add_settings_error(
					'freshdesk_domain_url', // setting title
					'fd_invalid_domain', // error ID
					"$url doesn't seem right. Can you please check it once and try again? :)", // error message
					'error' // type of message
				);
				$error = 1;
			}
			$cnames = explode(",", trim($input['freshdesk_cname'] ?? ''));
			foreach ($cnames as $cname) { // start of the $cnames loop
				$cname = trim($cname);
				if (!preg_match(FDO_DOMAIN_REGEX, $cname) && $cname) {
					add_settings_error(
						'freshdesk_cname', // setting title
						'fd_cname_invalid_domain', // error ID
						"$cname doesn't seem right. Can you please check it once and try again? :)", // error message
						'error' // type of message
					);
					$error = 1;
					break;
				}
			} // end of the $cnames loop
			$cname = trim($input['freshdesk_cname'] ?? '');

			$sso_secret = $input['freshdesk_sso_key'] ?? '';
			$api_key = $input['freshdesk_api_key'];
			$enable_sso = isset($input['freshdesk_enable_sso']) ? $this->validate_fdo_checkbox($input['freshdesk_enable_sso']) : '';
			$sso_type = $input['sso_type'] ?? '';
			$enable_comment_convertion = isset($input['freshdesk_enable_tickets']) ? $this->validate_fdo_checkbox($input['freshdesk_enable_tickets']) : '';

			if ($enable_sso == 'checked') {
				if ($sso_type) {
					if ($sso_type == 'freshdesk') {
						if (!$sso_secret) {
							// executes when SSO Shared Secret is empty
							add_settings_error(
								'freshdesk_sso_key', // setting title
								'fd_sso_key_not_present', // error ID
								'Please enter your shared secret key', // error message
								'error' // type of message
							);
							$error = 1;
						} elseif (!$cname) {
							// executes when Portal URLs is empty
							add_settings_error(
								'freshdesk_cname', // setting title
								'fd_cname_invalid_domain', // error ID
								'Please enter portal URLs', // error message
								'error' // type of message
							);
							$error = 1;
						}
					}
				} else {
					// executes when Portal URLs is empty
					add_settings_error(
						'sso_type', // setting title
						'sso_type_selection', // error ID
						'Please select sso type', // error message
						'error' // type of message
					);
					$error = 1;
				}
			}

			if (($enable_sso == 'checked' && $sso_type == 'freshdesk') || $enable_comment_convertion == 'checked') {
				if (!$url) {
					// executes when Helpdesk URL is empty
					add_settings_error(
						'freshdesk_domain_url', // setting title
						'fd_domain_url_not_present', // error ID
						'Please enter your Freshdesk URL', // error message
						'error' // type of message
					);
					$error = 1;
				}
			}

			if ($enable_comment_convertion == 'checked') {
				if (!$api_key) {
					add_settings_error(
						'freshdesk_api_key', // setting title
						'fd_api_key_not_present', // error ID
						'Please enter your API key.', // error message
						'error' // type of message
					);
					$error = 1;
				}
			}

			if ($error) {
				return $this->freshdesk_options;
			}

			$settings = array(
				'freshdesk_domain_url' => $url,
				'freshdesk_cname' => $cname,
				'freshdesk_enable_sso' => $enable_sso,
				'sso_type' => $sso_type,
				'freshdesk_sso_key' => $sso_secret,
				'freshdesk_enable_tickets' => $enable_comment_convertion,
				'freshdesk_api_key' => $api_key
			);

			return $settings;
		}

		/**
		 * Function Name: validate_fdo_fb_settings
		 * @param $input
		 * @return array
		 */
		public function validate_fdo_fb_settings($input)
		{
			$enable_feedback = isset($input['freshdesk_enable_feedback']) ? $this->validate_fdo_checkbox($input['freshdesk_enable_feedback']) : '';
			$fb_widget_code = $input['freshdesk_fb_widget_code'];
			$settings = array('freshdesk_fb_widget_code' => $fb_widget_code, 'freshdesk_enable_feedback' => $enable_feedback);
			return $settings;
		}

		/**
		 * Function Name: validate_fdo_checkbox
		 * @param $input
		 * @return string
		 */
		public function validate_fdo_checkbox($input)
		{
			if ($input == 'on') {
				$input = 'checked';
			}
			return $input;
		}

		/**
		 * Function Name: fdo_comment_action
		 * Adding 'Create Ticket' Action for the Comments
		 * @param $actions, $comment
		 * @return string
		 */
		public function fdo_comment_action($actions, $comment)
		{
			if (current_user_can('administrator')) {
				if ((trim(get_comment_meta($comment->comment_ID, "fd_ticket_id", true)) == false)) {

					if ($this->freshdesk_options['freshdesk_enable_tickets'] == 'checked')
						$actions['freshdesk'] = '<a class="fd_convert_ticket" href="#" domain_url=' . esc_url($this->freshdesk_options['freshdesk_domain_url']) . ' id="' . $comment->comment_ID . '">' . __('Convert to Ticket', 'fd_ticket') . '</a>';
				} else {
					$actions['freshdesk'] = '<a class="fd_convert_ticket" href="#" title="hello" ticket_id="' . get_comment_meta($comment->comment_ID, "fd_ticket_id", true) . '"domain_url=' . esc_url($this->freshdesk_options['freshdesk_domain_url']) . ' id="' . esc_attr($comment->comment_ID) . '">' . __('View Ticket', 'fd_ticket_link') . '</a>';
				}
			}
			return $actions;
		}

		/**
		 * Function Name: fdo_login
		 * freshdesk login sso handler/feedback widget handler.
		 * and css/js loader for settings and comments page.
		 * @return void
		 */
		public function fdo_login()
		{
			global $pagenow, $display_name, $user_email;
			if ('wp-login.php' == $pagenow) {
				if (!isset($_REQUEST['host_url']) || empty(esc_url_raw($_REQUEST['host_url'])) || empty(sanitize_text_field($_GET['action']))) {
					return;
				}

				$domain = esc_url_raw($_REQUEST['host_url']);
				if (!$domain) {
					return;
				}
				if (sanitize_text_field($_GET['action']) == 'freshdesk-login') {
					// NOTE: is_user_logged_in dont't work during  [wp-submit] => Log In
					if ($this->freshdesk_options['freshdesk_enable_sso'] != 'checked' || $this->freshdesk_options['sso_type'] != 'freshdesk') {
						return;
					}
					if (is_user_logged_in()) {
						// For the case when user has already logged into Wordpress and then in another tab opens Freshdesk and click on login then he should be logged into FD with out entering credentials.
						$current_user = wp_get_current_user();
						$secret = $this->freshdesk_options['freshdesk_sso_key'];
						$user_name = $current_user->data->display_name;
						$user_email = $current_user->user_email;
						$url = $this->fdo_sso_login_url($this->fdo_get_redirect_url($domain), $user_name, $user_email, $secret);
						header('Location: ' . $url);
						die();
					} else { // if wordpress is not logged in.
						if (isset($domain)) {
							header("Location: " . wp_login_url() . "?redirect_to=fd_redirect_to=" . $domain);
						}
						die();
					}
				}
				if (sanitize_text_field($_GET['action']) == 'freshdesk-logout') {
					wp_logout();
					header('Location: ' . $this->fdo_get_redirect_url($domain));
					die();
				}
			}

			if ($pagenow == 'edit-comments.php' ||  (!empty($_GET['page']) && sanitize_text_field($_GET['page']) != null && sanitize_text_field($_GET['page']) == 'freshdesk-menu-handle')) {
				if (current_user_can('manage_options')) {
					wp_enqueue_script('fd_plugin_js', FDO_PLUGIN_URL . 'public/js/freshdesk_plugin_js.js', array('jquery'), filemtime(plugin_dir_path( __FILE__ ) . 'public/js/freshdesk_plugin_js.js'), false);
					wp_localize_script('fd_plugin_js', 'freshdeskAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
				}
			}

			wp_enqueue_style('fd_plugin_css', FDO_PLUGIN_URL . 'public/css/freshdesk_plugin.css', array(), filemtime(plugin_dir_path( __FILE__ ) . 'public/css/freshdesk_plugin.css'));

			$feedback_options = get_option('freshdesk_feedback_options');

			if (is_array($feedback_options)) {
				if ($feedback_options['freshdesk_enable_feedback'] == "checked") {
					add_action('wp_footer', array($this, 'fdo_widget_code'));
				}
			}
		}

		/**
		 * Function Name: fdo_widget_code
		 * Feedback widget code snippet include.
		 * @return void
		 */
		public function fdo_widget_code()
		{
			$allowed_tags = array(
				'script' => array(
					'type' => 'text/javascript',
					'src' => array()
				)
			);
			$options = get_option('freshdesk_feedback_options');
			
			if (is_array($options)) {
				echo wp_kses($options['freshdesk_fb_widget_code'], $allowed_tags);
			}
			
		}

		/**
		 * Function Name: fdo_sso_login_url
		 * @param $domain, $user_name, $email, $secret
		 * @return string
		 */
		public function fdo_sso_login_url($domain, $user_name, $email, $secret)
		{
			$time = time();
			$data = $user_name . $secret . $email . $time;
			$hash_key = hash_hmac("md5", $data, $secret);
			return $domain . "/login/sso?name=" . urlencode($user_name) . "&email=" . urlencode($email) . "&timestamp=" . $time . "&hash=" . urlencode($hash_key);
		}

		/**
		 * Function Name: fdo_action_callback
		 * Ajax Action handler. Freshdesk Ticket creation handled here.
		 * @return void
		 */
		public function fdo_action_callback($comment_ID, $comment_approved)
		{

			$id = $comment_ID;
			$comment = get_comment($id);
			$comment_link = get_comment_link($comment, 'all');
			$email = $comment->comment_author_email;
			$description = $comment->comment_content;
			$description = $description . "<br/><br/><a href=" . htmlentities($comment_link) . ">Go to comment</a>";
			$type = $comment->comment_type;
			$comment_meta = $comment->comment_agent;
			$comment_date = $comment->comment_date;
			$comment_post = $comment->comment_post_ID;
			$comment_author_name = $comment->comment_author;
			$subject = "comment id :" . $id;
			$options = get_option('freshdesk_options');

			require_once(plugin_dir_path(__FILE__) . 'includes/fresh-desk-plugin-api.php');
			$fd_api_handle = new Fresh_Desk_Plugin_Api($options['freshdesk_api_key'], $options['freshdesk_domain_url']);
			$result = $fd_api_handle->fdo_create_ticket($email, $subject, $description);
			$response = array(
				'what' => 'helpdesk_ticket',
				'action' => 'create',
				'id' => '1',
				'data' => $result
			);

			if ($result != -1) {
				$resp = add_comment_meta($id, 'fd_ticket_id', $result, false);
			}
		}

		/**
		 * Function Name: fdo_show_msg
		 * @param $message, $msgclass
		 * @return void
		 */
		public function fdo_show_msg($message, $msgclass = 'info')
		{
			echo "<div id='message' class='" . esc_attr($msgclass) . "'>". esc_html($message) ."</div>";
		}

		/**
		 * Function Name: fdo_admin_msgs
		 * @return void
		 */
		public function fdo_admin_msgs()
		{

			if (empty($_GET['page'])) {
				return;
			}
			// check for our settings page - need this in conditional further down
			$wptuts_settings_pg = strpos(sanitize_text_field($_GET['page']), FDO_PAGE_BASENAME);

			// collect setting errors/notices: //http://codex.wordpress.org/Function_Reference/get_settings_errors
			$set_errors = get_settings_errors();

			//display admin message only for the admin to see, only on our settings page and only when setting errors/notices are returned!
			if (current_user_can('manage_options') && $wptuts_settings_pg !== FALSE && !empty($set_errors)) {
				// have our settings succesfully been updated?
				if ($set_errors[0]['code'] == 'settings_updated' && sanitize_text_field($_GET['settings-updated']) != null) {
					$this->fdo_show_msg("<p>" . $set_errors[0]['message'] . "</p>", 'updated');
				} else {
					// there maybe more than one so run a foreach loop.
					foreach ($set_errors as $set_error) { // start of the $set_errors loop
						// set the title attribute to match the error "setting title" - need this in js file
						$this->fdo_show_msg("<p class='setting-error-message' title='" . $set_error['setting'] . "'>" . $set_error['message'] . "</p>", 'error');
					} // end of the $set_errors loop
				}
			}
		}

		/**
		 * Function Name: fdo_action_ajax_callback
		 * Ajax Action handler. Freshdesk Ticket creation handled here.
		 * @return void
		 */
		public function fdo_action_ajax_callback()
		{
			$id = sanitize_text_field($_POST['commentId']);
			$comment = get_comment($id);
			$comment_link = get_comment_link($comment, 'all');
			$email = $comment->comment_author_email;
			$description = $comment->comment_content;
			$description = $description . "<br/><br/><a href=" . htmlentities($comment_link) . ">Go to comment</a>";
			$type = $comment->comment_type;
			$comment_meta = $comment->comment_agent;
			$comment_date = $comment->comment_date;
			$comment_post = $comment->comment_post_ID;
			$comment_author_name = $comment->comment_author;
			$subject = "comment id :" . $id;
			$options = get_option('freshdesk_options');

			require_once(plugin_dir_path(__FILE__) . 'includes/fresh-desk-plugin-api.php');
			$fd_api_handle = new Fresh_Desk_Plugin_Api($options['freshdesk_api_key'], $options['freshdesk_domain_url']);
			$result = $fd_api_handle->fdo_create_ticket($email, $subject, $description);
			$response = array(
				'what' => 'helpdesk_ticket',
				'action' => 'create',
				'id' => '1',
				'data' => $result
			);
			if ($result != -1) {
				$resp = add_comment_meta($id, 'fd_ticket_id', $result, false);
			}
			$xmlResponse = new WP_Ajax_Response($response);
			$xmlResponse->send();
		}
	}
}

/*
* Initialising Fresh_Desk_Official instance
*/
new Fresh_Desk_Official();
