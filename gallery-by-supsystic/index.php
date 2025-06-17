<?php

/**
 * Plugin Name: Photo Gallery by Supsystic
 * Description: Easy to use Gallery by Supsystic with professional gallery templates. Show off your best design, photography and creative work
 * Version: 1.15.31
 * Author: supsystic.com
 * Author URI: https://supsystic.com
 * Text Domain: grid-gallery
 **/

//Fix RSC Class rename for PRO plugin
function sggChangeProVersionNotice()
{
    global $pagenow;
    if ($pagenow == "admin.php" || $pagenow == "plugins.php") {
        echo '<div class="notice notice-warning is-dismissible"><p><b>WARNING!</b> You using <b>OLD Photo Gallery by Supsystic PRO</b> version! For continued use and before activating the PRO plugin - please <b>UPDATE PRO VERSION</b>. Thank you. <br><b>You can download new compatible PRO version direct from this <a href="https://supsystic.com/pro/supsystic-gallery-pro.zip">LINK</a></b>.</p></div>';
    }
}
require_once ABSPATH . "wp-admin/includes/plugin.php";
$proPluginPath = dirname(__FILE__);
$proPluginPath = str_replace(
    "gallery-supsystic",
    "supsystic-gallery-pro",
    $proPluginPath
);
$proPluginPath = str_replace(
    "gallery-by-supsystic",
    "supsystic-gallery-pro",
    $proPluginPath
);
$proPluginPath = $proPluginPath . "/index.php";
if (file_exists($proPluginPath)) {
    $pluginData = get_file_data(
        $proPluginPath,
        ["Version" => "Version"],
        false
    );
    if (
        !empty($pluginData["Version"]) &&
        version_compare($pluginData["Version"], "2.9.7", "<")
    ) {
        add_action("admin_notices", "sggChangeProVersionNotice");
        deactivate_plugins("supsystic-gallery-pro/index.php");
    }
}
require_once dirname(__FILE__) . "/app/SupsysticGallery.php";

if (!defined("SGG_PLUGIN_URL")) {
    define("SGG_PLUGIN_URL", plugin_dir_url(__FILE__));
}

$supsysticGallery = new SupsysticGallery("1.15.31");
$supsysticGallery->run();
