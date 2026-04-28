<?php

class Ccsd_Form_Decorator_Lang_Keyword extends Ccsd_Form_Decorator_Lang
{
    public $values = array();
    public $indice;
    
    public function buildJSchange ()
    {
        return <<<JAVASCRIPT
function %%FCT_NAME%% (elm, name) {
    var code = $(elm).attr('val');
    var libelle = $(elm).html();
   
    $(elm).closest(".btn-group").find("button").val(code);
    var textNode = $(elm).closest(".btn-group").find("button").contents().first();            
    textNode.replaceWith(libelle);  
                         
    $(elm).closest('.input-group').find('input').attr('lang', code);
    $(elm).closest('.input-group').find('input').attr('name', name + "[" + code + "][]");            

    return false;
}
JAVASCRIPT;
        
    }
}