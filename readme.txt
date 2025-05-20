=== BB Custom Media Offload MS ===
Contributors: buddybossadvanced
Tags: buddyboss, multisite, media, offload, bunny, cdn
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smart media offloading to Bunny.net for WordPress Multisite with support for local file retention.

== Description ==

BB Custom Media Offload MS provides efficient media management for WordPress multisite installations, especially those running BuddyBoss Platform. This plugin allows you to offload media files to Bunny.net CDN while keeping specific file types (like Lottie animations) on your local server.

= Key Features =

* **Selective File Handling** - Configure which file types remain on your local server
* **Delayed Offloading** - Users see uploads immediately while files are queued for offloading
* **NFS Integration** - Works with shared NFS storage across multiple WordPress servers
* **Multisite Support** - Network-wide settings with per-site enablement options
* **BuddyBoss Integration** - Seamlessly integrates with the BuddyBoss Advanced Enhancements menu

= Perfect For =

* High-traffic BuddyBoss communities
* WordPress multisite networks with large media libraries
* Sites using Lottie animations or other special media that need to stay local
* Setups with load-balanced WordPress servers

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/bb-custom-media-offload-ms` directory, or install the plugin through the WordPress plugins screen.
2. Network activate the plugin on your WordPress Multisite network.
3. Configure the plugin settings via the "BB Advanced > Media Offload" menu in your network admin.

= NFS Setup =

For optimal performance with load-balanced WordPress servers, we recommend setting up a dedicated NFS server:

1. Create a dedicated Ubuntu droplet for NFS server
2. Attach a Volume to this NFS server droplet
3. Install NFS server: `apt install nfs-kernel-server`
4. Configure exports to allow access from your WordPress servers
5. On WordPress servers: `apt install nfs-common`
6. Mount NFS share to same location on all WordPress servers
7. Set that mount point as the NFS Base Path in plugin settings

== Frequently Asked Questions ==

= Can I keep certain file types on my local server? =

Yes! You can specify file extensions (like json for Lottie animations) that should remain on your local server instead of being offloaded to Bunny.net.

= How does this work with multiple WordPress servers? =

The plugin is designed to work with shared NFS storage across multiple WordPress instances. Files are first stored in the shared NFS location, then offloaded to Bunny.net after a configurable delay.

= Does this work with BuddyBoss Platform? =

Yes, this plugin is specifically designed to integrate with BuddyBoss Platform and enhance its media handling capabilities.

= Can I control which sites in my network use this feature? =

Yes, the network settings allow you to enable or disable the feature for specific sites in your multisite network.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release