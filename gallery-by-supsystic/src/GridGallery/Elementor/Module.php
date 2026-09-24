<?php

/**
 * Registers the "Photo Gallery by Supsystic" and "Gallery Group by
 * Supsystic" Elementor widgets, so a gallery (or a whole gallery group) can
 * be picked from a dropdown and previewed live in the Elementor editor
 * instead of only being insertable as a raw shortcode.
 */
class GridGallery_Elementor_Module extends RscSgg_Mvc_Module
{
  private $widgetRegistered = false;
  private $groupWidgetRegistered = false;

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, '3.5.0', '<')) {
      add_action('elementor/widgets/widgets_registered', [$this, 'registerWidget']);
      add_action('elementor/widgets/widgets_registered', [$this, 'registerGroupWidget']);
    } else {
      add_action('elementor/widgets/register', [$this, 'registerWidget']);
      add_action('elementor/widgets/register', [$this, 'registerGroupWidget']);
    }

    if ($this->isElementorRenderContext()) {
      // The gallery's own display CSS/JS is only wp_enqueue_style()'d from
      // inside the shortcode callback (Galleries::getGallery()), which runs
      // while the_content() is rendered - after wp_head() already printed
      // the page's <link> tags. That's fine for regular pages that also
      // load the same assets on every page load some other way, but
      // Elementor's editor preview iframe renders through the same
      // front-end template with nothing else pulling those assets in, so
      // the late enqueue never makes it into <head>. Force the same
      // registration/enqueue eagerly, before wp_head fires, priority 20 so
      // it runs after GridGallery_Ui_Assets::enqueueFrontend() (priority
      // 10) has registered the style/script handles.
      add_action('wp_enqueue_scripts', [$this, 'forceLoadAssets'], 20);
    }
  }

  /**
   * @return bool Whether the current request is Elementor rendering/editing
   * this page (the editor's own admin screen, its preview iframe, or an
   * elementor_ajax widget-render request).
   */
  private function isElementorRenderContext()
  {
    $ajaxAction = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    $pageAction = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

    return $ajaxAction === 'elementor_ajax' || $pageAction === 'elementor' || isset($_GET['elementor-preview']);
  }

  public function forceLoadAssets()
  {
    $galleries = $this->getModule('galleries');
    if ($galleries) {
      $galleries->loadFrontendAssets();
    }
  }

  /**
   * @param \Elementor\Widgets_Manager|null $widgetsManager
   */
  public function registerWidget($widgetsManager = null)
  {
    if ($this->widgetRegistered) {
      return;
    }

    if (!did_action('elementor/loaded') || !class_exists('\Elementor\Widget_Base')) {
      return;
    }

    $widgetFile = $this->getLocation() . '/elementor/widget.php';
    if (!class_exists('\Elementor\Widget_Supsystic_Gallery') && is_file($widgetFile)) {
      require_once $widgetFile;
    }

    if (!class_exists('\Elementor\Widget_Supsystic_Gallery')) {
      return;
    }

    $this->widgetRegistered = true;
    $this->registerElementorWidget($widgetsManager, new \Elementor\Widget_Supsystic_Gallery());
  }

  /**
   * @param \Elementor\Widgets_Manager|null $widgetsManager
   */
  public function registerGroupWidget($widgetsManager = null)
  {
    if ($this->groupWidgetRegistered) {
      return;
    }

    if (!did_action('elementor/loaded') || !class_exists('\Elementor\Widget_Base')) {
      return;
    }

    $widgetFile = $this->getLocation() . '/elementor/group-widget.php';
    if (!class_exists('\Elementor\Widget_Supsystic_Gallery_Group') && is_file($widgetFile)) {
      require_once $widgetFile;
    }

    if (!class_exists('\Elementor\Widget_Supsystic_Gallery_Group')) {
      return;
    }

    $this->groupWidgetRegistered = true;
    $this->registerElementorWidget($widgetsManager, new \Elementor\Widget_Supsystic_Gallery_Group());
  }

  /**
   * @param \Elementor\Widgets_Manager|null $widgetsManager
   * @param \Elementor\Widget_Base $widget
   */
  private function registerElementorWidget($widgetsManager, $widget)
  {
    if ($widgetsManager && method_exists($widgetsManager, 'register')) {
      $widgetsManager->register($widget);
    } elseif ($widgetsManager && method_exists($widgetsManager, 'register_widget_type')) {
      $widgetsManager->register_widget_type($widget);
    } elseif (isset(\Elementor\Plugin::$instance->widgets_manager)) {
      $manager = \Elementor\Plugin::$instance->widgets_manager;
      method_exists($manager, 'register') ? $manager->register($widget) : $manager->register_widget_type($widget);
    }
  }
}
