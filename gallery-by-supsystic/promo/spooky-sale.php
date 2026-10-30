<?php

if (!defined('ABSPATH')) {
  exit();
}

if (!class_exists('SupsysticSpookySale2026')) {
  /**
   * "Spooky Sale" popup banner on the admin pages of Supsystic plugins, October 29 - November 9, 2026.
   * Every Supsystic plugin ships a copy of this file: the class is defined once and each plugin registers its own pages.
   * The banner shows at most once a day in each plugin - the browser remembers it in a cookie of that plugin
   * that lives for 24 hours.
   */
  class SupsysticSpookySale2026
  {
    const VERSION = '1.3.0';
    const START_DATE = '2026-10-29';
    const END_DATE = '2026-11-09';
    const COOKIE = 'supsystic_spooky_sale_2026';
    const FONTS_URL = 'https://fonts.googleapis.com/css?family=Creepster|Montserrat:400,600&display=swap';
    const COUPON = 'SUPOOKYSALE30';
    const SHOP_URL = 'https://supsystic.com/';
    const ALL_PLUGINS_URL = 'https://supsystic.com/wordpress-plugins/';
    const UTM_MEDIUM = 'spooky_sale_2026';

    /**
     * Admin page slug prefix => settings of the plugin that owns those pages
     */
    private static $_pages = [];
    /**
     * Settings of the plugin whose page is open, once the banner is due on it
     */
    private static $_current = null;

    /**
     * @param string|array $pagePrefixes Prefixes of the plugin admin page slugs (the "page" query arg)
     * @param string $assetsUrl URL of the plugin's promo folder, with a trailing slash
     * @param array $args proPid - shop product id of the plugin's PRO (Personal, 1 year): "Upgrade to PRO" puts it
     *                    in the cart with the coupon applied and opens the checkout, where the licence and term
     *                    can still be changed; proUrl - the plugin's PRO page, used when there is no proPid;
     *                    isPro - callable, true when PRO is active (checked late, once PRO had a chance to load);
     *                    utmCampaign, utmSource - UTM tags of the links
     */
    public static function register($pagePrefixes, $assetsUrl, $args = [])
    {
      if (empty(self::$_pages)) {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAssets']);
      }
      $settings = array_merge([
        'proPid' => 0,
        'proUrl' => '',
        'isPro' => null,
        'utmCampaign' => '',
        'utmSource' => 'plugin',
      ], $args);
      $settings['assetsUrl'] = $assetsUrl;
      foreach ((array) $pagePrefixes as $prefix) {
        $settings['cookie'] = self::COOKIE . '_' . str_replace('-', '_', $prefix);
        self::$_pages[$prefix] = $settings;
      }
    }

    public static function enqueueAssets()
    {
      self::$_current = self::getCurrentSettings();
      if (empty(self::$_current)) {
        return;
      }
      $assetsUrl = self::$_current['assetsUrl'];
      wp_enqueue_style('supsystic-spooky-sale-fonts', self::FONTS_URL, [], null);
      wp_enqueue_style('supsystic-spooky-sale', $assetsUrl . 'spooky-sale.css', ['supsystic-spooky-sale-fonts'], self::VERSION);
      wp_enqueue_script('supsystic-spooky-sale', $assetsUrl . 'spooky-sale.js', [], self::VERSION, true);
      add_action('admin_footer', [__CLASS__, 'render']);
    }

    /**
     * Settings of the plugin when the banner is due on the current admin page, otherwise null
     */
    private static function getCurrentSettings()
    {
      if (wp_doing_ajax() || defined('IFRAME_REQUEST')) {
        return null;
      }
      $today = current_time('Y-m-d');
      if ($today < self::START_DATE || $today > self::END_DATE) {
        return null;
      }
      // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which screen is open
      $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
      if ($page === '') {
        return null;
      }
      foreach (self::$_pages as $prefix => $settings) {
        if (strpos($page, $prefix) === 0) {
          return empty($_COOKIE[$settings['cookie']]) ? $settings : null;
        }
      }
      return null;
    }

    /**
     * Link of the "Upgrade to PRO" button: the shop checkout with the plugin's PRO in the cart and the coupon applied
     */
    private static function getUpgradeUrl($settings)
    {
      if (empty($settings['proPid'])) {
        return $settings['proUrl'];
      }
      return add_query_arg([
        'mod' => 'user',
        'action' => 'addToCart',
        'pid' => (int) $settings['proPid'],
        'addQty' => 1,
        'goto' => 'checkout',
        'lang' => 'en',
        'coupon' => self::COUPON,
      ], self::SHOP_URL);
    }

    private static function addUtm($url, $settings)
    {
      return add_query_arg([
        'utm_source' => $settings['utmSource'],
        'utm_medium' => self::UTM_MEDIUM,
        'utm_campaign' => $settings['utmCampaign'],
      ], $url);
    }

    public static function render()
    {
      $settings = self::$_current;
      $isPro = is_callable($settings['isPro']) && call_user_func($settings['isPro']);
      $upgradeUrl = $isPro ? '' : self::getUpgradeUrl($settings);
      ?>
      <div id="supsystic-spooky-sale" class="supsystic-spooky-overlay" data-cookie="<?php echo esc_attr($settings['cookie']); ?>" data-cookie-path="<?php echo esc_attr(ADMIN_COOKIE_PATH); ?>" hidden>
        <div class="supsystic-spooky-popup" role="dialog" aria-modal="true" aria-labelledby="supsystic-spooky-title">
          <button type="button" class="supsystic-spooky-close" aria-label="Close">
            <svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="M1.5 1.5l9 9m0-9l-9 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>
          </button>
          <div class="supsystic-spooky-content">
            <div class="supsystic-spooky-eyebrow">Trick or treat</div>
            <div class="supsystic-spooky-headline" id="supsystic-spooky-title">Spooky Sale</div>
            <div class="supsystic-spooky-sub supsystic-spooky-offer">30% off everything.</div>
            <div class="supsystic-spooky-sub supsystic-spooky-label">Your coupon code is:</div>
            <div class="supsystic-spooky-code"><?php echo esc_html(self::COUPON); ?></div>
            <div class="supsystic-spooky-note supsystic-spooky-valid">Valid through November 9</div>
            <div class="supsystic-spooky-actions">
              <?php if (!empty($upgradeUrl)) { ?>
                <a class="supsystic-spooky-btn supsystic-spooky-btn-primary" href="<?php echo esc_url(self::addUtm($upgradeUrl, $settings)); ?>" target="_blank" rel="noopener">Upgrade to PRO</a>
              <?php } ?>
              <a class="supsystic-spooky-btn supsystic-spooky-btn-secondary" href="<?php echo esc_url(self::addUtm(self::ALL_PLUGINS_URL, $settings)); ?>" target="_blank" rel="noopener">All plugins list</a>
            </div>
            <div class="supsystic-spooky-note">No tricks &mdash; just treats.</div>
          </div>
        </div>
      </div>
      <?php
    }
  }
}
