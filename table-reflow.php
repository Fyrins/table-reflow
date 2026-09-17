<?php
/**
 * Plugin Name:       Table Reflow – Accessible Responsive Tables
 * Plugin URI:        https://github.com/Fyrins/table-reflow
 * Description:       Adds a "stacked on mobile" option to the WordPress table block. Below a chosen width each row becomes a card and every cell is prefixed with its column header, so tables stop scrolling sideways. Helps meet WCAG 2.2 success criterion 1.4.10 (Reflow).
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Alexandre Revire
 * Author URI:        https://alexandre-revire.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       table-reflow
 * Domain Path:       /languages
 *
 * @package TableReflow
 */

/*
Copyright (C) 2026 Alexandre Revire

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version, kept in sync with the readme.txt stable tag and the Git tag.
 */
define( 'TABLE_REFLOW_VERSION', '1.0.0' );

/**
 * Absolute path to the plugin main file.
 */
define( 'TABLE_REFLOW_FILE', __FILE__ );

/**
 * Absolute path to the plugin directory, with a trailing slash.
 */
define( 'TABLE_REFLOW_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Public URL of the plugin directory, with a trailing slash.
 */
define( 'TABLE_REFLOW_URL', plugin_dir_url( __FILE__ ) );

require_once TABLE_REFLOW_PATH . 'includes/class-table-reflow-config.php';
require_once TABLE_REFLOW_PATH . 'includes/class-table-reflow-renderer.php';
require_once TABLE_REFLOW_PATH . 'includes/class-table-reflow-plugin.php';

/**
 * Boots the plugin.
 *
 * The instance is created immediately: every hook it registers fires on `init`
 * or later, so nothing is translated before `init` runs.
 *
 * @since 1.0.0
 *
 * @return Table_Reflow_Plugin The plugin instance.
 */
function table_reflow_bootstrap() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new Table_Reflow_Plugin();
		$plugin->register_hooks();
	}

	return $plugin;
}

table_reflow_bootstrap();
