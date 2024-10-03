# Localist Events

Creates a custom block to embed the Localist events widget. These blocks are configurable, and can set the following options:

<!-- - A "Supertext" field (plain text, optional) -->
- A heading field (plain text, optional)
- A School(s) plain text field (required) - e.g., ucsf
- A Group(s) plain text field (required) - e.g., psychiatry
- A Days integer field (required, default to 31)
- A Number of events field (required, default to 3)
- A Show Repeating Occurrences? boolean field ("all_instances") - default to 1 (true)
- A Show Hours? boolean field ("show_times") - default to 0 (false)
- An Open Event in Own Tab? boolean field ("target_blank") - default to 1 (true)

The Widget ID required by the Localist JavaScript Is automatically set using the block's unique ID.

## Admin Options

The module also provides an admin configuration page to set the global domain used for the widget: `/admin/config/system/localist-events-settings`
