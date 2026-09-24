<?php

namespace Elementor;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Elementor widget that inserts a Photo Gallery by Supsystic gallery, picked
 * from a dropdown, and previews it live in the editor.
 */
class Widget_Supsystic_Gallery extends Widget_Base
{
  public function get_name()
  {
    return 'supsystic_gallery';
  }

  public function get_title()
  {
    return esc_html__('Photo Gallery by Supsystic', 'sgg');
  }

  public function get_icon()
  {
    return 'eicon-gallery-grid';
  }

  public function get_categories()
  {
    return ['general', 'basic'];
  }

  public function get_keywords()
  {
    return ['gallery', 'photo gallery', 'images', 'supsystic'];
  }

  /**
   * The widget has no content_template(), so Elementor re-renders it via a
   * PHP round-trip on every settings change; this tells the editor to
   * reload the whole preview iframe for that round-trip instead of trying
   * to patch the DOM in place (same approach Elementor's own core Shortcode
   * widget uses).
   */
  public function is_reload_preview_required()
  {
    return true;
  }

  protected function register_controls()
  {
    $this->start_controls_section('section_supsystic_gallery', [
      'label' => esc_html__('Gallery', 'sgg'),
    ]);

    $options = $this->getGalleryOptions();

    $this->add_control('gallery_id', [
      'label' => esc_html__('Select gallery', 'sgg'),
      'type' => Controls_Manager::SELECT,
      'options' => $options ?: ['' => esc_html__('No galleries yet - create one first', 'sgg')],
      'default' => $options ? (string) array_key_first($options) : '',
    ]);

    $this->end_controls_section();
  }

  protected function render()
  {
    $settings = $this->get_settings_for_display();
    $galleryId = !empty($settings['gallery_id']) ? (int) $settings['gallery_id'] : 0;

    if (!$galleryId) {
      if (Plugin::$instance->editor->is_edit_mode()) {
        printf(
          '<div class="elementor-alert elementor-alert-info">%s</div>',
          esc_html__('Select a gallery to display it here.', 'sgg')
        );
      }
      return;
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gallery markup is built/escaped by the plugin's own shortcode renderer.
    echo do_shortcode(sprintf('[supsystic-gallery id="%d"]', $galleryId));
  }

  /**
   * @return array id => label
   */
  private function getGalleryOptions()
  {
    $model = new \GridGallery_Galleries_Model_Galleries();
    $options = [];

    foreach ($model->getOptionsForSelect() as $id => $label) {
      $options[(string) $id] = $label;
    }

    return $options;
  }
}
