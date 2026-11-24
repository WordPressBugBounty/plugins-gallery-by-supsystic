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
    add_action('elementor/elements/categories_registered', [$this, 'addSupsysticCategory'], 5);
    add_action('elementor/editor/after_enqueue_styles', [$this, 'printEditorIconStyle']);
    add_action('elementor/editor/after_enqueue_scripts', [$this, 'printEditorLinkScript']);

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
    // Carousel and Slider galleries: Swiper, PhotoSwipe and their script
    $carousel = $this->getModule('carousel');
    if ($carousel && method_exists($carousel, 'enqueueFrontAssets')) {
      $carousel->enqueueFrontAssets(true, true);
    }
  }

  /**
   * The shared "Supsystic" category at the top of the Elementor panel (fix/shared/gutenberg-supsystic-category.md §3).
   * Added once, whichever Supsystic plugin comes first. Elementor has no API for the order, so the category is moved
   * up by reflection; if that fails in some Elementor version, it simply stays where add_category() put it.
   *
   * @param \Elementor\Elements_Manager $elementsManager
   */
  public function addSupsysticCategory($elementsManager)
  {
    if (!is_object($elementsManager) || !method_exists($elementsManager, 'add_category')) {
      return;
    }
    $categories = method_exists($elementsManager, 'get_categories') ? $elementsManager->get_categories() : [];
    if (isset($categories['supsystic'])) {
      return;
    }
    $elementsManager->add_category('supsystic', ['title' => 'Supsystic', 'icon' => 'supsystic-el-icon']);
    try {
      $property = new \ReflectionProperty($elementsManager, 'categories');
      $property->setAccessible(true);
      $all = $property->getValue($elementsManager);
      if (is_array($all) && isset($all['supsystic'])) {
        $property->setValue($elementsManager, ['supsystic' => $all['supsystic']] + $all);
      }
    } catch (\Throwable $e) {
    }
  }

  /**
   * The Supsystic logo as the widgets' panel icon (get_icon() 'supsystic-el-icon'): a mask in the text colour, so it
   * has the colour and size of the other widget icons. The same rule in every Supsystic plugin
   * (fix/shared/builder-blocks-branding.md).
   */
  public function printEditorIconStyle()
  {
    $icon = esc_url(SGG_PLUGIN_URL . 'app/assets/img/supsystic-adminbar.png');
    wp_register_style('sgg-elementor-editor', false, [], $this->getConfig()->get('plugin_version'));
    wp_enqueue_style('sgg-elementor-editor');
    wp_add_inline_style(
      'sgg-elementor-editor',
      '.elementor-element .icon .supsystic-el-icon::before, .supsystic-el-icon::before {'
      . ' content: ""; display: inline-block; width: 1em; height: 1em; vertical-align: middle;'
      . ' background-color: currentColor;'
      . ' -webkit-mask: url("' . $icon . '") center / contain no-repeat;'
      . ' mask: url("' . $icon . '") center / contain no-repeat;'
      . ' }'
    );
  }

  /**
   * "Edit this gallery / gallery group" in the widget panel opens the one picked now: the link is a list of
   * galleries until the editor knows the picked id (widget.php, group-widget.php).
   */
  public function printEditorLinkScript()
  {
    wp_register_script('sgg-elementor-editor', false, ['jquery'], $this->getConfig()->get('plugin_version'), true);
    wp_enqueue_script('sgg-elementor-editor');
    wp_add_inline_script('sgg-elementor-editor', <<<'JS'
(function ($) {
  function currentSettings() {
    try {
      var page = window.elementor && elementor.getPanelView && elementor.getPanelView().getCurrentPageView();
      var model = page && page.model;
      return model && model.get ? model.get('settings') : null;
    } catch (e) {
      return null;
    }
  }
  $(document).on('mouseover focusin mousedown', 'a.sgg-el-edit-link', function () {
    var settings = currentSettings();
    var id = settings && settings.get ? settings.get(this.getAttribute('data-sgg-setting')) : '';
    if (id) {
      this.href = this.getAttribute('data-sgg-edit') + encodeURIComponent(id);
    }
  });
})(jQuery);
JS
    );
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
