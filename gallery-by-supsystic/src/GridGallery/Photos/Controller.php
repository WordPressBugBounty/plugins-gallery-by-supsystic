<?php

/**
 * Class GridGallery_Photos_Controller
 *
 * @package GridGallery\Photos
 */
class GridGallery_Photos_Controller extends GridGallery_Core_BaseController
{

    const STD_VIEW = 'list'; // accepts 'list' or 'block'.

    public function requireNonces() {
        return array(
            'addAction',
            'addFolderAction',
            'deleteAction',
            'moveAction',
            'updateTitleAction',
            'updateAttachmentAction',
            'updatePositionAction'
        );
    }
    /**
     * {@inheritdoc}
     */
    protected function getModelAliases()
    {
        return array(
            'resources' => 'GridGallery_Galleries_Model_Resources',
            'photos' => 'GridGallery_Photos_Model_Photos',
            'folders' => 'GridGallery_Photos_Model_Folders',
            'position' => 'GridGallery_Photos_Model_Position',
        );
    }

    /**
     * Index Action
     * Shows the list of the all photos
     */
    public function indexAction(RscSgg_Http_Request $request)
    {
        $stats = $this->getEnvironment()->getModule('stats');
        $stats->save('Images.tab');

        if ('grid-gallery-images' === $request->query->get('page')) {
            $redirectUrl = $this->generateUrl('photos');

            return $this->redirect($redirectUrl);
        }

        $folders = $this->getModel('folders');
        $photos = $this->getModel('photos');
        $position = $this->getModel('position');

        $images = array_map(
            array($position, 'setPosition'),
            $photos->getAllWithoutFolders()
        );

        return $this->response(
            '@photos/index.twig',
            array(
                'entities' => array(
                    'images' => $position->sort($images),
                    'folders' => $folders->getAll()
                ),
                'view_type' => sanitize_text_field($request->query->get('view', self::STD_VIEW)),
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
            )
        );
    }

    /**
     * View Action
     * Shows the photos in the selected album
     *
     * @param RscSgg_Http_Request $request
     * @return RscSgg_Http_Response
     */
    public function viewAction(RscSgg_Http_Request $request)
    {
        if (!$request->query->has('folder_id')) {
            $this->redirect(
                $this->getEnvironment()->generateUrl('photos', 'index')
            );
        }

        $stats = $this->getEnvironment()->getModule('stats');
        $stats->save('folders.view');

        $folderId = (int)$request->query->get('folder_id');

        $folders = $this->getModel('folders');

        if (!$folder = $folders->getById($folderId)) {
            $this->redirect(
                $this->getEnvironment()->generateUrl('photos', 'index')
            );
        }

        $position = $this->getModel('position');

        foreach ($folder->photos as $index => $row) {
            $folder->photos[$index] = $position->setPosition(
                $row,
                'folder',
                $folderId
            );
        }

        $folder->photos = $position->sort($folder->photos);

        return $this->response(
            '@photos/view.twig',
            array(
                'folder' => $folder,
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                'view_type' => sanitize_text_field($request->query->get('view', self::STD_VIEW)),
            )
        );
    }

	/**
	 * Extract for use in Pro version
	 */
	protected function addPhotoResForAddAction($_photos, $_attachment, $_request) {
		return $_photos->add($_attachment->ID, $_request->post->get('folder_id', 0), array());
	}

    /**
     * Add Action
     * Adds new photos to the database
     *
     * @param RscSgg_Http_Request $request
     * @return RscSgg_Http_Response
     */
    public function addAction(RscSgg_Http_Request $request)
    {
        $env = $this->getEnvironment();

		$photos = $this->getModel('photos');

        if ($env->getConfig()->isEnvironment(
            RscSgg_Environment::ENV_DEVELOPMENT
        )
        ) {
            $photos->setDebugEnabled(true);
        }

        $attachment = get_post(sanitize_key($request->post->get('attachment_id')));
        $viewType = sanitize_text_field($request->post->get('view_type'));

        $stats = $this->getEnvironment()->getModule('stats');
        $stats->save('photos.add');

        $this->getModule('galleries')->cleanCache(sanitize_key($request->post->get('galleryId')));

		if (!$this->addPhotoResForAddAction($photos, $attachment, $request)) {
            $response = array(
                'error' => true,
                'photo' => null,
                'message' => sprintf(
                    $env->translate('Unable to save chosen photo %s: %s'),
                    esc_html($attachment->post_title),
                    esc_html($photos->getLastError())
                ),
            );
        } else {
            $response = array(
                'error' => false,
                'message' => sprintf(
                    $env->translate(
                        'Photo %s was successfully imported to the Grid Gallery'
                    ),
                    esc_html($attachment->post_title)
                ),
				'link' => $this->generateUrl(
					'galleries',
					'view',
					array('gallery_id' => sanitize_key($request->post->get('galleryId')))
				),
            );
        }

        if($request->post->get('attachType') && $request->post->get('galleryId')) {
            $this->getModel('resources')->attach(sanitize_key($request->post->get('galleryId')), 'photo', $photos->getByAttachmentId($attachment->ID)->id,true);
        }

		$imageParams = array(
			'gallery_id' => sanitize_key($request->post->get('galleryId')),
			'attachment' => $attachment,
		);
		do_action('sgg_add_new_image_to_gallery', $imageParams);

        return $this->response(RscSgg_Http_Response::AJAX, $response);
    }

    /**
     * Add Folder Action
     * Adds the new folder
     *
     * @param RscSgg_Http_Request $request
     * @return RscSgg_Http_Response
     */
    public function addFolderAction(RscSgg_Http_Request $request)
    {
        $env = $this->getEnvironment();
        $folders = new GridGallery_Photos_Model_Folders();

        $stats = $this->getEnvironment()->getModule('stats');
        $stats->save('folders.add');

        if ($env->getConfig()->isEnvironment(
            RscSgg_Environment::ENV_DEVELOPMENT
        )
        ) {
            $folders->setDebugEnabled(true);
        }

        $folderName = sanitize_text_field($request->post->get('folder_name'));
        $viewType = sanitize_text_field($request->post->get('view_type'));

        if (!$folders->add(
            ($folderName) ? $folderName : $env->translate('New Folder')
        )
        ) {
            $response = array(
                'error' => true,
                'folder' => null,
            );
        } else {
            $folder = $env->getTwig()->render(
                sprintf('@ui/%s/folder.twig', $viewType ? $viewType : 'block'),
                array('folder' => $folders->getById($folders->getInsertId()))
            );

            $response = array(
                'error' => false,
                'folder' => $folder,
                'id' => $folders->getInsertId(),
            );
        }

        return $this->response('ajax', $response);
    }

    /**
     * Delete Action
     * Deletes the specified folders and photos
     *
     * @param RscSgg_Http_Request $request
     * @return RscSgg_Http_Response
     */
    public function deleteAction(RscSgg_Http_Request $request)
    {
        $env = $this->getEnvironment();
        $data = $request->post->get('data');
        $debug = $env->getConfig()->isEnvironment(
            RscSgg_Environment::ENV_DEVELOPMENT
        );
        $photos = new GridGallery_Photos_Model_Photos($debug);
        $folders = new GridGallery_Photos_Model_Folders($debug);

        $stats = $this->getEnvironment()->getModule('stats');

        if (!$data) {
            return $this->response(
                'ajax',
                array(
                    'error' => true,
                )
            );
        }

        foreach ($data as $type => $identifies) {
            foreach ($identifies as $id) {
                if ($type === 'photo') {
                    $stats->save('photos.delete');
                    $photos->deleteById((int)$id);
                } else {
                    $stats->save('folders.delete');
                    $folders->deleteById((int)$id);
                }
            }
        }

        return $this->response(
            'ajax',
            array(
                'error' => false,
            )
        );
    }

    public function checkPhotoUsageAction(RscSgg_Http_Request $request)
    {
        $photoId = intval($request->post->get('photo_id'));
    
        $photos = $this->getModel('photos');
        $photo = $photos->getById($photoId);
    
        $resources = $this->getModel('resources');
    
        if ($photo && $photo->folder_id > 0) {
            $galleries = $resources->getGalleriesWithFolder($photo->folder_id);
        } elseif ($photo) {
            $galleries = $resources->getGalleriesWithPhoto($photo->id);
        } else {
            $galleries = array();
        }
    
        return $this->response(RscSgg_Http_Response::AJAX, array(
            'count' => count($galleries),
        ));
    }
    
    public function rotatePhotoAction(RscSgg_Http_Request $request)
    {
        $env = $this->getEnvironment();
        $ids = array_map('intval', (array)$request->post->get('ids'));
        $rotateType = sanitize_text_field($request->post->get('rotateType'));
        $rotated = 0;
        if (!empty($ids)) {
            $photos = $this->getModel('photos');
    
            foreach ($ids as $photoId) {
                $photo = $photos->getById($photoId);
                if ($photo && $photos->rotateAttachment($photo->attachment, $rotateType)) {
                    $rotated++;
                }
            }
        }
        $this->getModule('galleries')->cleanCache(intval($request->post->get('gallery_id')));
        return $this->response(RscSgg_Http_Response::AJAX, array('message' => sprintf($env->translate('There are %d photos successfully rotated'), $rotated)));
    }
    
    public function moveAction(RscSgg_Http_Request $request)
    {
        $photos = new GridGallery_Photos_Model_Photos();
        $error = true;
    
        $photoId = intval($request->post->get('photo_id'));
        $folderId = intval($request->post->get('folder_id'));
    
        if ($photos->toFolder($photoId, $folderId)) {
            $error = false;
        }
    
        return $this->response(
            'ajax',
            array(
                'error' => $error,
            )
        );
    }
    
    public function renderAction(RscSgg_Http_Request $request)
    {
        $photos = $request->post->get('photos');
    
        if (!is_array($photos)) {
            return $this->response(
                'ajax',
                array(
                    'error' => true,
                    'photos' => null,
                )
            );
        }
    
        $renders = array();
    
        foreach ($photos as $photo) {
            $renders[] = $this->getEnvironment()->getTwig()->render(
                '@photos/includes/photo.twig', array('photo' => $photo)
            );
        }
    
        return $this->response(
            'ajax',
            array(
                'error' => false,
                'photos' => $renders,
            )
        );
    }
    
    public function updateTitleAction(RscSgg_Http_Request $request)
    {
        $env = $this->getEnvironment();
        $folders = new GridGallery_Photos_Model_Folders();
        $title = sanitize_text_field($request->post->get('folder_name'));
        $folderId = intval($request->post->get('folder_id'));
    
        if (empty($title)) {
            return $this->response(
                'ajax',
                array(
                    'error' => true,
                    'message' => $env->translate('The title can\'t be empty'),
                )
            );
        }
    
        if ($folders->updateTitle($folderId, $title)) {
            return $this->response(
                'ajax',
                array(
                    'error' => false,
                    'message' => $env->translate('Title successfully updated'),
                )
            );
        }
    
        return $this->response(
            'ajax',
            array(
                'error' => true,
                'message' => $env->translate(
                    'Unable to update the title. Try again later'
                ),
            )
        );
    }
    
    public function isEmptyAction()
    {
        $debugEnabled = $this->getEnvironment()->isDev();
    
        $isEmpty = true;
        $photos = new GridGallery_Photos_Model_Photos($debugEnabled);
    
        $photoCount = $photos->getAllImgCount();
    
        if ($photoCount > 0) {
            $isEmpty = false;
        }    
        return $this->response(
            RscSgg_Http_Response::AJAX,
            array(
                'isEmpty' => $isEmpty,
            )
        );
    }
    
    protected function beforeUpdateAttachment(RscSgg_Http_Request $request){
     $photos = $this->getModel('photos');    
        if ($replaceAttachmentId = intval($request->post->get('replace_attachment_id'))) {
            $gallery = $this->getModule('galleries');
            $replacePost = get_post($replaceAttachmentId);
            $newAttachId = $gallery->media_sideload_image($replacePost->guid, 0);
            $photos->updateAttachmentId(intval($request->post->get('image_id')), $newAttachId);
            $request->post->set('attachment_id', $newAttachId);
            $request->post->set('replace_attachment_id', null);
        }
    }
    
    public function updateAttachmentAction(RscSgg_Http_Request $request) {
    
        $photos = $this->getModel('photos');
    
        $alt = sanitize_text_field($request->post->get('alt'));
        $attachmentId = intval($request->post->get('attachment_id'));
        $replaceAttachmentId = intval($request->post->get('replace_attachment_id'));
        if ($replaceAttachmentId) {
            $photos->updateAttachmentId(intval($request->post->get('image_id')), $replaceAttachmentId);
            $attachmentId = $replaceAttachmentId;
        }
        $caption = sanitize_text_field($request->post->get('caption'));
        $description = sanitize_textarea_field($request->post->get('description'));
        $target = sanitize_text_field($request->post->get('target', '_self'));
        $link = esc_url_raw($request->post->get('link'));
        $captionEffect = sanitize_text_field($request->post->get('captionEffect'));
        $cropPosition = sanitize_text_field($request->post->get('cropPosition'));
    
        if ($link) {
            if (!empty($request->post->get('rel', ''))) {
                $rel = implode(' ', array_map('sanitize_text_field', (array)$request->post->get('rel', '')));
            } else {
                $rel = '';
            }
        } else {
            $rel = '';
        }
    
        $update = array();
        if (!is_null($request->post->get('alt'))) $update['alt'] = (empty($alt) ? " " : $alt);
        if (!is_null($request->post->get('caption'))) $update['caption'] = $caption;
        if (!is_null($request->post->get('description'))) $update['description'] = $description;
        if (!is_null($request->post->get('captionEffect'))) $update['captionEffect'] = $captionEffect;
        if (!is_null($request->post->get('cropPosition'))) $update['cropPosition'] = $cropPosition;
        if (!is_null($request->post->get('link'))) {
            $update['link'] = $link;
            $update['target'] = $target;
            $update['rel'] = $rel;
        }
        $update = $this->getEnvironment()->getDispatcher()->applyFilters('before_update_photo_attachment', $update, $attachmentId);
        $photos->updateMetadata($attachmentId, $update);
    
        $this->getModule('galleries')->cleanCache(intval($request->post->get('gallery_id')));
    
        return $this->response(RscSgg_Http_Response::AJAX);
    }
    
    public function updatePositionAction(RscSgg_Http_Request $request)
    {
        $response = $this->getErrorResponseData(
            $this->translate('Failed to update position.')
        );
        $data = (array)$request->post->get('data');
    
        if ($this->getModel('position')->replacePosition($data)) {
            $response = $this->getSuccessResponseData(
                $this->translate('Position updated successfully!')
            );
        }
        $this->getModule('galleries')->cleanCache($data['scope_id']);
    
        return $this->response(RscSgg_Http_Response::AJAX, $response);
    }
}