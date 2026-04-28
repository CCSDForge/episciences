<?php

trait Ccsd_Form_Trait_MultiOptions {
    
    use Ccsd_Form_Trait_Populate;
    
    public function init ()
    {
        if ($this->isPopulate()) {
            $this->build();
            $this->options = $this->getData();
        }
    }
    
 }