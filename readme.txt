=== Freshdesk (official)===
Contributors: freshdeskintegration
Tags: freshdesk, helpdesk, contact form, knowledge base, customer support software, freshdesk ticket
Requires at least: 3.4
Tested up to: 6.4.3
Stable tag: 2.4.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Quickly embed the Freshdesk help widget, convert WordPress comments to tickets and seamlessly log your WordPress users into your support portal.

== Description ==

With the Freshdesk (official) plugin, you can now:

* quickly integrate the help widget into your WordPress site or blog
* convert comments on your WordPress site into Freshdesk tickets
* allow users on WordPress to seamlessly login to your support portal via SSO


== Installation ==

* For an automatic installation through WordPress

1. Go to the Plugins page in your WordPress admin section.
2. Click the 'Add new button.
3. Search for the 'Freshdesk (official)' plugin.
4. Click 'Install now' and activate the plugin.


== Manual Installation ==

1. Download the latest version of the 'Freshdesk (official)'  plugin from the WordPress plugin directory.
2. Extract the zip and upload the "freshdesk-support" directory to your /wp-content/plugins directory.
3. Go to the 'Plugins' page in your admin section and activate the plugin.
4. You now have a new admin menu 'Freshdesk' in your WordPress admin menu bar. Click on it and configure the settings.

== In case of NGNIX Server == 

Follow the below steps:

Case #1 - If your domain is like example.com, add the below line in your nginx.conf (located at /etc/nginx/sites-available/)

location / {
try_files $uri $uri/ /index.php?q=uri&args;
}

Case #2 - If your domain is like example.com/freshdesk, add the below line in your nginx.conf (located at /etc/nginx/sites-available/)

location /freshdesk {
try_files $uri $uri/ /freshdesksso/index.php?q=$uri&$args;
}

== In case of Apache Server == 

Please make sure the "Permalink" set to " Post name" under "Settings -> Permalink -> Common Settings"


== Frequently asked questions ==

= 1. What is the help widget? =

The help widget allows you to embed a contact form or help articles on your website. To use the help widget, you need to create a Freshdesk account by [signing up here](https://freshdesk.com/signup?utm_source=wordpressplugin&utm_medium=pluginreadme). 

= 2. Where can I find the help widget code? = 

Once you've logged in to your Freshdesk account, you can go to **Admin** > **Widgets** and create a new widget. You can click the 'Embed code' tab to copy the code from your Freshdesk account.

You can then paste it into the plugin settings page in WordPress. This will make the widget appear on all pages in your WordPress account.

= 3. Where do I find the SSO shared secret key? = 

  The SSO shared secret will be available in your Freshdesk account's **Admin** -> **Security** page.


== Screenshots == 

1. The Freshdesk help widget
2. Freshdesk (official) plugin's settings page in WordPress


== Changelog ==

= 2.4.1 =
  Added compatibility with other plugins that uses 'login_message' filter

= 2.4.0 =
  Updated OAuth library to version 4.4.0

= 2.3.6 =
  Fixed php 8.2 compatibility warnings
  Updated Client Information Section

= 2.3.5 =
  Fixed Basic Settings missing checkbox issue

= 2.3.4 =
  Fixed minor bugs

= 2.3.3 =
  Fixed the warning Undefined array key "action"

= 2.3.2 =
  There is a typo in a function that updates 'wo_options' on wp-oauth-main which is resolved and aded validation

= 2.3.1 =
  Widget not showing on the front end issue is resolved

= 2.3 =
  Typo Fix
  Last used URLs with credentials
  Support with PHP 8.0 & PHP 8.1
  Updated code to match WordPress coding standards
  Updated oauth library

= 2.2 =
  Introducing support for Freshworks SSO via OAuth.

= 2.1 =
  Freshdesk URL fields are made to be synced with each other.

= 2.0 =
  Updated code to match WordPress's latest coding standards.
  Reorganized the settings page.
  The updated content on the settings page makes things easier to understand.
  And we're back! :)
= 1.8.4 =
  minor SSO bug fix.
= 1.8.3 =
  fixed redirects not working when HTTP protocol is not allowed in the redirect parameter.
= 1.8.2 =
  replaced split with explode to support php7
= 1.8.1 =
  Fixed minor bugs.
= 1.8 =
  Security fix for open redirect vulnerability. Now users are required to configure all the portal URLs on the settings page.
= 1.7 =
  Added option to enable/disable SSO.
  Logging out of WordPress no longer logs out of the Freshdesk session.
  Added validations for the settings page.
  Fixed the error showing up on the plugin settings page.
= 1.6 =
  Fix the SSO login redirect to Freshdesk and on WordPress logout, logout of Freshdesk as well.
= 1.5 =
  Includes fix for SSO and Vanity URL redirect.
= 1.4 =
  Bug fix for SSO and Vanity URL redirect.
= 1.3 =
 Added comment link to the ticket description
= 1.2 =
 Fix the error message("The plugin does not have a valid header.") on enabling the plugin
= 1.1 =
Previous revision.
Bug Fix:
 - Freshdesk remote log-in failing for new users.
 - Sign-out from freshdesk does not log out WordPress session.
= 1.0 =
First Release Version.