<?php

/**
 * Class GridGallery_Optimization_Model_ServerOptimize
 *
 * Compresses gallery images with the image library already installed on this
 * server (GD or Imagick) instead of sending them to a third-party API. Built
 * on WP_Image_Editor so it inherits WordPress' own library detection, EXIF
 * orientation handling and mime support checks rather than re-implementing
 * them - which also means it honours the plugin's own "Image Preprocessor"
 * setting (Advanced Settings -> auto / GD / Imagick).
 *
 * Nothing here talks to the network, so it works on hosts with no outbound
 * access, and there is no per-image quota.
 *
 * @package GridGallery\Optimization
 */
class GridGallery_Optimization_Model_ServerOptimize
{
  /**
   * Formats we can additionally emit next to the original file.
   */
  const FORMAT_WEBP = 'image/webp';
  const FORMAT_AVIF = 'image/avif';

  /**
   * @var string|null One of 'auto', 'gd', 'imagic' - the plugin-wide preference.
   */
  private $preferredEditor;

  /**
   * @param string|null $preferredEditor
   */
  public function __construct($preferredEditor = null)
  {
    $this->preferredEditor = $preferredEditor;
  }

  /**
   * True when this server can do any image processing at all.
   *
   * @return bool
   */
  public function isAvailable()
  {
    return extension_loaded('gd') || extension_loaded('imagick');
  }

  /**
   * Which library WordPress will actually pick, for display purposes.
   *
   * @return string
   */
  public function getEngineName()
  {
    if (extension_loaded('imagick') && class_exists('Imagick')) {
      return 'Imagick';
    }
    if (extension_loaded('gd')) {
      return 'GD';
    }
    return 'none';
  }

  /**
   * Whether a target format can be written on this host. WordPress reports
   * this per mime type, and support genuinely varies: WebP needs WP 5.8+ with
   * a GD built against libwebp, AVIF needs WP 6.5+ and a much newer library
   * still - so this is checked per run rather than assumed.
   *
   * @param string $mime
   * @return bool
   */
  public function supportsFormat($mime)
  {
    if (!function_exists('wp_image_editor_supports')) {
      return false;
    }

    return (bool) wp_image_editor_supports([
      'mime_type' => $mime,
      'methods' => ['resize', 'save'],
    ]);
  }

  /**
   * Optimizes a single file in place (or alongside it) and optionally writes
   * modern-format siblings.
   *
   * $options:
   *   max_width         int|null  0/null keeps the current width
   *   max_height        int|null  0/null keeps the current height
   *   quality           int       1-100, 100 means "re-encode losslessly as far as the library allows"
   *   replace_original  bool      false keeps a .io-backup copy of the untouched file
   *   convert_webp      bool
   *   convert_avif      bool
   *
   * @param string $path Absolute path to the source image.
   * @param array $options
   * @return array {ok:bool, before:int, after:int, created:string[], error:string|null}
   */
  public function optimizeFile($path, array $options = [])
  {
    $result = ['ok' => false, 'before' => 0, 'after' => 0, 'created' => [], 'error' => null];

    if (!$this->isAvailable()) {
      $result['error'] = 'No image library (GD or Imagick) available on this server.';
      return $result;
    }

    if (!is_string($path) || !is_file($path) || !is_readable($path)) {
      $result['error'] = 'File not found or not readable.';
      return $result;
    }

    if (!is_writable(dirname($path))) {
      $result['error'] = 'Directory is not writable.';
      return $result;
    }

    $options = $this->normalizeOptions($options);

    clearstatcache(true, $path);
    $result['before'] = (int) filesize($path);

    // Back the original up before the first destructive write, so "Restore"
    // on the gallery keeps working exactly as it did for the TinyPNG engine -
    // same io-backup sub-folder, same layout.
    if (!$options['replace_original'] && !$this->backupOriginal($path)) {
      $result['error'] = 'Could not create a backup of the original file.';
      return $result;
    }

    $editor = $this->makeEditor($path);
    if (is_wp_error($editor)) {
      $result['error'] = $editor->get_error_message();
      return $result;
    }

    $resized = $this->applyResize($editor, $options);
    if ($resized === null) {
      $result['error'] = 'Resize failed.';
      return $result;
    }

    $editor->set_quality($options['quality']);

    // Encode to a scratch file rather than straight over the source. Re-encoding
    // an already-well-compressed JPEG can easily come out LARGER than the
    // original - at quality 100 it reliably does - and silently inflating the
    // gallery is the opposite of what this feature is for. The new file is
    // adopted only when it is genuinely smaller, or when a resize means the
    // source is simply the wrong image to keep.
    $scratch = $this->scratchPathFor($path);

    $saved = $editor->save($scratch);
    if (is_wp_error($saved)) {
      $result['error'] = $saved->get_error_message();
      return $result;
    }

    $scratch = isset($saved['path']) ? $saved['path'] : $scratch;

    clearstatcache(true, $scratch);
    $encoded = (int) filesize($scratch);

    if ($resized || ($encoded > 0 && $encoded < $result['before'])) {
      if (!@rename($scratch, $path)) {
        @unlink($scratch);
        $result['error'] = 'Could not replace the original file.';
        return $result;
      }
      clearstatcache(true, $path);
      $result['after'] = (int) filesize($path);
    } else {
      @unlink($scratch);
      $result['after'] = $result['before'];
    }

    // Siblings are generated from the already-resized editor instance, so they
    // inherit the same dimensions instead of being re-derived from the source.
    foreach ([self::FORMAT_WEBP => 'convert_webp', self::FORMAT_AVIF => 'convert_avif'] as $mime => $flag) {
      if (!$options[$flag]) {
        continue;
      }

      $created = $this->writeSibling($path, $editor, $mime, $options['quality']);
      if ($created !== null) {
        $result['created'][] = $created;
      }
    }

    $result['ok'] = true;

    return $result;
  }

  /**
   * Writes just the modern-format sibling for a file, leaving the source
   * untouched. Used to build a sibling on demand for the cropped/watermarked
   * derivative the gallery actually renders, which is generated lazily at
   * render time and so cannot be enumerated up front.
   *
   * @param string $path
   * @param string $mime
   * @param int $quality
   * @return string|null Path written, or null if it could not be produced.
   */
  public function createSibling($path, $mime, $quality = 90)
  {
    if (!$this->isAvailable() || !is_file($path) || !is_readable($path) || !is_writable(dirname($path))) {
      return null;
    }

    if (!$this->supportsFormat($mime)) {
      return null;
    }

    $editor = $this->makeEditor($path);
    if (is_wp_error($editor)) {
      return null;
    }

    return $this->writeSibling($path, $editor, $mime, (int) $quality);
  }

  /**
   * Absolute path of the modern-format sibling for a given source file:
   * /uploads/photo.jpg -> /uploads/photo.jpg.webp
   *
   * The extension is appended rather than replaced so two sources that differ
   * only by extension (photo.jpg / photo.png) cannot collide on one sibling,
   * and so the original name stays recoverable from the sibling name.
   *
   * @param string $path
   * @param string $mime
   * @return string|null
   */
  public static function getSiblingPath($path, $mime)
  {
    $ext = self::getExtensionForMime($mime);
    if ($ext === null) {
      return null;
    }

    return $path . '.' . $ext;
  }

  /**
   * @param string $mime
   * @return string|null
   */
  public static function getExtensionForMime($mime)
  {
    if ($mime === self::FORMAT_WEBP) {
      return 'webp';
    }
    if ($mime === self::FORMAT_AVIF) {
      return 'avif';
    }
    return null;
  }

  /**
   * @param array $options
   * @return array
   */
  private function normalizeOptions(array $options)
  {
    $quality = isset($options['quality']) ? (int) $options['quality'] : 100;
    if ($quality < 1 || $quality > 100) {
      $quality = 100;
    }

    return [
      'max_width' => isset($options['max_width']) ? max(0, (int) $options['max_width']) : 0,
      'max_height' => isset($options['max_height']) ? max(0, (int) $options['max_height']) : 0,
      'quality' => $quality,
      'replace_original' => !empty($options['replace_original']),
      'convert_webp' => !empty($options['convert_webp']),
      'convert_avif' => !empty($options['convert_avif']),
    ];
  }

  /**
   * Builds a WP_Image_Editor, forcing the library the plugin is configured to
   * prefer when the user picked one explicitly.
   *
   * @param string $path
   * @return WP_Image_Editor|WP_Error
   */
  private function makeEditor($path)
  {
    $filter = null;

    if ($this->preferredEditor === 'gd' || $this->preferredEditor === 'imagic') {
      $wanted = $this->preferredEditor === 'gd' ? 'WP_Image_Editor_GD' : 'WP_Image_Editor_Imagick';
      $filter = function ($editors) use ($wanted) {
        if (class_exists($wanted)) {
          array_unshift($editors, $wanted);
        }
        return $editors;
      };
      add_filter('wp_image_editors', $filter, 99);
    }

    $editor = wp_get_image_editor($path);

    if ($filter !== null) {
      remove_filter('wp_image_editors', $filter, 99);
    }

    return $editor;
  }

  /**
   * Applies the size cap. An empty max width or height means "unconstrained on
   * that axis" - WP_Image_Editor::resize() already keeps the aspect ratio when
   * one side is null, which is exactly the documented behaviour of leaving the
   * field blank in the settings.
   *
   * @param WP_Image_Editor $editor
   * @param array $options
   * @return bool|null true when pixels changed, false when nothing was needed,
   *                   null on failure.
   */
  private function applyResize($editor, array $options)
  {
    if (!$options['max_width'] && !$options['max_height']) {
      return false;
    }

    $size = $editor->get_size();
    $currentWidth = isset($size['width']) ? (int) $size['width'] : 0;
    $currentHeight = isset($size['height']) ? (int) $size['height'] : 0;

    // Never upscale: a cap larger than the image is a no-op, not an instruction
    // to blow the photo up and lose quality.
    $needsResize =
      ($options['max_width'] && $currentWidth > $options['max_width']) || ($options['max_height'] && $currentHeight > $options['max_height']);

    if (!$needsResize) {
      return false;
    }

    $resized = $editor->resize($options['max_width'] ? $options['max_width'] : null, $options['max_height'] ? $options['max_height'] : null, false);

    return is_wp_error($resized) ? null : true;
  }

  /**
   * Scratch file next to the source, keeping the original extension so
   * WP_Image_Editor infers the same output format.
   *
   * @param string $path
   * @return string
   */
  private function scratchPathFor($path)
  {
    $dir = dirname($path);
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $name = pathinfo($path, PATHINFO_FILENAME);

    return $dir . DIRECTORY_SEPARATOR . $name . '.sgg-opt-tmp' . ($ext !== '' ? '.' . $ext : '');
  }

  /**
   * @param string $path
   * @param WP_Image_Editor $editor
   * @param string $mime
   * @param int $quality
   * @return string|null Absolute path of the file written, or null.
   */
  private function writeSibling($path, $editor, $mime, $quality)
  {
    if (!$this->supportsFormat($mime)) {
      return null;
    }

    $target = self::getSiblingPath($path, $mime);
    if ($target === null) {
      return null;
    }

    $editor->set_quality($quality);
    $saved = $editor->save($target, $mime);

    if (is_wp_error($saved)) {
      return null;
    }

    // WP may normalise the filename it actually wrote; trust its answer.
    return isset($saved['path']) ? $saved['path'] : $target;
  }

  /**
   * Copies the untouched file into the same io-backup sub-folder the TinyPNG
   * engine uses, so one Restore path covers both engines. An existing backup
   * is never overwritten - it is the pre-optimization state and a second run
   * must not replace it with an already-optimized copy.
   *
   * @param string $path
   * @return bool
   */
  private function backupOriginal($path)
  {
    $dir = dirname($path) . DIRECTORY_SEPARATOR . GridGallery_Optimization_Model_Optimization::$restoreSubFolder;

    if (!file_exists($dir) && !wp_mkdir_p($dir)) {
      return false;
    }

    $target = $dir . DIRECTORY_SEPARATOR . basename($path);

    if (file_exists($target)) {
      return true;
    }

    return (bool) @copy($path, $target);
  }
}
