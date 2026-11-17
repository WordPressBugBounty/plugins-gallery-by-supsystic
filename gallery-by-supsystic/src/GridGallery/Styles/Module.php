<?php

/**
 * Style library of the editors: a window with all styles of the gallery's type (GridGallery_Styles_Library), free
 * and PRO, with search, a free/PRO filter and collections. A click puts the values of the style into the settings
 * form, so the live preview shows it at once and Save keeps it; the previous values can be brought back.
 *
 * It serves the classic gallery settings and the Carousel and Slider editors. PRO styles open while the PRO
 * license is active; otherwise they show the PRO window (Upsell module).
 */
class GridGallery_Styles_Module extends RscSgg_Mvc_Module
{
  /** @var string|false|null The type of the gallery of this settings page, false on other pages */
  private $pageType = null;

  public function onInit()
  {
    parent::onInit();

    if (is_admin() && $this->getEnvironment()->isPluginPage()) {
      add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 30);
      add_action('admin_footer', [$this, 'printPicker']);
    }
  }

  /**
   * Whether the PRO styles are open: the same license check as the PRO effects of the slider types.
   * @return bool
   */
  public function proOpen()
  {
    $carousel = $this->getEnvironment()->getModule('carousel');

    return $carousel && method_exists($carousel, 'lockedEffects') && !$carousel->lockedEffects();
  }

  /**
   * @param string $type gallery, carousel or slider
   * @return array Styles ready for the browser
   */
  public function stylesFor($type)
  {
    $open = $this->proOpen();
    $out = [];
    foreach (GridGallery_Styles_Library::forType($type) as $id => $style) {
      $out[] = [
        'id' => $id,
        'name' => $this->getEnvironment()->translate($style['name']),
        // With PRO active the five basic styles are not "free" ones, just the first collection
        'collection' => $style['collection'] === 'Free' && $open ? 'Essentials' : $style['collection'],
        'pro' => $style['pro'],
        'locked' => $style['pro'] && !$open,
        'swatch' => $style['swatch'],
        'shape' => GridGallery_Styles_Library::shape($style['profile']),
        'values' => $style['values'],
      ];
    }

    return $out;
  }

  /**
   * The type of the gallery whose settings page this is.
   * @return string|false
   */
  public function pageType()
  {
    if ($this->pageType !== null) {
      return $this->pageType;
    }
    $this->pageType = false;
    $galleryId = isset($_GET['gallery_id']) ? (int) $_GET['gallery_id'] : 0;
    if ($galleryId > 0 && $this->getEnvironment()->isModule('galleries', 'settings')) {
      $carousel = $this->getEnvironment()->getModule('carousel');
      $this->pageType = $carousel && method_exists($carousel, 'getTypeOf') ? $carousel->getTypeOf($galleryId) : 'gallery';
    }

    return $this->pageType;
  }

  public function enqueueAssets()
  {
    if (!$this->pageType()) {
      return;
    }
    $url = plugin_dir_url(__FILE__) . 'assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-st-16';
    wp_enqueue_style('sgg-style-mock', $url . '/css/mock.css', [], $version);
    wp_enqueue_script('sgg-style-mock', $url . '/js/mock.js', [], $version, true);
    wp_enqueue_style('sgg-styles', $url . '/css/styles.css', ['sgg-style-mock'], $version);
    wp_enqueue_script('sgg-styles', $url . '/js/styles.js', ['jquery', 'sgg-style-mock'], $version, true);
  }

  public function printPicker()
  {
    $type = $this->pageType();
    if (!$type) {
      return;
    }
    $styles = $this->stylesFor($type);
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes its values.
    echo $this->getEnvironment()->getTwig()->render('@styles/picker.twig', [
      'type' => $type,
      'prefix' => $type === 'gallery' ? '' : 'slider',
      'styles' => $styles,
      'total' => count($styles),
      'free' => count(array_filter($styles, function ($style) {
        return !$style['pro'];
      })),
      'collections' => array_keys(GridGallery_Styles_Library::collections()),
      'proOpen' => $this->proOpen(),
    ]);
  }
}
