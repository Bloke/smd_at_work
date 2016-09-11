# smd_at_work

Tell visitors your Textpattern website is undergoing maintenance with the flick of a switch.

- Install and enable the plugin [Requires Textpattern 4.6.0+].
- Visit the __Admin > Prefs__ panel.
- Set __Maintenance mode enabled__ on or off as desired and set the optional message to display.

With the switch on, anyone not logged into the admin side will see a 503 error status and your given message. If you set up an `error_503` Page template, that will be delivered instead.

When maintenance mode is on, a message is displayed in the lower-right hand corner of all admin-side panels to remind you of the fact, with a link to the preferences panel so you can easily turn it off.

This plugin is a complete rip of the excellent rvm_maintenance plugin, but with the ability to control things via a preference instead of using the plugin's enabled/disabled state. This makes it easier to manage your site's status in a disk-based plugin cache environment where plugins are "always on". Thanks to Ruud van Melick for the original plugin.
