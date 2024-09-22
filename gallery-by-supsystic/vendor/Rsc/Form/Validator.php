<?php


class RscSgg_Form_Validator
{

    const METHOD_POST = 'post';
    const METHOD_GET = 'query';

    /**
     * @var RscSgg_Http_Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var RscSgg_Common_Collection
     */
    protected $rules;

    /**
     * @var RscSgg_Common_Collection
     */
    protected $filters;

    /**
     * @var array
     */
    public $errors;

    /**
     * Constructor
     * @param RscSgg_Http_Request $request
     * @param string|null $method
     * @param RscSgg_Common_Collection|array $rules
     * @param RscSgg_Common_Collection|array $filters
     */
    public function __construct(RscSgg_Http_Request $request = null, $method = null, array $rules = array(), array $filters = array())
    {
        $this->request = ($request === null ? RscSgg_Http_Request::create() : $request);
        $this->method = ($method === null ? self::METHOD_POST : $this->prepareMethod($method));
        $this->rules = $rules;
        $this->filters = $filters;
    }

    /**
     * Is valid HTTP request?
     * @return bool
     * @throws RscSgg_Form_Validator_EmptyRequestException If HTTP request is not specified
     */
    public function isValid()
    {
        if (!$this->request) {
            throw new RscSgg_Form_Validator_EmptyRequestException('You must submit the request via ' . __CLASS__ . '::setRequest() method');
        }

        /** @var RscSgg_Http_Parameters $method */
        $method = $this->request->{$this->method};

        if ($method->isEmpty()) {
            return false;
        }

        $this->applyFilters($method);
        $this->doValidation($method);

        if (!empty($this->errors) && count($this->errors) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Set current HTTP request for validation
     * @param RscSgg_Http_Request $request
     * @return RscSgg_Form_Validator
     */
    public function setRequest(RscSgg_Http_Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Returns object of current HTTP request
     * @return RscSgg_Http_Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set HTTP method to validate
     * @param string $method HTTP method to validate (POST or GET)
     * @throws RscSgg_Form_Validator_InvalidMethodException If specified method is wrong
     * @return RscSgg_Form_Validator
     */
    public function setMethod($method)
    {
        $method = $this->prepareMethod($method);

        if (!in_array($method, array(self::METHOD_GET, self::METHOD_POST))) {
            throw new RscSgg_Form_Validator_InvalidMethodException('Invalid method. Type of the method can only be POST or GET (QUERY)');
        }

        return $this;
    }

    /**
     * Get HTTP method to validate
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Set fields filters
     * @param array $filters
     * @return RscSgg_Form_Validator
     */
    public function setFilters(array $filters)
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Returns an array of filters
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Set validation rules
     * @param array $rules
     * @return RscSgg_Form_Validator
     */
    public function setRules(array $rules)
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Returns an array of validation rules
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Returns an array of errors
     * @return array
     */
    public function getErrors()
    {
        $errors = array();

        if (!$this->errors) {
            return null;
        }

        foreach ($this->errors as $error) {
            $errors = array_merge($errors, $error);
        }

        return $errors;
    }

    /**
     * Returns an array of errors for specified field
     * @param string $field Field name
     * @return array|null
     */
    public function getFieldErrors($field)
    {
        if (isset($this->errors[$field])) {
            return $this->errors[$field];
        }

        return null;
    }

    /**
     * Applies the specified filters
     * @param RscSgg_Http_Parameters $method
     */
    protected function applyFilters(RscSgg_Http_Parameters $method)
    {
        if (empty($this->filters)) {
            return;
        }

        /** @var RscSgg_Form_Filter_Interface $filter */
        foreach ($this->filters as $field => $filters) {

            if (!is_array($this->filters[$field])) {
                $filters = array($filters);
            }

            foreach ($filters as $filter) {
                if ($method->has($field)) {
                    $data = $filter->apply($method->get($field));

                    $method->set($field, $data);
                }
            }
        }
    }

    /**
     * Do validation
     * @param RscSgg_Http_Parameters $method
     */
    protected function doValidation(RscSgg_Http_Parameters $method)
    {
        if (empty($this->rules)) {
            return;
        }

        /** @var RscSgg_Form_Rule_Interface $rule */
        foreach ($this->rules as $field => $rules) {

            if (!is_array($this->rules[$field])) {
                $rules = array($rules);
            }

            foreach ($rules as $rule) {
                if ($method->has($field)) {
                    if (!$rule->validate($method->get($field))) {

                        if (!is_array($this->errors)) {
                            $this->errors = array();
                        }

                        $this->errors[$field][] = $rule->getMessage();
                    }
                }
            }
        }
    }

    /**
     * Prepare specified HTTP method
     * @param string $method HTTP method
     * @return string
     */
    protected function prepareMethod($method)
    {
        return str_replace('get', 'query', strtolower(trim($method)));
    }
}