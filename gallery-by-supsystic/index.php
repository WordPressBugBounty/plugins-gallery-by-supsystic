<?php

/**
 * Plugin Name: Photo Gallery by Supsystic
 * Plugin URI: https://supsystic.com/plugins/photo-gallery/
 * Description: Responsive photo and image galleries with masonry, albums and a lightbox. Bulk upload, drag-and-drop ordering, Gutenberg block and Elementor widget.
 * Version: 1.21.7
 * Author: supsystic.com
 * Author URI: https://supsystic.com
 * Text Domain: grid-gallery
 **/

require_once dirname(__FILE__) . '/app/SupsysticGallery.php';

if (!defined('SGG_PLUGIN_URL')) {
  define('SGG_PLUGIN_URL', plugin_dir_url(__FILE__));
}

$supsysticGallery = new SupsysticGallery('1.21.7');
$supsysticGallery->run();

/**
 * Spooky Sale banner on the plugin admin pages
 */
if (is_admin()) {
  require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'promo' . DIRECTORY_SEPARATOR . 'spooky-sale.php';
  SupsysticSpookySale2026::register('supsystic-gallery', plugin_dir_url(__FILE__) . 'promo/', [
    'proPid' => 190,
    'proUrl' => 'https://supsystic.com/plugins/photo-gallery/',
    'isPro' => function () use ($supsysticGallery) {
      return $supsysticGallery->getEnvironment()->isPro() || (function_exists('is_plugin_active') && is_plugin_active('supsystic-gallery-pro/index.php'));
    },
    'utmCampaign' => 'gallery',
  ]);
}
