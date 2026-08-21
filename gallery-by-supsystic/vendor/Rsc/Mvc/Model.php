<?php

class RscSgg_Mvc_Model
{
  /**
   * @var wpdb
   */
  protected $db;

  /**
   * Constructor
   */
  public function __construct()
  {
    global $wpdb;

    $this->db = $wpdb;
  }

  /**
   * Do query with the dbDelta function.
   * On MySQL a malformed statement just sets $wpdb->last_error and execution
   * continues; the SQLite compatibility layer (WordPress Playground, WP Studio)
   * instead throws, so we catch it to keep behavior consistent across engines.
   * @param string $query MySQL query
   * @return mixed
   */
  public function delta($query)
  {
    if (!function_exists('dbDelta')) {
      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    try {
      return @dbDelta($query);
    } catch (Throwable $e) {
      $this->logDbError($e->getMessage(), $query);
      return false;
    }
  }

  /**
   * Runs a raw query, catching driver exceptions instead of letting them fatal
   * the request. Keeps DDL/upgrade queries fault-tolerant the same way they
   * already silently are on MySQL.
   * @param string $query
   * @return int|bool
   */
  public function safeQuery($query)
  {
    try {
      return $this->db->query($query);
    } catch (Throwable $e) {
      $this->logDbError($e->getMessage(), $query);
      return false;
    }
  }

  /**
   * Logs a failed query when debugging is enabled.
   * @param string $message
   * @param string $query
   */
  protected function logDbError($message, $query)
  {
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log(sprintf('[Supsystic Gallery] Query failed: %s | Query: %s', $message, $query));
    }
  }

  /**
   * Detects whether the site runs on the SQLite compatibility layer (e.g.
   * WordPress Playground, WP Studio, the sqlite-database-integration plugin)
   * instead of a real MySQL/MariaDB server.
   * @return bool
   */
  public function isSqlite()
  {
    if (defined('DB_ENGINE') && 'sqlite' === DB_ENGINE) {
      return true;
    }

    return is_a($this->db, 'WP_SQLite_DB');
  }

  /**
   * Returns an instance of Query Builder
   * @return BarsMaster_ChainQueryBuilder
   */
  public function getQueryBuilder()
  {
    return new BarsMaster_ChainQueryBuilder();
  }

  /**
   * Returns an instance of Wpdb
   * @return \wpdb
   */
  public function getDb()
  {
    return $this->db;
  }

  /**
   * Returns the database connection resource if it is accessible
   * @return null|resource
   */
  public function getDatabaseHandler()
  {
    if ($this->isAccessibleDbConnection()) {
      return $this->db->dbh;
    }

    return null;
  }

  /**
   * Checks whether the table is exists
   * @param string $table The name of the table
   * @return bool TRUE if the table is exists, FALSE otherwise
   */
  public function isTableExists($table)
  {
    return $this->db->get_var($this->db->prepare('SHOW TABLES LIKE %s', $this->db->esc_like($table))) === $table;
  }

  /**
   * Checks whether we can access to the database connection
   * @return bool TRUE if we can, FALSE otherwise
   */
  public function isAccessibleDbConnection()
  {
    if (!method_exists($this->db, '__get')) {
      return false;
    }

    if (!is_resource($this->db->dbh)) {
      return false;
    }

    return true;
  }
}
