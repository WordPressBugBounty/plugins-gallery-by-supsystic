<?php

class GridGallery_GalleryGroups_Module extends RscSgg_Mvc_Module
{
  public function onInit()
  {
    parent::onInit();

    add_action($this->getConfig()->get('hooks_prefix') . 'after_ui_loaded', [$this, 'loadAssets']);
    add_action('wp_ajax_sg_gallerygroups_search_galleries', [$this, 'ajaxSearchGalleries']);
    add_action('wp_ajax_sg_gallerygroups_search_groups', [$this, 'ajaxSearchGroups']);

    add_shortcode('supsystic-gallery-group', [$this, 'getGalleryGroup']);
    add_action('widgets_init', [$this, 'registerWidget']);
  }

  public function registerWidget()
  {
    register_widget('sggGroupWidget');
  }

  /**
   * Shortcode callback - renders every gallery that belongs to the group,
   * one after another, by reusing the Galleries module's own single-gallery
   * renderer (Free/Pro gating, e-commerce restrictions, modern image
   * formats, CDN rewriting - all of it - stay in one place). The classic
   * widget, Elementor widget and Gutenberg block all funnel through this
   * shortcode, same pattern as the single-gallery display already does.
   *
   * @param array $attributes
   * @return string
   */
  public function getGalleryGroup($attributes)
  {
    $groupId = !empty($attributes['id']) ? (int) $attributes['id'] : 0;
    if (!$groupId) {
      return '';
    }

    $galleryIds = (new GridGallery_GalleryGroups_Model_Groups())->getGalleryIds($groupId);
    if (!$galleryIds) {
      return '';
    }

    $galleriesModule = $this->getModule('galleries');
    if (!$galleriesModule) {
      return '';
    }

    $output = '';
    foreach ($galleryIds as $galleryId) {
      $output .= (string) $galleriesModule->getGallery(['id' => $galleryId]);
    }

    return sprintf('<div class="sgg-gallery-group" data-gallery-group-id="%d">%s</div>', $groupId, $output);
  }

  public function loadAssets(GridGallery_Ui_Module $ui)
  {
    $environment = $this->getEnvironment();
    $isGallerySettings = $environment->isModule('galleries') && $environment->isAction('settings');
    if ($environment->isModule('gallerygroups') || $isGallerySettings) {
      $ui->asset->enqueue('styles', [
        SGG_PLUGIN_URL . '/src/GridGallery/Galleries/assets/css/grid-gallery.galleries.list.css',
        $this->getLocationUrl() . '/assets/css/gallery-groups.css',
      ]);
      $ui->asset->enqueue('scripts', [
        $this->getLocationUrl() . '/assets/js/gallery-groups.js',
      ]);
    }
  }

  public function ajaxSearchGalleries()
  {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => __('Access denied.', 'sgg')], 403);
    }

    check_ajax_referer('supsystic-gallery', 'nonce');

    global $wpdb;

    $term = isset($_REQUEST['term']) ? sanitize_text_field(wp_unslash($_REQUEST['term'])) : '';
    $where = '';
    $args = [];

    if ($term !== '') {
      $where = ' WHERE title LIKE %s';
      $args[] = '%' . $wpdb->esc_like($term) . '%';
    }

    $query = "SELECT id, title FROM `{$wpdb->prefix}gg_galleries`{$where} ORDER BY title ASC LIMIT 20";
    $rows = $args
      ? $wpdb->get_results($wpdb->prepare($query, $args))
      : $wpdb->get_results($query);

    $items = [];
    foreach ((array) $rows as $row) {
      $items[] = [
        'id' => (int) $row->id,
        'text' => '#' . (int) $row->id . ' ' . $row->title,
      ];
    }

    wp_send_json_success(['items' => $items]);
  }

  public function ajaxSearchGroups()
  {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => __('Access denied.', 'sgg')], 403);
    }

    check_ajax_referer('supsystic-gallery', 'nonce');

    global $wpdb;

    $term = isset($_REQUEST['term']) ? sanitize_text_field(wp_unslash($_REQUEST['term'])) : '';
    $activeOnly = !empty($_REQUEST['active_only']);
    $where = [];
    $args = [];

    if ($term !== '') {
      $where[] = 'name LIKE %s';
      $args[] = '%' . $wpdb->esc_like($term) . '%';
    }
    if ($activeOnly) {
      $where[] = 'active = 1';
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $query = "SELECT group_id, name FROM `{$wpdb->prefix}gg_gallery_groups`{$whereSql} ORDER BY name ASC LIMIT 20";
    $rows = $args
      ? $wpdb->get_results($wpdb->prepare($query, $args))
      : $wpdb->get_results($query);

    $items = [];
    foreach ((array) $rows as $row) {
      $items[] = [
        'id' => (int) $row->group_id,
        'text' => $row->name,
      ];
    }

    wp_send_json_success(['items' => $items]);
  }
}

require_once 'Model/widget.php';
