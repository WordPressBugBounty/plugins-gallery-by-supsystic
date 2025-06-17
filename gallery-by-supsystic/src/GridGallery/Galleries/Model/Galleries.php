<?php

/**
 * Class GridGallery_Galleries_Model_Galleries
 * Provides the logic to work with the galleries
 *
 * @package GridGallery\Galleries\Model
 * @author Artur Kovalevsky
 */
#[\AllowDynamicProperties]
class GridGallery_Galleries_Model_Galleries extends GridGallery_Core_BaseModel
{

    /**
     * @var string
     */
    protected $table;

    /**
     * Constructor
     * @param bool $debugEnabled
     */
    public function __construct($debugEnabled = false)
    {
        parent::__construct($debugEnabled);

        $this->debugEnabled = (bool)$debugEnabled;
        $this->table = $this->db->prefix . 'gg_galleries';
    }

    public function sanitize_array( &$array ) {
    foreach ($array as $key => &$value) {
			if( !is_array($value) )	{
				$value = $this->sanitizeString($value);
			} else {
				self::sanitize_array($value);
			}
    }
    return $array;
  }

	public function sanitizeString($str) {
			$allowedHtml = $this->getAllowedHtml();
			if (!empty($str) && is_string($str)) {
				$str = htmlspecialchars_decode($str);
				$str = wp_kses($str, $allowedHtml);
				$str = str_replace('"&#039;', "&#39;", $str);
				$str = str_replace('&#039;"', "&#39;", $str);
				$str = html_entity_decode($str);
				//error_log('KSES: '.$str);
			}
			return $str;
	}

	private function getAllowedHtml() {
		if(empty($this->allowedHtml)) {
			$allowedHtml = wp_kses_allowed_html();
			$newAllowedHtml = array(
				'li' => array(
					'style' => 1,
					'class' => 1,
					'id' => 1,
				),
				'ul' => array(
					'style' => 1,
					'class' => 1,
					'id' => 1,
				),
				'ol' => array(
					'style' => 1,
					'class' => 1,
					'id' => 1,
				),
				'i' => array(
					'style' => 1,
					'class' => 1,
					'id' => 1,
				),
				'img' => array(
					'src' => 1,
					'style' => 1,
					'width' => 1,
					'height' => 1,
					'id' => 1,
					'class' => 1,
					'alt' => 1,
					'class' => 1,
					'alignnone' => 1,
					'size-full' => 1,
					'wp-image-3300' => 1,
				),
				'video' => array(
					'src' => 1,
					'style' => 1,
					'width' => 1,
					'height' => 1,
					'id' => 1,
					'class' => 1,
					'poster' => 1,
					'autoplay' => 1,
					'controls' => 1,
					'crossorigin' => 1,
					'autobuffer' => 1,
					'buffered' => 1,
					'played' => 1,
					'loop' => 1,
					'muted' => 1,
					'preload' => 1,
				),
				'track' => array(
					'src' => 1,
					'kind' => 1,
					'label' => 1,
					'srclang' => 1,
				),
				'source' => array(
					'src' => 1,
					'type' => 1,
				),
				'audio' => array(
					'src' => 1,
					'style' => 1,
					'width' => 1,
					'height' => 1,
					'id' => 1,
					'class' => 1,
					'autoplay' => 1,
					'controls' => 1,
					'crossorigin' => 1,
					'loop' => 1,
					'muted' => 1,
					'preload' => 1,
				),
				'iframe' => array(
					'src' => 1,
					'style' => 1,
					'width' => 1,
					'height' => 1,
					'id' => 1,
					'class' => 1,
					'title' => 1,
					'allow' => 1,
					'allowfullscreen' => 1,
					'allowpaymentrequest' => 1,
					'csp' => 1,
					'height' => 1,
					'loading' => 1,
					'name' => 1,
					'referrerpolicy' => 1,
					'sandbox' => 1,
					'srcdoc' => 1,
				),
				'br' => array(),
				'a' => array(
					'target' => 1,
					'href' => 1,
					'download' => 1,
					'hreflang' => 1,
					'media' => 1,
					'rel' => 1,
					'type' => 1,
					'link' => 1,
					'style' => 1,
                    'class' => 1,
                    'data-quantity' => 1,
                    'data-product_id' => 1,

				),
				'hr' => array(
					'align' => 1,
					'style' => 1,
					'class' => 1,
					'id' => 1,
				),
				'p' => array(
					'style' => 1,
					'class' => 1,
					'id' => 1,
				),
				'sup' => array(
				),
				'sub' => array(
				),
                'button' => array(
					'autocomplete' => 1,
					'style' => 1,
					'class' => 1,
					'autofocus' => 1,
                    'id' => 1,
                    'utocomplete' => 1,
                    'disabled' => 1,
                    'form' => 1,
                    'formenctype' => 1,
                    'formmethod' => 1,
                    'formnovalidate' => 1,
                    'name' => 1,
                    'formtarget' => 1,
                    'type' => 1,
                    'value' => 1,
                    'onclick' =>1,
				),
			);
			$allowedDiv = array(
            'div' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'title' => 1,
               'id' => 1,

            ) ,
            'small' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'span' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'pre' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'p' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'br' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'hr' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'hgroup' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'h1' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'h2' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'h3' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'h4' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'h5' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'h6' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'ul' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'ol' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'li' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'dl' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'dt' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'dd' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'strong' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'em' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'b' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'i' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'u' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'img' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,
							 'src' => 1,
							 'alt' => 1,
            ) ,
            'a' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,
							 'href' => 1,

            ) ,
            'abbr' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'address' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'blockquote' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,
            ) ,
            'area' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'audio' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'video' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'form' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'fieldset' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'label' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'input' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'value' => 1,
               'type' => 1,
               'id' => 1,

            ) ,
            'textarea' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'caption' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'table' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'tbody' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'td' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'tfoot' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'th' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'thead' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'tr' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'iframe' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'select' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

            ) ,
            'option' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

               'selected' => 1,
               'data-number' => 1,
               'value' => 1,
            ),
						'alt' => array(
               'style' => 1,
               'title' => 1,
               'align' => 1,
               'class' => 1,
               'width' => 1,
               'height' => 1,
               'id' => 1,

               'selected' => 1,
               'data-number' => 1,
               'value' => 1,
            )
         );
				 $ar1 = array_merge($allowedHtml, $newAllowedHtml);
				 $ar2 = array_merge($allowedDiv, $ar1);

			$this->allowedHtml = $ar2;
		}
		return $this->allowedHtml;
	}

    /**
     * Returns the data of the gallery by the identifier
     *
     * @param int $id The identifier of the gallery
     * @return object|null
     */
    public function getById($attributes)
    {
        $galleryId = is_numeric($attributes) ? $attributes : $attributes['id'];
        $attrArray = is_array($attributes) ? $attributes : array();
        $query = $this->getQueryBuilder()->select('*')
            ->from($this->table)
            ->where('id', '=', (int)$galleryId);

        if ($gallery = $this->db->get_row($query->build())) {
	        $gallery = $this->extend($gallery, $attrArray);
        }
		$gallery = $this->environment->getDispatcher()->applyFilters('after_gallery_get', $gallery);

        return $gallery;
    }

    /**
     * Returns the array of the galleries
     *
     * @return array|null
     */
    public function getAll()
    {
        if ($galleries = $this->getList()) {
            foreach ($galleries as $index => $gallery) {
                $galleries[$index] = $this->extend($gallery);
            }
        }

        return $galleries;
    }

    /**
     * Returns the array of the galleries with thumbnails
     *
     * @return array|null
     */
    public function getListWithThumbnails()
    {

        $query = array(
            'SELECT {prefix}gg_galleries.*, {prefix}gg_photos.attachment_id, r.total, {prefix}gg_settings_sets.data as settings, {prefix}gg_photos.link_default',
                'FROM {prefix}gg_galleries',
                'LEFT JOIN',
                '(SELECT count(resource_id) as total, resource_id, gallery_id',
                    'FROM {prefix}gg_galleries_resources GROUP BY gallery_id) as r',
                'ON {prefix}gg_galleries.id = r.gallery_id',
                'LEFT JOIN {prefix}gg_photos ON r.resource_id = {prefix}gg_photos.id',
                'LEFT JOIN {prefix}gg_settings_sets ON {prefix}gg_galleries.id = {prefix}gg_settings_sets.gallery_id',
                'ORDER BY {prefix}gg_galleries.id DESC'
            );
        $query = implode(' ', $query);
        $query = str_replace('{prefix}', $this->db->prefix, $query);
        return $this->db->get_results($query);
    }

    /**
     * Returns the array of the NOT extended galleries
     *
     * @return null|array
     */
    public function getList()
    {
        $query = $this->getQueryBuilder()->select('*')
            ->from($this->table)
            ->orderBy('id')
            ->order('DESC');

        return $this->db->get_results($query->build());
    }

    /**
     * Adds the empty gallery to the database
     * @param string $title
     * @return bool TRUE on success, FALSE otherwise
     */
    public function add($title)
    {
        $query = $this->getQueryBuilder()->insertInto($this->table)
            ->fields('title')
            ->values($title);

        if (!$this->db->query($query->build())) {
            return false;
        }

        $this->insertId = $this->db->insert_id;

        do_action(apply_filters('gg_hooks_prefix', 'gallery_created'), $this->db->insert_id);

        return true;
    }

    /**
     * Creates the new gallery from the HTTP request
     * @param RscSgg_Http_Request $request The HTTP request
     * @param RscSgg_Lang $lang The instance of the language class
     * @param RscSgg_Config $config
     * @return bool TRUE on success, FALSE otherwise
     */
    public function createFromRequest(RscSgg_Http_Request $request, RscSgg_Lang $lang, RscSgg_Config $config)
    {
        $title = $request->post->get('title');
        $unnamed = (!$title || strlen($title) == 0);

        if($unnamed) {
            $title = $lang->translate('Unnamed gallery');
        }

        $title = htmlspecialchars($request->post->get('title'), ENT_QUOTES);

        $res = $this->add($title);

        if ($res) {
            $id = $this->db->insert_id;

            if($unnamed) {
                $this->rename($id, $lang->translate('Gallery ').$id);
            }

            $config->load('@galleries/presets.php');
            $presets = $config->get('gallery_presets');

            $data = $presets[$request->post->get('preset', 1)];

            $settings = new GridGallery_Galleries_Model_Settings();
            $settings->save($id, unserialize($data));

            return true;
        }

        return false;
    }

    /**
     * Renames the gallery by the specified identifier
     *
     * @param int $galleryId The identifier of the gallery
     * @param string $title The new title of the gallery
     * @return bool TRUE on success, FALSE otherwise
     */
    public function rename($galleryId, $title)
    {
        $query = $this->getQueryBuilder()->update($this->table)
            ->fields('title')
            ->values(htmlspecialchars($title, ENT_QUOTES, get_bloginfo('charset')))
            ->where('id', '=', (int)$galleryId);

        if (!$this->db->query($query->build())) {

            if ($this->logger) {
                $this->logger->error('Failed to execute query: ({query}) in method {method}', array(
                    'query' => $query,
                    'method' => __METHOD__,
                ));
            }

            return false;
        }

        return true;
    }

    /**
     * Renames the gallery from the HTTP request
     *
     * @param RscSgg_Http_Request $request An instance of the HTTP request
     * @param RscSgg_Lang $lang An instance of the translation class
     * @throws RuntimeException When rename query return FALSE
     * @throws InvalidArgumentException If at least one argument is invalid
     */
    public function renameFromRequest(RscSgg_Http_Request $request, RscSgg_Lang $lang)
    {
        $galleryId = $request->post->get('gallery_id');
        $title = $request->post->get('title');

        if (!is_numeric($galleryId)) {
            throw new InvalidArgumentException($lang->translate('The identifier of the gallery is invalid'));
        }

        if (empty($title)) {
            throw new InvalidArgumentException($lang->translate('Title is empty'));
        }

        if (!$this->rename($galleryId, $title)) {
            throw new RuntimeException(
                $lang->translate('Failed to rename the gallery')
            );
        }
    }

    /**
     * Deletes gallery from the database from the HTTP request
     *
     * @param RscSgg_Http_Request $request An instance of the HTTP request
     * @param RscSgg_Lang $lang An instance of the translation class
     * @param string $hooksPrefix The prefix for the plugin hooks
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function deleteFromRequest(RscSgg_Http_Request $request, RscSgg_Lang $lang, $hooksPrefix = null)
    {
        $galleryId = $request->query->get('gallery_id');

        if (!is_numeric($galleryId)) {
            throw new UnexpectedValueException($lang->translate('Invalid gallery identifier specified'));
        }

        if (!$this->delete($galleryId, $hooksPrefix)) {
            throw new RuntimeException($lang->translate('Failed to delete the gallery'));
        }
    }

    /**
     * Deletes the gallery by the identifier
     *
     * @param int $galleryId The identifier of the gallery
     * @param string $hooksPrefix The prefix for the plugin hooks
     * @return bool TRUE on success, FALSE otherwise
     */
    public function delete($galleryId, $hooksPrefix = null)
    {
        $query = $this->getQueryBuilder()->deleteFrom($this->table)
            ->where('id', '=', (int)$galleryId);

        if (!$this->db->query($query->build())) {

            if ($this->logger) {
                $this->logger->error('Failed to execute query ({query}) in method {method}', array(
                    'query' => $query,
                    'method' => __METHOD__,
                ));
            }

            return false;
        }

        do_action($hooksPrefix . 'gallery_delete', $galleryId);

        return true;
    }

    /**
     * Sets the settings for the gallery
     *
     * @param int $galleryId
     * @param int $settingsId
     * @return bool TRUE on success, FALSE otherwise
     */
    public function setSettings($galleryId, $settingsId)
    {
        $query = $this->getQueryBuilder()->update($this->table)
            ->fields('settings_id')
            ->values((int)$settingsId)
            ->where('id', '=', (int)$galleryId);

        if (!$this->db->query($query->build())) {
            return false;
        }

        return true;
    }

    /**
     * Sets the default settings for the gallery
     *
     * @param int $galleryId
     * @return bool TRUE on success, FALSE otherwise
     */
    public function setDefaultSettings($galleryId)
    {
        return true;
    }

    /**
     * Extends default gallery select
     *
     * @param object $gallery The result of the SELECT
     * @return mixed
     */
    protected function extend($gallery, $attributes = array())
    {
        if (!is_object($gallery)) {
            if ($this->debugEnabled) {
                wp_die(sprintf('The $gallery variable must be type of object, %s given', esc_html(gettype($gallery))));
            }

            return $gallery;
        }

        $models = array();
        $models['settings'] = new GridGallery_Galleries_Model_Settings($this->debugEnabled);
        $settings = $models['settings']->getByGalleryId($gallery->id);

        if (is_object($settings)) {
            $gallery->settings_id = $settings->id;
            $gallery->settings = $settings->data;
        }

        // if(isset($attributes) && isset($attributes['membershipModel'])) {
        //     $memberShipModel = $attributes['membershipModel'];
        //     foreach($attributes['image-list'] as $oneImage) {
        //         $gallery->photos[] = $memberShipModel->getGalleryAttachmenEmuledImage($oneImage);
        //     }
        // } else {
            $resources = new GridGallery_Galleries_Model_Resources($this->debugEnabled);
            $resourcesData = $resources->getByGalleryId($gallery->id);

            if (!$resourcesData) {
                return $gallery;
            }

            $gallery->photos = array();

            $models['photos'] = new GridGallery_Photos_Model_Photos($this->debugEnabled);
            $models['folders'] = new GridGallery_Photos_Model_Folders($this->debugEnabled);

            /*foreach ($resourcesData as $data) {
				switch ($data->resource_type) {
					case 'folder':
						if ($photos = $models['folders']->getPhotosById($data->resource_id)) {
							foreach ($photos as $photo) {
								if (!$this->isExcluded($gallery->id, $data->resource_id, $photo->id)) {
									$gallery->photos[] = $photo;
								}
							}
						}
						break;

					case 'photo':
						$gallery->photos[] = $models['photos']->getById($data->resource_id);
						break;
				}
			}*/
            $gallery->photos = $models['photos']->getPhotos($resourcesData);
        // }

        return $gallery;
    }

    public function isExcluded($galleryId, $folderId, $photoId)
    {
        $table = $this->db->prefix . 'gg_galleries_excluded';

        $query = $this->getQueryBuilder()->select('*')
            ->from($table)
            ->where('gallery_id', '=', (int)$galleryId)
            ->andWhere('folder_id', '=', (int)$folderId)
            ->andWhere('photo_id', '=', (int)$photoId);

        return (null !== $this->db->get_row($query->build()));
    }

    /**
     * Sets a logger instance on the object
     *
     * @param RscSgg_Logger_Interface $logger
     * @return null
     */
    public function setLogger(RscSgg_Logger_Interface $logger)
    {
        $this->logger = $logger;
    }

	public function getGalleryTitle($id) {
		$query = $this->getQueryBuilder()->select('title')
			->from($this->table)
			->where('id', '=', (int)$id);

		$res = $this->db->get_row($query->build(), ARRAY_A);
		if(count($res) && !empty($res['title'])) {
			return $res['title'];
		}
		return null;
	}

	public function cloneGallery($galleryId, $cloneType, $parameters) {
		$language = $parameters['environment']->getLang();
		$resourceModel = $parameters['resources'];
		$settingsModel = $parameters['settings'];
		$positionModel = $parameters['position'];
		$cdnModel = $parameters['cdn'];
		// $membershipModel = $parameters['membership'];

		$hasError = false;
		$message = '';
		$galleryId = (int) $galleryId;
		if(!$galleryId) {
			$hasError = true;
			$message = $language->translate('Source gallery incorrect id');
		}
		if($cloneType != 1 && $cloneType != 2) {
			$hasError = true;
			$message = $language->translate('Clone type parameter is incorrect');
		}
		// copy all tables
		// gg_galleries
		if(!$hasError) {
			$ggTitle = $this->getGalleryTitle($galleryId);
			if($ggTitle) {
				$ggTitle = $language->translate('Cloned ') . $ggTitle;
			} else {
				$ggTitle = $language->translate('Cloned gallery ' . $galleryId);
			}
			if(!$this->add($ggTitle)) {
				$message = $language->translate('Can\'t create new gallery!');
				$hasError = true;
			}
		}

		if(!$hasError && $this->insertId) {
			$newGalleryId = $this->insertId;
			if($cloneType != 2) {
				// gg_galleries_excluded
				// gg_galleries_resources
				if(!$resourceModel->cloneByGalleryId($galleryId, $newGalleryId)) {
					$message = $language->translate('Can\'t create resources for gallery!');
					$hasError = true;
				}
				// gg_photos_pos
				if(!$hasError && !$positionModel->cloneByScopeId($galleryId, $newGalleryId)) {
					$message = $language->translate('Can\'t create image position for gallery!');
					$hasError = true;
				}
			}
			// gg_settings_sets
			if(!$hasError && !$settingsModel->cloneByGalleryId($galleryId, $newGalleryId)) {
				$message = $language->translate('Can\'t create settings for gallery!');
				$hasError = true;
			}
			// gg_membership_presets
			// if(!$hasError && !$membershipModel->cloneByGalleryId($galleryId, $newGalleryId)) {
			// 	$message = $language->translate('Can\'t create membership params for gallery!');
			// 	$hasError = true;
			// }
		}

		return array(
			'message' => $message,
			'isError' => $hasError,
			'newGalleryId' => $newGalleryId,
		);
    }
}
