=== The Publisher Desk ads.txt ===
Contributors: thepublisherdesk
Tags: advertising, monetization, ads, publishing, ads.txt
Requires at least: 3.0
Tested up to: 6.0.1
Stable tag: trunk
Requires PHP: 5.2.4
Version: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author URI: http://www.publisherdesk.com

Ads.txt management tool for publishers in The Publisher Desk portfolio.

=== Description ===

This is a WordPress plugin for clients of The Publisher Desk, intended to automatically propagate changes made to The Publisher Desk's ads.txt file to client websites, while letting them add/retain custom lines as desired. 

=== Functionality ===

This plugin provides management of the ads.txt file (Authorized Digital Sellers), an initiative from IAB Technology Laboratory, geared towards client of The Publisher Desk. Because the administration of a file outside the wordpress directory can pose challenges depending on the WP/Server configuration, the plugin decides on installation whether to use an actual ads.txt file in the root of the domain, or a quasi-ads.txt file built through a WP option and responding to a server request for "/ads.txt" by providing the contents of that option (containing both custom lines and those associated with TPD's centralalized list).


Troubleshooting an installation:
If you already have an ads.txt file present on your site, the plugin will rename it to ads-txt-original.txt on installation, and re-rename that file back on de-installation. If your site's wordpress instance doesn't have access to files outside of its installation directory and you already have an ads.txt file, the plugin won't be able to dislodge the existing file and allow the management of the one it creates. You can check whether this has happened if you check your site's ads.txt file directly (site.com/ads.txt) and there's no line reading:
	"### END TPD ADS TXT ###"
a line that is used by the plugin to demarcate custom entries and The Publisher Desk's. If this is the case, please deactivate the plugin, rename or remove your existing file, re-activate, and check the file again (you might have to refresh several times depending on caching). If you still don't see that line and the file looks the same as before the update, please reach out to TPD for assistance.


Final Note: it's good practice to have a backup of your custom ads.txt lines. We have functionality for retaining your custom lines on plugin updates/re-installs, but it's good to make sure since it can be a big pain to recollect entries if you lose them.



