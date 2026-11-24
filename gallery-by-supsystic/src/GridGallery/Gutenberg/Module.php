<?php

/**
 * Registers Gutenberg blocks for Photo Gallery by Supsystic: pick a gallery,
 * or a whole gallery group, from a dropdown in the block editor. When the
 * editor canvas is an iframe, the block shows the gallery as on the page
 * (server-rendered markup + the gallery's CSS/JS added to that iframe only,
 * see block-preview.js); otherwise only the picker. The picked gallery/group
 * renders normally on the published page, same as the shortcode and the
 * classic widget.
 */
class GridGallery_Gutenberg_Module extends RscSgg_Mvc_Module
{
  const BLOCK_NAME = 'supsystic-gallery/gallery';
  const GROUP_BLOCK_NAME = 'supsystic-gallery/gallery-group';

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    add_action('init', [$this, 'registerBlock']);
    add_action('init', [$this, 'registerGroupBlock']);
    add_action('enqueue_block_editor_assets', [$this, 'addCanvasAssets'], 5);
    add_filter('widget_types_to_hide_from_legacy_widget_block', [$this, 'hideLegacyWidgets']);
    // The shared "Supsystic" category, first in the inserter (fix/shared/gutenberg-supsystic-category.md)
    if (function_exists('get_default_block_categories')) {
      add_filter('block_categories_all', [$this, 'addSupsysticBlockCategory'], 5);
    } else {
      add_filter('block_categories', [$this, 'addSupsysticBlockCategory'], 5);
    }
  }

  /**
   * The "Supsystic" block category, put first. Every Supsystic plugin adds it the same way, only if it isn't there
   * yet, so any set of our plugins gives one category.
   *
   * @param array $categories
   * @return array
   */
  public function addSupsysticBlockCategory($categories)
  {
    $categories = (array) $categories;
    foreach ($categories as $category) {
      if (isset($category['slug']) && $category['slug'] === 'supsystic') {
        return $categories;
      }
    }
    array_unshift($categories, ['slug' => 'supsystic', 'title' => 'Supsystic', 'icon' => null]);

    return $categories;
  }

  /**
   * Block widget editor (Appearance → Widgets, Customizer): offer the gallery and group blocks only, not the same
   * thing again as a "Legacy Widget". It only hides them from the inserter: widgets already placed keep rendering
   * and stay editable, and the classic widgets screen (Classic Widgets plugin) still lists them.
   *
   * @param array $widgetTypes id_base list
   * @return array
   */
  public function hideLegacyWidgets($widgetTypes)
  {
    $widgetTypes = (array) $widgetTypes;
    if (!class_exists('WP_Block_Type_Registry')) {
      return $widgetTypes;
    }
    $registry = WP_Block_Type_Registry::get_instance();
    foreach ([self::BLOCK_NAME => 'sggwidget', self::GROUP_BLOCK_NAME => 'sgggroupwidget'] as $block => $idBase) {
      if ($registry->is_registered($block) && !in_array($idBase, $widgetTypes, true)) {
        $widgetTypes[] = $idBase;
      }
    }

    return $widgetTypes;
  }

  const PREVIEW_HANDLE = 'supsystic-gallery-gutenberg-preview';

  /**
   * The live preview shared by both blocks (block-preview.js).
   */
  private function registerPreviewScript()
  {
    if (wp_script_is(self::PREVIEW_HANDLE, 'registered')) {
      return;
    }
    wp_register_script(
      self::PREVIEW_HANDLE,
      $this->getLocationUrl() . '/assets/js/block-preview.js',
      ['wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor', 'wp-server-side-render'],
      $this->getConfig()->get('plugin_version'),
      true
    );
  }

  /**
   * Block editor only: the tags of the gallery's front-end CSS/JS, for block-preview.js to add to the editor
   * canvas iframe when a gallery block is there. Printed into a separate queue, so nothing is enqueued on the
   * admin screen itself and the front end is not touched.
   */
  public function addCanvasAssets()
  {
    if (!wp_script_is(self::PREVIEW_HANDLE, 'registered')) {
      return;
    }
    $html = $this->canvasAssetsHtml();
    if ($html === '') {
      return;
    }
    wp_add_inline_script(self::PREVIEW_HANDLE, 'window.SupsysticGalleryCanvas = ' . wp_json_encode(['assets' => $html]) . ';', 'before');
  }

  /**
   * @return string <link>/<style>/<script> tags of the same assets the Elementor editor preview loads
   */
  private function canvasAssetsHtml()
  {
    global $wp_styles, $wp_scripts;

    $galleries = $this->getModule('galleries');
    if (!$galleries || !method_exists($galleries, 'loadFrontendAssets') || !($wp_styles instanceof WP_Styles) || !($wp_scripts instanceof WP_Scripts)) {
      return '';
    }

    // Same approach as WordPress for the editor iframe (_wp_get_iframed_editor_assets): a separate queue
    $currentStyles = $wp_styles;
    $currentScripts = $wp_scripts;
    $wp_styles = new WP_Styles();
    $wp_scripts = new WP_Scripts();
    $wp_styles->registered = $currentStyles->registered;
    $wp_scripts->registered = $currentScripts->registered;

    $level = ob_get_level();
    $html = '';
    try {
      // The gallery's front-end handles are registered on wp_enqueue_scripts, which the admin doesn't fire
      $ui = $this->getModule('ui');
      if ($ui && isset($ui->asset) && method_exists($ui->asset, 'enqueueFrontend')) {
        $ui->asset->enqueueFrontend();
      }
      $galleries->loadFrontendAssets();
      // Carousel and Slider galleries: Swiper, PhotoSwipe and their script
      $carousel = $this->getModule('carousel');
      if ($carousel && method_exists($carousel, 'enqueueFrontAssets')) {
        $carousel->enqueueFrontAssets(true, true);
      }
      ob_start();
      $wp_styles->do_items();
      $wp_scripts->do_items();
      $html = (string) ob_get_clean();
    } catch (\Throwable $e) {
      while (ob_get_level() > $level) {
        ob_end_clean();
      }
      $html = '';
    }

    $wp_styles = $currentStyles;
    $wp_scripts = $currentScripts;

    return $html;
  }

  public function registerBlock()
  {
    if (!function_exists('register_block_type') || !function_exists('wp_register_script')) {
      return;
    }

    $blockJson = $this->getLocation() . '/block/block.json';
    if (!is_file($blockJson)) {
      return;
    }

    $scriptHandle = 'supsystic-gallery-gutenberg-block';
    $config = $this->getConfig();
    $this->registerPreviewScript();

    wp_register_script(
      $scriptHandle,
      $this->getLocationUrl() . '/assets/js/block.js',
      ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor', self::PREVIEW_HANDLE],
      $config->get('plugin_version'),
      true
    );

    wp_localize_script($scriptHandle, 'SupsysticGalleryBlockData', [
      'galleries' => $this->getGalleryOptionsForEditor(),
    ] + $this->getBrandingForEditor('galleries', 'view', 'gallery_id', 'showPresets'));

    register_block_type($blockJson, [
      'render_callback' => [$this, 'render'],
    ]);
  }

  /**
   * Block render_callback - the published page and the editor preview (REST block renderer), reuses the
   * shortcode renderer, same as the Elementor widget and the classic widget.
   *
   * @param array $attributes
   * @return string
   */
  public function render($attributes)
  {
    $galleryId = !empty($attributes['galleryId']) ? (int) $attributes['galleryId'] : 0;

    if (!$galleryId) {
      return '';
    }

    return do_shortcode(sprintf('[supsystic-gallery id="%d"]', $galleryId));
  }

  /**
   * The Supsystic icon of the block and its "Edit this … / Create a …" links (fix/shared/builder-blocks-branding.md).
   * The links are given only to users who can open the plugin.
   *
   * @param string $module Plugin module of the edited object
   * @param string $editAction Its edit screen
   * @param string $idParam The id parameter of that screen (the id is added by the block)
   * @param string $createAction The "create new" screen of the same module
   * @return array icon, canManage, editUrl, createUrl
   */
  private function getBrandingForEditor($module, $editAction, $idParam, $createAction)
  {
    $menu = (array) $this->getConfig()->get('plugin_menu');
    $capability = !empty($menu['capability']) ? $menu['capability'] : 'manage_options';
    $base = 'admin.php?page=' . (!empty($menu['menu_slug']) ? $menu['menu_slug'] : 'supsystic-gallery') . '&module=' . $module;
    $canManage = is_user_logged_in() && current_user_can($capability);

    return [
      'icon' => SGG_PLUGIN_URL . 'app/assets/img/supsystic-adminbar.png',
      'canManage' => $canManage,
      'editUrl' => $canManage ? admin_url($base . '&action=' . $editAction . '&' . $idParam . '=') : '',
      'createUrl' => $canManage ? admin_url($base . '&action=' . $createAction) : '',
    ];
  }

  /**
   * @return array [{label, value}, ...] for the block's SelectControl
   */
  private function getGalleryOptionsForEditor()
  {
    $model = new GridGallery_Galleries_Model_Galleries();
    $options = [];

    foreach ($model->getOptionsForSelect() as $id => $label) {
      $options[] = ['label' => $label, 'value' => (string) $id];
    }

    return $options;
  }

  public function registerGroupBlock()
  {
    if (!function_exists('register_block_type') || !function_exists('wp_register_script')) {
      return;
    }

    $blockJson = $this->getLocation() . '/block/group-block.json';
    if (!is_file($blockJson)) {
      return;
    }

    $scriptHandle = 'supsystic-gallery-group-gutenberg-block';
    $config = $this->getConfig();
    $this->registerPreviewScript();

    wp_register_script(
      $scriptHandle,
      $this->getLocationUrl() . '/assets/js/group-block.js',
      ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor', self::PREVIEW_HANDLE],
      $config->get('plugin_version'),
      true
    );

    wp_localize_script($scriptHandle, 'SupsysticGalleryGroupBlockData', [
      'groups' => $this->getGroupOptionsForEditor(),
    ] + $this->getBrandingForEditor('gallerygroups', 'edit', 'group_id', 'create'));

    register_block_type($blockJson, [
      'render_callback' => [$this, 'renderGroup'],
    ]);
  }

  /**
   * Block render_callback - the published page and the editor preview (REST block renderer), reuses the
   * group shortcode renderer, same as the Elementor group widget.
   *
   * @param array $attributes
   * @return string
   */
  public function renderGroup($attributes)
  {
    $groupId = !empty($attributes['groupId']) ? (int) $attributes['groupId'] : 0;

    if (!$groupId) {
      return '';
    }

    return do_shortcode(sprintf('[supsystic-gallery-group id="%d"]', $groupId));
  }

  /**
   * @return array [{label, value}, ...] for the group block's SelectControl
   */
  private function getGroupOptionsForEditor()
  {
    $model = new GridGallery_GalleryGroups_Model_Groups();
    $options = [];

    foreach ($model->getOptionsForSelect() as $id => $label) {
      $options[] = ['label' => $label, 'value' => (string) $id];
    }

    return $options;
  }
}
