<?php

/**
 * Asks for a review on WordPress.org once the plugin has done something useful for the site: a gallery went live on a
 * published page, several galleries were built or many photos added, PRO reported its own moment (a video added to a
 * gallery) or, as a fallback, two weeks of use. The card is shown inside the plugin's own pages only, to the users
 * allowed to use the plugin, and it is not shown again once it is answered.
 *
 * PRO (or any add-on) reports its moments with do_action('sgg_review_moment', $type, ['gallery' => $id]).
 */
class GridGallery_Review_Module extends RscSgg_Mvc_Module
{
  const REVIEW_URL = 'https://wordpress.org/support/plugin/gallery-by-supsystic/reviews/#new-post';
  const SUPPORT_URL = 'https://supsystic.com/contact-us/';

  // No card in the first days after install, whatever happens
  const MIN_DAYS = 3;
  // Without any moment the card comes after two weeks of use
  const FALLBACK_DAYS = 14;
  const GALLERIES_MILESTONE = 3;
  const PHOTOS_MILESTONE = 30;
  const SNOOZE_DAYS = 30;
  const MAX_SNOOZES = 3;

  // The option of the old review dialog: ['date' => first visit, 'is_shown' => answered]
  const OLD_OPTION = 'showGalleryRevNotice';

  /**
   * Moments in the order the card picks them: the first one recorded wins.
   * @var string[]
   */
  private $momentTypes = ['live', 'video'];

  /** @var string[] */
  private $shortcodes = ['supsystic-gallery', 'grid-gallery'];

  /** @var array|null */
  private $state;

  /** @var array|null|false False until the card is built for the current request */
  private $card = false;

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    add_filter('do_shortcode_tag', [$this, 'onShortcodeOutput'], 10, 3);
    add_action('wp_after_insert_post', [$this, 'onPostSaved'], 10, 2);
    add_action('sgg_review_moment', [$this, 'recordMoment'], 10, 2);

    if (is_admin()) {
      add_filter('plugin_row_meta', [$this, 'addPluginRowLink'], 10, 2);

      if ($this->getEnvironment()->isPluginPage()) {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        // The plugin layout dispatches "messages" at the top of its content panel
        $this->getEnvironment()->getDispatcher()->on('messages', [$this, 'printCard']);
      }
    }
  }

  /**
   * A gallery is rendered on a published page that a visitor (or the site owner) opens: the gallery is live.
   * Covers the shortcode, the block and the Elementor widget, as they all render the shortcode.
   * @param string|false $output
   * @param string $tag
   * @param array|string $attr
   * @return string|false
   */
  public function onShortcodeOutput($output, $tag, $attr)
  {
    if (!in_array($tag, $this->shortcodes, true)) {
      return $output;
    }
    if (!is_string($output) || !is_array($attr) || empty($attr['id']) || $this->hasMoment('live')) {
      return $output;
    }
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_preview() || is_feed() || is_customize_preview() || isset($_GET['elementor-preview'])) {
      return $output;
    }
    if (!is_singular() || strpos($output, 'grid-gallery') === false) {
      return $output;
    }
    $post = get_queried_object();
    if ($post instanceof WP_Post && $post->post_status === 'publish' && !post_password_required($post)) {
      $this->addMoment('live', ['gallery' => (int) $attr['id'], 'post' => (int) $post->ID]);
    }

    return $output;
  }

  /**
   * A post with a gallery block or shortcode is published: the gallery is live even before anyone opens the page.
   * @param int $postId
   * @param WP_Post $post
   */
  public function onPostSaved($postId, $post)
  {
    if (!$post instanceof WP_Post || $post->post_status !== 'publish' || $this->hasMoment('live')) {
      return;
    }
    if (wp_is_post_revision($postId) || wp_is_post_autosave($postId) || !is_post_type_viewable($post->post_type)) {
      return;
    }
    $galleryId = $this->findGalleryIn((string) $post->post_content);
    if ($galleryId) {
      $this->addMoment('live', ['gallery' => $galleryId, 'post' => (int) $postId]);
    }
  }

  /**
   * Handler of the "sgg_review_moment" action.
   * @param string $type One of the moment types (video)
   * @param array $data ['gallery' => id]
   */
  public function recordMoment($type, $data = [])
  {
    if (!is_string($type) || $type === 'live' || !in_array($type, $this->momentTypes, true) || $this->hasMoment($type)) {
      return;
    }
    $this->addMoment($type, ['gallery' => is_array($data) && isset($data['gallery']) ? (int) $data['gallery'] : 0]);
  }

  /**
   * The card for the current user on the current page, or null when there is nothing to show.
   * @return array|null
   */
  public function getCard()
  {
    if ($this->card !== false) {
      return $this->card;
    }
    $this->card = null;

    $environment = $this->getEnvironment();
    $onCardPage = $environment->isModule('overview') || $environment->isModule('galleries') || $environment->isModule('settings');
    if (!$onCardPage || $environment->isModule('galleries', 'preview') || !apply_filters('sgg_review_request', true)) {
      return null;
    }
    $userId = get_current_user_id();
    if (!$userId || !$this->currentUserCanUsePlugin()) {
      return null;
    }
    $answer = $this->getAnswer($userId);
    if ($answer['status'] === 'rated' || $answer['status'] === 'dismissed') {
      return null;
    }
    if ($answer['status'] === 'snoozed' && $answer['until'] > time()) {
      return null;
    }
    $state = $this->getState();
    if (time() - $state['since'] < self::MIN_DAYS * DAY_IN_SECONDS) {
      return null;
    }
    $moment = $this->pickMoment($state);
    if (!$moment) {
      return null;
    }

    $this->card = array_merge($moment, [
      'reviewUrl' => self::REVIEW_URL,
      'supportUrl' => self::SUPPORT_URL,
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('supsystic-gallery'),
    ]);

    return $this->card;
  }

  public function enqueueAssets()
  {
    if (!$this->getCard()) {
      return;
    }
    $url = $this->getLocationUrl() . '/assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-review-1';

    wp_enqueue_style('sgg-review', $url . '/css/review.css', [], $version);
    wp_enqueue_script('sgg-review', $url . '/js/review.js', [], $version, true);
  }

  public function printCard()
  {
    $card = $this->getCard();
    if (!$card) {
      return;
    }
    // Every value is escaped by the template
    echo $this->getEnvironment()->getTwig()->render('@review/card.twig', ['card' => $card]);
  }

  /**
   * Saves the answer of a user. "rate" and "done" end the requests; "later", "support" and "close" put them off for a
   * month, and the third time (or the first time on a site that answered the old review dialog) for good.
   * @param int $userId
   * @param string $answer
   * @return bool
   */
  public function saveAnswer($userId, $answer)
  {
    $userId = (int) $userId;
    if (!$userId || !is_string($answer)) {
      return false;
    }
    $now = time();

    if ($answer === 'rate' || $answer === 'done') {
      update_user_meta($userId, $this->key(), ['status' => 'rated', 'answer' => $answer, 'time' => $now]);
      return true;
    }
    if ($answer === 'later' || $answer === 'support' || $answer === 'close') {
      $snoozes = $this->getAnswer($userId)['snoozes'] + 1;
      $state = $this->getState();
      $final = $snoozes >= self::MAX_SNOOZES || $state['legacy'];
      update_user_meta($userId, $this->key(), [
        'status' => $final ? 'dismissed' : 'snoozed',
        'answer' => $answer,
        'until' => $now + self::SNOOZE_DAYS * DAY_IN_SECONDS,
        'snoozes' => $snoozes,
        'time' => $now,
      ]);
      return true;
    }

    return false;
  }

  /**
   * "Rate us" link in the plugin's row on the Plugins page.
   * @param array $links
   * @param string $file
   * @return array
   */
  public function addPluginRowLink($links, $file)
  {
    if (!is_array($links) || $file !== plugin_basename($this->getEnvironment()->getPluginPath() . '/index.php')) {
      return $links;
    }
    $links[] = sprintf(
      '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s <span style="color:#ffb900;letter-spacing:1px;" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span></a>',
      esc_url(self::REVIEW_URL),
      esc_attr($this->getEnvironment()->translate('Rate Photo Gallery on WordPress.org')),
      esc_html($this->getEnvironment()->translate('Rate us'))
    );

    return $links;
  }

  /**
   * Site state: when the plugin started to be used, the recorded moments and whether the old review dialog was answered.
   * @return array
   */
  public function getState()
  {
    if ($this->state !== null) {
      return $this->state;
    }
    $state = get_option($this->key());
    if (!is_array($state) || !isset($state['since']) || !is_int($state['since'])) {
      $state = $this->createState();
      update_option($this->key(), $state, true);
    }
    $state['legacy'] = !empty($state['legacy']);
    $state['moments'] = isset($state['moments']) && is_array($state['moments']) ? $state['moments'] : [];
    $this->state = $state;

    return $this->state;
  }

  /**
   * @param int $userId
   * @return array ['status' => ''|'snoozed'|'rated'|'dismissed', 'until' => int, 'snoozes' => int]
   */
  public function getAnswer($userId)
  {
    $answer = get_user_meta((int) $userId, $this->key(), true);
    $answer = is_array($answer) ? $answer : [];

    return [
      'status' => isset($answer['status']) && is_string($answer['status']) ? $answer['status'] : '',
      'until' => isset($answer['until']) ? (int) $answer['until'] : 0,
      'snoozes' => isset($answer['snoozes']) ? max(0, (int) $answer['snoozes']) : 0,
    ];
  }

  /**
   * The same rule as the plugin's AJAX handler: administrators and the roles given access in the plugin settings.
   * @return bool
   */
  private function currentUserCanUsePlugin()
  {
    if (current_user_can('manage_options')) {
      return true;
    }
    $settings = get_option($this->getEnvironment()->getConfig()->get('db_prefix') . 'settings');
    $roles = is_array($settings) && isset($settings['access_roles']) && is_array($settings['access_roles']) ? $settings['access_roles'] : [];
    $user = wp_get_current_user();

    return !empty($roles) && !empty($user->roles) && array_intersect($user->roles, $roles);
  }

  /**
   * The first state of a site. Existing sites keep their history: the start is the first gallery (or the first visit
   * the old review dialog counted from), and an answered old dialog is remembered.
   * @return array
   */
  private function createState()
  {
    global $wpdb;

    $now = time();
    $since = $now;
    $legacy = false;

    $old = get_option(self::OLD_OPTION);
    if (is_array($old)) {
      if (!empty($old['is_shown'])) {
        $legacy = true;
      }
      $date = isset($old['date']) ? $old['date'] : null;
      if ($date instanceof DateTime) {
        $date = $date->getTimestamp();
      }
      if (is_numeric($date) && (int) $date > 0) {
        $since = min($since, (int) $date);
      }
    }

    $suppress = $wpdb->suppress_errors(true);
    $firstGallery = $wpdb->get_var("SELECT MIN(created) FROM {$wpdb->prefix}gg_galleries");
    $wpdb->suppress_errors($suppress);
    if ($firstGallery && ($firstTime = strtotime($firstGallery)) && $firstTime > 0) {
      $since = min($since, $firstTime);
    }

    return ['since' => (int) $since, 'legacy' => $legacy, 'moments' => []];
  }

  /**
   * @param array $state
   * @return array|null
   */
  private function pickMoment(array $state)
  {
    $environment = $this->getEnvironment();

    foreach ($this->momentTypes as $type) {
      if (empty($state['moments'][$type]) || !is_array($state['moments'][$type])) {
        continue;
      }
      $moment = $state['moments'][$type];
      $galleryId = isset($moment['gallery']) ? (int) $moment['gallery'] : 0;
      $gallery = $this->getGalleryTitle($galleryId);
      if ($gallery === null) {
        // The gallery was deleted since then
        continue;
      }
      $gallery = trim($gallery) !== '' ? trim($gallery) : '#' . $galleryId;

      if ($type === 'live') {
        $post = isset($moment['post']) ? get_post((int) $moment['post']) : null;
        if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
          continue;
        }
        $postTitle = get_the_title($post);

        return [
          'type' => 'live',
          'eyebrow' => $environment->translate('Gallery published'),
          'title' => $postTitle !== '' ? sprintf($environment->translate('“%1$s” is live on “%2$s”'), $gallery, $postTitle) : sprintf($environment->translate('“%s” is live on your site'), $gallery),
          'text' => $environment->translate('Visitors can now browse your photos and open them in the lightbox.'),
          'link' => get_permalink($post),
          'linkText' => $environment->translate('View page'),
        ];
      }

      return [
        'type' => 'video',
        'eyebrow' => $environment->translate('Video gallery'),
        'title' => sprintf($environment->translate('“%s” now plays your videos'), $gallery),
        'text' => $environment->translate('YouTube and Vimeo videos open right in the gallery lightbox.'),
      ];
    }

    // A site that answered the old review dialog is asked once more only after a real success
    if ($state['legacy']) {
      return null;
    }

    $galleries = $this->countRows('gg_galleries');
    if ($galleries >= self::GALLERIES_MILESTONE) {
      return [
        'type' => 'galleries',
        'eyebrow' => $environment->translate('Milestone'),
        'title' => sprintf($environment->translate('You have built %d galleries with Photo Gallery'), $galleries),
        'text' => $environment->translate('Nice work. Your photos look their best.'),
      ];
    }

    $photos = $this->countRows('gg_photos');
    if ($photos >= self::PHOTOS_MILESTONE) {
      return [
        'type' => 'photos',
        'eyebrow' => $environment->translate('Milestone'),
        'title' => sprintf($environment->translate('You have added %d photos to your galleries'), $photos),
        'text' => $environment->translate('Nice work. Your site is getting more visual every day.'),
      ];
    }

    $days = (int) floor((time() - $state['since']) / DAY_IN_SECONDS);
    if ($galleries >= 1 && $days >= self::FALLBACK_DAYS) {
      $weeks = (int) floor($days / 7);

      return [
        'type' => 'time',
        'eyebrow' => $environment->translate('Thank you for using Photo Gallery'),
        'title' => $weeks < 9 ? sprintf($environment->translate('Photo Gallery has been working on your site for %d weeks'), $weeks) : $environment->translate('Photo Gallery has been working on your site for a while now'),
        'text' => $environment->translate('We hope it makes showing your photos a pleasure.'),
      ];
    }

    return null;
  }

  /**
   * @param string $type
   * @return bool
   */
  private function hasMoment($type)
  {
    $state = $this->getState();

    return !empty($state['moments'][$type]);
  }

  /**
   * @param string $type
   * @param array $data
   */
  private function addMoment($type, array $data)
  {
    $state = $this->getState();
    $state['moments'][$type] = array_merge($data, ['time' => time()]);
    $this->state = $state;
    update_option($this->key(), $state, true);
  }

  /**
   * The first gallery in a gallery block or a gallery shortcode of the content.
   * @param string $content
   * @return int
   */
  private function findGalleryIn($content)
  {
    if ($content === '' || (strpos($content, 'supsystic-gallery') === false && strpos($content, 'grid-gallery') === false)) {
      return 0;
    }
    if (preg_match('#<!--\s+wp:' . preg_quote(GridGallery_Gutenberg_Module::BLOCK_NAME, '#') . '\s+(\{.*?\})\s+/?-->#', $content, $match)) {
      $attributes = json_decode($match[1], true);
      if (is_array($attributes) && !empty($attributes['galleryId']) && (int) $attributes['galleryId'] > 0) {
        return (int) $attributes['galleryId'];
      }
    }
    if (preg_match_all('/' . get_shortcode_regex($this->shortcodes) . '/', $content, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $shortcode) {
        $attributes = shortcode_parse_atts($shortcode[3]);
        if (is_array($attributes) && !empty($attributes['id']) && (int) $attributes['id'] > 0) {
          return (int) $attributes['id'];
        }
      }
    }

    return 0;
  }

  /**
   * @param int $id
   * @return string|null Null when there is no such gallery
   */
  private function getGalleryTitle($id)
  {
    global $wpdb;

    if ($id < 1) {
      return null;
    }
    $title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}gg_galleries WHERE id = %d", $id));

    return $title === null ? null : (string) $title;
  }

  /**
   * @param string $table gg_galleries or gg_photos
   * @return int
   */
  private function countRows($table)
  {
    global $wpdb;

    $suppress = $wpdb->suppress_errors(true);
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$table}");
    $wpdb->suppress_errors($suppress);

    return $count;
  }

  /**
   * Name of the site option and of the user meta.
   * @return string
   */
  private function key()
  {
    return $this->getEnvironment()->getConfig()->get('db_prefix') . 'review';
  }
}
