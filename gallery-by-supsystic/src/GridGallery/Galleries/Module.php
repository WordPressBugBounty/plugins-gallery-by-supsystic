<?php

/**
 * Class GridGallery_Galleries_Module
 *
 * @package GridGallery\Galleries
 * @author Artur Kovalevsky
 */
#[\AllowDynamicProperties]
class GridGallery_Galleries_Module extends GridGallery_Core_Module
{
  /**
   * The one popup engine + theme Free is allowed to render: base Colorbox.
   * Every other theme (and the styling options around the popup) stays Pro
   * and is shown PRO-badged/locked in settings.twig's #themeDialog.
   *
   * Keep FREE_POPUP_THEME in sync with the `freePopupTheme` value at the top
   * of that dialog in settings.twig - if the two drift, the settings page
   * starts advertising a theme the frontend won't actually render.
   */
  const FREE_POPUP_TYPE = '0';
  const FREE_POPUP_THEME = 'theme_1';

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();
    $this->registerShortcode();

    $resources = new GridGallery_Galleries_Model_Resources();

    $config = $this->getEnvironment()->getConfig();
    $prefix = $config->get('hooks_prefix');

    add_action($prefix . 'after_ui_loaded', [$this, 'registerAssets']);
    add_action($prefix . 'gallery_delete', [$resources, 'deleteByGalleryId']);

    /* Delete attachment */
    add_action('delete_attachment', [$resources, 'deleteAttachmentResources']);
    add_action('grid_gallery_delete_image', [$resources, 'deleteByResourceId']);
    add_action('gg_delete_photo_id', [$resources, 'deletePhotoById']);

    add_image_size('gg_gallery_thumbnail', 450, 250, true);

    // !!!!!! use {} for preg_* functions as start and end of the expresion.
    $pregReplaceFilter = new Twig_SupTwgSgg_SimpleFilter('preg_replace', [$this, 'pregReplace']);

    $unitReplaceFilter = new Twig_SupTwgSgg_SimpleFilter(
      'unitReplace', // The name of the filter in Twig
      [$this, 'unitReplace'], // The function that will handle the replacement logic
    );

    // Same shape as unitReplace|default() - a stored numeric setting (icons[size]
    // etc.) can end up non-numeric (empty string past its "empty" check, stray
    // text, or an array from a malformed submit) and Twig's own default() only
    // catches genuinely empty values, not "present but not a number". Anywhere
    // that value later hits an arithmetic operator (e.g. `* 2` for icon
    // width/height), a non-numeric string throws a fatal TypeError and an array
    // throws "Array to string conversion" - both seen in production logs.
    $numDefaultFilter = new Twig_SupTwgSgg_SimpleFilter('numDefault', [$this, 'numDefault']);

    $httpFilter = new Twig_SupTwgSgg_SimpleFilter('force_http', [$this, 'forceHttpUrl']);
    $htmlspecialchars_decode = new Twig_SupTwgSgg_SimpleFilter('htmlspecialchars_decode', 'htmlspecialchars_decode');

    $function = new Twig_SupTwgSgg_SimpleFunction('translate', [$this->getController(), 'translate']);
    $ceilFunction = new Twig_SupTwgSgg_SimpleFunction('ceil', 'ceil');
    $all_categories_func = new Twig_SupTwgSgg_SimpleFunction('all_categories', 'get_categories');
    $hexToRgbaFunction = new Twig_SupTwgSgg_SimpleFunction('hex_to_rgba', 'GridGallery_Galleries_Module::hexToRgbaStr');

    $twig = $this->getEnvironment()->getTwig();
    $twig->enableAutoReload();
    $twig->addFilter($pregReplaceFilter);
    $twig->addFilter($unitReplaceFilter);
    $twig->addFilter($numDefaultFilter);
    $twig->addFilter($httpFilter);
    $twig->addFilter($htmlspecialchars_decode);
    $twig->addFunction($function);
    $twig->addFunction($ceilFunction);
    $twig->addFunction($all_categories_func);
    $twig->addFunction($hexToRgbaFunction);

    // To avoid conflict with other plugins which have older twig version.
    $twig->addFilter(new Twig_SupTwgSgg_SimpleFilter('round', 'round'));

    // Widget
    add_action('widgets_init', [$this, 'registerWidget']);
    // Cache dir
    $this->cacheDirectory = $this->getConfig()->get('plugin_cache_galleries');

    add_action('init', [$this, 'sggInit']);
  }

  public function sggInit()
  {
    if (is_rtl()) {
      wp_enqueue_style('sggRtlBackendCss', $this->getLocationUrl() . '/assets/css/rtl.backend.css');
    }
  }

  //on shutdown check is footer is printed , if not print scripts for our gallery
  public function shutdown()
  {
    if (!(defined('DOING_AJAX') && DOING_AJAX) && !(defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) && !did_action('wp_footer')) {
      wp_print_footer_scripts();
    }
  }

  public function getFrontendCSS()
  {
    return [
      $this->getEnvironment()->getConfig()->get('plugin_url') . '/app/assets/css/libraries/fontawesome/font-awesome.min.css',
      $this->getLocationUrl() . '/assets/css/grid-gallery.galleries.frontend.css',
      $this->getLocationUrl() . '/assets/css/grid-gallery.galleries.effects.css',
      $this->getLocationUrl() . '/assets/css/jquery.flex-images.css',
      $this->getLocationUrl() . '/assets/css/lightSlider.css',
      $this->getLocationUrl() . '/assets/css/prettyPhoto.css',
      $this->getLocationUrl() . '/assets/css/photobox.css',
      // $this->getLocationUrl() . '/assets/css/photobox.ie.css', Need to add check if IE from UA
      $this->getLocationUrl() . '/assets/css/gridgallerypro-embedded.css',
      $this->getLocationUrl() . '/assets/css/icons-effects.css',
      $this->getLocationUrl() . '/assets/css/loaders.css',
    ];
  }

  public function getFrontendJS()
  {
    $url = $this->getEnvironment()->getConfig()->get('plugin_url');
    return [
      'jquery',
      $this->getLocationUrl() . '/assets/js/lib/imagesLoaded.min.js',
      $this->getLocationUrl() . '/assets/js/lib/jquery.easing.js',
      $this->getLocationUrl() . '/assets/js/lib/jquery.prettyphoto.js',
      $this->getLocationUrl() . '/assets/js/lib/jquery.quicksand.js',
      $this->getLocationUrl() . '/assets/js/lib/jquery.wookmark.js',
      $this->getLocationUrl() . '/assets/js/lib/hammer.min.js',
      $this->getLocationUrl() . '/assets/js/lib/jquery.history.js',
      $url . '/app/assets/js/jquery.lazyload.min.js',
      [
        'handle' => 'frontend.jquery.slimscroll.js',
        'source' => $this->getLocationUrl() . '/assets/js/lib/jquery.slimscroll.js',
      ],
      $this->getLocationUrl() . '/assets/js/jquery.photobox.js',
      $this->getLocationUrl() . '/assets/js/jquery.sliphover.js',
      [
        'handle' => 'sgg-frontend-js',
        'source' => $this->getLocationUrl() . '/assets/js/frontend.js',
        'dependencies' => [
          'jquery',
          'hammer.min.js',
          'frontend.jquery.slimscroll.js',
          'imagesLoaded.min.js',
          'jquery.prettyphoto.js',
          'jquery.easing.js',
          'jquery.prettyphoto.js',
          'jquery.quicksand.js',
          'jquery.wookmark.js',
          'jquery.photobox.js',
          'jquery.sliphover.js',
          'jquery.colorbox.js',
          'jquery.history.js',
        ],
      ],
    ];
  }

  public function getBackendCSS()
  {
    $cssList = [
      $this->getLocationUrl() . '/assets/css/grid-gallery.galleries.style.css',
      $this->getLocationUrl() . '/assets/css/grid-gallery.galleries.effects.css',
      $this->getLocationUrl() . '/assets/css/ui.jqgrid.css',
      $this->getLocationUrl() . '/assets/css/jquery-ui.theme.min.css',
      $this->getLocationUrl() . '/assets/css/jquery-ui.structure.min.css',
      $this->getLocationUrl() . '/assets/css/jquery.jqplot.min.css',
      $this->getLocationUrl() . '/assets/css/jquery-ui.min.css',
      $this->getLocationUrl() . '/assets/css/gridgallerypro-embedded.css',
      $this->getLocationUrl() . '/assets/css/icons-effects.css',
      $this->getLocationUrl() . '/assets/css/loaders.css',
      $this->getLocationUrl() . '/assets/css/customChosen.css',
      SGG_PLUGIN_URL . '/app/assets/css/chosen.min.css',
    ];

    $environment = $this->getEnvironment();
    if ($environment->isAction('index')) {
      $cssList[] = $this->getLocationUrl() . '/assets/css/grid-gallery.galleries.list.css';
    }
    if ($environment->isAction('view')) {
      $cssList[] = $this->getLocationUrl() . '/assets/css/grid-gallery.galleries.tiles.css';
    }
    $ecommerceModule = $environment->getModule('ecommerce');
    if ($ecommerceModule && ($environment->isAction('settings') || $environment->isAction('view'))) {
      $ecommerceCss = $ecommerceModule->getLocation() . '/assets/css/ecommerce.css';
      if (is_file($ecommerceCss)) {
        $cssList[] = $ecommerceModule->getLocationUrl() . '/assets/css/ecommerce.css';
      }
    }

    return $cssList;
  }

  public function getBackendJS()
  {
    $environment = $this->getEnvironment();
    $jsList = [
      $this->getLocationUrl() . '/assets/js/settings.js',
      $this->getLocationUrl() . '/assets/js/attrchange.js',
      $this->getLocationUrl() . '/assets/js/addImages.js',
      $this->getLocationUrl() . '/assets/js/position.js',
      $this->getLocationUrl() . '/assets/js/jquery.jqGrid.min.js',
      $this->getLocationUrl() . '/assets/js/grid.locale-en.js',
      $this->getLocationUrl() . '/assets/js/holder.js',
      $this->getLocationUrl() . '/assets/js/grid-gallery.galleries.index.js',
      //$this->getLocationUrl() . '/assets/js/grid-gallery.galleries.view.js',
      //$this->getLocationUrl() . '/assets/js/grid-gallery.galleries.preview.js',
    ];

    $jsList[] = [
      'source' => $this->getLocationUrl() . '/assets/js/settings.index.js',
      'dependencies' => ['chosen.jquery.min.js'],
    ];

    if ($environment->isAction('view')) {
      $jsList[] = $this->getLocationUrl() . '/assets/js/grid-gallery.galleries.view.js';
    }
    if ($environment->isAction('preview')) {
      $jsList[] = $this->getLocationUrl() . '/assets/js/grid-gallery.galleries.preview.js';
    }

    $jsList[] = $this->getLocationUrl() . '/assets/js/grid-gallery.galleries.thumb.js';
    $jsList[] = SGG_PLUGIN_URL . '/app/assets/js/chosen.jquery.min.js';

    if ($environment->isAction('index')) {
      $jsList[] = $this->getLocationUrl() . '/assets/js/gallery.index.js';
    }
    $ecommerceModule = $environment->getModule('ecommerce');
    if ($ecommerceModule && $environment->isAction('settings')) {
      $ecommerceAdminJs = $ecommerceModule->getLocation() . '/assets/js/admin.js';
      if (is_file($ecommerceAdminJs)) {
        $jsList[] = $ecommerceModule->getLocationUrl() . '/assets/js/admin.js';
      }
    }

    return $jsList;
  }

  /**
   * Loads assets
   * @param GridGallery_Ui_Module $ui An instance of the UI module.
   */
  public function registerAssets(GridGallery_Ui_Module $ui)
  {
    $ui->asset->enqueue('styles', $this->getBackendCSS());
    $ui->asset->enqueue('scripts', $this->getBackendJS());
    $ui->asset->register('styles', $this->getFrontendCSS());
    $ui->asset->register('scripts', $this->getFrontendJS());
    // 2.2.9 Backward compatibility for users with old pro
    if ($this->getConfig()->get('is_pro')) {
      if (version_compare($this->getConfig()->get('pro_plugin_version'), '2.2.9', '<')) {
        $ui->asset->enqueue('styles', $this->getFrontendCSS(), 'frontend');
        $ui->asset->enqueue('scripts', $this->getFrontendJS(), 'frontend');
      }
    }
  }

  /**
   * @var GridGallery_Galleries_Attachment|null
   */
  protected $shortcodeAttachment = null;

  /**
   * @var array Mime types to try for the gallery currently being rendered.
   */
  protected $modernFormats = [];

  /**
   * @var int Encoding quality for siblings built on demand.
   */
  protected $modernQuality = 90;

  /**
   * @var GridGallery_Optimization_Model_ServerOptimize|null
   */
  protected $modernEngine = null;

  /**
   * Twig `get_attachment`: the model call, plus a swap to a modern-format
   * sibling when the gallery asked for one and the browser accepts it.
   *
   * @param int $attachmentId
   * @param int $width
   * @param int|null $height
   * @param string|null $cropPosition
   * @param int|null $cropQuality
   * @return string
   */
  public function twigGetAttachment($attachmentId, $width, $height = null, $cropPosition = null, $cropQuality = null)
  {
    if ($this->shortcodeAttachment === null) {
      $this->shortcodeAttachment = new GridGallery_Galleries_Attachment();
    }

    $url = $this->shortcodeAttachment->getAttachment($attachmentId, $width, $height, $cropPosition, $cropQuality);

    return $this->toModernFormatUrl($url);
  }

  public function twigModernImageUrl($url)
  {
    return $this->toModernFormatUrl($url);
  }

  /**
   * Decides, once per rendered gallery, which modern formats may be served.
   *
   * Content negotiation via the Accept header is used rather than <picture>
   * markup on purpose: the gallery figure is styled and scripted in a dozen
   * places, and wrapping every image would risk all of it, whereas swapping
   * the URL changes nothing structurally. The trade-off is that the response
   * now varies by Accept, so a Vary header is sent to stop a shared cache
   * handing an AVIF page to a browser that cannot render it.
   *
   * @param array $settingsData
   * @return void
   */
  protected function beginModernImageFormats($settingsData)
  {
    $this->modernFormats = [];

    if (!is_array($settingsData) || !class_exists('GridGallery_Optimization_Model_ServerOptimize')) {
      return;
    }

    $options = isset($settingsData['optimization']) && is_array($settingsData['optimization']) ? $settingsData['optimization'] : [];
    if (!isset($options['enabled']) || $options['enabled'] !== 'true') {
      return;
    }

    $frontendFormat = isset($options['frontend_format']) ? (string) $options['frontend_format'] : '';
    $wantAvif = $frontendFormat === 'avif' || ($frontendFormat === '' && isset($options['serve_avif']) && $options['serve_avif'] === '1');
    $wantWebp = $frontendFormat === 'webp' || ($frontendFormat === '' && isset($options['serve_webp']) && $options['serve_webp'] === '1');

    if (!$wantAvif && !$wantWebp) {
      return;
    }

    $accept = isset($_SERVER['HTTP_ACCEPT']) ? (string) $_SERVER['HTTP_ACCEPT'] : '';

    // Preference order matters: AVIF is the smaller of the two, so try it
    // first and fall back to WebP for browsers that only advertise WebP.
    if ($wantAvif && strpos($accept, 'image/avif') !== false) {
      $this->modernFormats[] = GridGallery_Optimization_Model_ServerOptimize::FORMAT_AVIF;
    }
    if ($wantWebp && strpos($accept, 'image/webp') !== false) {
      $this->modernFormats[] = GridGallery_Optimization_Model_ServerOptimize::FORMAT_WEBP;
    }

    $quality = isset($options['quality']) ? (int) $options['quality'] : 90;
    // Quality 100 is a sensible default for "leave my JPEG alone", but it makes
    // a pointlessly heavy WebP - cap the on-demand conversion where the format
    // still wins clearly.
    $this->modernQuality = $quality > 0 && $quality < 100 ? $quality : 90;

    if (!empty($this->modernFormats) && !headers_sent()) {
      header('Vary: Accept', false);
    }
  }

  /**
   * @return void
   */
  protected function endModernImageFormats()
  {
    $this->modernFormats = [];
  }

  protected function galleryHasActiveEcommerceRestrictions($galleryId)
  {
    $ecommerceModule = $this->getEnvironment()->getModule('ecommerce');

    return $ecommerceModule
      && method_exists($ecommerceModule, 'galleryHasActiveRestriction')
      && $ecommerceModule->galleryHasActiveRestriction((int) $galleryId);
  }

  protected function disableOptimizationAndCdnForEcommerce(array $settingsData)
  {
    if (!isset($settingsData['optimization']) || !is_array($settingsData['optimization'])) {
      $settingsData['optimization'] = [];
    }
    if (!isset($settingsData['cdn']) || !is_array($settingsData['cdn'])) {
      $settingsData['cdn'] = [];
    }

    $settingsData['optimization']['enabled'] = 'false';
    $settingsData['optimization']['serve_webp'] = '0';
    $settingsData['optimization']['serve_avif'] = '0';
    $settingsData['optimization']['frontend_format'] = 'original';
    $settingsData['cdn']['enabled'] = 'false';
    $settingsData['cdn']['auto'] = '0';

    return $settingsData;
  }

  /**
   * @param string $url
   * @return string The sibling URL when one exists on disk, else $url.
   */
  protected function toModernFormatUrl($url)
  {
    if (empty($this->modernFormats) || !is_string($url) || $url === '') {
      return $url;
    }

    if ($this->shortcodeAttachment === null) {
      $this->shortcodeAttachment = new GridGallery_Galleries_Attachment();
    }

    $path = $this->shortcodeAttachment->replaceUrlToFilePath($url);
    if (!$path) {
      return $url;
    }

    foreach ($this->modernFormats as $mime) {
      $pathPart = parse_url($url, PHP_URL_PATH);
      $extension = $pathPart ? strtolower(pathinfo($pathPart, PATHINFO_EXTENSION)) : '';
      if ($extension === GridGallery_Optimization_Model_ServerOptimize::getExtensionForMime($mime)) {
        return $url;
      }

      $siblingPath = GridGallery_Optimization_Model_ServerOptimize::getSiblingPath($path, $mime);
      if ($siblingPath === null) {
        continue;
      }

      // Built lazily: the rendered file is a derivative that only comes into
      // existence when the gallery is first displayed, so the sibling cannot
      // be produced ahead of time. Costs one conversion on the first request
      // and is reused from disk afterwards.
      if (!is_file($siblingPath)) {
        if ($this->modernEngine === null) {
          $this->modernEngine = new GridGallery_Optimization_Model_ServerOptimize();
        }
        $this->modernEngine->createSibling($path, $mime, $this->modernQuality);
      }

      if (is_file($siblingPath)) {
        return $url . '.' . GridGallery_Optimization_Model_ServerOptimize::getExtensionForMime($mime);
      }
    }

    return $url;
  }

  public function loadFrontendAssets()
  {
    $config = $this->getConfig();
    $prefix = $config->get('plugin_name') . '-';

    $this->getModule('colorbox')->loadColoboxStyles();

    foreach ($this->getFrontendCSS() as $source) {
      $handle = basename($source);
      wp_enqueue_style($handle);
    }

    foreach ($this->getFrontendJS() as $source) {
      if (is_array($source)) {
        if (isset($source['handle'])) {
          $handle = $source['handle'];
        } else {
          $handle = basename($source['source']);
        }
      } else {
        $handle = basename($source);
      }
      wp_enqueue_script($handle);
    }
    wp_localize_script('sgg-frontend-js', 'sggStandartFontsList', $this->getModule('ui')->getStandardFontsList());
    wp_localize_script('sgg-frontend-js', 'sggIsMobile', [$this->getModel('settings')->isMobile(true) ? 1 : 0]);

    //on shutdown check is footer is printed , if not print scripts for our gallery
    //add_action('shutdown', array($this,'shutdown'));
  }

  public function registerWidget()
  {
    register_widget('sggWidget');
  }

  public function unitReplace($key, array $unitMap = [])
  {
    // Provide a default array if none is provided
    $defaultMap = [
      0 => 'px',
      1 => '%',
      2 => 'em',
    ];
    // Use provided array if it's not empty, otherwise fallback to default
    $map = !empty($unitMap) ? $unitMap : $defaultMap;
    // Check if the key exists in the map, return the value if found, otherwise return the key itself
    return $map[$key ?? ''] ?? $key;
  }

  public function numDefault($value, $default)
  {
    // Only steps in for the case that was actually crashing (present but
    // non-numeric/array). A normal numeric value is returned completely
    // untouched - same string, same type - exactly like default() already
    // did, so nothing changes for every gallery that never hit this bug.
    return is_numeric($value) ? $value : $default;
  }

  public function pregReplace($value, $pattern, $replacement)
  {
    return preg_replace($pattern, $replacement, $value);
  }

  /**
   * Adds the http:// to the URL's without it.
   * @param  string $url URL.
   * @return string
   */
  public function forceHttpUrl($url)
  {
    if (!preg_match('/^https?:\/\//', $url)) {
      return 'http://' . $url;
    }

    return $url;
  }

  /**
   * Shortcode callback.
   * @param  array $attributes An array of the shortcode parameters.
   * @return string
   */
  public function getGallery($attributes)
  {
    if (is_feed()) {
      return;
    }
    $this->loadFrontendAssets();
    $id = $attributes['id'];
    $cachePath = $this->getCache($id);
    $settingsModel = $this->getModel('settings');
    // $membershipModel = $this->getModel('membership');

    // if($membershipModel->isPluginActive() && isset($attributes['image-list']) && count($attributes['image-list'])) {
    // 	$cachePath .= '-' . md5(json_encode($attributes['image-list']));
    // 	$attributes['membershipModel'] = $this->getModel('membership');
    // }
    // $this->initSocialSharePlugin($id);

    global $wpdb;
    $optValue = get_option($wpdb->prefix . $this->getConfig()->get('db_prefix') . 'rand_sorts');

    // if($optValue === false
    //             || !isset($optValue['id']) || !isset($optValue['val'])
    //             || !($optValue['id'] == $id && $optValue['val'] === true)) {

    //             if (file_exists($cachePath) && $this->getEnvironment()->isProd()) {
    //                 $cacheEntry = file_get_contents($cachePath);
    //                 // if CDN enable, replace HTTP_HOST
    //                 $this->replacePhotoHttpHostForCdnServer($cacheEntry, $id);
    //                 // if two identical galleries on page
    //                 $cacheEntry = preg_replace('/grid-gallery-([\d]+)-[\d]+/', 'grid-gallery-\1-' . rand(1, 99999), $cacheEntry);
    //                 return $cacheEntry;
    //             }
    //         }
    // }
    // Backward compatible with pro version 2.1.5.
    // In case when user have old pro version installed and new free we return old gallery realization.
    // if ($this->getConfig()->get('is_pro') && $this->getConfig()->get('pro_plugin_version') === null) {
    //     return $this->getOldGallery($attributes);
    // }

    $environment = $this->getEnvironment();

    if ($init = $this->initGallery($attributes)) {
      extract($init);

      $settingsData = is_object($settings) ? $settings->data : $settings;
      // Deliberately NOT $environment->isModule('license'), which checks
      // "is the license admin page the current request" - always false on
      // a real (non-admin) page load regardless of actual license status,
      // which would force this else branch unconditionally for everyone.
      // getModule() safely returns null (not a fatal) when the license
      // module isn't registered at all, e.g. a Free-only install.
      $licenseModule = $environment->getModule('license');
      if ($environment->isPro() && $licenseModule && $licenseModule->isActive()) {
      } else {
        $settingsData['icons']['enabled'] = 'false';
        $settingsData['thumbnail']['overlay']['enabled'] = 'false';
        $settingsData['lazyload']['enabled'] = '0';
        $settingsData['slideshow'] = false;

        // Free ships a REAL working popup - competitors (NextGEN, FooGallery)
        // all include a lightbox in their free tier, and showing a configured
        // popup in the settings UI that silently does nothing on the frontend
        // was worse than not offering it at all.
        //
        // What stays Pro is everything AROUND the popup: the other themes,
        // the popup border, slideshow, custom size and image-fit mode. Those
        // are forced back to their neutral values here so a gallery that was
        // configured under an active license (or has a Pro preset stored)
        // can't leak premium popup styling into a Free render.
        //
        // box.enabled is deliberately NOT touched: turning the popup off is a
        // legitimate Free choice and must survive. When it IS off, box.type is
        // blanked rather than forced to '0' - gallery.twig's `box.type == '0'`
        // branch doesn't re-check box.enabled, so a '0' there would emit a
        // second data-popup-type alongside the "disable" one, and helpers.twig
        // would still hang gg-colorbox on every photo.
        $popupEnabled = !isset($settingsData['box']['enabled']) || $settingsData['box']['enabled'] !== 'false';

        if ($popupEnabled) {
          $settingsData['box']['type'] = self::FREE_POPUP_TYPE;
          $settingsData['box']['theme'] = self::FREE_POPUP_THEME;
        } else {
          $settingsData['box']['type'] = '';
        }

        $settingsData['box']['slideshow'] = 'false';
        $settingsData['box']['slideshowAuto'] = 'false';
        $settingsData['box']['popupwidth'] = '';
        $settingsData['box']['popupheight'] = '';

        if (!isset($settingsData['popup']) || !is_array($settingsData['popup'])) {
          $settingsData['popup'] = [];
        }
        $settingsData['popup']['placementType'] = 0;
        $settingsData['popup']['border']['enable'] = '';

        // Optimization and CDN are PRO in full, including the frontend
        // delivery switches - a gallery configured under a licence must not
        // keep serving WebP/AVIF once that licence lapses.
        $settingsData['optimization']['serve_webp'] = '0';
        $settingsData['optimization']['serve_avif'] = '0';
        $settingsData['optimization']['frontend_format'] = 'original';
      }

      $hasEcommerceRestrictions = $this->galleryHasActiveEcommerceRestrictions((int) $id);
      if ($hasEcommerceRestrictions) {
        $settingsData = $this->disableOptimizationAndCdnForEcommerce($settingsData);
      }

      $this->beginModernImageFormats($settingsData);

      $gallery->random_val = rand(1, 99999);
      $this->applyEcommerceAccessState($gallery);
      $renderData = $this->render('@galleries/shortcode/gallery.twig', [
        'gallery' => $gallery,
        'settings' => $settingsData,
        'colorbox' => $this->getEnvironment()->getModule('colorbox')->getLocationUrl(),
        'isMobile' => $settingsModel->isMobile(true),
        'mobile' => isset($settings->data['box']['mobile']) ? $settingsModel->isMobile($settings->data['box']['mobile']) : null,
      ]);
      // if (isset($this->cacheDirectory)) {
      //     file_put_contents($cachePath, $renderData);
      // }
      $this->endModernImageFormats();

      // if CDN enable, replace HTTP_HOST
      if (!$hasEcommerceRestrictions && $environment->isPro() && $licenseModule && $licenseModule->isActive()) {
        $this->replacePhotoHttpHostForCdnServer($renderData, $id);
      }

      return $renderData;
    }
  }

  function replacePhotoHttpHostForCdnServer(&$renderData, $galleryId)
  {
    $settings = $this->getModel('settings')->get((int) $galleryId);
    $settingsData = $settings && isset($settings->data) && is_array($settings->data) ? $settings->data : [];
    $cdnOptions = isset($settingsData['cdn']) && is_array($settingsData['cdn']) ? $settingsData['cdn'] : [];
    if (!isset($cdnOptions['enabled']) || $cdnOptions['enabled'] !== 'true') {
      return false;
    }

    $cdnModel = $this->getModel('cdn');
    $currentHost = trim($cdnModel->getCurrentServerName());

    $cdnSett = $cdnModel->getServiceSettings();
    if (!$cdnModel->checkRequirements()) {
      $isGalleryTransfer = $cdnModel->isGalleryTransfer($galleryId);
      if (count($cdnSett) && $isGalleryTransfer && !empty($cdnSett['current']) && isset($cdnSett['setting'][$cdnSett['current']]) && count($cdnSett['setting'][$cdnSett['current']]) && !empty($cdnSett['setting'][$cdnSett['current']]['zone_name'])) {
        $cdnHost = trim($cdnSett['setting'][$cdnSett['current']]['zone_name']);
        if (strtolower($currentHost) == strtolower($cdnHost)) {
          return false;
        }

        $pattern = ["`(src=[\'\"]{1}http[s]?:\/\/)" . $currentHost . '([-\/\d\w_.]*(jpg|jpeg|bmp|gif|png))`iu', "`(href=[\'\"]{1}http[s]?:\/\/)" . $currentHost . '([-\/\d\w_.]*(jpg|jpeg|bmp|gif|png))`ui'];
        $toReplace = ['$1' . $cdnHost . '$2', '$1' . $cdnHost . '$2'];
        $renderData = preg_replace($pattern, $toReplace, $renderData);

        return true;
      }
    }
    return false;
  }

  public function initGallery($attributes)
  {
    $galleries = $this->getModel('galleries');
    $cache = $this->getEnvironment()->getCache();
    $gallery = $galleries->getById($attributes);

    if (!$gallery) {
      return;
    }

    $key = sprintf('gallery_settings_%s', $attributes['id']);

    /** @var GridGallery_Settings_Registry $registry */
    $registry = $this->getEnvironment()->getModule('settings')->getRegistry();

    if (true === (bool) $registry->get('cache_enabled')) {
      $ttl = $registry->get('cache_ttl');
      $cache->setTtl($ttl);

      if (null === ($settings = $cache->get($key))) {
        $settings = $this->getGallerySettings($attributes['id']);
        $cache->set($key, $settings, (int) $ttl);
      }
    } else {
      $settings = $this->getGallerySettings($attributes['id']);
    }

    // $settings->data['socialSharing'] = $this->initGallerySocialShare($settings->data);

    $posArray = ['left', 'center', 'right'];

    $settings->data['area']['position'] = isset($settings->data['area']['position']) && isset($posArray[$settings->data['area']['position']]) ? $posArray[$settings->data['area']['position']] : 'center';
    $settings->data['rtl'] = is_rtl();

    if (isset($settings->data['area']['distance']) && $settings->data['area']['distance'] != 0) {
      $settings->data['area']['distance'] = $settings->data['area']['distance'] + 0.3;
    }

    if (property_exists($gallery, 'photos') && is_array($gallery->photos)) {
      $position = new GridGallery_Photos_Model_Position();

      /*foreach ($gallery->photos as $index => $row) {
				$gallery->photos[$index] = $position->setPosition(
					$row,
					'gallery',
					$gallery->id
				);
			}*/

      $positions = $position->setPosition($gallery->photos, 'gallery', $gallery->id);

      foreach ($gallery->photos as $index => $row) {
        foreach ($positions as $pos) {
          if ($row->id == $pos->photo_id) {
            $gallery->photos[$index]->position = $pos->position;
          }
        }
      }

      //ASC && DESC sort
      if (isset($settings->data['sort'])) {
        $gallery->photos = $position->sort($gallery->photos, $settings->data['sort']);
      } else {
        $gallery->photos = $position->sort($gallery->photos);
      }

      foreach ($gallery->photos as $photo) {
        if (!is_null($photo->attachment)) {
          $photo->attachment['caption'] = html_entity_decode($photo->attachment['caption']);
          $photo->attachment['description'] = html_entity_decode($photo->attachment['description']);
        }
      }
    }

    $settingsModel = $this->getModel('settings');

    return compact('gallery', 'settings', 'settingsModel');
  }

  // /**
  //  * init social share for gallery
  //  * @param $settings
  //  * @return array of social sharing setting and values
  //  */
  // public function initGallerySocialShare($settingsData){

  // 	$socialSharingModel = $this->getModel('socialSharing');
  // 	if(isset($settingsData['socialSharing'])){
  // 		$socialSharing = $settingsData['socialSharing'];
  // 	}else{
  // 		$socialSharing = array();
  // 	}

  // 	$socialSharing['pluginInstalled'] = $socialSharingModel->isPluginInstalled();
  // 	$socialSharing['projectsList'] = $socialSharingModel->getProjectsList();

  // 	$socialSharing['html'] = "";
  // 	if(
  // 		$socialSharing['pluginInstalled']
  // 	&&
  // 		isset($socialSharing['enabled'])
  // 	&&
  // 		$socialSharing['enabled']
  // 	){
  // 		$socialSharing['html'] = apply_filters(
  // 		    'sss_gallery_html',
  //             isset($socialSharing['projectId']) ? $socialSharing['projectId'] : null,
  //             !empty($settingsData['custom_network']) ? $settingsData['custom_network'] : null
  //         );
  // 	}

  // 	return $socialSharing;
  // }

  // public function initSocialSharePlugin($id){

  // 	$socialSharingModel = $this->getModel('socialSharing');
  //     $socialSharingModel->getProjectsList();

  //     $socialSharingModel = $this->getModel('socialSharing');
  //     $pluginInstalled = $socialSharingModel->isPluginInstalled();
  //     $projectsList = array_keys($socialSharingModel->getProjectsList());
  //     if(
  //         $pluginInstalled
  //     ){
  //         $settings = $this->getGallerySettings($id);
  // 		if(isset($settings->data['socialSharing']['projectId'])){
  // 			//Todo: clear social buttons only - not the whole gallery
  // 			//$this->cleanCache($id);
  // 			apply_filters('sss_gallery_html', $settings->data['socialSharing']['projectId']);
  // 		}
  // 	}
  // }

  public function getModel($alias)
  {
    return $this->getController()->getModel($alias);
  }

  protected function applyEcommerceAccessState($gallery)
  {
    if (!$gallery || empty($gallery->id) || empty($gallery->photos) || !is_array($gallery->photos)) {
      return;
    }

    $ecommerceModule = $this->getEnvironment()->getModule('ecommerce');
    if (!$ecommerceModule || !method_exists($ecommerceModule, 'getPhotoAccessState')) {
      return;
    }

    $groupIds = $this->getGalleryGroupIdsForEcommerce((int) $gallery->id);

    foreach ($gallery->photos as $photo) {
      $photoId = !empty($photo->id) ? (int) $photo->id : 0;
      $attachmentId = !empty($photo->attachment_id) ? (int) $photo->attachment_id : (!empty($photo->attachment['id']) ? (int) $photo->attachment['id'] : 0);
      if (!$photoId || !$attachmentId) {
        continue;
      }

      $state = $ecommerceModule->getPhotoAccessState((int) $gallery->id, $photoId, $groupIds);
      if (!$state) {
        continue;
      }

      if (empty($state->locked) && method_exists($ecommerceModule, 'buildProtectedImageUrl')) {
        $state->protected_url = $ecommerceModule->buildProtectedImageUrl((int) $gallery->id, $photoId, $attachmentId);
      }

      $photo->ecommerce = $state;
    }
  }

  protected function getGalleryGroupIdsForEcommerce($galleryId)
  {
    if (!class_exists('GridGallery_GalleryGroups_Model_Groups')) {
      return [];
    }

    $groups = new GridGallery_GalleryGroups_Model_Groups();
    if (method_exists($groups, 'setEnvironment')) {
      $groups->setEnvironment($this->getEnvironment());
    }

    return method_exists($groups, 'getActiveGroupIdsByGalleryId')
      ? $groups->getActiveGroupIdsByGalleryId((int) $galleryId)
      : [];
  }

  public function render($template, $parameters)
  {
    $twig = $this->getEnvironment()->getTwig();
    try {
      return preg_replace('/\s+/', ' ', trim($twig->render($template, $parameters)));
    } catch (Exception $e) {
      if (WP_DEBUG) {
        return $e->getMessage();
      }
    }
    return preg_replace('/\s+/', ' ', trim($twig->render($template, $parameters)));
  }

  public function getOldGallery($attributes)
  {
    $galleries = $this->getModel('galleries');
    $twig = $this->getEnvironment()->getTwig();
    $cache = $this->getEnvironment()->getCache();
    $gallery = $galleries->getById($attributes['id']);

    if (!$gallery) {
      return;
    }

    $key = sprintf('gallery_settings_%s', $attributes['id']);

    /** @var GridGallery_Settings_Registry $registry */
    $registry = $this->getEnvironment()->getModule('settings')->getRegistry();

    if (true === (bool) $registry->get('cache_enabled')) {
      $ttl = $registry->get('cache_ttl');
      $cache->setTtl($ttl);

      if (null === ($settings = $cache->get($key))) {
        $settings = $this->getGallerySettings($attributes['id']);
        $cache->set($key, $settings, (int) $ttl);
      }
    } else {
      $settings = $this->getGallerySettings($attributes['id']);
    }

    // $settings->data['socialSharing'] = $this->initGallerySocialShare($settings->data);

    $posArray = ['left', 'center', 'right'];

    $settings->data['area']['position'] = isset($settings->data['area']['position']) && isset($posArray[$settings->data['area']['position']]) ? $posArray[$settings->data['area']['position']] : 'center';

    if (property_exists($gallery, 'photos') && is_array($gallery->photos)) {
      $position = new GridGallery_Photos_Model_Position();

      /*foreach ($gallery->photos as $index => $row) {
                $gallery->photos[$index] = $position->setPosition(
                    $row,
                    'gallery',
                    $gallery->id
                );
            }*/

      $positions = $position->setPosition($gallery->photos, 'gallery', $gallery->id);

      foreach ($gallery->photos as $index => $row) {
        foreach ($positions as $pos) {
          if ($row->id == $pos->photo_id) {
            $gallery->photos[$index]->position = $pos->position;
          }
        }
      }

      $gallery->photos = $position->sort($gallery->photos);

      $cats = [];
      foreach ($gallery->photos as $photo) {
        if (property_exists($photo, 'tags') && is_array($photo->tags) && count($photo->tags) > 0) {
          foreach ($photo->tags as $tag) {
            if (!isset($cats[$tag])) {
              $cats[$tag] = true;
            }
          }
        }
      }
    }

    $settingsModel = new GridGallery_Galleries_Model_Settings();
    $postsLenght = sizeof($settingsModel->getPostsToRender($attributes['id'])) + sizeof($settingsModel->getPagesToRender($attributes['id']));

    if (isset($settings->data['posts']) && $settings->data['posts']['enable']) {
      foreach ($settingsModel->getPostsToRender($attributes['id']) as $post) {
        foreach ($post['categories'] as $category) {
          if (!isset($cats[$category['name']])) {
            $cats[$category['name']] = true;
          }
        }
      }

      foreach ($settingsModel->getPagesToRender($attributes['id']) as $page) {
        foreach ($page['categories'] as $category) {
          if (!isset($cats[$category['name']])) {
            $cats[$category['name']] = true;
          }
        }
      }
    }

    if (is_array($gallery->photos) && $gallery->photos) {
      foreach ($gallery->photos as $photo) {
        $photo->attachment['caption'] = html_entity_decode($photo->attachment['caption']);
      }
    }

    $template = $twig->render('@galleries/r314/shortcode/gallery.twig', [
      'gallery' => $gallery,
      'settings' => is_object($settings) ? $settings->data : $settings,
      'colorbox' => $this->getEnvironment()->getModule('colorbox')->getLocationUrl(),
      'categories' => isset($cats) ? $cats : [],
      'postsLength' => $postsLenght,
      'posts' => $settingsModel->getPostsToRender($attributes['id']),
      'pages' => $settingsModel->getPagesToRender($attributes['id']),
      'mobile' => isset($settings->data['box']['mobile']) ? $settingsModel->isMobile($settings->data['box']['mobile']) : null,
    ]);

    return preg_replace('/\s+/', ' ', trim($template));
  }

  public function addFrontendCss()
  {
    $stylesheets = $this->getFrontendCSS();

    foreach ($stylesheets as $url) {
      echo '<link rel="stylesheet" type="text/css" href="' . esc_url($url) . '"/>';
    }
  }

  public function addFrontendJs()
  {
    $javascripts = [
      //$this->getLocationUrl() . '/assets/js/grid-gallery.galleries.frontend.js'
      $this->getLocationUrl() . '/assets/js/frontend.js',
      $this->getLocationUrl() . '/assets/js/jquery.photobox.js',
      $this->getLocationUrl() . '/assets/js/jquery.sliphover.js',
    ];

    wp_enqueue_script('jquery.colorbox.js');

    foreach ($javascripts as $url) {
      echo '<script type="text/javascript" src="' . esc_url($url) . '"></script>';
    }
  }

  /**
   * Returns the gallery settings from the database.
   * If gallery is not configured, then default settings will be loaded.
   * @param int $galleryId Gallery identifier.
   * @return array
   */
  public function getGallerySettings($galleryId)
  {
    $model = new GridGallery_Galleries_Model_Settings();

    if (null === ($settings = $model->get((int) $galleryId))) {
      $config = $this->getEnvironment()->getConfig();
      $config->load('@galleries/settings.php');

      $settings = unserialize($config->get('gallery_settings'), ['allowed_classes' => false]);
    }

    return $settings;
  }

  /**
   * Registers the Gallery by Supsystic shortcode in the WordPress.
   */
  public function registerShortcode()
  {
    $attachment = new GridGallery_Galleries_Attachment();
    $handler = [$this, 'getGallery'];
    $shortcode = $this->getEnvironment()->getConfig()->get('shortcode_name');

    $this->shortcodeAttachment = $attachment;

    // Wrapped rather than bound straight to the model: this is the single
    // choke point where every rendered image URL is produced, and it is the
    // only place a WebP/AVIF swap can catch the cropped/watermarked
    // derivatives the template actually outputs.
    $this->getEnvironment()
      ->getTwig()
      ->addFunction(new Twig_SupTwgSgg_SimpleFunction('get_attachment', [$this, 'twigGetAttachment']));
    $this->getEnvironment()
      ->getTwig()
      ->addFunction(new Twig_SupTwgSgg_SimpleFunction('modern_image_url', [$this, 'twigModernImageUrl']));
    $this->getEnvironment()
      ->getTwig()
      ->addFunction(new Twig_SupTwgSgg_SimpleFunction('set_attachment_settings', [$attachment, 'setAttachmentSettings']));

    if (!empty($shortcode) && $shortcode !== null) {
      add_shortcode($shortcode, $handler);
    }

    // for the backward capability =< 0.2.2
    add_shortcode('grid-gallery', $handler);
  }

  public function isTranslationPluginExists()
  {
    return class_exists('frameTbs');
  }

  public function getCache($galleryId)
  {
    $protocol = is_ssl() ? '-ssl' : '';
    $cachePath = $this->cacheDirectory . DIRECTORY_SEPARATOR . $galleryId;
    if ($this->getModel('settings')->isMobile(true)) {
      $cachePath .= 'm';
    }
    if ($this->isTranslationPluginExists() && method_exists(frameTbs::_()->getModule('lang'), 'getLocale')) {
      $cachePath .= '-' . frameTbs::_()->getModule('lang')->getLocale();
    }
    $cachePath .= $protocol;
    return $cachePath;
  }

  public function cleanCache($galleryId, $cleanOtherCache = false)
  {
    if (empty($galleryId)) {
      return;
    }

    $cachePath = $this->getConfig()->get('plugin_cache_galleries') . DIRECTORY_SEPARATOR;
    $files = glob($cachePath . $galleryId . '*');

    if ($cleanOtherCache) {
      // remove all cache
      array_map('unlink', glob($cachePath . '*'));
      return;
    }

    foreach ($files as $file) {
      if (preg_match('/^(?:\d+(?:$|-.*$|m))/m', basename($file))) {
        unlink($file);
      }
    }
  }

  public function media_sideload_image($file, $post_id, $desc = null)
  {
    preg_match('/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file, $matches);
    $file_array = [];
    $file_array['name'] = basename($matches[0]);

    // Download file to temp location.
    $file_array['tmp_name'] = download_url($file);

    // If error storing temporarily try to download manualy else return the error.
    if (is_wp_error($file_array['tmp_name'])) {
      try {
        $temp = tmpfile();
        fwrite($temp, file_get_contents($file));
        $meta = stream_get_meta_data($temp);
        $file_array['tmp_name'] = $meta['uri'];
      } catch (Exception $e) {
        return $file_array['tmp_name'];
      }
    }

    // Do the validation and storage stuff.
    $id = media_handle_sideload($file_array, $post_id, $desc);

    // If error storing permanently, unlink.
    if (is_wp_error($id)) {
      @unlink($file_array['tmp_name']);
      return $id;
    }

    return $id;
  }

  public static function rgbToArray($rgb)
  {
    if ($rgb === null || is_array($rgb)) {
      return [];
    }

    $rgb = trim((string)$rgb);
    if ($rgb === '') {
      return [];
    }

    $rgb = array_map('trim', explode(',', trim(str_replace(['rgb', 'a', '(', ')'], '', $rgb))));
    return $rgb;
  }

  public static function hexToRgb($hex)
  {
    if ($hex === null || is_array($hex)) {
      return [];
    }

    $hex = trim((string)$hex);
    if ($hex === '') {
      return [];
    }

    if (strpos($hex, 'rgb') !== false) {
      // Maybe it's already in rgb format - just return it as array
      return self::rgbToArray($hex);
    }
    $hex = str_replace('#', '', $hex);

    if (strlen($hex) == 3) {
      $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
      $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
      $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
      $r = hexdec(substr($hex, 0, 2));
      $g = hexdec(substr($hex, 2, 2));
      $b = hexdec(substr($hex, 4, 2));
    }
    $rgb = [$r, $g, $b];
    return $rgb; // returns an array with the rgb values
  }

  public static function hexToRgbaStr($hex, $alpha = 1)
  {
    $rgbArr = self::hexToRgb($hex);
    if (empty($rgbArr)) {
      return '';
    }

    return 'rgba(' . implode(',', $rgbArr) . ',' . $alpha . ')';
  }
}

require_once 'Model/widget.php';
