<?php 

class Ccsd_View_Helper_AutoComplete  extends ZendX_JQuery_View_Helper_AutoComplete  {

    protected $_events  = "{}";
    protected $_methods = "{}"; 
    
    protected function _prepareEvents ($events)
    {
        if (!empty ($events)) {
            $this->_events = "{";
            foreach ($events as $k => $event) {
                $filepath = realpath (APPLICATION_PATH . '/../public' . $event);
                if ($filepath) {
                    $this->_events .= "\"$k\": function (event, ui) { " . file_get_contents(APPLICATION_PATH . '/../public' . $event) . " },";
                }
            }
            $this->_events .= "}";
        } 
    }
    
    protected function _prepareMethods ( $methods )
    {
        if (!empty ($methods)) {
            $this->_methods = "{";
            foreach ($methods as $k => $method) {
            	
                $filepath = realpath (APPLICATION_PATH . '/../public' . $method);
                if ($filepath) {
                    $this->_methods .= "\"$k\": function (event, ui) { ";
                    $this->_methods .= file_get_contents(APPLICATION_PATH . '/../public' . $method);
                    $this->_methods .= " },";
                }
            }
            $this->_methods .= "}";
        }
    }
    
    /**
     * Helps with building the correct Attributes Array structure.
     *
     * @param String $id
     * @param String $value
     * @param Array $attribs
     * @return Array $attribs
     */
    protected function _prepareAttributes($id, $value, $attribs)
    {
        if(!isset($attribs['id'])) {
            $attribs['id'] = $id;
        }
        $attribs['name']  = $id;
        $attribs['value'] = "";
    
        return $attribs;
    }
    
    /**
     * Builds an AutoComplete ready input field.
     *
     * This view helper builds an input field with the {@link Zend_View_Helper_FormText} FormText
     * Helper and adds additional javascript to the jQuery stack to initialize an AutoComplete
     * field. Make sure you have set one out of the two following options: $params['data'] or
     * $params['url']. The first one accepts an array as data input to the autoComplete, the
     * second accepts an url, where the autoComplete content is returned from. For the format
     * see jQuery documentation.
     *
     * @link   http://docs.jquery.com/UI/Autocomplete
     * @throws ZendX_JQuery_Exception
     * @param  String $id
     * @param  String $value
     * @param  array $params
     * @param  array $events
     * @param  array $methods
     * @param  array $attribs
     * @return String
     */
    public function autoComplete($id, $value = null, array $params = array(), array $events = array(), array $methods = array(), array $attribs = array())
    {
        $attribs = $this->_prepareAttributes($id, $value, $attribs);
        
        if (!isset($params['source'])) {
            if (isset($params['url'])) {
                $params['source'] = $params['url'];
                unset($params['url']);
            } else if (isset($params['data'])) {
                $params['source'] = $params['data'];
                unset($params['data']);
            } else {
                require_once "ZendX/JQuery/Exception.php";
                throw new ZendX_JQuery_Exception(
                        "Cannot construct AutoComplete field without specifying 'source' field, ".
                        "either an url or an array of elements."
                );
            }
        }
        
        $this->_prepareEvents  ( $events  );
        $this->_prepareMethods ( $methods );
        
        $options = uniqid('options');
       
        $js = sprintf('
                
                var %s = %s.extend(%s,%s,%s); 
                
                %s(document).on("focus", ":input[id=\'%s\']", function () { 
                    %s(this).autocomplete(%s).data("ui-autocomplete")._renderItem = function (ul, item) {
                        return %s("<li>").append("<a>" + item.label + "</a>").appendTo(ul);
                    }; 
                });
       
                
                ', 
                $options,
                ZendX_JQuery_View_Helper_JQuery::getJQueryHandler(),
                ZendX_JQuery::encodeJson($params), 
                $this->_events, 
                $this->_methods,
                ZendX_JQuery_View_Helper_JQuery::getJQueryHandler(), 
                $attribs['id'],
                ZendX_JQuery_View_Helper_JQuery::getJQueryHandler(),
                $options,
                ZendX_JQuery_View_Helper_JQuery::getJQueryHandler()
        );

        $this->jquery->addOnLoad($js);
    
        return $this->view->formText($id, $value, $attribs);
    }
}