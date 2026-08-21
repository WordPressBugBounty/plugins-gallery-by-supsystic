<?php

/**
 * Class GridGallery_Installer_Module
 */
class GridGallery_Installer_Module extends GridGallery_Core_Module
{
  const LAST_VERSION = 'grid_gallery_last_version';
  const LAST_PRO_VERSION = 'grid_gallery_last_pro_version';

  /**
   * @var GridGallery_Installer_Model
   */
  protected $model;

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    $config = $this->getEnvironment()->getConfig();
    $currentVersion = $config->get('plugin_version');
    $lastVersion = get_option(self::LAST_VERSION);

    if ($lastVersion === false) {
      update_option(self::LAST_VERSION, $currentVersion);
    }

    if (version_compare($currentVersion, $lastVersion, '>')) {
      $this->cleanTwigCacheDir($config->get('plugin_cache_twig'));
      $this->cleanHTMLCache($config->get('plugin_cache_galleries'));

      update_option(self::LAST_VERSION, $currentVersion);

      if ($lastVersion !== false) {
        // Skip show welcome page if user updates plugin.
        update_option($config->get('db_prefix') . 'welcome_page_was_showed', 1);
      }

      if (false === $config->get('plugin_db_update')) {
        return;
      }

      self::executeUpdate($currentVersion, $lastVersion);
    }

    if ($config->get('is_pro')) {
      $lastProVersion = get_option(self::LAST_PRO_VERSION);
      $currentProVersion = $config->get('pro_plugin_version');
      if (version_compare($currentProVersion, $lastProVersion, '>')) {
        $this->cleanTwigCacheDir($config->get('plugin_cache_twig'));
        update_option(self::LAST_PRO_VERSION, $currentProVersion);
      }
    }
  }

  protected static function executeUpdate($currentVersion, $previousVersion = false)
  {
    $model = self::getModel();
    $queries = self::getQueries();

    if (function_exists('is_multisite') && is_multisite()) {
      global $wpdb;
      $blog_id = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
      foreach ($blog_id as $id) {
        if (switch_to_blog($id)) {
          $model->update($queries);
          self::maybeBackfillIconsEnabled($model, $previousVersion);
          update_option(self::LAST_VERSION, $currentVersion);
          restore_current_blog();
        }
      }
    } else {
      $model->update($queries);
      self::maybeBackfillIconsEnabled($model, $previousVersion);
    }
  }

  /**
   * One-shot: only runs while upgrading from a version older than 1.19.0,
   * the release that introduced GridGallery_Installer_Model::
   * backfillIconsEnabledDefault(). $previousVersion is false on a brand
   * new install (nothing to backfill yet) and is captured before onInit()
   * overwrites the stored LAST_VERSION option with $currentVersion.
   */
  private static function maybeBackfillIconsEnabled($model, $previousVersion)
  {
    if ($previousVersion !== false && version_compare($previousVersion, '1.19.0', '<')) {
      $model->backfillIconsEnabledDefault();
    }
  }

  /**
   * {@inhertidoc}
   */
  public function onInstall()
  {
    parent::onInstall();

    $model = self::getModel();
    $queries = self::getQueries();

    $config = $this->getEnvironment()->getConfig();
    $currentVersion = $config->get('plugin_version');

    if (function_exists('is_multisite') && is_multisite()) {
      global $wpdb;
      $blog_id = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
      foreach ($blog_id as $id) {
        if (switch_to_blog($id)) {
          $model->install($queries);
          update_option(self::LAST_VERSION, $currentVersion);
          restore_current_blog();
        }
      }
    } else {
      if (!$model->install($queries)) {
        wp_die('Failed to update database.');
      }
      update_option(self::LAST_VERSION, $currentVersion);
    }
  }

  /*public function onDeactivation()
    {
        $response = false;
        //Uncomment to enable deactivation page
        //$response = $this->getController()->askUninstallAction();

        if (!is_bool($response)) {
            exit($response);
        }

        if ($response) {
            $model = self::getModel();
            $queries = self::getQueries();

            $model->drop($queries);
        }
    }*/

  /**
   * {@inheritdoc}
   */
  public static function onUninstall()
  {
    global $grid_gallery_supsystic;

    $self = $grid_gallery_supsystic->getResolver()->getModulesList()->get('installer');

    $model = self::getModel();
    $queries = self::getQueries();

    if (function_exists('is_multisite') && is_multisite()) {
      global $wpdb;
      $blog_id = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
      foreach ($blog_id as $id) {
        if (switch_to_blog($id)) {
          $model->drop($queries);
          delete_option(self::LAST_VERSION);
          restore_current_blog();
        }
      }
    } else {
      $model->drop($queries);
    }
  }

  /**
   * Returns the database queries.
   * @return array|null
   */
  protected static function getQueries()
  {
    if (!is_file($file = dirname(__FILE__) . '/Queries.php')) {
      return null;
    }

    return include $file;
  }

  protected static function getModel()
  {
    return new GridGallery_Installer_Model();
  }

  private function cleanHTMLCache($cachePath)
  {
    if ($cachePath) {
      array_map('unlink', glob("$cachePath/*"));
    }
  }

  private function cleanTwigCacheDir($dir)
  {
    if (!$dir || !is_dir($dir)) {
      return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      is_dir("$dir/$file") ? $this->cleanTwigCacheDir("$dir/$file") : @unlink("$dir/$file");
    }
    return @rmdir($dir);
  }
}
