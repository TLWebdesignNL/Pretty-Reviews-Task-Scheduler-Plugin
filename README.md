# Pretty Reviews Task Scheduler Plugin
Joomla 4.1 or newer Task Plugin to auto update Google Reviews of Pretty Reviews module.

Version 1.1.0 requires Pretty Reviews module 1.2.0 or newer. The task calls the module helper directly, so Google API keys must allow requests from the server IP address; HTTP referrer-only key restrictions will not work for scheduled refreshes.

The plugin also logs its actions in the logs/joomla_scheduler.php by default.
