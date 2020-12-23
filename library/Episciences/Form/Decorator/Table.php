<?php
 
class Episciences_Form_Decorator_Table extends Zend_Form_Decorator_Abstract
{
	public function render ($content)
	{
		$element = $this->getElement();
		$view	= $element->getView();
		if (null === $view) {
			return $content;
		}
 
		$showHeaders = $this->getOption('showHeaders');
		$columns = $this->getOption('columns');
		$class = $this->getOption('class');
		$id = $this->getOption('id');
 
		if ($showHeaders) {

		    $columns_html = '';
		    foreach ($columns as $current_column_name) {
		        $columns_html .= '<th>'.$current_column_name.'</th>';
		    }
		    
		    $headers = '
				<thead>
					<tr>
						'.$columns_html.'
					</tr>
				</thead>
			';
		} else {
		    $headers = '';
		}
		
		$result = '
			<table class="'.$class.'" id="'.$id.'">
				'.$headers.'
				<tbody>
					'.$content.'
				</tbody>
			</table>
		';
 
		return $result;
	}
}