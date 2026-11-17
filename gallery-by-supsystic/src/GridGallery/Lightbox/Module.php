<?php

/**
 * The modern lightbox of Photo Gallery for every gallery type: PhotoSwipe 5 (MIT) with themes. A classic gallery opens
 * its images in it when box.type is '3'; the old Colorbox, prettyPhoto and Photobox stay for the values 0 to 2, so a
 * gallery made before keeps its popup. Carousel and Slider open their images in it too.
 *
 * The colors come from the style of the gallery (its palette) unless the gallery has colors of its own. Free has the
 * lightbox itself with three themes; the other themes, the thumbnail strip, the slideshow, downloads and sharing are
 * PRO, the same license check as the PRO styles.
 */
class GridGallery_Lightbox_Module extends RscSgg_Mvc_Module
{
  /** box.type of a classic gallery that uses this lightbox */
  const BOX_TYPE = '3';
  const THEMES = ['style', 'dark', 'light', 'glass', 'minimal', 'side', 'filmstrip', 'polaroid', 'cinema'];
  const FREE_THEMES = ['style', 'dark', 'light'];
  const ARROWS = ['circle', 'square', 'minimal', 'none'];
  const CAPTIONS = ['auto', 'below', 'overlay', 'side', 'none'];
  const ANIMATIONS = ['zoom', 'fade', 'none'];

  /** @var array Backdrop and ink of the themes that have their own; the others take the palette of the style */
  private static $looks = [
    'dark' => ['#0b0f19', '#f8fafc'],
    'light' => ['#f8fafc', '#0f172a'],
    'polaroid' => ['#ece6da', '#3f3a33'],
    'cinema' => ['#000000', '#ffffff'],
  ];

  /** @var array Where the caption goes when the gallery leaves it to the theme */
  private static $captionOf = ['side' => 'side', 'glass' => 'overlay', 'cinema' => 'none'];

  /**
   * The lightbox settings of a classic gallery (lightbox[...] in its settings) and of the Carousel and Slider.
   * colors: 'style' for the palette of the style, 'own' for bg, ui and accent. palette*: the palette the Style library
   * or the creation wizard put there.
   * @return array
   */
  public function defaults()
  {
    return [
      'theme' => 'style',
      'colors' => 'style',
      'bg' => '#0b0f19',
      'ui' => '#f8fafc',
      'accent' => '#0e8a93',
      'paletteBg' => '',
      'paletteUi' => '',
      'paletteAccent' => '',
      'opacity' => 92,
      'arrows' => 'circle',
      'caption' => 'auto',
      'anim' => 'zoom',
      'counter' => 1,
      'zoom' => 1,
      'fullscreen' => 1,
      'thumbs' => 0,
      'share' => 0,
    ];
  }

  /**
   * Whether the PRO parts are open: the same license check as the PRO styles.
   * @return bool
   */
  public function proOpen()
  {
    $styles = $this->getEnvironment()->getModule('styles');

    return $styles && method_exists($styles, 'proOpen') && $styles->proOpen();
  }

  /**
   * The themes for the editors: name, what it looks like, whether it is PRO and locked now.
   * @return array
   */
  public function themes()
  {
    $env = $this->getEnvironment();
    $open = $this->proOpen();
    $list = [
      'style' => [$env->translate('Style colors'), $env->translate('The colors of the gallery style')],
      'dark' => [$env->translate('Dark'), $env->translate('Deep dark backdrop, light controls')],
      'light' => [$env->translate('Light'), $env->translate('Bright backdrop, dark controls')],
      'glass' => [$env->translate('Glass'), $env->translate('The page shows through a blur')],
      'minimal' => [$env->translate('Minimal'), $env->translate('Controls appear when the mouse moves')],
      'side' => [$env->translate('Side caption'), $env->translate('Title and text in a panel beside the photo')],
      'filmstrip' => [$env->translate('Filmstrip'), $env->translate('A strip of thumbnails under the photo')],
      'polaroid' => [$env->translate('Polaroid'), $env->translate('A white frame with the caption under the photo')],
      'cinema' => [$env->translate('Cinema'), $env->translate('Black, edge to edge, nothing but the photo')],
    ];
    $out = [];
    foreach ($list as $id => $item) {
      $pro = !in_array($id, self::FREE_THEMES, true);
      $out[$id] = ['name' => $item[0], 'note' => $item[1], 'pro' => $pro, 'locked' => $pro && !$open];
    }

    return $out;
  }

  /**
   * Front settings of a classic gallery: its lightbox settings, and the popup options it already had that mean the
   * same here (caption text, hidden captions, slideshow and download of PRO).
   * @param array|object $settings
   * @return array
   */
  public function galleryConfig($settings)
  {
    $settings = is_object($settings) && isset($settings->data) ? $settings->data : $settings;
    $settings = is_array($settings) ? $settings : [];
    $lightbox = isset($settings['lightbox']) && is_array($settings['lightbox']) ? $settings['lightbox'] : [];
    $box = isset($settings['box']) && is_array($settings['box']) ? $settings['box'] : [];
    $palette = [
      $this->color(isset($lightbox['paletteBg']) ? $lightbox['paletteBg'] : '', ''),
      $this->color(isset($lightbox['paletteUi']) ? $lightbox['paletteUi'] : '', ''),
      $this->color(isset($lightbox['paletteAccent']) ? $lightbox['paletteAccent'] : '', ''),
    ];

    return $this->config($lightbox, $palette, [
      'hideCaption' => isset($box['captions']) && $box['captions'] === 'on',
      'slideshow' => isset($box['slideshow']) && $box['slideshow'] === 'true',
      'slideshowAuto' => isset($box['slideshowAuto']) && $box['slideshowAuto'] === 'true',
      'delay' => isset($box['slideshowSpeed']) ? (int) $box['slideshowSpeed'] : 0,
      'download' => isset($box['enableDownloadPhotosButton']) && (string) $box['enableDownloadPhotosButton'] === '1',
    ]);
  }

  /**
   * Front settings of a Carousel or Slider: its lightbox settings in the colors of its style.
   * @param array $settings Checked settings of the type
   * @return array
   */
  public function typeConfig(array $settings)
  {
    $lightbox = isset($settings['lightbox']) && is_array($settings['lightbox']) ? $settings['lightbox'] : [];
    $style = isset($settings['style']) && is_array($settings['style']) ? $settings['style'] : [];
    $palette = [
      $this->color(isset($style['captionBg']) ? $style['captionBg'] : '', ''),
      $this->color(isset($style['captionColor']) ? $style['captionColor'] : '', ''),
      $this->color(isset($style['accent']) ? $style['accent'] : '', ''),
    ];

    return $this->config($lightbox, $palette, [
      'hideCaption' => false,
      'slideshow' => !empty($lightbox['slideshow']),
      'slideshowAuto' => false,
      'delay' => isset($lightbox['delay']) ? (int) $lightbox['delay'] * 1000 : 0,
      'download' => !empty($lightbox['download']),
    ]);
  }

  /**
   * @param array $lightbox
   * @param array $palette Backdrop, ink and accent of the style ('' when unknown)
   * @param array $extra
   * @return array
   */
  private function config(array $lightbox, array $palette, array $extra)
  {
    $defaults = $this->defaults();
    $lightbox += $defaults;
    $open = $this->proOpen();
    $env = $this->getEnvironment();

    $theme = in_array($lightbox['theme'], self::THEMES, true) ? $lightbox['theme'] : 'style';
    if (!$open && !in_array($theme, self::FREE_THEMES, true)) {
      $theme = 'style';
    }
    $look = isset(self::$looks[$theme]) ? self::$looks[$theme] : [null, null];
    $bg = $look[0] ?: ($palette[0] !== '' ? $palette[0] : $defaults['bg']);
    $ui = $look[1] ?: ($palette[1] !== '' ? $palette[1] : $this->readableOn($bg));
    $accent = $palette[2] !== '' ? $palette[2] : $defaults['accent'];
    if ($lightbox['colors'] === 'own') {
      $bg = $this->color($lightbox['bg'], $bg);
      $ui = $this->color($lightbox['ui'], $ui);
      $accent = $this->color($lightbox['accent'], $accent);
    }

    $caption = in_array($lightbox['caption'], self::CAPTIONS, true) ? $lightbox['caption'] : 'auto';
    if ($caption === 'auto') {
      $caption = isset(self::$captionOf[$theme]) ? self::$captionOf[$theme] : 'below';
    }
    if ($extra['hideCaption']) {
      $caption = 'none';
    }
    $opacity = max(40, min(100, (int) $lightbox['opacity']));
    if ($theme === 'cinema') {
      $opacity = 100;
    }

    return [
      'theme' => $theme,
      'bg' => $bg,
      'ui' => $ui,
      'accent' => $accent,
      'onAccent' => $this->readableOn($accent),
      'opacity' => $opacity / 100,
      'arrows' => in_array($lightbox['arrows'], self::ARROWS, true) ? $lightbox['arrows'] : 'circle',
      'caption' => $caption,
      'anim' => in_array($lightbox['anim'], self::ANIMATIONS, true) ? $lightbox['anim'] : 'zoom',
      'counter' => !empty($lightbox['counter']),
      'zoom' => !empty($lightbox['zoom']),
      'fullscreen' => !empty($lightbox['fullscreen']),
      // The Filmstrip theme is the strip itself (and PRO)
      'thumbs' => $theme === 'filmstrip' || ($open && !empty($lightbox['thumbs'])),
      'share' => $open && !empty($lightbox['share']),
      'download' => $open && $extra['download'],
      'slideshow' => $open && $extra['slideshow'] ? ['delay' => max(1500, $extra['delay'] ?: 4000), 'auto' => $extra['slideshowAuto']] : false,
      'i18n' => [
        'close' => $env->translate('Close'),
        'zoom' => $env->translate('Zoom'),
        'prev' => $env->translate('Previous'),
        'next' => $env->translate('Next'),
        'fullscreen' => $env->translate('Full screen'),
        'download' => $env->translate('Download'),
        'share' => $env->translate('Share'),
        'copy' => $env->translate('Copy link'),
        'copied' => $env->translate('Link copied'),
        'play' => $env->translate('Play slideshow'),
        'pause' => $env->translate('Pause slideshow'),
        'error' => $env->translate('The image could not be loaded.'),
      ],
    ];
  }

  /**
   * PhotoSwipe (the same files as the Carousel) and the lightbox of the plugin.
   */
  public function enqueue()
  {
    $vendor = plugin_dir_url(dirname(dirname(__FILE__)) . '/Carousel/Module.php') . 'assets/vendor/photoswipe';
    $url = plugin_dir_url(__FILE__) . 'assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-lb-4';
    wp_enqueue_style('sgg-photoswipe', $vendor . '/photoswipe.css', [], '5.4.4');
    wp_enqueue_script('sgg-photoswipe', $vendor . '/photoswipe.umd.min.js', [], '5.4.4', true);
    wp_enqueue_style('sgg-lightbox', $url . '/css/lightbox.css', ['sgg-photoswipe'], $version);
    wp_enqueue_script('sgg-lightbox', $url . '/js/lightbox.js', ['sgg-photoswipe'], $version, true);
  }

  /**
   * @param mixed $value
   * @param string $default
   * @return string
   */
  private function color($value, $default)
  {
    $value = trim((string) $value);

    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
  }

  /**
   * White or dark ink, whichever reads better on a color (relative luminance of WCAG).
   * @param string $hex
   * @return string
   */
  public function readableOn($hex)
  {
    $hex = ltrim((string) $hex, '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
      return '#ffffff';
    }
    $light = 0;
    foreach ([0.2126, 0.7152, 0.0722] as $index => $weight) {
      $channel = hexdec(substr($hex, $index * 2, 2)) / 255;
      $light += $weight * ($channel <= 0.03928 ? $channel / 12.92 : pow(($channel + 0.055) / 1.055, 2.4));
    }

    return $light > 0.2 ? '#0f172a' : '#ffffff';
  }
}
