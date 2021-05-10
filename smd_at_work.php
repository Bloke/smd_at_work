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

$plugin['version'] = '0.4.2';
$plugin['author'] = 'Stef Dawson / Dale Chapman';
$plugin['author_uri'] = 'https://stefdawson.com/';
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
#@owner smd_at_work
#@language en, en-ca, en-gb, en-us
#@admin-side
smd_at_work_admin_message => Website is in <a href="{url}">Maintenance Mode</a>
#@prefs
smd_at_work => Maintenance mode
smd_at_work_enabled => Maintenance mode enabled
smd_at_work_message => Maintenance message
#@language fr
#@admin-side
smd_at_work_admin_message => Le site est en <a href="{url}">mode de maintenance</a>
#@prefs
smd_at_work => Mode de maintenance
smd_at_work_enabled => Activer le mode de maintenance ?
smd_at_work_message => Message de maintenance
EOT;

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * smd_at_work: a Textpattern CMS plugin for informing visitors of site maintenance.
 *
 * @author Stef Dawson
 * @see https://stefdawson.com/
 */
if (txpinterface === 'admin') {
    new smd_at_work();
} elseif (txpinterface === 'public') {
    register_callback('smd_at_work_init', 'pretext');

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
            if (txpinterface === 'public' && !gps('txpcleantest') && !is_logged_in()) {
                $_GET = $_POST = $_REQUEST = array();
                register_callback('smd_at_work', 'pretext_end');
            }
        }
    }

    /**
     * Throw an HTTP 503 error.
     */
    function smd_at_work()
    {
        txp_die(get_pref('smd_at_work_message', 'Site maintenance in progress. Please check back later.'), 503);
    }
}

// Tag registration.
if (class_exists('\Textpattern\Tag\Registry')) {
    Txp::get('\Textpattern\Tag\Registry')
        ->register('smd_at_work_status')
        ->register('smd_at_work_message')
        ->register('smd_if_at_work');
}

/**
 * Public tag to set the maintenance status.
 *
 * Only logged-in users may set this. It is up to application
 * logic if this is restricted any further.
 */
function smd_at_work_status($atts = array(), $thing = null)
{
    extract(lAtts(array(
        'status' => null,
    ), $atts));

    // Null status toggles the state.
    if ($status === null) {
        $status = !get_pref('smd_at_work_enabled', null, true);
    }

    if (is_logged_in()) {
        set_pref('smd_at_work_enabled', (($status) ? 1 : 0));
    }
}

/**
 * Public conditional tag to determine if maintenance mode is active.
 *
 * Not so much use on the actual public site, but handy for admin-side
 * dashboards.
 */
function smd_if_at_work($atts = array(), $thing = null)
{
    return parse($thing, get_pref('smd_at_work_enabled', null, true) == '1');
}

/**
 * Return the given status message or the currently set maintenance message
 */
function smd_at_work_message($atts = array(), $thing = null)
{
    extract(lAtts(array(
        'text' => null,
    ), $atts));

    if ($text === null) {
        $text = get_pref('smd_at_work_message', 'Site maintenance in progress. Please check back later.');
    }

   return $text;
}

/**
 * Admin-side class.
 */
class smd_at_work
{
    /**
     * The plugin's event.
     *
     * @var string
     */
    protected $event = __CLASS__;

    /**
     * The plugin's privileges.
     *
     * @var string
     */
    protected $privs = '1';

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_privs('prefs.'.$this->event, $this->privs);
        add_privs('plugin_prefs.'.$this->event, $this->privs);
        add_privs($this->event.'.bannerlink', $this->privs);
        register_callback(array($this, 'welcome'), 'plugin_lifecycle.'.$this->event);
        register_callback(array($this, 'banner'), 'admin_side', 'footer');
        register_callback(array($this, 'install'), 'prefs', null, 1);
        register_callback(array($this, 'options'), 'plugin_prefs.'.$this->event, null, 1);
    }

    /**
     * Handler for plugin lifecycle events.
     *
     * @param string $evt Textpattern action event
     * @param string $stp Textpattern action step
     */
    public function welcome($evt, $stp)
    {
        switch ($stp) {
            case 'installed':
            case 'enabled':
                $this->install();
                break;
            case 'deleted':
                remove_pref(null, 'smd_at_work');
                safe_delete('txp_lang', "owner = 'smd\_at\_work'");
                break;
        }
    }

    /**
     * Display a banner to inform admins that the site is in maintenance mode.
     *
     * @param string $evt  Textpattern action event
     * @param string $stp  Textpattern action step
     * @param string $data The current footer content
     */
    public function banner($evt, $stp, $data)
    {
        global $event, $step;

        // Force DB lookup of pref to avoid stale message on prefs screen.
        $force = ($event === 'prefs' && $step === 'prefs_save') ? true : false;
        $link = $this->prefs_link();

        if (get_pref('smd_at_work_enabled', null, $force) == '1') {
            // Prepend the maintenance message to the footer content.
            $msg = gTxt('smd_at_work_admin_message', array('{url}' => $link));
            $msg = has_privs($this->event.'.bannerlink') ? $msg : strip_tags($msg);
            $data = '<p class="warning" style="display:inline-block;">'.$msg.'</p>'.
                n.$data;
        }

        return $data;
    }

    /**
     * Fetch the admin-side prefs panel link.
     */
    protected function prefs_link()
    {
        return '?event=prefs#prefs_group_smd_at_work';
    }

    /**
     * Jump to the prefs panel.
     */
    public function options()
    {
        $link = $this->prefs_link();

        header('Location: ' . $link);
    }

    /**
     * Install the prefs if necessary.
     *
     * When operating under a plugin cache environment, the install lifecycle
     * event is never fired, so this is a fallback.
     *
     * The lifecycle callback remains for deletion purposes under a regular installation,
     * since the plugin cannot detect this in a cache environment.
     *
     * @see welcome()
     */
    function install()
    {
        if (get_pref('smd_at_work_enabled', null) === null) {
            set_pref('smd_at_work_enabled', 0, 'smd_at_work', PREF_PLUGIN, 'yesnoradio', 10);
        }

        if (get_pref('smd_at_work_message', null) === null) {
            set_pref('smd_at_work_message', 'Site maintenance in progress. Please check back later.', 'smd_at_work', PREF_PLUGIN, 'pref_longtext_input', 20);
        } else {
            update_pref('smd_at_work_message', null, null, null, 'pref_longtext_input');
        }
    }
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
h1. smd_at_work

Tell visitors your Textpattern website is undergoing maintenance with the flick of a switch.

# Install and enable the plugin [Requires Textpattern 4.7.0+].
# Visit the __Admin > Prefs__ panel.
# Set __Maintenance mode enabled__ on or off as desired and set the optional message to display.

With the switch on, anyone not logged into the admin side will see a 503 error status and your given message. If you set up an @error_503@ Page template, that will be delivered instead.

When maintenance mode is on, a message is displayed in the footer of all admin-side panels to remind you of the fact. Admins also have a link to the preferences panel so they can easily turn it off.

h2. Public tags

There are three public tags available. The tags are of limited use in live templates, but can be very helpful on admin-side dashboards to allow shortcuts for setting the maintenance state of the site.

h3. @<txp:smd_if_at_work>@

Conditional tag, with no attributes, that executes the contained content if the site is in maintenance mode. Supports @<txp:else />@.

h3. @<txp:smd_at_work_status>@

Set the site maintenance state. Attribute:

h4. @status@

Values:

* omitted: toggle status
* 0: set maintenance mode off
* 1: set maintenance mode on

Note that the plugin only checks to see if the tag is used by any logged-in user. If you wish to exact finer-grained control, you must do so in your Page template.

h3. @<txp:smd_at_work_message>@

Display a site maintenance message. Attribute:

h4. @text@

The text to display. If omitted, the system-wide message (as set in prefs) is used.

h2. Example 1: toggle the site state on click of a link

bc.. <txp:adi_gps name="maintenance" quiet="1" />

<txp:if_variable name="maintenance" value="">
<txp:else />
   <txp:smd_at_work_status />
</txp:if_variable>

<txp:smd_if_at_work>
   Maintenance Mode is on. <a href="?event=dashboard&maintenance=1">Make site live</a>.
<txp:else />
   Website is live. <a href="?event=dashboard&maintenance=1">Put it in Maintenance Mode</a>.
</txp:smd_if_at_work>

h2. Author/credits

Cobbled together by "Stef Dawson":https://stefdawson.com/sw, this plugin is a complete rip of the excellent rvm_maintenance plugin, but with the ability to control things via a preference instead of using the plugin's enabled/disabled state. This makes it easier to manage your site's status in a disk-based plugin cache environment where plugins are "always on". Thanks to Ruud van Melick for the original plugin.
# --- END PLUGIN HELP ---
-->
<?php
}
?>