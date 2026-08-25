<?php

class GridGallery_GalleryGroups_Model_Groups extends GridGallery_Core_BaseModel
{
  protected $table;
  protected $relationsTable;

  public function __construct($debugEnabled = false)
  {
    parent::__construct($debugEnabled);

    $this->table = $this->db->prefix . 'gg_gallery_groups';
    $this->relationsTable = $this->db->prefix . 'gg_gallery_group_relations';
  }

  public function getPaginatedList($page = 1, $perPage = 20, $sortColumn = 'group_id', $sortDir = 'DESC', $search = '')
  {
    $sortableColumns = [
      'group_id' => 'group_id',
      'name' => 'name',
      'active' => 'active',
      'created_at' => 'created_at',
      'updated_at' => 'updated_at',
    ];

    $page = max(1, (int) $page);
    $perPage = min(100, max(1, (int) $perPage));
    $offset = ($page - 1) * $perPage;
    $sortColumn = array_key_exists($sortColumn, $sortableColumns) ? $sortableColumns[$sortColumn] : 'group_id';
    $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';
    $search = trim((string) $search);

    $where = '';
    $args = [];
    if ($search !== '') {
      $where = ' WHERE name LIKE %s';
      $args[] = '%' . $this->db->esc_like($search) . '%';
    }

    $query = "SELECT * FROM `{$this->table}`{$where} ORDER BY `{$sortColumn}` {$sortDir} LIMIT %d OFFSET %d";
    $args[] = $perPage;
    $args[] = $offset;
    $rows = $this->db->get_results($this->db->prepare($query, $args));

    $totalQuery = "SELECT COUNT(*) FROM `{$this->table}`{$where}";
    $total = $search === ''
      ? (int) $this->db->get_var($totalQuery)
      : (int) $this->db->get_var($this->db->prepare($totalQuery, '%' . $this->db->esc_like($search) . '%'));

    return ['rows' => $rows, 'total' => $total];
  }

  public function getAll()
  {
    return $this->db->get_results("SELECT * FROM `{$this->table}` ORDER BY name ASC");
  }

  public function getActive()
  {
    return $this->db->get_results("SELECT * FROM `{$this->table}` WHERE active = 1 ORDER BY name ASC");
  }

  public function getById($groupId)
  {
    return $this->db->get_row($this->db->prepare("SELECT * FROM `{$this->table}` WHERE group_id = %d", (int) $groupId));
  }

  public function saveFromRequest(RscSgg_Http_Request $request)
  {
    $groupId = (int) $request->post->get('group_id');
    $name = sanitize_text_field((string) $request->post->get('name'));
    $active = (int) $request->post->get('active', 0) ? 1 : 0;
    $galleryIds = $request->post->get('gallery_ids', []);

    if ($name === '') {
      $name = __('Unnamed group', 'sgg');
    }

    $now = current_time('mysql');
    $data = [
      'name' => $name,
      'active' => $active,
      'updated_at' => $now,
    ];

    if ($groupId > 0 && $this->getById($groupId)) {
      $this->db->update($this->table, $data, ['group_id' => $groupId], ['%s', '%d', '%s'], ['%d']);
    } else {
      $data['created_at'] = $now;
      $this->db->insert($this->table, $data, ['%s', '%d', '%s', '%s']);
      $groupId = (int) $this->db->insert_id;
    }

    $this->setGalleryIds($groupId, $galleryIds);

    return $groupId;
  }

  public function delete($groupId)
  {
    $groupId = (int) $groupId;
    if ($groupId <= 0) {
      return false;
    }

    $this->db->delete($this->relationsTable, ['gallery_group_id' => $groupId], ['%d']);
    return (bool) $this->db->delete($this->table, ['group_id' => $groupId], ['%d']);
  }

  public function getGalleryIds($groupId)
  {
    $rows = $this->db->get_col($this->db->prepare(
      "SELECT gallery_id FROM `{$this->relationsTable}` WHERE gallery_group_id = %d",
      (int) $groupId
    ));

    return array_map('intval', is_array($rows) ? $rows : []);
  }

  public function getGroupIdsByGalleryId($galleryId)
  {
    $rows = $this->db->get_col($this->db->prepare(
      "SELECT gallery_group_id FROM `{$this->relationsTable}` WHERE gallery_id = %d",
      (int) $galleryId
    ));

    return array_map('intval', is_array($rows) ? $rows : []);
  }

  public function getActiveGroupIdsByGalleryId($galleryId)
  {
    $rows = $this->db->get_col($this->db->prepare(
      "SELECT r.gallery_group_id FROM `{$this->relationsTable}` AS r
       INNER JOIN `{$this->table}` AS g ON g.group_id = r.gallery_group_id
       WHERE r.gallery_id = %d AND g.active = 1",
      (int) $galleryId
    ));

    return array_map('intval', is_array($rows) ? $rows : []);
  }

  public function setGalleryIds($groupId, $galleryIds)
  {
    $groupId = (int) $groupId;
    if ($groupId <= 0) {
      return false;
    }

    $galleryIds = is_array($galleryIds) ? $galleryIds : [];
    $galleryIds = array_values(array_unique(array_filter(array_map('intval', $galleryIds))));

    $this->db->delete($this->relationsTable, ['gallery_group_id' => $groupId], ['%d']);
    foreach ($galleryIds as $galleryId) {
      $this->db->insert($this->relationsTable, [
        'gallery_group_id' => $groupId,
        'gallery_id' => $galleryId,
      ], ['%d', '%d']);
    }

    return true;
  }

  public function setGroupIdsForGallery($galleryId, $groupIds)
  {
    $galleryId = (int) $galleryId;
    if ($galleryId <= 0) {
      return false;
    }

    $groupIds = is_array($groupIds) ? $groupIds : [];
    $groupIds = array_values(array_unique(array_filter(array_map('intval', $groupIds))));

    $this->db->delete($this->relationsTable, ['gallery_id' => $galleryId], ['%d']);
    foreach ($groupIds as $groupId) {
      $this->db->insert($this->relationsTable, [
        'gallery_group_id' => $groupId,
        'gallery_id' => $galleryId,
      ], ['%d', '%d']);
    }

    return true;
  }
}
