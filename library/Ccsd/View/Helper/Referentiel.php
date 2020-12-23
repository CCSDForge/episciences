<?php 

class Ccsd_View_Helper_Referentiel  extends Ccsd_View_Helper_AutoComplete  {

    protected $_core = '';
    protected $_id = '';
    protected $_multiple = false;
    
    protected function _prepareMethods ( $methods )
    {
        $id = $this->_id;

        $type = $this->_core;
        
        $id = trim($id, "[]");

        $this->_methods = <<<JAVASCRIPT
{select: function (event, ui) {
    if (ui.item.id == 0) {
        $.ajax({
            url : "/ajax/ajaxgetreferentiel/element/$id/type/$type/new/true",
            async: false,
            type : "get",
            success : function (msg) {
                $(this).attr('disabled', 'disabled');                 
                $(msg).filter('.modal').modal({keyboard : true});
            }
        });
    } else {
        var founded = false;
        
        $.each ($(event.target).closest('.form-group').find('div[data-id]'), function (i) {
            if (ui.item.id == $(this).attr('data-id')) {
                founded = true;
            }
        });
        
        var colorChanged = function (c) {
            $(event.target).closest('.form-group').find('div[data-id=' + ui.item.id + '] *').css('color', c);
            $(event.target).closest('.form-group').find('div[data-id=' + ui.item.id + '] *').css('-webkit-transition', 'color 1000ms linear');
            $(event.target).closest('.form-group').find('div[data-id=' + ui.item.id + '] *').css('-moz-transition', 'color 1000ms linear');
            $(event.target).closest('.form-group').find('div[data-id=' + ui.item.id + '] *').css('-o-transition', 'color 1000ms linear');
            $(event.target).closest('.form-group').find('div[data-id=' + ui.item.id + '] *').css('-ms-transition', 'color 1000ms linear');
            $(event.target).closest('.form-group').find('div[data-id=' + ui.item.id + '] *').css('transition', 'color 1000ms linear');
        };
        
        if (!founded) {
        
            $.ajax({
                url : "/ajax/ajaxgetreferentiel/element/$id/type/$type/id/" + ui.item.id,
                async: false,
                type : "get",
                success : function (msg) {
                
                    add_ref ('$type', '$id', msg);
                }
            });
        
        } else {
        
            colorChanged('#53bc66');
            setTimeout(function() { colorChanged('inherit'); } ,2000);
            
        } 
    }
    
    $(this).val('');
    return false;
},
focus: function (event, ui) {
                    		return false;
                    		}
}
JAVASCRIPT;
        
    }

    /**
     * @param        $id
     * @param null   $value
     * @param array  $params
     * @param array  $events
     * @param array  $methods
     * @param array  $attribs
     * @param string $core
     * @param bool   $multiple
     * @return String
     */
    public function referentiel($id, $value = null, array $params = array(), array $events = array(), array $methods = array(), array $attribs = array(), $core = '', $multiple = false)
    {
        $this->_id = $id;
        $this->_multiple = $multiple;
        $this->_core = $core;
 
        return parent::autoComplete($id, $value, $params, $events, $methods, $attribs);
    }
}

