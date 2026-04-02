<?php
class Tinify_Exception extends Exception
{
  public static function create($message, $type, $status)
  {
    if ($status == 401 || $status == 429) {
      $klass = 'Tinify_AccountException';
    } elseif ($status >= 400 && $status <= 499) {
      $klass = 'Tinify_ClientException';
    } elseif ($status >= 500 && $status <= 599) {
      $klass = 'Tinify_ServerException';
    } else {
      $klass = 'Tinify_Exception';
    }

    if (empty($message)) {
      $message = 'No message was provided';
    }
    return new $klass($message, $type, $status);
  }

  function __construct($message, $type = null, $status = null)
  {
    if ($status) {
      $this->status = $status;
      parent::__construct($message . ' (HTTP ' . $status . '/' . $type . ')');
    } else {
      parent::__construct($message);
    }
  }
}

class Tinify_AccountException extends Tinify_Exception {}
class Tinify_ClientException extends Tinify_Exception {}
class Tinify_ServerException extends Tinify_Exception {}
class Tinify_ConnectionException extends Tinify_Exception {}
