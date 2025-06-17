<?php


class GridGallery_Core_BaseModel extends RscSgg_Mvc_Model implements RscSgg_Logger_AwareInterface
{

    /**
     * @var bool
     */
    protected $debugEnabled;

    /**
     * @var string
     */
    protected $lastError;

    /**
     * @var int
     */
    protected $insertId;

    /**
     * @var RscSgg_Logger_Interface
     */
    protected $logger;

    /**
     * @var RscSgg_Environment
     */
	protected $environment;

	public function setEnvironment($environment) {
		$this->environment = $environment;
	}
	
	public function translate($str) {
		if($this->environment && method_exists($this->environment, 'translate')) {
			return $this->environment->translate($str);
		}
		return $str;
	}

    /**
     * Sets the debug mode enabled
     *
     * @param bool $debugEnabled
     * @return GridGallery_Core_BaseModel
     */
    public function setDebugEnabled($debugEnabled)
    {
        $this->debugEnabled = $debugEnabled;
        return $this;
    }

    /**
     * Returns the last insert id
     *
     * @return int
     */
    public function getInsertId()
    {
        return $this->insertId;
    }

    /**
     * Returns the last MySQL error
     *
     * @return string|null
     */
    public function getLastError()
    {
        if (!$this->lastError) {
            $this->lastError = $this->db->last_error;
        }

        return $this->lastError;
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

}
