<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Keep organization settings by default so accidental deletion does not erase configuration.
// Future releases may add an explicit "delete data on uninstall" option.
