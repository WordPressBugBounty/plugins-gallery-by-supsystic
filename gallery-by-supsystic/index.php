<?php

/**
 * Plugin Name: Photo Gallery by Supsystic
 * Description: Easy to use Gallery by Supsystic with professional gallery templates. Show off your best design, photography and creative work
 * Version: 1.19.0
 * Author: supsystic.com
 * Author URI: https://supsystic.com
 * Text Domain: grid-gallery
 **/

require_once dirname(__FILE__) . '/app/SupsysticGallery.php';

if (!defined('SGG_PLUGIN_URL')) {
  define('SGG_PLUGIN_URL', plugin_dir_url(__FILE__));
}

$supsysticGallery = new SupsysticGallery('1.19.0');
$supsysticGallery->run();
