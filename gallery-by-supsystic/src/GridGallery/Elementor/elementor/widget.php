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
    return esc_html__('Photo Gallery', 'sgg');
  }

  public function get_icon()
  {
    // The Supsystic logo (CSS: GridGallery_Elementor_Module::printEditorIconStyle)
    return 'supsystic-el-icon';
  }

  public function get_categories()
  {
    return ['supsystic'];
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

    $this->addPluginLinks($options);

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
   * "Edit this gallery in Photo Gallery · Create a gallery in Photo Gallery" under the selector, for users who can
   * open the plugin (fix/shared/builder-blocks-branding.md). The edit link gets the picked id in the editor
   * (GridGallery_Elementor_Module::printEditorLinkScript); until then it opens the list of galleries.
   *
   * @param array $options id => label
   */
  private function addPluginLinks(array $options)
  {
    if (!current_user_can('manage_options')) {
      return;
    }
    $base = admin_url('admin.php?page=supsystic-gallery&module=galleries');
    $links = [];
    if ($options) {
      $links[] = sprintf(
        '<a class="sgg-el-edit-link" href="%s" data-sgg-edit="%s" data-sgg-setting="gallery_id" target="_blank" rel="noopener">%s</a>',
        esc_url($base),
        esc_url($base . '&action=view&gallery_id='),
        esc_html__('Edit this gallery in Photo Gallery', 'sgg')
      );
    }
    $links[] = sprintf(
      '<a href="%s" target="_blank" rel="noopener">%s</a>',
      esc_url($base . '&action=showPresets'),
      esc_html__('Create a gallery in Photo Gallery', 'sgg')
    );

    $this->add_control('sgg_plugin_links', [
      'type' => Controls_Manager::RAW_HTML,
      'raw' => implode(' · ', $links),
      'content_classes' => 'elementor-descriptor',
    ]);
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
