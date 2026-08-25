<?php

class GridGallery_GalleryGroups_Controller extends GridGallery_Core_BaseController
{
  protected function getModelAliases()
  {
    return [
      'groups' => 'GridGallery_GalleryGroups_Model_Groups',
      'galleries' => 'GridGallery_Galleries_Model_Galleries',
    ];
  }

  public function requireNonces()
  {
    return ['saveAction', 'deleteAction'];
  }

  public function indexAction(RscSgg_Http_Request $request)
  {
    $page = max(1, (int) $request->query->get('paged', 1));
    $perPage = min(100, max(1, (int) $request->query->get('per_page', 20)));
    $sort = (string) $request->query->get('sort', 'group_id');
    $dir = (string) $request->query->get('dir', 'desc');
    $search = trim((string) $request->query->get('s', ''));
    $result = $this->getModel('groups')->getPaginatedList($page, $perPage, $sort, $dir, $search);

    return $this->response('@gallerygroups/index.twig', [
      'groups' => $result['rows'],
      'recordsTotal' => $result['total'],
      'page' => $page,
      'perPage' => $perPage,
      'sort' => $sort,
      'dir' => strtolower($dir) === 'asc' ? 'asc' : 'desc',
      'search' => $search,
    ]);
  }

  public function createAction(RscSgg_Http_Request $request)
  {
    return $this->editAction($request);
  }

  public function editAction(RscSgg_Http_Request $request)
  {
    $groupId = (int) $request->query->get('group_id');
    $groups = $this->getModel('groups');
    $group = $groupId ? $groups->getById($groupId) : null;

    if (!$group) {
      $group = (object) [
        'group_id' => 0,
        'name' => '',
        'active' => 1,
      ];
    }

    $galleries = $this->getModel('galleries')->getList();
    $selectedGalleries = $groupId ? $groups->getGalleryIds($groupId) : [];

    return $this->response('@gallerygroups/form.twig', [
      'group' => $group,
      'galleries' => $galleries,
      'selectedGalleries' => $selectedGalleries,
      'selectedGalleryChoices' => $this->buildGalleryChoices($galleries, $selectedGalleries),
    ]);
  }

  public function saveAction(RscSgg_Http_Request $request)
  {
    $groups = $this->getModel('groups');
    $groupId = $groups->saveFromRequest($request);

    return $this->redirect($this->generateUrl('gallerygroups', 'edit', [
      'group_id' => $groupId,
      'message' => 'saved',
    ]));
  }

  public function deleteAction(RscSgg_Http_Request $request)
  {
    $this->getModel('groups')->delete((int) $request->query->get('group_id'));

    return $this->redirect($this->generateUrl('gallerygroups'));
  }

  protected function buildGalleryChoices($galleries, array $selectedIds)
  {
    $selectedIds = array_map('intval', $selectedIds);
    $choices = [];

    foreach ((array) $galleries as $gallery) {
      if (in_array((int) $gallery->id, $selectedIds, true)) {
        $choices[] = [
          'id' => (int) $gallery->id,
          'text' => '#' . (int) $gallery->id . ' ' . $gallery->title,
        ];
      }
    }

    return $choices;
  }
}
