<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'smd_at_work';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.10';
$plugin['author'] = 'Stef Dawson / Dale Chapman';
$plugin['author_uri'] = 'http://stefdawson.com/';
$plugin['description'] = 'Switchable site maintenance mode';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '1';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '3';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

$plugin['textpack'] = <<<EOT
#@smd_at_work
smd_at_work => Maintenance mode
smd_at_work_admin_message => Website is in <a href="?event=prefs">Maintenance Mode</a>
smd_at_work_enabled => Maintenance mode enabled
smd_at_work_message => Maintenance message
EOT;

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * smd_at_work: a Textpattern CMS plugin for informing visitors of site maintenance
 *
 * @author Stef Dawson
 * @see http://stefdawson.com/
 */
if (txpinterface === 'admin') {
	add_privs('prefs.smd_at_work', '1');
	register_callback('smd_at_work_welcome', 'plugin_lifecycle.smd_at_work');
	register_callback('smd_at_work_banner', 'admin_side', 'pagetop_end');
} elseif (txpinterface === 'public') {
	register_callback('smd_at_work_init', 'pretext');
}

/**
 * Handler for plugin lifecycle events
 *
 * @param string $evt Textpattern action event
 * @param string $stp Textpattern action step
 */
function smd_at_work_welcome($evt, $stp)
{
	switch ($stp) {
		case 'installed':
		case 'enabled':
			// TODO: change to PREF_PLUGIN from 4.6
			set_pref('smd_at_work_enabled', 0, 'smd_at_work', PREF_ADVANCED, 'yesnoradio', 10);
			set_pref('smd_at_work_message', 'Site maintenance in progress. Please check back later.', 'smd_at_work', PREF_ADVANCED, 'text_input', 20);
			break;
		case 'deleted':
			if (function_exists('remove_pref')) {
				// 4.6 API
				remove_pref(null, 'smd_at_work');
			} else {
				safe_delete('txp_prefs', "event='smd_at_work'");
			}
			break;
	}
}

/**
 * Display a banner to inform admins that the site is in maintenance mode.
 *
 * @param string $evt Textpattern action event
 * @param string $stp Textpattern action step
 */
function smd_at_work_banner($evt, $stp)
{
	// Force DB lookup of pref to avoid stale message.
	if (get_pref('smd_at_work_enabled', null, true) == '1') {
		echo '<div class="information" style="position:fixed; right:20px; bottom:0;">' . gTxt('smd_at_work_admin_message') . '</div>';
	}
}

/**
 * Public-side initialisation.
 *
 * Only sets up the callback if:
 *  1. The plugin's toggle pref is on.
 *  2. The visitor is not logged into the admin side.
 *  3. the URL param txpcleantest is missing.
 *
 * @see smd_at_work()
 */
function smd_at_work_init()
{
	if (get_pref('smd_at_work_enabled') == '1') {
		if (txpinterface === 'public' and !gps('txpcleantest') and !is_logged_in()) {
			$_GET = $_POST = $_REQUEST = array();
			register_callback('smd_at_work', 'pretext_end');
		}
	}
}

/**
 * Throw an HTTP 503 error
 */
function smd_at_work()
{
	txp_die(get_pref('smd_at_work_message', 'Site maintenance in progress. Please check back later.'), 503);
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_at_work

Tell visitors your Textpattern website is undergoing maintenance with the flick of a switch.

# Install and enable the plugin.
# Visit the __Admin > Prefs__ panel.
# Set __Maintenance mode enabled__ on or off as desired and set the optional message to display.

With the switch on, anyone not logged into the admin side will see a 503 error status and your given message. If you set up an @error_503@ Page template, that will be delivered instead.

When maintenance mode is on, a message is displayed in the lower-right hand corner of all admin-side panels to remind you of the fact, with a link to the preferences panel so you can easily turn it off.

This plugin is a complete rip of the excellent rvm_maintenance plugin, but with the ability to control things via a preference instead of using the plugin's enabled/disabled state. This makes it easier to manage your site's status in a disk-based plugin cache environment where plugins are "always on". Thanks to Ruud van Melick for the original plugin.
# --- END PLUGIN HELP ---
-->
<?php
}
?>