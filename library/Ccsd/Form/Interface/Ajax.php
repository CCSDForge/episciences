<?php

interface Ccsd_Form_Interface_Ajax
{
    public function setUrl ($url);
    public function setSettings ($settings);
    public function setAccepts ($accepts);
    public function setAsync ($async);
    //public function setBeforeSend ($beforeSend);
    public function setCache ($cache);
    public function setComplete ($complete);
    /*public function setContent ($content);
    public function setContentType ($contentType);
    public function setContext ($context);
    public function setConverters ($converters);
    public function setCrossDomain ($crossDomain);*/
    public function setData ($data);
    public function setDataFilter ($dataFilter);
    public function setDataType ($dataType);
    /*public function setError ($error);
    public function setGlobal ($global);
    public function setHeaders ($headers);
    public function setIfModified ($idModified);
    public function setIsLocal ($isLocal);
    public function setJsonp ($jsonp);
    public function setJsonpCallback ($jsonpCallback);
    public function setMimeType ($mimeType);
    public function setPassword ($password);
    public function setProcessData ($processData);
    public function setScriptCharset ($scriptCharset);
    public function setStatusCode ($statusCode);*/
    public function setSuccess ($success);
    public function setTimeout ($timeout);
    //public function setTraditional ($traditional);
    public function setType ($type);
    /*public function setUsername ($username);
    public function setXhr ($xhr);
    public function setXhrFields ($xhrFields);*/    
}