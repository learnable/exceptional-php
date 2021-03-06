<?php

class ExceptionalData
{
    
    protected $exception;
    protected $backtrace = array();
    
    function __construct(Exception $exception)
    {
        $this->exception = $exception;
        
        $trace = $this->exception->getTrace();
        foreach ($trace as $t) {
            if (!isset($t["file"])) continue;
            $this->backtrace[] = "$t[file]:$t[line]:in `$t[function]\'";
        }
    }

    function uniqueness_hash()
    {
        return md5(implode("", $this->backtrace));
    }

    function to_json()
    {
        // environment data
        $data = ExceptionalEnvironment::to_array();
        
        // exception data
        $message = $this->exception->getMessage();
        $now = date("D M j H:i:s O Y");
        
        // spoof 404 error
        $error_class = get_class($this->exception);
        if ($error_class == "Http404Error") {
            $error_class = "ActionController::UnknownAction";
        }

        $data["exception"] = array(
            "exception_class" => $error_class,
            "message" => $message,
            "backtrace" => $this->backtrace,
            "occurred_at" => $now
        );
        
        // context
        $context = Exceptional::$context;
        if (!empty($context)) {
            $data["context"] = $context;
        }
        
        // request data
        $session = (!is_null($_SESSION)) ? $_SESSION : array("session_id" => "", "data" => array());
        
        // sanitize headers
        $headers = getallheaders();
        if (isset($headers["Cookie"])) {
          $headers["Cookie"] = preg_replace("/PHPSESSID=\S+/", "PHPSESSID=[FILTERED]", $headers["Cookie"]);
        }
        
        // must set these
        $params = $_REQUEST;
        if (!isset($params["controller"])) {
            $params["controller"] = "";
        }
        if (!isset($params["action"])) {
            $params["action"] = "";
        }
        
        $protocol = (!empty($_SERVER["HTTPS"]) && @$_SERVER["HTTPS"] != "off") ? "https://" : "http://";
        $data["request"] = array(
            "url" => "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
            "controller" => "",
            "action" => "",
            "parameters" => $params,
            "request_method" => strtolower($_SERVER["REQUEST_METHOD"]),
            "remote_ip" => $_SERVER["REMOTE_ADDR"],
            "headers" => $headers,
            "session" => $session
        );
        
        //var_dump($data);
        
        return json_encode($data);
    }

}

// http://php.net/manual/en/function.getallheaders.php
if (!function_exists("getallheaders"))
{
    function getallheaders()
    {
       foreach ($_SERVER as $name => $value) {
           if (substr($name, 0, 5) == "HTTP_") {
               $headers[str_replace(" ", "-", ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
           }
       }
       return $headers;
    }
}
