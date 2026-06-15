=== NexiSettings - Login, Redirect & Admin Toolkit ===
Contributors: nexibyllc
Tags: login, custom login, redirects, branding
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure and customize WordPress with custom login URLs, redirect management, security enhancements, performance tweaks, and admin branding tools.

== Description ==

NexiSettings is a lightweight WordPress admin toolkit that helps site owners customize and harden common WordPress settings from one place.

The plugin includes tools for login customization, redirect management, basic security hardening, performance cleanup, and admin branding.

features include:

* Custom login URL protection.
* Login page branding options.
* Simple redirect manager.
* Security toggles for XML-RPC, user enumeration, and WordPress version output.
* Performance toggles for emojis and embeds.
* Custom admin footer branding.

NexiSettings is designed for administrators who want a simple settings panel without adding multiple separate plugins for small site tweaks.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/nexisettings/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate NexiSettings through the Plugins screen in WordPress.
3. Open the NexiSettings menu item in the WordPress admin area.
4. Configure the login, redirect, security, performance, and branding options you want to use.

== Frequently Asked Questions ==

= How do I recover access if I set the wrong custom login URL? =

Add the following constant to your `wp-config.php` file:

`define( 'NEXISETTINGS_DISABLE_CUSTOM_LOGIN', true );`

Then log in through the standard WordPress login URL and update your NexiSettings configuration.

= Does NexiSettings replace a full security plugin? =

No. NexiSettings provides useful security-related toggles for common WordPress hardening tasks, but it is not intended to replace a dedicated firewall, malware scanner, backup system, or full security suite.

= Can I create redirects to external websites? =

No. Administrators can create redirects to internal. Only users with administrator-level permissions should be allowed to manage redirects.

= Will the custom login URL affect logged-in users? =

The custom login URL is intended to protect the standard login endpoint for visitors who are not already authenticated. Administrators should save the new login URL somewhere safe after enabling the feature.

= Can I disable the custom login feature manually? =

Yes. Add this constant to `wp-config.php`:

`define( 'NEXISETTINGS_DISABLE_CUSTOM_LOGIN', true );`

This disables the custom login protection so you can regain access and update the plugin settings.

== Screenshots ==

1. NexiSettings admin dashboard.
2. Login customization settings.
3. Redirect manager settings.
4. Security and performance toggles.

== Changelog ==

= 1.0.0 =
* Initial release.
