=== NexiSettings – Login, Redirect & Admin Toolkit ===
Contributors: devatiq
Tags: login, redirects, security, performance, admin, branding
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure and customize WordPress with custom login URLs, redirect management, security enhancements, performance tweaks, and admin branding tools.

== Description ==

NexiSettings is a lightweight WordPress toolkit for common login, redirect, security, performance, and admin customization tasks.

Free version features include:

* Custom login URL protection.
* Login page branding.
* Simple redirect manager.
* Security toggles for XML-RPC, user enumeration, and WordPress version output.
* Performance toggles for emojis and embeds.
* Custom admin footer branding.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/nexisettings` directory, or install the plugin through the WordPress Plugins screen.
2. Activate NexiSettings through the Plugins screen.
3. Open the NexiSettings menu item in WordPress admin.
4. Configure the desired toolkit options.

== Frequently Asked Questions ==

= How do I recover access if I set the wrong custom login URL? =

Add the following constant to `wp-config.php`:

`define( 'NEXISETTINGS_DISABLE_CUSTOM_LOGIN', true );`

Then log in through the standard WordPress login URL and update your settings.

== Changelog ==

= 1.0.0 =

* Initial release.
