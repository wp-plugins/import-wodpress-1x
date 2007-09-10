<?php
/**
Plugin Name: Import WordPress 1.x
Plugin URI: http://cauguanabara.jsbrasil.com/wp_plugins/
Description: This plugin adds a new importer that allows you to import posts, categories and comments from remote installations of WordPress, through RSS feeds (including 1.x versions). Go to Manage > Import, then click in 'WordPress 1.x'.
Version: 1.0
Author: Cau Guanabara
Author URI: http://cauguanabara.jsbrasil.com/
*/

$iwp1x_dir = preg_replace("/^.+(\\\\|\/)/", "", dirname(__FILE__));

add_action('activate_'.$iwp1x_dir.'/import1x.php', 'iwp1x_install');
add_action('deactivate_'.$iwp1x_dir.'/import1x.php', 'iwp1x_uninstall');

load_plugin_textdomain('import1x', PLUGINDIR.'/'.$iwp1x_dir);

function iwp1x_install() {
global $iwp1x_dir;
$fil = ABSPATH.'wp-admin/import/wordpress1x.php';
  if(!is_file($fil)) copy(ABSPATH.PLUGINDIR.'/'.$iwp1x_dir.'/wordpress1x.php', $fil);
}

function iwp1x_uninstall() { @unlink(ABSPATH.'wp-admin/import/wordpress1x.php'); }
?>