=== Somatic Framework ===
Contributors: somatic
Tags: CMS, custom post type, metabox, custom taxonomy
Donate link: http://somaticstudios.com.com/code
Requires at least: 3.3
Tested up to: 3.3
Stable tag: 1.3.2
License: GPLv2 or later

Adds useful classes for getting the most out of Wordpress' advanced CMS features

== Description ==

This framework is a collection of classes and functions for handling advanced custom post types cases. With just a few pre-populated arrays, it can create custom post types, their labels, metaboxes, save routines, and any custom taxonomies.

NOTE: this began life as an internal development tool, and as such, does not have much (if any documentation) just yet. It's not really end-user friendly in its current state. So if you're not running a site I have built for you personally, you probably don't need it ;-)

== Installation ==

Upload, activate, have a drink... but first, install and activate Scribu's excellent Posts 2 Posts plugin, which this framework requires!


== Frequently Asked Questions ==

= Do I need this plugin? =

If you're using a theme or setting up a site I built for you, then very likely, yes...

Otherwise, not yet ;-)

= I updated my call to soma_init_taxonomy() and added new terms, but why aren't they appearing? =

deactivate and reactivate your theme/plugin that contains the function call, as term generation only happens upon activation...

== Changelog ==

= 1.3.2 =
* admin footer text output
* fixed numeric field input to allow colon (:)

= 1.3.1 =
* fixed problem with setting the option soma_meta_serialize, which needs to be passed 0 or 1, not boolean true or false (false resulted in empty option_value, which broke everything)
* NOTE: when using soma_asset_meta(), don't include the prefix when specifying the post_meta key! it gets added automatically. Just use the exact ID you gave when declaring the field in  soma_metabox_data()
* new API function: soma_singular_term() for retrieving the term of a taxonomy that is meant to have only one value at a time.
* new documentation - example PHP code in the doc folder, to help demonstrate how to use the somatic framework

= 1.3 =
* added 'navbar' argument to soma_init_type to choose whether to display a nav menu item to the primary navbar for custom post types [default: true]
* added 'sortable' argument to soma_init_type to choose whether to make items manually sortable (instead of automatically sorting by date) [default: false]
* post meta keys are now stored individually by default. If you want to store all metadata per post as a serialized array in a single key, you need to set the option 'soma_meta_serialize' to true
* the default post meta key name prefix is "_soma" (what you give as the ID for a metabox field is added to it). If you want a custom prefix, you need to set the option 'soma_meta_prefix' to "_YOUR_PREFIX"
* admin type sorting page rows fit content better
* new API function: soma_asset_meta() for manipulating post_meta (abstracts the core functions to better handle serialization cases)
* new API function: soma_featured_image() for retrieving everything you could possibly need to know about the featured image (post thumbnail)
* in edit listing columns, the checkbox column is always included now, so don't need to pass it in soma_init_type column array
* fixed a query parsing filter that was forcing everything to order by menu_order ASC, no matter what...
* had forgotten to actually enqueue jquery UI datepicker and slider js and css this whole time <facepalm>
* added button to clear date values when using datepicker

= 1.2.1 =
* revised somaFunctions::fetch_connected_items() to handle p2p plugin evolution
* note: must pass the p2p type ID and *not* the post_type anymore! Please revise all calls to fetch_connected_items()!
* When passing "p2p" field data with soma_metabox_data(), you must also pass "p2pname" for the unique registered p2p connection ID and "type" (p2p-list or p2p-thumb) for output
* cleaned up save routines, stripslashes bugs
* metabox type "richtext" now uses the new WP3.3 wp_editor() function (multiple rich editors possible, yay!)
* new metabox type "html" uses the new WP3.3 wp_editor() function, but without the visual editor
* metabox type "editor" removed - use "richtext" instead with ID of "post_content" and data of "core" if you're trying to replace the core post editor (just make sure to NOT include "editor" in the post type support!)
* some css tweaks in the editor to keep up with WP3.3
* added action hook "soma_column_data" to inject custom post type column data output

= 1.2 =
* added listings for public custom post types and taxonomies to the Right Now dashboard widget
* added ability to pick future years (+10) in the basic date picker

= 1.1.1 =
* bugfix: soma_metabox_data was expecting unecessary array keys
* bugfix: legacy date selectors couldn't handle mysqldate format
* bugfix: somaFunctions::fetch_featured_image() couldn't handle when wp uploads were organized in year-month folders. Also couldn't handle when all the sizes (thumb, medium, full) didn't exist... ugh...

= 1.1 =
* created public functions in api.php to initialize things like custom post type, taxonomy, terms, and custom metabox data
* added flush_rewrite_rules to plugin activation
* added contextual help customization per CPT
* generate custom icon paths automatically based on CPT slug, just provide URL to directory where they're located, image name scheme "slug-menu-icon.png"
* limit taxonomy term insertion to plugin or theme activation (two scenarios where soma_init_taxonomy could be called)

= 1.0 =
* first public release on wordpress.org

= 0.6 =
* added jPlayer for metaboxes - meta type Audio or Video
* asset_meta() can be set to serialize or not post_meta via somaMetaboxes::$meta_serialize var (default true), can also be overridden via function params
* somaMetaboxes::$meta_prefix var for themes to override
* added arg to init_taxonomy to automatically hide metaboxes on custom taxonomies

= 0.5 =
* added file upload field type
* added attachment gallery display field type
* added colorbox lightbox viewing for images, pdf, doc, xls, ppt (with google doc iframe viewer)
* added somaDownload class for creating links to download attachments directly
* added jqueryUI datepicker and timepicker

= 0.4 =
* added "help" metabox field type, displaying the text across both table columns
* fixed ridiculous metabox field table layout issues
* fixed saving of incomplete "date" fields
* included soma-admin-jquery.js
* new fetch_index function for dealing with $_GET and $_POST

= 0.3 =
* purged tons of outdated/unused code from other projects
* changed save_asset() for core data types to use wp_update_post instead of $wpdb->update
* added new metabox field type: richtext (with tinymce)
* new functions for fetching userdata
* individual metabox save buttons

= 0.2 =
* First release
* added somaTypes class, handling generation of custom post types, taxonomies, and terms

= 0.1 = 
* Code documentation is crude, with comments everywhere. Will standardize docs soon...
* includes somaFunctions, somaMetaboxes, somaSave, and somaSorter classes


== Upgrade Notice ==

= 1.1.1 =
Nasty bugs squashed!

= 1.1 =
A bit more user-friendly with new public api calls...

= 1.0 =
Want to stay in sync? Install this version!

== Screenshots ==

1. Not much to say yet...
