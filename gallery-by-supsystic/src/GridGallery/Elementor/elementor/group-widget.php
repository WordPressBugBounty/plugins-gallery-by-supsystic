<?php

namespace Elementor;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Elementor widget that inserts a Gallery Group, picked from a dropdown, and
 * previews all of its galleries live in the editor.
 */
class Widget_Supsystic_Gallery_Group extends Widget_Base
{
  public function get_name()
  {
    return 'supsystic_gallery_group';
  }

  public function get_title()
  {
    return esc_html__('Gallery Group by Supsystic', 'sgg');
  }

  public function get_icon()
  {
    return 'eicon-gallery-group';
  }

  public function get_categories()
  {
    return ['general', 'basic'];
  }

  public function get_keywords()
  {
    return ['gallery', 'gallery group', 'photo gallery', 'supsystic'];
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
    $this->start_controls_section('section_supsystic_gallery_group', [
      'label' => esc_html__('Gallery Group', 'sgg'),
    ]);

    $options = $this->getGroupOptions();

    $this->add_control('group_id', [
      'label' => esc_html__('Select gallery group', 'sgg'),
      'type' => Controls_Manager::SELECT,
      'options' => $options ?: ['' => esc_html__('No gallery groups yet - create one first', 'sgg')],
      'default' => $options ? (string) array_key_first($options) : '',
    ]);

    $this->end_controls_section();
  }

  protected function render()
  {
    $settings = $this->get_settings_for_display();
    $groupId = !empty($settings['group_id']) ? (int) $settings['group_id'] : 0;

    if (!$groupId) {
      if (Plugin::$instance->editor->is_edit_mode()) {
        printf(
          '<div class="elementor-alert elementor-alert-info">%s</div>',
          esc_html__('Select a gallery group to display it here.', 'sgg')
        );
      }
      return;
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gallery markup is built/escaped by the plugin's own shortcode renderer.
    echo do_shortcode(sprintf('[supsystic-gallery-group id="%d"]', $groupId));
  }

  /**
   * @return array id => label
   */
  private function getGroupOptions()
  {
    $model = new \GridGallery_GalleryGroups_Model_Groups();
    $options = [];

    foreach ($model->getOptionsForSelect() as $id => $label) {
      $options[(string) $id] = $label;
    }

    return $options;
  }
}
