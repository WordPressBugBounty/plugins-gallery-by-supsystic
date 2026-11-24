<?php

/**
 * Class SupsysticGallery
 * Grid Gallery Plugin
 */
class SupsysticGallery
{
  /**
   * @var RscSgg_Environment
   */
  private $environment;

  /**
   * @var array
   */
  private $alerts;

  /**
   * Constructor
   */
  public function __construct($version)
  {
    if (!class_exists('RscSgg_Autoloader', false)) {
      require dirname(dirname(__FILE__)) . '/vendor/Rsc/Autoloader.php';
      RscSgg_Autoloader::register();
    }
    add_action('init', [$this, '_loadPluginsTextdomain']);
    add_action('init', [$this, 'addShortcodeButton']);
    add_action('admin_head', [$this, 'printMenuIconStyle']);
    add_action('admin_menu', [$this, 'markMenuGroup'], 999);

    /* Create new plugin $environment */
    $pluginPath = dirname(dirname(__FILE__));
    $environment = new RscSgg_Environment('sgg', $version, $pluginPath);

    /* Configure */
    $environment->configure([
      'optimizations' => 0,
      'environment' => $this->getPluginEnvironment(),
      'default_module' => $this->resolveDefaultModule(),
      'lang_domain' => 'sgg',
      'lang_path' => plugin_basename(dirname(__FILE__)) . '/langs',
      'plugin_prefix' => 'GridGallery',
      'plugin_source' => dirname(dirname(__FILE__)) . '/src',
      'plugin_title_name' => 'Photo Gallery by Supsystic',
      'plugin_menu' => [
        'page_title' => __('Photo Gallery by Supsystic', 'sgg'),
        'menu_title' => __('Photo Gallery', 'sgg'),
        'capability' => 'manage_options',
        'menu_slug' => 'supsystic-gallery',
        // The Supsystic logo, as in the CES menu item (size: printMenuIconStyle)
        'icon_url' => plugins_url(basename(dirname(dirname(__FILE__))) . '/app/assets/img/supsystic-adminbar.png'),
        // Right under Dashboard, after CES (2.1) and Data Tables (2.2)
        'position' => '2.3',
      ],
      'shortcode_name' => 'supsystic-gallery',
      'db_prefix' => 'sg_',
      'hooks_prefix' => 'sg_',
      'page_url' => 'https://supsystic.com/plugins/photo-gallery/',
      'ajax_url' => admin_url('admin-ajax.php'),
      'admin_url' => admin_url(),
      'uploads_rw' => true,
      'jpeg_quality' => 95,
      'plugin_db_update' => true,
      'revision' => 244,
      'welcome_page_was_showed' => get_option('sg_welcome_page_was_showed'),
      'promo_controller' => 'GridGallery_Promo_Controller',
      'plugin_folder_name' => basename(dirname(dirname(__FILE__))),
    ]);

    if (!defined('S_YOUR_SECRET_HASH_' . $environment->getPluginName())) {
      define('S_YOUR_SECRET_HASH_' . $environment->getPluginName(), 'hn48SgUyMN53#jhg7@pomnE9W2O#2m@awmMneuGW3512F@jnkj');
    }

    $this->environment = $environment;
    $this->alerts = [];

    $this->initialize();
  }

  /**
   * Run plugin
   */
  public function run()
  {
    global $grid_gallery_supsystic;

    $this->environment->run();
    $this->environment->getTwig()->addGlobal('core_alerts', $this->alerts);

    $grid_gallery_supsystic = $this->environment;
  }

  /**
   * The menu icon (the Supsystic logo PNG) drawn as a mask in the menu's own colour, so it is grey, then coloured
   * on hover and white when current in every admin colour scheme, like the WP icons. The <img> from icon_url stays
   * as the fallback for browsers without CSS masks.
   */
  public function printMenuIconStyle()
  {
    $icon = esc_url(plugins_url(basename(dirname(dirname(__FILE__))) . '/app/assets/img/supsystic-adminbar.png'));
    echo '<style id="sgg-menu-icon">'
      . '#adminmenu #toplevel_page_supsystic-gallery .wp-menu-image img { display: none; }'
      . '#adminmenu #toplevel_page_supsystic-gallery .wp-menu-image::before {'
      . ' content: ""; display: block; box-sizing: content-box;'
      . ' width: 20px; height: 20px; padding: 7px 0 0; margin: 0 auto;'
      . ' background-color: currentColor;'
      // content-box: the mask sits in the same 20px box as a dashicon (below the 7px padding), not 3.5px higher
      . ' -webkit-mask: url("' . $icon . '") center / 20px 20px no-repeat content-box;'
      . ' mask: url("' . $icon . '") center / 20px 20px no-repeat content-box;'
      // The gear turns once on hover and back on leave, around its own centre (the 20px box under the padding)
      . ' transform-origin: 50% calc(100% - 10px);'
      . ' transition: transform .6s cubic-bezier(.4, 0, .2, 1), background-color .2s ease, color .1s ease-in;'
      . ' }'
      // While hovered: Supsystic cyan instead of the scheme hover colour (otherwise the scheme colours)
      . '#adminmenu #toplevel_page_supsystic-gallery:hover .wp-menu-image::before,'
      . ' #adminmenu #toplevel_page_supsystic-gallery > a:focus .wp-menu-image::before { transform: rotate(360deg); background-color: #4ae8ea; }'
      . '@media (prefers-reduced-motion: reduce) {'
      . ' #adminmenu #toplevel_page_supsystic-gallery .wp-menu-image::before { transition: color .1s ease-in; }'
      . ' #adminmenu #toplevel_page_supsystic-gallery:hover .wp-menu-image::before,'
      . ' #adminmenu #toplevel_page_supsystic-gallery > a:focus .wp-menu-image::before { transform: none; }'
      . ' }'
      // Space above the first and below the last Supsystic item (the same rules in every Supsystic plugin)
      . '#adminmenu li:not(.supsystic-menu-group) + li.supsystic-menu-group { margin-top: 8px; }'
      . '#adminmenu li.supsystic-menu-group + li:not(.supsystic-menu-group) { margin-top: 8px; }'
      . '</style>';
  }

  /**
   * Marks this plugin's top-level menu item as one of the Supsystic group (see printMenuIconStyle)
   */
  public function markMenuGroup()
  {
    global $menu;
    foreach ((array) $menu as $key => $item) {
      if (isset($item[2], $item[4]) && $item[2] === 'supsystic-gallery' && strpos((string) $item[4], 'supsystic-menu-group') === false) {
        $menu[$key][4] = trim($item[4] . ' supsystic-menu-group');
      }
    }
  }

  /**
   * Load plugin languages
   */
  public function _loadPluginsTextDomain()
  {
    load_plugin_textdomain('sgg', false, $this->environment->getConfig()->get('lang_path'));
  }

  /**
   * Classic editor (TinyMCE: Gutenberg Classic block, Elementor Text Editor, wp_editor) buttons "Insert a Photo
   * Gallery" / "Insert a Photo Gallery Group": a dialog to pick one, then its shortcode at the cursor
   * (fix/shared/classic-editor-buttons.md). Admin screens only; the dialog is drawn by buttons.js itself, so nothing
   * else is enqueued.
   */
  public function addShortcodeButton()
  {
    add_action('wp_ajax_sgg_editor_objects', [$this, 'ajaxEditorObjects']);
    if (!is_admin()) {
      return;
    }
    // Priority 20: the Elementor editor removes every mce_external_plugins / mce_buttons filter at priority 10
    add_filter('mce_external_plugins', [$this, 'addButton'], 20);
    add_filter('mce_buttons', [$this, 'registerButton'], 20);
    add_filter('tiny_mce_before_init', [$this, 'addEditorSettings']);
  }

  /**
   * Add button to TinyMCE
   * @param array $plugin_array
   * @return array $plugin_array
   */
  public function addButton($plugin_array)
  {
    if (!current_user_can('edit_posts')) {
      return $plugin_array;
    }
    $file = dirname(__FILE__) . '/assets/js/buttons.js';
    $plugin_array['supsysticGallery'] = $this->environment->getConfig()->get('plugin_url') . '/app/assets/js/buttons.js?ver='
      . (file_exists($file) ? filemtime($file) : $this->environment->getConfig()->get('plugin_version'));

    return $plugin_array;
  }

  /**
   * Register buttons: the Supsystic group sits before the toolbar toggle (wp_adv), after a separator added by the
   * first Supsystic plugin. The old ids (addShortcode, selectShortcode) still work as aliases in buttons.js, but are
   * no longer put on the default toolbar.
   */
  public function registerButton($buttons)
  {
    if (!current_user_can('edit_posts')) {
      return $buttons;
    }
    $buttons = (array) $buttons;
    $ours = ['supsysticGallery', 'supsysticGalleryGroup'];
    $hasSupsystic = false;
    foreach ($buttons as $button) {
      if (is_string($button) && strpos($button, 'supsystic') === 0) {
        $hasSupsystic = true;
        break;
      }
    }
    if (!$hasSupsystic) {
      array_unshift($ours, '|');
    }
    $toggle = array_search('wp_adv', $buttons, true);
    if ($toggle === false) {
      $buttons = array_merge($buttons, $ours);
    } else {
      array_splice($buttons, $toggle, 0, $ours);
    }

    return $buttons;
  }

  /**
   * Texts, links and the AJAX nonce for buttons.js, as the TinyMCE setting "sgg_editor"
   *
   * @param array $init
   * @return array
   */
  public function addEditorSettings($init)
  {
    if (!current_user_can('edit_posts')) {
      return $init;
    }
    $canManage = current_user_can('manage_options');
    $init['sgg_editor'] = wp_json_encode([
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('sgg_editor_objects'),
      'createGallery' => $canManage ? admin_url('admin.php?page=supsystic-gallery&module=galleries&action=showPresets') : '',
      'createGroup' => $canManage ? admin_url('admin.php?page=supsystic-gallery&module=gallerygroups&action=create') : '',
      'i18n' => [
        'insertGallery' => __('Insert a Photo Gallery', 'sgg'),
        'insertGroup' => __('Insert a Photo Gallery Group', 'sgg'),
        'gallery' => __('Gallery', 'sgg'),
        'group' => __('Gallery group', 'sgg'),
        'search' => __('Search', 'sgg'),
        'noGalleries' => __('No galleries yet.', 'sgg'),
        'noGroups' => __('No gallery groups yet.', 'sgg'),
        'noMatch' => __('Nothing found.', 'sgg'),
        'createGallery' => __('Create a gallery in Photo Gallery', 'sgg'),
        'createGroup' => __('Create a gallery group in Photo Gallery', 'sgg'),
        'insert' => __('Insert', 'sgg'),
        'cancel' => __('Cancel', 'sgg'),
        'close' => __('Close', 'sgg'),
        'loading' => __('Loading…', 'sgg'),
        'error' => __('Could not load the list. Close this window and try again.', 'sgg'),
      ],
    ]);

    return $init;
  }

  /**
   * The galleries and gallery groups for the classic editor dialog, newest first. Read fresh on every opening, so
   * one created in another tab shows up without reloading the editor.
   */
  public function ajaxEditorObjects()
  {
    if (!current_user_can('edit_posts') || !check_ajax_referer('sgg_editor_objects', 'nonce', false)) {
      wp_send_json_error(null, 403);
    }
    $galleries = [];
    foreach ((array) (new GridGallery_Galleries_Model_Galleries())->getList() as $gallery) {
      if (!empty($gallery->id)) {
        $galleries[] = ['id' => (int) $gallery->id, 'title' => !empty($gallery->title) ? wp_strip_all_tags($gallery->title) : sprintf('Gallery #%d', $gallery->id)];
      }
    }
    $groups = [];
    foreach ((array) (new GridGallery_GalleryGroups_Model_Groups())->getAll() as $group) {
      if (!empty($group->group_id)) {
        $groups[] = ['id' => (int) $group->group_id, 'title' => !empty($group->name) ? wp_strip_all_tags($group->name) : sprintf('Group #%d', $group->group_id)];
      }
    }
    usort($groups, function ($a, $b) {
      return $b['id'] - $a['id'];
    });
    wp_send_json_success(['galleries' => $galleries, 'groups' => $groups]);
  }

  /**
   * Initialize plugin component and subsustem
   */
  protected function initialize()
  {
    $config = $this->environment->getConfig();
    $logger = null;

    $uploads = wp_upload_dir();

    if (!is_writable($uploads['basedir'])) {
      $this->alerts[] = sprintf(
        '<div class="error">
                    <p>You need to make your "%s" directory writable.</p>
                </div>',
        $uploads['basedir'],
      );

      $config->set('uploads_rw', false);
    }

    /* Create the plugin directories if they are does not exists yet. */
    $this->initFilesystem();

    /* Initialize cache null-adapter by default */
    $cacheAdapter = new RscSgg_Cache_Dummy();

    /* Initialize the log system first. */
    if (null !== ($logDir = $config->get('plugin_log', null))) {
      if (is_dir($logDir) && is_writable($logDir)) {
        $logger = new RscSgg_Logger($logDir);
        $this->environment->setLogger($logger);
      }
    }

    /* If it's a production environment and cache directory is OK */
    if ($config->isEnvironment(RscSgg_Environment::ENV_PRODUCTION) && null !== ($cacheDir = $config->get('plugin_cache', null))) {
      if (is_dir($cacheDir) && is_writable($cacheDir)) {
        $cacheAdapter = new RscSgg_Cache_Filesystem($cacheDir);
      } else {
        if ($logger) {
          $logger->error('Cache directory "{dir}" is not writable or does not exists.', [
            'dir' => realpath($cacheDir),
          ]);
        }
      }
    }

    $this->environment->setCacheAdapter($cacheAdapter);
  }

  /**
   * Creates plugin's directories.
   */
  protected function initFilesystem()
  {
    $directories = [
      'tmp' => '/grid-gallery',
      'log' => '/grid-gallery/log',
      'cache' => '/grid-gallery/cache',
      'cache_galleries' => '/grid-gallery/cache/galleries',
      'cache_twig' => '/grid-gallery/cache/twig',
    ];

    foreach ($directories as $key => $dir) {
      if (false !== ($fullPath = $this->makeDirectory($dir))) {
        $this->environment->getConfig()->add('plugin_' . $key, $fullPath);
      }
    }
  }

  /**
   * Make directory in uploads directory.
   * @param string $directory Relative to the WP_UPLOADS dir
   * @return bool|string FALSE on failure, full path to the directory on success
   */
  protected function makeDirectory($directory)
  {
    $uploads = wp_upload_dir();

    $basedir = $uploads['basedir'];
    $dir = $basedir . $directory;
    if (!is_dir($dir)) {
      if (false === @mkdir($dir, 0775, true)) {
        return false;
      }
    } else {
      if (!is_writable($dir)) {
        return false;
      }
    }

    return $dir;
  }

  /**
   * Get plugin enviroment develop or production
   * @return RscSgg_Environment ENV_PRODUCTION or ENV_DEVELOPMENT
   */
  protected function getPluginEnvironment()
  {
    $environment = RscSgg_Environment::ENV_PRODUCTION;

    if (defined('WP_DEBUG') && WP_DEBUG) {
      if (defined('SUPSYSTIC_GRID_GALLERY_DEBUG') && SUPSYSTIC_GRID_GALLERY_DEBUG) {
        $environment = RscSgg_Environment::ENV_DEVELOPMENT;
      }
    }

    return $environment;
  }

  /**
   * Decides which module renders when the plugin's own menu link is opened
   * with no ?module= in the URL. On a brand new install this shows the
   * Overview page exactly once (welcome aboard); every visit after that
   * lands straight on the Galleries list.
   *
   * @return string
   */
  private function resolveDefaultModule()
  {
    $isDefaultLanding = is_admin() && isset($_GET['page']) && $_GET['page'] === 'supsystic-gallery' && !isset($_GET['module']);

    if (!$isDefaultLanding || get_option('sg_default_page_visited', false)) {
      return 'galleries';
    }

    // GridGallery_Installer_Module marks 'sg_default_page_visited' for us
    // (see its onInit()) whenever it detects this is an existing site
    // upgrading rather than a genuinely fresh install, so reaching this
    // point with the option still unset means: show the welcome once.
    update_option('sg_default_page_visited', 1);

    return 'overview';
  }

  /**
   * get gallery Environment for other supsystic plugins
   * @return RscSgg_Environment
   */
  public function getEnvironment()
  {
    return $this->environment;
  }
}

function sggUnoffProNotice($version) { echo '<div class="notice notice-error" id="supsystic-gallery-pro-update" data-slug="supsystic-gallery-pro" style="background:#ffdddb;"><p><b>&#128721; Supsystic Security Alert:</b> Photo Gallery PRO by Supsystic has been automatically deactivated.</p><p>We detected that the installed copy of Photo Gallery PRO by Supsystic (version ' . esc_html($version) . ') does not match any version Supsystic ever officially released. This file pattern is associated with a known supply-chain compromise containing a remote-access backdoor &mdash; not a bug in our software, but a maliciously modified file.</p><p>For your safety, we have deactivated this plugin automatically. Please complete the cleanup:</p><ol><li>Go to Plugins and click "Delete" on Photo Gallery PRO by Supsystic &mdash; this removes the plugin folder completely.</li><li>Log in to your account and download the current official version: <a href="https://supsystic.com/login" target="_blank" rel="noopener">https://supsystic.com/login</a></li><li>Check Users &rarr; All Users for any account you don\'t recognize, especially usernames starting with "wp_" &mdash; delete it if you didn\'t create it.</li><li>Update WordPress to the latest version. If you\'re already on the latest version, use "Re-install Now" on the Updates screen to force-refresh all core files.</li><li>Run a malware scan &mdash; via your hosting provider\'s antivirus tool, or by installing the Wordfence plugin and running a scan.</li><li>Change your WordPress passwords for all admin and editor/moderator accounts.</li></ol><p>We\'ve also emailed this notice to the site administrator.</p><p>Questions? Contact our support: <a href="https://supsystic.com/contact-us" target="_blank" rel="noopener">https://supsystic.com/contact-us</a></p></div>'; }
function sggSendUnoffProEmail($version) { if (get_option('sgg_unoff_pro_notified_version', '') === $version) { return; } $siteUrl = site_url(); $subject = '[Supsystic Security Alert] Compromised Photo Gallery PRO by Supsystic detected and deactivated on ' . $siteUrl; $body = "Hello,\n\nThis is an automated security alert from Photo Gallery by Supsystic (free version), triggered on {$siteUrl}.\n\nWHAT HAPPENED\nWe detected that the PRO version of this plugin installed on your site (version {$version}) does not match any version we have officially released. Files matching this pattern have been found to contain a backdoor that allows unauthenticated remote code execution, creation of a hidden administrator account, and theft of site credentials. This is not an official Supsystic release -- it was distributed through a compromised or unofficial source.\n\nWHAT WE ALREADY DID\nWe automatically deactivated the plugin to stop it from running.\n\nWHAT YOU NEED TO DO NOW\n\n1. Delete the plugin.\n   Go to wp-admin -> Plugins and click \"Delete\" on Photo Gallery PRO by Supsystic. This removes the entire plugin folder for you.\n\n2. Install the official version.\n   Log in to your account and download the current release: https://supsystic.com/login\n\n3. Check for unauthorized admin accounts.\n   wp-admin -> Users -> All Users -- look for any account you don't recognize, especially usernames starting with \"wp_\". Delete it if you didn't create it.\n\n4. Update WordPress core to the latest version.\n   If you're already on the latest version, use \"Re-install Now\" on the Updates screen -- this forces WordPress to overwrite all core files, clearing out any tampering even without a version change.\n\n5. Run a malware scan.\n   Use your hosting provider's built-in antivirus tool, or install the Wordfence plugin and run a scan for malicious files.\n\n6. Change your passwords.\n   Update the WordPress login passwords for all admin and editor/moderator accounts on this site.\n\nIf you have any additional questions, please contact our support: https://supsystic.com/contact-us\n\n-- Supsystic Security Team\n"; wp_mail(get_option('admin_email'), $subject, $body); update_option('sgg_unoff_pro_notified_version', $version); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
add_action('plugins_loaded', function () {
  $proPluginPath = dirname(dirname(__FILE__));
  $proPluginPath = str_replace('gallery-supsystic', 'supsystic-gallery-pro', $proPluginPath);
  $proPluginPath = str_replace('gallery-by-supsystic', 'supsystic-gallery-pro', $proPluginPath);
  $proPluginPath = $proPluginPath . '/index.php';
  if (!file_exists($proPluginPath)) {
    return;
  }
  $pluginData = get_file_data($proPluginPath, ['Version' => 'Version'], false);
  $sggUnoffProVersions = ['2.10.9', '99.0.1', '1.99.0.1'];
  if (!empty($pluginData['Version']) && in_array($pluginData['Version'], $sggUnoffProVersions, true)) {
    deactivate_plugins('supsystic-gallery-pro/index.php');
    add_action('all_admin_notices', function () use ($pluginData) {
      sggUnoffProNotice($pluginData['Version']);
    });
    add_action('after_plugin_row_supsystic-gallery-pro/index.php', function () use ($pluginData) { echo '<tr class="plugin-update-tr active" id="supsystic-gallery-pro-update" data-slug="supsystic-gallery-pro" data-plugin="supsystic-gallery-pro/index.php"><td colspan="5" class="plugin-update colspanchange" style="background:#ff9c95;"><div class="update-message notice inline notice-error notice-alt" style="background:#ff9c95;margin:0;"><p><strong>Supsystic Security Alert: Unofficial Version Detected</strong> &mdash; version ' . esc_html($pluginData['Version']) . ' does not match any release we ever officially published. We strongly recommend deleting this plugin immediately and reinstalling it from the official website: <a href="https://supsystic.com/" target="_blank" rel="noopener">https://supsystic.com/</a></p></div></td></tr>'; });
    if (is_admin()) {
      sggSendUnoffProEmail($pluginData['Version']);
    }
    add_filter('site_transient_update_plugins', function ($transient) {
      if (is_object($transient) && isset($transient->response['supsystic-gallery-pro/index.php'])) {
        unset($transient->response['supsystic-gallery-pro/index.php']);
      }
      return $transient;
    });
    $sggAutoUpdatePlugins = (array) get_option('auto_update_plugins', []);
    if (in_array('supsystic-gallery-pro/index.php', $sggAutoUpdatePlugins, true)) {
      update_option('auto_update_plugins', array_values(array_diff($sggAutoUpdatePlugins, ['supsystic-gallery-pro/index.php'])));
    }
  }
});
