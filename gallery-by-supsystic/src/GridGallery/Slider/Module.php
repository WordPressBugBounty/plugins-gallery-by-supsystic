<?php

/**
 * Slider: a gallery type of its own, chosen when a gallery is created. One large slide at a time, sized by height
 * per device, captions over the image. It shares the engine of the Carousel type and has its own defaults and
 * editor; PRO extends it with GridGalleryPro_Slider_Module.
 *
 * Each slide can also have an image of its own for phones, a Before / After comparison with a second image and,
 * in PRO, a video. They are kept under "slides" in the slider settings, by photo id.
 *
 * Layers: headings, text, buttons and badges over each image, placed separately for desktop, tablet and phone.
 * They are kept under "layers" in the slider settings, by photo id. Until a tablet or a phone gets values of its
 * own, it follows the bigger device with the text scaled down a little and the layer a little wider.
 */
class GridGallery_Slider_Module extends GridGallery_Carousel_Module
{
  /** Layers on one slide at most, in any version */
  const LAYERS_MAX = 20;

  /** Layers on one slide the free version shows */
  const LAYERS_FREE = 2;

  const LAYER_TYPES = ['heading', 'text', 'button', 'badge'];
  const LAYER_ANIMATIONS = ['none', 'fade', 'up', 'down', 'left', 'right', 'zoom'];
  const LAYER_WEIGHTS = [400, 500, 600, 700, 800];
  const LAYER_ALIGN = ['left', 'center', 'right'];

  const VIDEO_MODES = ['click', 'background'];

  /** How a tablet follows desktop and a phone follows tablet: text size and width factors */
  const LAYER_FOLLOW = ['t' => [0.8, 1.1], 'm' => [0.85, 1.3]];

  public function type()
  {
    return 'slider';
  }

  public function getLabel()
  {
    return $this->getEnvironment()->translate('Slider');
  }

  public function getDefaults()
  {
    $defaults = parent::getDefaults();
    $defaults['layout']['heightMode'] = 'fixed';
    $defaults['layout']['ratio'] = '16:9';
    $defaults['layout']['height'] = ['d' => 560, 't' => 440, 'm' => 320];
    $defaults['responsive']['perView'] = ['d' => 1, 't' => 1, 'm' => 1];
    $defaults['responsive']['gap'] = ['d' => 0, 't' => 0, 'm' => 0];
    $defaults['motion']['autoplay'] = 1;
    $defaults['motion']['delay'] = 5000;
    $defaults['motion']['grabCursor'] = 0;
    $defaults['nav']['arrows'] = ['d' => 1, 't' => 1, 'm' => 1];
    $defaults['nav']['dotsPos'] = 'inside';
    $defaults['captions']['mode'] = 'overlay';
    $defaults['captions']['text'] = 1;
    $defaults['style']['radius'] = 16;
    // Thumbnails under the slider, per device
    $defaults['nav']['thumbs'] = ['d' => 0, 't' => 0, 'm' => 0];
    $defaults['nav']['thumbHeight'] = 72;
    $defaults['nav']['thumbGap'] = 10;

    return $defaults;
  }

  public function onInit()
  {
    parent::onInit();
    $this->ranges['nav.thumbHeight'] = [36, 200];
    $this->ranges['nav.thumbGap'] = [0, 40];
  }

  public function cssVars(array $settings)
  {
    return parent::cssVars($settings)
      . '--sgg-sl-thumb-h:' . (int) $settings['nav']['thumbHeight'] . 'px;'
      . '--sgg-sl-thumb-gap:' . (int) $settings['nav']['thumbGap'] . 'px;';
  }

  public function hasThumbs(array $settings)
  {
    return $settings['content']['source'] !== 'products' && (bool) array_filter($settings['nav']['thumbs']);
  }

  protected function editorTemplate()
  {
    return '@slider/editor.twig';
  }

  // ---------------------------------------------------------------- layers: version limits

  /**
   * Layers on one slide that the site shows. The PRO module raises it while its license is active; layers over the
   * limit stay saved and come back with the license.
   * @return int
   */
  public function layerLimit()
  {
    return self::LAYERS_FREE;
  }

  /**
   * Whether layers play their entrance animations. Open in PRO.
   * @return bool
   */
  public function layerAnimations()
  {
    return false;
  }

  /**
   * Whether slides may play a video. Open in PRO; the links stay saved without it.
   * @return bool
   */
  public function slideVideos()
  {
    return false;
  }

  /**
   * Whether a slide may compare its image with an "after" image (Before / After). Open in PRO; without it the chosen
   * images stay saved and the slide shows its own image only.
   * @return bool
   */
  public function slideCompare()
  {
    return false;
  }

  // ---------------------------------------------------------------- layers: checking

  /**
   * Starting values of a new layer of each type.
   * @param string $type
   * @return array
   */
  public function layerDefaults($type)
  {
    // color '' and bg 'auto' take the palette of the style: the caption color for text, the accent for buttons and
    // badges (with ink that reads on it); bg '' is no background
    $common = ['color' => '', 'bg' => '', 'weight' => 400, 'align' => 'left', 'radius' => 0, 'shadow' => 1, 'anim' => 'none', 'delay' => 0];
    switch ($type) {
      case 'heading':
        return ['weight' => 700, 'd' => $this->box(8, 38, 60, 44)] + $common;
      case 'button':
        return ['bg' => 'auto', 'weight' => 600, 'align' => 'center', 'radius' => 10, 'shadow' => 0, 'd' => $this->box(8, 80, 0, 16)] + $common;
      case 'badge':
        return ['bg' => 'auto', 'weight' => 700, 'align' => 'center', 'radius' => 100, 'shadow' => 0, 'd' => $this->box(8, 10, 0, 13)] + $common;
      default:
        return ['d' => $this->box(8, 62, 50, 18)] + $common;
    }
  }

  /**
   * The layers of the editor's field or of the database, checked: known types and keys only, numbers in range, plain
   * text, safe links and colors.
   * @param array $settings
   * @param array $input
   * @return array
   */
  protected function extra(array $settings, array $input)
  {
    $raw = null;
    if (isset($input['layersJson']) && is_string($input['layersJson']) && strlen($input['layersJson']) <= 524288) {
      $raw = json_decode($input['layersJson'], true);
    } elseif (isset($input['layers'])) {
      $raw = $input['layers'];
    }
    $settings['layers'] = $this->cleanLayers($raw);

    $raw = null;
    if (isset($input['slidesJson']) && is_string($input['slidesJson']) && strlen($input['slidesJson']) <= 262144) {
      $raw = json_decode($input['slidesJson'], true);
    } elseif (isset($input['slides'])) {
      $raw = $input['slides'];
    }
    $settings['slides'] = $this->cleanSlides($raw);

    return $settings;
  }

  // ---------------------------------------------------------------- settings of each slide

  /**
   * Phone image, Before / After and video of the slides, by photo id: images must be images of the Media Library,
   * labels plain text, the video a web link.
   * @param mixed $raw
   * @return array
   */
  protected function cleanSlides($raw)
  {
    $out = [];
    if (!is_array($raw)) {
      return $out;
    }
    foreach ($raw as $photoId => $item) {
      $photoId = (int) $photoId;
      if ($photoId < 1 || !is_array($item)) {
        continue;
      }
      $text = function ($key) use ($item) {
        $value = isset($item[$key]) && is_scalar($item[$key]) ? sanitize_text_field((string) $item[$key]) : '';

        return function_exists('mb_substr') ? mb_substr($value, 0, 40) : substr($value, 0, 40);
      };
      $image = function ($key) use ($item) {
        $id = isset($item[$key]) && is_numeric($item[$key]) ? (int) $item[$key] : 0;

        return $id > 0 && wp_attachment_is_image($id) ? $id : 0;
      };
      $video = isset($item['video']) && is_string($item['video']) ? esc_url_raw(trim($item['video']), ['http', 'https']) : '';
      $slide = [
        'mobile' => $image('mobile'),
        'compare' => $image('compare'),
        'before' => $text('before'),
        'after' => $text('after'),
        'start' => isset($item['start']) && is_numeric($item['start']) ? max(0, min(100, (int) round($item['start']))) : 50,
        'video' => strlen($video) <= 500 ? $video : '',
        'videoMode' => isset($item['videoMode']) && in_array($item['videoMode'], self::VIDEO_MODES, true) ? $item['videoMode'] : 'click',
      ];
      if ($slide['mobile'] || $slide['compare'] || $slide['video'] !== '') {
        $out[$photoId] = $slide;
      }
      if (count($out) >= 500) {
        break;
      }
    }

    return $out;
  }

  /**
   * What a video link points to: a YouTube or Vimeo video, or a video file.
   * @param string $url
   * @return array|null kind and id, or kind and src
   */
  public function parseVideo($url)
  {
    if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $match)) {
      return ['kind' => 'youtube', 'id' => $match[1]];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d{3,})~', $url, $match)) {
      return ['kind' => 'vimeo', 'id' => $match[1]];
    }
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if (preg_match('~\.(mp4|webm|ogv|mov|m4v)$~i', $path)) {
      return ['kind' => 'file', 'src' => $url];
    }

    return null;
  }

  /**
   * The settings of each slide ready for the markup, by photo id.
   * @param array $settings
   * @return array
   */
  protected function frontExtras(array $settings)
  {
    if (empty($settings['slides'])) {
      return [];
    }
    $videos = $this->slideVideos();
    $compare = $this->slideCompare();
    $out = [];
    foreach ($settings['slides'] as $photoId => $slide) {
      $extra = ['mobile' => '', 'compare' => null, 'video' => null];
      if ($slide['mobile']) {
        $image = wp_get_attachment_image_src($slide['mobile'], 'large');
        $extra['mobile'] = $image ? $image[0] : '';
      }
      if ($compare && $slide['compare']) {
        $image = wp_get_attachment_image_src($slide['compare'], 'large');
        if ($image) {
          $extra['compare'] = [
            'src' => $image[0],
            'srcset' => (string) wp_get_attachment_image_srcset($slide['compare'], 'large'),
            'before' => $slide['before'],
            'after' => $slide['after'],
            'start' => $slide['start'],
          ];
        }
      }
      // A comparison keeps the slide still, so it shows no video
      if ($videos && !$extra['compare'] && $slide['video'] !== '') {
        $video = $this->parseVideo($slide['video']);
        if ($video) {
          $extra['video'] = $video + ['mode' => $slide['videoMode']];
        }
      }
      if ($extra['mobile'] !== '' || $extra['compare'] || $extra['video']) {
        $out[$photoId] = $extra;
      }
    }

    return $out;
  }

  /**
   * @param mixed $raw Layers by photo id
   * @return array
   */
  protected function cleanLayers($raw)
  {
    $out = [];
    if (!is_array($raw)) {
      return $out;
    }
    foreach ($raw as $photoId => $items) {
      $photoId = (int) $photoId;
      if ($photoId < 1 || !is_array($items)) {
        continue;
      }
      $layers = [];
      foreach (array_values($items) as $item) {
        $layer = $this->cleanLayer($item);
        if ($layer) {
          $layers[] = $layer;
        }
        if (count($layers) >= self::LAYERS_MAX) {
          break;
        }
      }
      if ($layers) {
        $out[$photoId] = $layers;
      }
      if (count($out) >= 500) {
        break;
      }
    }

    return $out;
  }

  /**
   * @param mixed $item
   * @return array|null
   */
  protected function cleanLayer($item)
  {
    if (!is_array($item) || !isset($item['type']) || !in_array($item['type'], self::LAYER_TYPES, true)) {
      return null;
    }
    $type = $item['type'];
    $base = $this->layerDefaults($type);
    $value = function ($key) use ($item) {
      return isset($item[$key]) && is_scalar($item[$key]) ? (string) $item[$key] : null;
    };

    $text = $value('text') !== null ? sanitize_textarea_field($value('text')) : '';
    $id = $value('id');
    $weight = (int) $value('weight');
    $align = $value('align');
    $anim = $value('anim');
    $bg = $value('bg');

    $color = $value('color');
    $layer = [
      'id' => $id !== null && preg_match('/^[a-z0-9]{1,16}$/', $id) ? $id : strtolower(wp_generate_password(8, false)),
      'type' => $type,
      'text' => function_exists('mb_substr') ? mb_substr($text, 0, 500) : substr($text, 0, 500),
      'link' => $value('link') ? esc_url_raw(trim($value('link')), ['http', 'https', 'mailto', 'tel']) : '',
      'newTab' => $value('newTab') ? 1 : 0,
      // '' (or 'auto'): the color of the style; an own color may be transparent
      'color' => $color === null ? $base['color'] : (in_array(trim($color), ['', 'auto'], true) ? '' : $this->layerColor($color, '#ffffff')),
      // '' no background, 'auto' the accent of the style, or an own color
      'bg' => $bg === null ? $base['bg'] : (trim($bg) === '' ? '' : (trim($bg) === 'auto' ? 'auto' : $this->layerColor($bg, '#000000'))),
      'weight' => in_array($weight, self::LAYER_WEIGHTS, true) ? $weight : $base['weight'],
      'align' => in_array($align, self::LAYER_ALIGN, true) ? $align : $base['align'],
      'radius' => $value('radius') !== null ? max(0, min(100, (int) $value('radius'))) : $base['radius'],
      'shadow' => $value('shadow') !== null ? ($value('shadow') ? 1 : 0) : $base['shadow'],
      'anim' => in_array($anim, self::LAYER_ANIMATIONS, true) ? $anim : 'none',
      'delay' => max(0, min(3000, (int) $value('delay'))),
    ];
    $layer['d'] = $this->cleanBox(isset($item['d']) ? $item['d'] : null, $base['d']);
    $layer['t'] = isset($item['t']) && is_array($item['t']) && $item['t'] ? $this->cleanBox($item['t'], $layer['d']) : null;
    $layer['m'] = isset($item['m']) && is_array($item['m']) && $item['m'] ? $this->cleanBox($item['m'], $layer['t'] ?: $layer['d']) : null;

    return $layer;
  }

  /**
   * An own color of a layer: an opaque color stays #rrggbb as before; a transparent one (rgba() of the editor, or a
   * hex code with alpha) is kept as rgba(r, g, b, a). Anything else gives the default.
   * @param string $value
   * @param string $default
   * @return string
   */
  protected function layerColor($value, $default)
  {
    $value = strtolower(trim((string) $value));
    $rgba = null;
    if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(\d*\.?\d+)(%?)\s*)?\)$/', $value, $match)) {
      if ((int) $match[1] > 255 || (int) $match[2] > 255 || (int) $match[3] > 255) {
        return $default;
      }
      $alpha = isset($match[4]) && $match[4] !== '' ? (float) $match[4] / (empty($match[5]) ? 1 : 100) : 1;
      $rgba = [(int) $match[1], (int) $match[2], (int) $match[3], $alpha];
    } elseif (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $value, $match)) {
      $rgba = [hexdec($match[1]), hexdec($match[2]), hexdec($match[3]), hexdec($match[4]) / 255];
    }
    if ($rgba === null) {
      return $this->color($value, $default);
    }
    $alpha = round(max(0, min(1, $rgba[3])), 2);
    if ($alpha >= 1) {
      return sprintf('#%02x%02x%02x', $rgba[0], $rgba[1], $rgba[2]);
    }

    return sprintf('rgba(%d, %d, %d, %s)', $rgba[0], $rgba[1], $rgba[2], rtrim(rtrim(sprintf('%.2F', $alpha), '0'), '.'));
  }

  /**
   * The same color without its transparency, for the ink that reads on it.
   * @param string $color
   * @return string
   */
  protected function opaqueColor($color)
  {
    if (preg_match('/^rgba?\((\d+), (\d+), (\d+)/', (string) $color, $match)) {
      return sprintf('#%02x%02x%02x', $match[1], $match[2], $match[3]);
    }

    return substr((string) $color, 0, 7);
  }

  /**
   * Place and size of a layer on one device. x and y are percents where 0 is the left or top edge and 100 the right
   * or bottom edge, so a layer never leaves the image; w is the width in percents of the image, 0 for the width of its text.
   * @param mixed $box
   * @param array $fallback
   * @return array
   */
  protected function cleanBox($box, array $fallback)
  {
    $box = is_array($box) ? $box : [];
    $number = function ($key, $min, $max) use ($box, $fallback) {
      $value = isset($box[$key]) && is_numeric($box[$key]) ? (float) $box[$key] : (float) $fallback[$key];

      return max($min, min($max, $value));
    };

    return [
      'x' => round($number('x', 0, 100), 1),
      'y' => round($number('y', 0, 100), 1),
      'w' => (int) round($number('w', 0, 100)),
      'size' => (int) round($number('size', 8, 200)),
      'hide' => isset($box['hide']) ? (empty($box['hide']) ? 0 : 1) : (int) $fallback['hide'],
    ];
  }

  /**
   * @param float $x
   * @param float $y
   * @param int $w
   * @param int $size
   * @return array
   */
  private function box($x, $y, $w, $size)
  {
    return ['x' => $x, 'y' => $y, 'w' => $w, 'size' => $size, 'hide' => 0];
  }

  // ---------------------------------------------------------------- layers: site

  /**
   * Layers ready for the slide markup, by photo id: the classes and CSS variables of every device, only as many
   * layers as the version shows, animations only where they are open.
   * @param array $settings
   * @return array
   */
  protected function frontLayers(array $settings)
  {
    if (empty($settings['layers'])) {
      return [];
    }
    $limit = $this->layerLimit();
    $animations = $this->layerAnimations();
    $out = [];
    foreach ($settings['layers'] as $photoId => $layers) {
      foreach (array_slice($layers, 0, $limit) as $layer) {
        if (trim($layer['text']) === '') {
          continue;
        }
        $boxes = ['d' => $layer['d']];
        $boxes['t'] = $layer['t'] ?: $this->follow($boxes['d'], 't');
        $boxes['m'] = $layer['m'] ?: $this->follow($boxes['t'], 'm');

        $classes = ['sgg-sl-layer', 'sgg-sl-layer--' . $layer['type'], 'sgg-sl-layer-a-' . $layer['align']];
        if ($layer['shadow']) {
          $classes[] = 'sgg-sl-layer-shadow';
        }
        $vars = [];
        foreach ($boxes as $device => $box) {
          $vars[] = '--x-' . $device . ':' . $this->decimal($box['x']) . '%';
          $vars[] = '--y-' . $device . ':' . $this->decimal($box['y']) . '%';
          $vars[] = '--w-' . $device . ':' . ($box['w'] > 0 ? $box['w'] . '%' : 'max-content');
          $vars[] = '--fs-' . $device . ':' . $box['size'] . 'px';
          if ($box['hide']) {
            $classes[] = 'sgg-sl-layer-hide-' . $device;
          }
        }
        // Colors of the style, unless the layer has its own (the variables live on the slider, so a new style
        // recolors every layer that follows it)
        if ($layer['bg'] === 'auto') {
          $vars[] = '--lb:var(--sgg-sl-accent)';
        } elseif ($layer['bg'] !== '') {
          $vars[] = '--lb:' . $layer['bg'];
        }
        if ($layer['color'] !== '') {
          $vars[] = '--lc:' . $layer['color'];
          $ink = $layer['color'];
        } elseif ($layer['bg'] === 'auto') {
          $vars[] = '--lc:var(--sgg-sl-on-accent)';
          $ink = $this->readableOn($settings['style']['accent']);
        } elseif ($layer['bg'] !== '') {
          $ink = $this->readableOn($this->opaqueColor($layer['bg']));
          $vars[] = '--lc:' . $ink;
        } else {
          $vars[] = '--lc:var(--sgg-sl-caption-color)';
          $ink = $settings['style']['captionColor'];
        }
        // Dark text gets a light glow instead of the dark shadow, so it stays readable over a photo
        if ($this->readableOn($this->opaqueColor($ink)) === '#ffffff') {
          $classes[] = 'sgg-sl-layer-ink-dark';
        }
        $vars[] = '--lr:' . $layer['radius'] . 'px';
        $vars[] = '--lw:' . $layer['weight'];
        $anim = $animations && $layer['anim'] !== 'none' ? $layer['anim'] : '';
        if ($anim && $layer['delay'] > 0) {
          $vars[] = '--ld:' . $layer['delay'] . 'ms';
        }

        $out[$photoId][] = [
          'type' => $layer['type'],
          'text' => $layer['text'],
          'link' => $layer['link'],
          'newTab' => $layer['newTab'],
          'class' => implode(' ', $classes),
          'style' => implode(';', $vars),
          'anim' => $anim,
        ];
      }
    }

    return $out;
  }

  /**
   * The values of a tablet or a phone that has none of its own: those of the bigger device, the text a little smaller
   * (small text stays readable) and a set width a little wider. layers.js does the same in the editor.
   * @param array $box
   * @param string $device t or m
   * @return array
   */
  protected function follow(array $box, $device)
  {
    list($text, $width) = self::LAYER_FOLLOW[$device];
    $box['size'] = max((int) round($box['size'] * $text), min($box['size'], 12));
    if ($box['w'] > 0) {
      $box['w'] = min(100, (int) round($box['w'] * $width));
    }

    return $box;
  }

  /**
   * A number for CSS, with a dot whatever the locale.
   * @param float $value
   * @return string
   */
  private function decimal($value)
  {
    return rtrim(rtrim(sprintf('%.1F', $value), '0'), '.');
  }

  // ---------------------------------------------------------------- layers: editor

  protected function editorExtra($galleryId, array $settings)
  {
    $slides = [];
    foreach ($this->photoSlides($galleryId) as $slide) {
      if (empty($slide['photoId'])) {
        continue;
      }
      $slides[] = [
        'id' => $slide['photoId'],
        'src' => $slide['src'],
        'width' => $slide['width'],
        'height' => $slide['height'],
        'title' => $slide['title'] !== '' ? $slide['title'] : $slide['alt'],
      ];
    }
    $defaults = [];
    foreach (self::LAYER_TYPES as $type) {
      $defaults[$type] = $this->layerDefaults($type);
    }

    // Settings of each slide with small pictures of the chosen images, for the slide editor
    $extras = [];
    foreach (isset($settings['slides']) ? $settings['slides'] : [] as $photoId => $slide) {
      foreach (['mobile', 'compare'] as $key) {
        $image = $slide[$key] ? wp_get_attachment_image_src($slide[$key], 'large') : false;
        $slide[$key . 'Url'] = $image ? $image[0] : '';
      }
      $extras[$photoId] = $slide;
    }

    return [
      'slideExtras' => $extras,
      'slideVideos' => $this->slideVideos(),
      'slideCompare' => $this->slideCompare(),
      'layerSlides' => $slides,
      'layerDefaults' => $defaults,
      'layerLimit' => $this->layerLimit(),
      'layerMax' => self::LAYERS_MAX,
      'layerFollow' => self::LAYER_FOLLOW,
      'layerAnimations' => $this->layerAnimations(),
    ];
  }

  public function enqueueEditorAssets()
  {
    parent::enqueueEditorAssets();
    if (!$this->isOwnEditor()) {
      return;
    }
    $url = plugin_dir_url(__FILE__) . 'assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-ly-13';
    // The Media Library chooses the phone image, the Before / After image and video files
    wp_enqueue_media();
    wp_enqueue_style('sgg-slider-layers', $url . '/css/layers.css', ['sgg-sliders-editor'], $version);
    // Colors of the layers with transparency
    wp_enqueue_script('sgg-slider-alpha-picker', $url . '/js/alpha-picker.js', [], $version, true);
    wp_enqueue_script('sgg-slider-layers', $url . '/js/layers.js', ['jquery', 'sgg-sliders-editor', 'sgg-slider-alpha-picker'], $version, true);
  }
}
