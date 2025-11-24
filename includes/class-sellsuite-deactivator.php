<?php
namespace SellSuite;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Clean up temporary data and flush rewrite rules.
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Clean up transients
        delete_transient('sellsuite_cache');
    }
}
