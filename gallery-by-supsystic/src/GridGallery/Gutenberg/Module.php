<?php

/**
 * Registers Gutenberg blocks for Photo Gallery by Supsystic: pick a gallery,
 * or a whole gallery group, from a dropdown in the block editor, no live
 * preview there (the block editor's iframed canvas doesn't load this
 * plugin's display JS/CSS). The picked gallery/group renders normally on
 * the published page, same as the shortcode and the classic widget.
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

    wp_register_script(
      $scriptHandle,
      $this->getLocationUrl() . '/assets/js/block.js',
      ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n'],
      $config->get('plugin_version'),
      true
    );

    wp_localize_script($scriptHandle, 'SupsysticGalleryBlockData', [
      'galleries' => $this->getGalleryOptionsForEditor(),
    ]);

    register_block_type($blockJson, [
      'render_callback' => [$this, 'render'],
    ]);
  }

  /**
   * Block render_callback - only runs on the published page, reuses the
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

    wp_register_script(
      $scriptHandle,
      $this->getLocationUrl() . '/assets/js/group-block.js',
      ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n'],
      $config->get('plugin_version'),
      true
    );

    wp_localize_script($scriptHandle, 'SupsysticGalleryGroupBlockData', [
      'groups' => $this->getGroupOptionsForEditor(),
    ]);

    register_block_type($blockJson, [
      'render_callback' => [$this, 'renderGroup'],
    ]);
  }

  /**
   * Block render_callback - only runs on the published page, reuses the
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
