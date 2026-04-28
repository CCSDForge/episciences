<?php

interface Ccsd_Form_Interface_Javascript
{
    /**
     * @param string $code
     * @return mixed
     */
    public function addDocumentReady ($code);

    /**
     * @param $function
     * @return mixed
     */
    public function addFunction ($function);

    /**
     * @param null $type
     * @param null $name
     * @return mixed
     */
    public function getJavascript ($type = null, $name = null);

    /**
     * @param $js
     * @param null $type
     * @param null $name
     * @return mixed
     */
    public function setJavascript ($js, $type = null, $name = null);

    /**
     * @return mixed
     */
    public function clearJavascript ();
}