<?php

/**
 * Carousel: a gallery type of its own, chosen when a gallery is created. It is also the shared base of the Slider
 * type (GridGallery_Slider_Module extends it), and PRO extends both with its own module classes.
 *
 * A gallery of this type is an ordinary gallery record: its settings set keeps the type in "kind" and the type's
 * options in "slider". A gallery without "kind" is a classic gallery and none of this code touches it. The slides
 * are the photos of the gallery or WooCommerce products. Front end: Swiper 14 and PhotoSwipe 5 (both MIT), bundled
 * in assets/vendor.
 */
class GridGallery_Carousel_Module extends RscSgg_Mvc_Module
{
  /** The types handled by this module and its subclasses; each one is also the name of its module */
  const TYPES = ['carousel', 'slider'];
  const PRO_EFFECTS = ['coverflow', 'cube', 'flip', 'cards', 'creative'];
  const PRO_SOURCES = ['posts'];
  /** Options this version keeps for PRO: the vertical direction; leaving out products, taking products only from
   * some categories and leaving out categories */
  const PRO_FEATURES = ['vertical', 'productFilters'];

  /** Device widths: below TABLET is a phone, below DESKTOP a tablet. The width of the slider decides, not the screen. */
  const TABLET = 768;
  const DESKTOP = 1024;

  /**
   * The values a setting may take, by its path.
   * @var array
   */
  protected $choices = [
    'content.source' => ['images', 'products', 'posts'],
    'layout.widthUnit' => ['%', 'px'],
    'layout.align' => ['center', 'left', 'right'],
    'layout.heightMode' => ['ratio', 'fixed', 'viewport', 'auto'],
    'layout.ratio' => ['16:9', '21:9', '3:2', '4:3', '1:1', '3:4', '2:3', '9:16'],
    'layout.fit' => ['cover', 'contain'],
    'motion.effect' => ['slide', 'fade', 'coverflow', 'cube', 'flip', 'cards', 'creative'],
    'motion.direction' => ['horizontal', 'vertical'],
    'nav.arrowStyle' => ['circle', 'square', 'minimal', 'bar'],
    'nav.arrowPos' => ['inside', 'outside'],
    'nav.dotsType' => ['bullets', 'dynamic', 'progressbar', 'fraction'],
    'nav.dotsPos' => ['below', 'inside'],
    'captions.mode' => ['overlay', 'below', 'hover', 'none'],
    'captions.align' => ['left', 'center', 'right'],
    'style.preset' => ['clean', 'soft', 'framed', 'dark', 'minimal'],
    'style.shadow' => ['none', 'soft', 'strong'],
    'click.action' => ['lightbox', 'link', 'none'],
    // The same values as GridGallery_Lightbox_Module
    'lightbox.theme' => ['style', 'dark', 'light', 'glass', 'minimal', 'side', 'filmstrip', 'polaroid', 'cinema'],
    'lightbox.arrows' => ['circle', 'square', 'minimal', 'none'],
    'lightbox.caption' => ['auto', 'below', 'overlay', 'side', 'none'],
    'lightbox.anim' => ['zoom', 'fade', 'none'],
    'products.orderby' => ['date', 'title', 'price', 'popularity', 'rating', 'menu_order', 'rand', 'include'],
    'products.order' => ['DESC', 'ASC'],
    'posts.orderby' => ['date', 'modified', 'title', 'comment_count', 'menu_order', 'rand', 'include'],
    'posts.order' => ['DESC', 'ASC'],
  ];

  /**
   * Allowed ranges of the numbers, by path; "*" stands for a device key.
   * @var array
   */
  protected $ranges = [
    'layout.width' => [10, 4000],
    'layout.maxWidth' => [0, 4000],
    'layout.height.*' => [80, 2000],
    'layout.vh' => [10, 100],
    'responsive.perView.*' => [1, 8],
    'responsive.gap.*' => [0, 120],
    'motion.speed' => [100, 5000],
    'motion.delay' => [800, 30000],
    'motion.marqueeSpeed' => [1000, 30000],
    'style.radius' => [0, 60],
    'products.limit' => [1, 60],
    'posts.limit' => [1, 60],
    'posts.excerptLength' => [0, 80],
    'lightbox.delay' => [2, 30],
  ];

  public function onInit()
  {
    parent::onInit();

    if (is_admin() && $this->getEnvironment()->isPluginPage()) {
      // After WooCommerce has registered its admin scripts, which the product pickers use
      add_action('admin_enqueue_scripts', [$this, 'enqueueEditorAssets'], 20);
    }
  }

  // ---------------------------------------------------------------- the type

  /**
   * The type of this module.
   * @return string
   */
  public function type()
  {
    return 'carousel';
  }

  /**
   * @param array $data The settings set of a gallery
   * @return string carousel, slider or gallery
   */
  public static function typeOf($data)
  {
    return is_array($data) && isset($data['kind']) && in_array($data['kind'], self::TYPES, true) ? $data['kind'] : 'gallery';
  }

  /**
   * @param int $galleryId
   * @return string
   */
  public function getTypeOf($galleryId)
  {
    $stored = $this->settingsModel()->get((int) $galleryId);

    return self::typeOf($stored && is_array($stored->data) ? $stored->data : []);
  }

  /**
   * Makes a just created gallery one of this type. The classic settings stay in the set unused, so the rest of the
   * plugin keeps working with the record as with any gallery.
   * @param int $galleryId
   * @return bool
   */
  public function createType($galleryId)
  {
    $model = $this->settingsModel();
    $stored = $model->get((int) $galleryId);
    $data = $stored && is_array($stored->data) ? $stored->data : [];
    $data['kind'] = $this->type();
    $data['slider'] = $this->getDefaults();
    $model->save((int) $galleryId, $data);

    return true;
  }

  /**
   * The name of the type for headings and lists.
   * @return string
   */
  public function getLabel()
  {
    return $this->getEnvironment()->translate('Carousel');
  }

  // ---------------------------------------------------------------- settings

  /**
   * Default settings. Per device values use the keys d (desktop), t (tablet) and m (phone).
   * @return array
   */
  public function getDefaults()
  {
    return [
      'content' => [
        'source' => 'images',
      ],
      'layout' => [
        'width' => 100,
        'widthUnit' => '%',
        'maxWidth' => 0,
        'align' => 'center',
        'heightMode' => 'ratio',
        'ratio' => '4:3',
        'height' => ['d' => 420, 't' => 360, 'm' => 280],
        'vh' => 70,
        'fit' => 'cover',
      ],
      'responsive' => [
        'perView' => ['d' => 3, 't' => 2, 'm' => 1],
        'gap' => ['d' => 20, 't' => 16, 'm' => 12],
      ],
      'motion' => [
        'effect' => 'slide',
        'speed' => 600,
        'loop' => 1,
        'autoplay' => 0,
        'delay' => 4000,
        'pauseOnHover' => 1,
        'centered' => 0,
        'freeMode' => 0,
        'direction' => 'horizontal',
        'keyboard' => 1,
        'mousewheel' => 0,
        'grabCursor' => 1,
        // Running line: the slides move on without stops, for logos and the like (Carousel only)
        'marquee' => 0,
        'marqueeSpeed' => 5000,
        'marqueeReverse' => 0,
      ],
      'nav' => [
        'arrows' => ['d' => 1, 't' => 1, 'm' => 0],
        'arrowStyle' => 'circle',
        'arrowPos' => 'inside',
        'dots' => ['d' => 1, 't' => 1, 'm' => 1],
        'dotsType' => 'bullets',
        'dotsPos' => 'below',
      ],
      'captions' => [
        'mode' => 'below',
        'title' => 1,
        'text' => 0,
        'align' => 'left',
      ],
      'style' => [
        'preset' => 'clean',
        'radius' => 12,
        'shadow' => 'soft',
        'accent' => '#377dff',
        'arrowBg' => '#ffffff',
        'arrowColor' => '#0f172a',
        'captionBg' => '#0f172a',
        'captionColor' => '#ffffff',
        'grayscale' => 0,
        // The style of the Style library the look comes from, '' for an own look
        'theme' => '',
      ],
      'click' => [
        'action' => 'lightbox',
      ],
      // The lightbox the images open in (Lightbox module). Its colors come from the style below; the thumbnails,
      // slideshow, download and share are PRO.
      'lightbox' => [
        'theme' => 'style',
        'arrows' => 'circle',
        'caption' => 'auto',
        'anim' => 'zoom',
        'counter' => 1,
        'zoom' => 1,
        'fullscreen' => 1,
        'thumbs' => 0,
        'slideshow' => 0,
        'delay' => 4,
        'download' => 0,
        'share' => 0,
      ],
      'products' => [
        'include' => [],
        'exclude' => [],
        'catsInclude' => [],
        'catsExclude' => [],
        'limit' => 12,
        'orderby' => 'date',
        'order' => 'DESC',
        'onlyInStock' => 1,
        'onlyOnSale' => 0,
        'onlyFeatured' => 0,
        'showTitle' => 1,
        'showPrice' => 1,
        'showRating' => 1,
        'showSale' => 1,
        'showButton' => 1,
        // 1: the cards keep the shop look of the theme (buttons, prices); 0: the palette of the style colors them
        'themeStyle' => 0,
      ],
      // Posts of the site (PRO): any public post type, chosen posts and categories in or out
      'posts' => [
        'postType' => 'post',
        'include' => [],
        'exclude' => [],
        'catsInclude' => [],
        'catsExclude' => [],
        'limit' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
        'showDate' => 1,
        'excerptLength' => 20,
      ],
    ];
  }

  /**
   * Effects this version keeps for PRO. The PRO module returns none while its license is active.
   * @return string[]
   */
  public function lockedEffects()
  {
    return self::PRO_EFFECTS;
  }

  /**
   * Slide sources this version keeps for PRO. The PRO module returns none while its license is active.
   * @return string[]
   */
  public function lockedSources()
  {
    return self::PRO_SOURCES;
  }

  /**
   * Options this version keeps for PRO (PRO_FEATURES). The PRO module returns none while its license is active.
   * @return string[]
   */
  public function lockedFeatures()
  {
    return self::PRO_FEATURES;
  }

  /**
   * Post types a slider can take posts from: the public ones, without media and products (products have a source
   * of their own).
   * @return array name => label
   */
  public function postTypes()
  {
    $types = [];
    foreach (get_post_types(['public' => true], 'objects') as $name => $object) {
      if (!in_array($name, ['attachment', 'product'], true)) {
        $types[$name] = $object->labels->singular_name;
      }
    }

    return $types;
  }

  /**
   * The settings as they are used: the defaults with the saved values over them, locked options back to free values.
   * @param array $data The settings set of the gallery
   * @return array
   */
  public function resolve($data)
  {
    $saved = is_array($data) && isset($data['slider']) && is_array($data['slider']) ? $data['slider'] : [];

    return $this->prepare($saved);
  }

  /**
   * Checks settings from the editor or the database: the known options, then what a type adds, then the license.
   * @param array $input
   * @return array
   */
  protected function prepare(array $input)
  {
    return $this->limit($this->extra($this->clean($this->getDefaults(), $input), $input));
  }

  /**
   * Settings a type keeps outside the fixed options, such as the layers of the Slider.
   * @param array $settings Checked options
   * @param array $input Raw input
   * @return array
   */
  protected function extra(array $settings, array $input)
  {
    return $settings;
  }

  /**
   * @param array $settings
   * @return array
   */
  protected function limit(array $settings)
  {
    if (in_array($settings['motion']['effect'], $this->lockedEffects(), true)) {
      $settings['motion']['effect'] = 'slide';
    }
    // Products stay the source while WooCommerce is off: the carousel shows nothing until it is back (renderShortcode)
    if (in_array($settings['content']['source'], $this->lockedSources(), true)) {
      $settings['content']['source'] = 'images';
    }
    if (!array_key_exists($settings['posts']['postType'], $this->postTypes())) {
      $settings['posts']['postType'] = 'post';
    }
    // The running line moves sideways only, in an endless line of plain slides
    if ($settings['motion']['marquee'] && $this->type() === 'carousel') {
      $settings['motion']['effect'] = 'slide';
      $settings['motion']['direction'] = 'horizontal';
      $settings['motion']['loop'] = 1;
      $settings['motion']['freeMode'] = 0;
      $settings['motion']['centered'] = 0;
    } else {
      $settings['motion']['marquee'] = 0;
    }
    $features = $this->lockedFeatures();
    if (in_array('vertical', $features, true)) {
      $settings['motion']['direction'] = 'horizontal';
    }
    if (in_array('productFilters', $features, true)) {
      $settings['products']['exclude'] = [];
      $settings['products']['catsInclude'] = [];
      $settings['products']['catsExclude'] = [];
    }
    // A post slide always leads to its post
    if ($settings['content']['source'] === 'posts') {
      $settings['click']['action'] = 'link';
    }

    return $settings;
  }

  /**
   * Saves the settings from the editor: only the known keys, each value checked against its type, choices and
   * range. The classic gallery settings in the same set stay as they are.
   * @param int $galleryId
   * @param array $input
   * @return array|null The saved settings, or null when the gallery is not of this type
   */
  public function saveSettings($galleryId, $input)
  {
    $model = $this->settingsModel();
    $stored = $model->get((int) $galleryId);
    $data = $stored && is_array($stored->data) ? $stored->data : null;
    if ($data === null || self::typeOf($data) !== $this->type()) {
      return null;
    }
    $data['slider'] = $this->prepare(is_array($input) ? $input : []);
    $model->save((int) $galleryId, $data);

    $galleries = $this->getEnvironment()->getModule('galleries');
    if ($galleries && method_exists($galleries, 'cleanCache')) {
      $galleries->cleanCache((int) $galleryId);
    }

    return $data['slider'];
  }

  /**
   * Takes from the input only what fits the defaults: the same keys and types, the allowed choices and ranges.
   * Missing or wrong values fall back to the default.
   * @param array $defaults
   * @param mixed $input
   * @param string $path
   * @return array
   */
  protected function clean(array $defaults, $input, $path = '')
  {
    $input = is_array($input) ? $input : [];
    $out = [];
    foreach ($defaults as $key => $default) {
      $current = $path === '' ? (string) $key : $path . '.' . $key;
      $value = array_key_exists($key, $input) ? $input[$key] : null;

      if (is_array($default) && ($default === [] || array_keys($default) === range(0, count($default) - 1))) {
        $out[$key] = $this->idList($value);
      } elseif (is_array($default)) {
        $out[$key] = $this->clean($default, $value, $current);
      } elseif ($value === null || is_array($value)) {
        $out[$key] = $default;
      } elseif (isset($this->choices[$current])) {
        $out[$key] = in_array((string) $value, $this->choices[$current], true) ? (string) $value : $default;
      } elseif (is_int($default)) {
        $out[$key] = $this->number($current, (int) round((float) $value), $default);
      } elseif (is_string($default) && $default !== '' && $default[0] === '#') {
        $out[$key] = $this->color($value, $default);
      } else {
        $out[$key] = sanitize_text_field((string) $value);
      }
    }

    return $out;
  }

  /**
   * @param string $path
   * @param int $value
   * @param int $default
   * @return int
   */
  protected function number($path, $value, $default)
  {
    $device = preg_replace('/\.(d|t|m)$/', '.*', $path);
    $range = isset($this->ranges[$path]) ? $this->ranges[$path] : (isset($this->ranges[$device]) ? $this->ranges[$device] : null);
    if ($range === null) {
      // Switches are 0 or 1
      return $default === 0 || $default === 1 ? ($value ? 1 : 0) : $value;
    }

    return max($range[0], min($range[1], $value));
  }

  /**
   * @param mixed $value
   * @param string $default
   * @return string
   */
  /**
   * White or dark ink, whichever reads better on a color (relative luminance of WCAG).
   * @param string $hex
   * @return string
   */
  public function readableOn($hex)
  {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}/', $hex)) {
      return '#ffffff';
    }
    $light = 0;
    foreach ([0.2126, 0.7152, 0.0722] as $index => $weight) {
      $channel = hexdec(substr($hex, $index * 2, 2)) / 255;
      $light += $weight * ($channel <= 0.03928 ? $channel / 12.92 : pow(($channel + 0.055) / 1.055, 2.4));
    }

    // The point where white and #0f172a give the same contrast
    return $light > 0.2 ? '#0f172a' : '#ffffff';
  }

  protected function color($value, $default)
  {
    $value = trim((string) $value);

    return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) ? strtolower($value) : $default;
  }

  /**
   * @param mixed $value
   * @return int[]
   */
  protected function idList($value)
  {
    if (is_string($value)) {
      $value = preg_split('/[\s,]+/', $value);
    }
    if (!is_array($value)) {
      return [];
    }
    $ids = [];
    foreach ($value as $id) {
      $id = (int) $id;
      if ($id > 0 && !in_array($id, $ids, true)) {
        $ids[] = $id;
      }
    }

    return array_slice($ids, 0, 200);
  }

  // ---------------------------------------------------------------- front end

  /**
   * Output of the gallery shortcode for a gallery of this type, or null for any other gallery.
   * @param array $attributes
   * @param array|null $override Settings to show instead of the saved ones (the editor's live preview)
   * @return string|null
   */
  public function renderShortcode($attributes, $override = null)
  {
    $galleryId = is_array($attributes) && isset($attributes['id']) ? (int) $attributes['id'] : 0;
    if ($galleryId < 1) {
      return null;
    }
    $stored = $this->settingsModel()->get($galleryId);
    $data = $stored && is_array($stored->data) ? $stored->data : [];
    if (self::typeOf($data) !== $this->type()) {
      return null;
    }
    if (is_array($override)) {
      $data['slider'] = $override;
    }
    $settings = $this->resolve($data);
    $source = $settings['content']['source'];
    $products = $source === 'products';
    if ($products) {
      $slides = $this->productSlides($settings);
    } elseif ($source === 'posts') {
      $slides = $this->postSlides($settings);
    } else {
      $slides = $this->photoSlides($galleryId);
    }
    $images = $source === 'images';
    // Products while WooCommerce is off: visitors see nothing, the people who edit the site a note
    $wooMissing = $products && !function_exists('wc_get_product');
    if ($wooMissing && !current_user_can('edit_posts')) {
      return '';
    }

    $this->enqueueFrontAssets($products, $settings['click']['action'] === 'lightbox' && $images);

    return $this->getEnvironment()->getTwig()->render('@carousel/shortcode.twig', [
      'uid' => 'sgg-sl-' . $galleryId . '-' . wp_rand(1000, 999999),
      'title' => $this->galleryTitle($galleryId),
      'galleryId' => $galleryId,
      'type' => $this->type(),
      'products' => $products,
      'wooMissing' => $wooMissing,
      'posts' => $source === 'posts',
      'settings' => $settings,
      'config' => wp_json_encode($this->frontConfig($settings)),
      'cssVars' => $this->cssVars($settings),
      'slides' => $slides,
      'layers' => $images ? $this->frontLayers($settings) : [],
      'extras' => $images ? $this->frontExtras($settings) : [],
      'thumbs' => $this->hasThumbs($settings),
    ]);
  }

  /**
   * Settings of each slide (phone image, Before / After, video), by photo id, ready for the markup. The Slider type
   * has them.
   * @param array $settings
   * @return array
   */
  protected function frontExtras(array $settings)
  {
    return [];
  }

  /**
   * Whether thumbnails go under the slides. The Slider type has them.
   * @param array $settings
   * @return bool
   */
  public function hasThumbs(array $settings)
  {
    return false;
  }

  /**
   * Layers over the slides, by photo id, ready for the markup. The Slider type has them.
   * @param array $settings
   * @return array
   */
  protected function frontLayers(array $settings)
  {
    return [];
  }

  /**
   * What the front script needs; the Swiper options are built from it in the browser, so the editor's live preview
   * runs the very same code.
   * @param array $settings
   * @return array
   */
  public function frontConfig(array $settings)
  {
    $lightbox = $settings['click']['action'] === 'lightbox' && $settings['content']['source'] === 'images';
    $module = $lightbox ? $this->getEnvironment()->getModule('lightbox') : null;

    return [
      'type' => $this->type(),
      'breakpoints' => ['t' => self::TABLET, 'd' => self::DESKTOP],
      'responsive' => $settings['responsive'],
      'motion' => $settings['motion'],
      'nav' => $settings['nav'],
      'layout' => ['heightMode' => $settings['layout']['heightMode']],
      'lightbox' => $lightbox,
      // The theme, colors and tools of the lightbox; without the module PhotoSwipe opens as it is
      'lightboxOptions' => $module ? $module->typeConfig($settings) : null,
    ];
  }

  /**
   * CSS variables of the slider, so the first paint already has the right size and colors.
   * @param array $settings
   * @return string
   */
  public function cssVars(array $settings)
  {
    $layout = $settings['layout'];
    $style = $settings['style'];
    list($rw, $rh) = array_map('floatval', explode(':', $layout['ratio']));
    $vars = [
      '--sgg-sl-width' => $layout['widthUnit'] === 'px' ? $layout['width'] . 'px' : min(100, $layout['width']) . '%',
      '--sgg-sl-max' => $layout['maxWidth'] > 0 ? $layout['maxWidth'] . 'px' : 'none',
      '--sgg-sl-ratio' => $rw > 0 && $rh > 0 ? $rw . ' / ' . $rh : '4 / 3',
      '--sgg-sl-h-d' => $layout['height']['d'] . 'px',
      '--sgg-sl-h-t' => $layout['height']['t'] . 'px',
      '--sgg-sl-h-m' => $layout['height']['m'] . 'px',
      '--sgg-sl-vh' => $layout['vh'] . 'vh',
      // The same share as a number: the phone preview of the editor takes it from its own screen height
      '--sgg-sl-vhn' => (int) $layout['vh'],
      '--sgg-sl-fit' => $layout['fit'],
      '--sgg-sl-radius' => $style['radius'] . 'px',
      '--sgg-sl-accent' => $style['accent'],
      // Text that reads on the accent: buttons and badges of the palette
      '--sgg-sl-on-accent' => $this->readableOn($style['accent']),
      '--sgg-sl-arrow-bg' => $style['arrowBg'],
      '--sgg-sl-arrow-color' => $style['arrowColor'],
      // The surface and text of the palette (the arrows use them too): cards of products and posts
      '--sgg-sl-surface' => $style['arrowBg'],
      '--sgg-sl-text' => $style['arrowColor'],
      '--sgg-sl-caption-bg' => $style['captionBg'],
      '--sgg-sl-caption-color' => $style['captionColor'],
    ];
    $css = '';
    foreach ($vars as $name => $value) {
      $css .= $name . ':' . $value . ';';
    }

    return $css;
  }

  /**
   * Slides from the photos of the gallery. Photos the Ecommerce module keeps locked are left out, and protected
   * photos are shown only through their protected address.
   * @param int $galleryId
   * @return array
   */
  protected function photoSlides($galleryId)
  {
    $galleries = $this->getEnvironment()->getModule('galleries');
    if (!$galleries) {
      return [];
    }
    $gallery = $galleries->getModel('galleries')->getById($galleryId);
    if (!$gallery || empty($gallery->photos) || !is_array($gallery->photos)) {
      return [];
    }
    if (method_exists($galleries, 'applyEcommerceAccess')) {
      $galleries->applyEcommerceAccess($gallery);
    }

    $slides = [];
    foreach ($gallery->photos as $photo) {
      $attachment = isset($photo->attachment) ? (array) $photo->attachment : [];
      $attachmentId = isset($attachment['id']) ? (int) $attachment['id'] : 0;
      $state = isset($photo->ecommerce) ? $photo->ecommerce : null;
      if ($state && !empty($state->locked)) {
        continue;
      }

      $src = isset($attachment['url']) ? (string) $attachment['url'] : '';
      $srcset = '';
      $width = isset($attachment['width']) ? (int) $attachment['width'] : 0;
      $height = isset($attachment['height']) ? (int) $attachment['height'] : 0;
      $full = [$src, $width, $height];
      $thumb = $src;
      if ($state && !empty($state->protected_url)) {
        $src = (string) $state->protected_url;
        $full = [$src, $width, $height];
        $thumb = $src;
      } elseif ($attachmentId && empty($attachment['isRemoteImage'])) {
        $small = wp_get_attachment_image_src($attachmentId, 'medium');
        if ($small) {
          $thumb = $small[0];
        }
        $large = wp_get_attachment_image_src($attachmentId, 'large');
        if ($large) {
          list($src, $width, $height) = $large;
        }
        $srcset = (string) wp_get_attachment_image_srcset($attachmentId, 'large');
        $original = wp_get_attachment_image_src($attachmentId, 'full');
        if ($original) {
          $full = [$original[0], (int) $original[1], (int) $original[2]];
        }
      }
      if ($src === '') {
        continue;
      }
      // The caption of the image, or its title when it has none: the same text the classic gallery's caption shows
      $caption = isset($attachment['caption']) ? trim(wp_strip_all_tags((string) $attachment['caption'])) : '';
      if ($caption === '' && isset($attachment['title'])) {
        $caption = trim(wp_strip_all_tags((string) $attachment['title']));
      }

      $slides[] = [
        'photoId' => isset($photo->id) ? (int) $photo->id : 0,
        'attachmentId' => $attachmentId,
        'src' => $src,
        'thumb' => $thumb,
        'srcset' => $srcset,
        'width' => $width,
        'height' => $height,
        'full' => $full[0],
        'fullWidth' => $full[1] ?: $width,
        'fullHeight' => $full[2] ?: $height,
        'alt' => isset($attachment['alt']) ? (string) $attachment['alt'] : '',
        'title' => $caption,
        // "description" is the SEO title of the image; the visible text is the caption description
        'text' => isset($attachment['captionDescription']) ? wp_strip_all_tags((string) $attachment['captionDescription']) : '',
        'link' => !empty($attachment['external_link']) ? (string) $attachment['external_link'] : '',
        'target' => !empty($attachment['target']) ? '_blank' : '',
      ];
    }

    return $slides;
  }

  /**
   * Slides from WooCommerce products: chosen products, products to leave out, categories to take from and to leave
   * out, hidden products never shown.
   * @param array $settings
   * @return array
   */
  protected function productSlides(array $settings)
  {
    if (!function_exists('wc_get_product')) {
      return [];
    }
    $p = $settings['products'];
    $args = [
      'post_type' => 'product',
      'post_status' => 'publish',
      'posts_per_page' => $p['limit'],
      'ignore_sticky_posts' => true,
      'no_found_rows' => true,
      'tax_query' => ['relation' => 'AND'],
    ];
    if ($p['include']) {
      $args['post__in'] = $p['include'];
    }
    if ($p['exclude']) {
      $args['post__not_in'] = $p['exclude'];
    }
    if ($p['catsInclude']) {
      $args['tax_query'][] = ['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $p['catsInclude'], 'operator' => 'IN'];
    }
    if ($p['catsExclude']) {
      $args['tax_query'][] = ['taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $p['catsExclude'], 'operator' => 'NOT IN'];
    }
    if (function_exists('wc_get_product_visibility_term_ids')) {
      $visibility = wc_get_product_visibility_term_ids();
      $hidden = [];
      if (!empty($visibility['exclude-from-catalog'])) {
        $hidden[] = $visibility['exclude-from-catalog'];
      }
      if ($p['onlyInStock'] && !empty($visibility['outofstock'])) {
        $hidden[] = $visibility['outofstock'];
      }
      if ($hidden) {
        $args['tax_query'][] = ['taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => $hidden, 'operator' => 'NOT IN'];
      }
      if ($p['onlyFeatured'] && !empty($visibility['featured'])) {
        $args['tax_query'][] = ['taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => [$visibility['featured']], 'operator' => 'IN'];
      }
    }
    if ($p['onlyOnSale'] && function_exists('wc_get_product_ids_on_sale')) {
      $onSale = array_map('intval', wc_get_product_ids_on_sale());
      $args['post__in'] = isset($args['post__in']) ? array_values(array_intersect($args['post__in'], $onSale)) : $onSale;
      if (!$args['post__in']) {
        return [];
      }
    }

    switch ($p['orderby']) {
      case 'price':
        $args['meta_key'] = '_price';
        $args['orderby'] = 'meta_value_num';
        break;
      case 'popularity':
        $args['meta_key'] = 'total_sales';
        $args['orderby'] = 'meta_value_num';
        break;
      case 'rating':
        $args['meta_key'] = '_wc_average_rating';
        $args['orderby'] = 'meta_value_num';
        break;
      case 'include':
        $args['orderby'] = !empty($args['post__in']) ? 'post__in' : 'date';
        break;
      default:
        $args['orderby'] = $p['orderby'];
    }
    $args['order'] = $p['order'];

    $query = new WP_Query($args);
    $slides = [];
    foreach ($query->posts as $post) {
      $product = wc_get_product($post);
      if (!$product || !$product->is_visible()) {
        continue;
      }
      $imageId = (int) $product->get_image_id();
      $image = $imageId ? wp_get_attachment_image_src($imageId, 'woocommerce_single') : false;
      $slides[] = [
        'src' => $image ? $image[0] : (function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('woocommerce_single') : ''),
        'srcset' => $imageId ? (string) wp_get_attachment_image_srcset($imageId, 'woocommerce_single') : '',
        'width' => $image ? (int) $image[1] : 0,
        'height' => $image ? (int) $image[2] : 0,
        'alt' => $imageId ? (string) get_post_meta($imageId, '_wp_attachment_image_alt', true) : '',
        'title' => $product->get_name(),
        'link' => $product->get_permalink(),
        'priceHtml' => $p['showPrice'] ? $product->get_price_html() : '',
        'ratingHtml' => $p['showRating'] && function_exists('wc_get_rating_html') ? wc_get_rating_html($product->get_average_rating(), $product->get_rating_count()) : '',
        'onSale' => $p['showSale'] && $product->is_on_sale(),
        'button' => $p['showButton'] ? [
          'url' => $product->add_to_cart_url(),
          'text' => $product->add_to_cart_text(),
          'ajax' => $product->supports('ajax_add_to_cart') && $product->is_purchasable() && $product->is_in_stock(),
          'id' => $product->get_id(),
          'sku' => $product->get_sku(),
        ] : null,
      ];
    }
    wp_reset_postdata();

    return $slides;
  }

  /**
   * Slides from posts of the site: the post type, chosen posts and posts to leave out, categories to take from and
   * to leave out. Only published posts with a featured image and without a password.
   * @param array $settings
   * @return array
   */
  protected function postSlides(array $settings)
  {
    $p = $settings['posts'];
    $type = $p['postType'];
    $args = [
      'post_type' => $type,
      'post_status' => 'publish',
      'posts_per_page' => $p['limit'],
      'ignore_sticky_posts' => true,
      'no_found_rows' => true,
      'has_password' => false,
      'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']],
    ];
    if ($p['include']) {
      $args['post__in'] = $p['include'];
    }
    if ($p['exclude']) {
      $args['post__not_in'] = $p['exclude'];
    }
    if (is_object_in_taxonomy($type, 'category')) {
      $tax = ['relation' => 'AND'];
      if ($p['catsInclude']) {
        $tax[] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => $p['catsInclude'], 'operator' => 'IN'];
      }
      if ($p['catsExclude']) {
        $tax[] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => $p['catsExclude'], 'operator' => 'NOT IN'];
      }
      if (count($tax) > 1) {
        $args['tax_query'] = $tax;
      }
    }
    $args['orderby'] = $p['orderby'] === 'include' ? (!empty($args['post__in']) ? 'post__in' : 'date') : $p['orderby'];
    $args['order'] = $p['order'];

    $query = new WP_Query($args);
    $slides = [];
    foreach ($query->posts as $post) {
      $imageId = (int) get_post_thumbnail_id($post);
      $image = $imageId ? wp_get_attachment_image_src($imageId, 'large') : false;
      if (!$image) {
        continue;
      }
      $small = wp_get_attachment_image_src($imageId, 'medium');
      $title = html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES, 'UTF-8');
      $excerpt = $p['excerptLength'] > 0 ? html_entity_decode(wp_trim_words(wp_strip_all_tags(get_the_excerpt($post)), $p['excerptLength'], '…'), ENT_QUOTES, 'UTF-8') : '';
      $alt = (string) get_post_meta($imageId, '_wp_attachment_image_alt', true);
      $slides[] = [
        'photoId' => 0,
        'src' => $image[0],
        'srcset' => (string) wp_get_attachment_image_srcset($imageId, 'large'),
        'width' => (int) $image[1],
        'height' => (int) $image[2],
        'thumb' => $small ? $small[0] : $image[0],
        'full' => $image[0],
        'fullWidth' => (int) $image[1],
        'fullHeight' => (int) $image[2],
        'alt' => $alt !== '' ? $alt : $title,
        'title' => $title,
        'text' => $excerpt,
        'meta' => $p['showDate'] ? get_the_date('', $post) : '',
        'link' => get_permalink($post),
        'target' => '',
      ];
    }
    wp_reset_postdata();

    return $slides;
  }

  /**
   * The front-end files live with the Carousel module and serve the Slider type too.
   * @return string
   */
  protected function engineUrl()
  {
    return plugin_dir_url(__FILE__) . 'assets';
  }

  /**
   * @param bool $products
   * @param bool $lightbox
   */
  public function enqueueFrontAssets($products = false, $lightbox = false)
  {
    $url = $this->engineUrl();
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-sl-26';
    wp_enqueue_style('sgg-swiper', $url . '/vendor/swiper/swiper-bundle.min.css', [], '14.3.0');
    wp_enqueue_style('sgg-sliders', $url . '/css/sliders.css', ['sgg-swiper'], $version);
    wp_enqueue_script('sgg-swiper', $url . '/vendor/swiper/swiper-bundle.min.js', [], '14.3.0', true);
    $dependencies = ['sgg-swiper'];
    if ($lightbox) {
      wp_enqueue_style('sgg-photoswipe', $url . '/vendor/photoswipe/photoswipe.css', [], '5.4.4');
      wp_enqueue_script('sgg-photoswipe', $url . '/vendor/photoswipe/photoswipe.umd.min.js', [], '5.4.4', true);
      wp_enqueue_script('sgg-photoswipe-lightbox', $url . '/vendor/photoswipe/photoswipe-lightbox.umd.min.js', ['sgg-photoswipe'], '5.4.4', true);
      $dependencies[] = 'sgg-photoswipe-lightbox';
      $module = $this->getEnvironment()->getModule('lightbox');
      if ($module) {
        $module->enqueue();
        $dependencies[] = 'sgg-lightbox';
      }
    }
    wp_enqueue_script('sgg-sliders', $url . '/js/sliders.js', $dependencies, $version, true);
    if ($products && wp_script_is('wc-add-to-cart', 'registered')) {
      wp_enqueue_script('wc-add-to-cart');
    }
  }

  // ---------------------------------------------------------------- editor

  /**
   * The editor template of this type.
   * @return string
   */
  protected function editorTemplate()
  {
    return '@carousel/editor.twig';
  }

  /**
   * The editor page of a gallery of this type.
   * @param int $galleryId
   * @return RscSgg_Http_Response
   */
  public function editorResponse($galleryId)
  {
    $environment = $this->getEnvironment();
    $stored = $this->settingsModel()->get((int) $galleryId);
    $data = $stored && is_array($stored->data) ? $stored->data : [];
    $galleries = $environment->getModule('galleries');
    $gallery = $galleries ? $galleries->getModel('galleries')->getById((int) $galleryId) : null;
    $settings = $this->resolve($data);

    $products = [];
    $categories = [];
    if (function_exists('wc_get_product')) {
      foreach (array_unique(array_merge($settings['products']['include'], $settings['products']['exclude'])) as $productId) {
        $product = wc_get_product($productId);
        if ($product) {
          $products[$productId] = wp_strip_all_tags($product->get_formatted_name());
        }
      }
      $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
      if (is_array($terms)) {
        foreach ($terms as $term) {
          $categories[$term->term_id] = $term->name;
        }
      }
    }

    // Posts and categories already chosen, by id, for the pickers
    $titles = ['posts' => [], 'cats' => []];
    foreach (array_unique(array_merge($settings['posts']['include'], $settings['posts']['exclude'])) as $postId) {
      $titles['posts'][$postId] = html_entity_decode(wp_strip_all_tags(get_the_title($postId)), ENT_QUOTES, 'UTF-8');
    }
    foreach (array_unique(array_merge($settings['posts']['catsInclude'], $settings['posts']['catsExclude'])) as $termId) {
      $term = get_term($termId, 'category');
      if ($term && !is_wp_error($term)) {
        $titles['cats'][$termId] = $term->name;
      }
    }
    $sources = [
      'locked' => $this->lockedSources(),
      'postTypes' => $this->postTypes(),
      'titles' => $titles,
    ];

    return RscSgg_Http_Response::create()->setContent($environment->getTwig()->render($this->editorTemplate(), $this->editorExtra((int) $galleryId, $settings) + [
      'proGuard' => $this->proGuard($data),
      'sources' => $sources,
      'restUrl' => rest_url(),
      'restNonce' => wp_create_nonce('wp_rest'),
      'gallery' => $gallery,
      'galleryId' => (int) $galleryId,
      'type' => $this->type(),
      'typeLabel' => $this->getLabel(),
      'settings' => $settings,
      'locked' => $this->lockedEffects(),
      'hasWoo' => function_exists('wc_get_product'),
      'products' => $products,
      'categories' => $categories,
      'preview' => $this->renderShortcode(['id' => (int) $galleryId]),
      'shortcode' => '[' . $environment->getConfig()->get('shortcode_name') . ' id=' . (int) $galleryId . ']',
      'nonce' => wp_create_nonce('supsystic-gallery'),
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'clock' => $this->phoneClock(),
    ]));
  }

  /**
   * PRO settings saved earlier that a save would reset now (the plugin works as the free version): the editor asks
   * before it saves. Layers, their animations and videos stay saved in any case.
   * @param array $data The settings set of the gallery
   * @return array labels, state (license, plugin or free) and the link that brings PRO back
   */
  protected function proGuard(array $data)
  {
    $raw = isset($data['slider']) && is_array($data['slider']) ? $data['slider'] : [];
    $environment = $this->getEnvironment();
    $labels = [];
    $effect = isset($raw['motion']['effect']) && is_string($raw['motion']['effect']) ? $raw['motion']['effect'] : '';
    if (in_array($effect, $this->lockedEffects(), true)) {
      $labels[] = sprintf($environment->translate('Effect: %s'), ucfirst($effect));
    }
    $source = isset($raw['content']['source']) && is_string($raw['content']['source']) ? $raw['content']['source'] : '';
    if (in_array($source, $this->lockedSources(), true)) {
      $labels[] = $environment->translate('Slides from posts');
    }
    $features = $this->lockedFeatures();
    if (in_array('vertical', $features, true) && isset($raw['motion']['direction']) && $raw['motion']['direction'] === 'vertical') {
      $labels[] = $environment->translate('Vertical direction');
    }
    $chosen = isset($raw['products']) && is_array($raw['products']) ? $raw['products'] : [];
    if (in_array('productFilters', $features, true) && (!empty($chosen['exclude']) || !empty($chosen['catsInclude']) || !empty($chosen['catsExclude']))) {
      $labels[] = $environment->translate('Products left out, categories to take products from and to leave out');
    }
    if (!$labels) {
      return [];
    }
    if ($environment->getModule('license')) {
      return ['labels' => $labels, 'state' => 'license', 'url' => $environment->generateUrl('license')];
    }
    if (is_dir(WP_PLUGIN_DIR . '/supsystic-gallery-pro')) {
      return ['labels' => $labels, 'state' => 'plugin', 'url' => admin_url('plugins.php')];
    }

    return ['labels' => $labels, 'state' => 'free', 'url' => $environment->getProUrl('utm_source=plugin&utm_medium=pro_settings_guard&utm_campaign=gallery')];
  }

  /**
   * What a type adds to its editor template.
   * @param int $galleryId
   * @param array $settings
   * @return array
   */
  protected function editorExtra($galleryId, array $settings)
  {
    return [];
  }

  /**
   * The time of the site for the status bar of the phone preview, as a phone shows it: hours without a leading zero
   * and no am / pm. The offset lets editor.js keep the clock going in the site's time zone.
   * @return array
   */
  protected function phoneClock()
  {
    $twelve = (bool) preg_match('/[gh]/', (string) get_option('time_format'));

    return [
      'text' => wp_date($twelve ? 'g:i' : 'G:i'),
      'offset' => (int) round((float) wp_timezone()->getOffset(new DateTime('now', new DateTimeZone('UTC')))),
      'twelve' => $twelve,
    ];
  }

  public function enqueueEditorAssets()
  {
    $environment = $this->getEnvironment();
    if (!$environment->isModule('galleries', 'settings')) {
      return;
    }
    if (!$this->isOwnEditor()) {
      return;
    }
    $url = $this->engineUrl();
    $version = $environment->getConfig()->get('plugin_version') . '-sl-26';
    $this->enqueueFrontAssets(true, true);
    wp_enqueue_style('sgg-sliders-editor', $url . '/css/editor.css', ['sgg-sliders'], $version);
    wp_enqueue_script('sgg-sliders-editor', $url . '/js/editor.js', ['jquery', 'sgg-sliders'], $version, true);
    if (wp_script_is('wc-enhanced-select', 'registered')) {
      wp_enqueue_script('wc-enhanced-select');
      wp_enqueue_style('woocommerce_admin_styles');
    }
  }

  // ---------------------------------------------------------------- helpers

  /**
   * @param int $galleryId
   * @return string The title of the gallery, for the name of the slider region
   */
  protected function galleryTitle($galleryId)
  {
    $galleries = $this->getEnvironment()->getModule('galleries');
    $gallery = $galleries ? $galleries->getModel('galleries')->getById((int) $galleryId) : null;

    return $gallery && !empty($gallery->title) ? wp_strip_all_tags((string) $gallery->title) : '';
  }

  /**
   * Whether the current admin page is the editor of a gallery of this type.
   * @return bool
   */
  protected function isOwnEditor()
  {
    if (!$this->getEnvironment()->isModule('galleries', 'settings')) {
      return false;
    }
    $galleryId = isset($_GET['gallery_id']) ? (int) $_GET['gallery_id'] : 0;

    return $galleryId > 0 && $this->getTypeOf($galleryId) === $this->type();
  }

  /**
   * @return GridGallery_Galleries_Model_Settings
   */
  protected function settingsModel()
  {
    return new GridGallery_Galleries_Model_Settings();
  }
}
