<?php

/**
 * Upsell windows of the free version, made like those of Data Tables: a window for each PRO feature with a small
 * picture of it, what it gives and where it helps, links to the other PRO features and a Get PRO button.
 *
 * Any PRO lock of the plugin opens its window with data-sgg-upsell="<feature>". The windows are <template> elements
 * printed in the footer of the plugin pages; one <dialog> shows them. A lock whose feature has no window keeps its
 * own behaviour (the old "Read more" popup of the classic gallery settings).
 */
class GridGallery_Upsell_Module extends RscSgg_Mvc_Module
{
  public function onInit()
  {
    parent::onInit();

    if (is_admin() && $this->getEnvironment()->isPluginPage()) {
      add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
      add_action('admin_footer', [$this, 'printWindows']);
    }
  }

  public function enqueueAssets()
  {
    $url = plugin_dir_url(__FILE__) . 'assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-up-3';
    wp_enqueue_style('sgg-upsell', $url . '/css/upsell.css', [], $version);
    wp_enqueue_script('sgg-upsell', $url . '/js/upsell.js', [], $version, true);
  }

  public function printWindows()
  {
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes its values.
    echo $this->getEnvironment()->getTwig()->render('@upsell/windows.twig', []);
  }
}
