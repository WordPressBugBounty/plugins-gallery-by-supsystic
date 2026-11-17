<?php

/**
 * Asks why Photo Gallery (or Photo Gallery PRO) is deactivated: registers the plugin with the short dialog shared by the
 * Supsystic plugins (deactivation-v1/deactivation.php in this folder says what is sent and when).
 */
class GridGallery_Deactivation_Module extends RscSgg_Mvc_Module
{
  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    if (!is_admin()) {
      return;
    }
    $environment = $this->getEnvironment();
    require_once __DIR__ . '/deactivation-v1/deactivation.php';

    Supsystic_Deactivation_V1::register([
      'slug' => 'gallery-by-supsystic',
      'name' => 'Photo Gallery',
      'file' => $environment->getPluginPath() . '/index.php',
      'tables' => ['gg_galleries', 'gg_photos', 'gg_folders'],
      // The start the review request counts from
      'since' => function () use ($environment) {
        $review = $environment->getModule('review');
        $state = $review && method_exists($review, 'getState') ? $review->getState() : [];
        return isset($state['since']) ? (int) $state['since'] : 0;
      },
      'pro' => [
        'slug' => 'supsystic-gallery-pro',
        'name' => 'Photo Gallery PRO',
        'email' => function () use ($environment) {
          $license = $environment->getModule('license');
          return $license && method_exists($license, 'getHelper') ? (string) $license->getHelper()->getEmail($license->getOptions()) : '';
        },
      ],
    ]);
  }
}
