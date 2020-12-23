<?php

/**
 * Class Ccsd_View_Helper_Chart
 */
class Ccsd_View_Helper_Chart  extends Zend_View_Helper_Abstract
{
    /* TODO ****************************************************
    * Gérer les transitions
    * Gérer les imageCharts
    * Gérer l'export CSV
    // *********************************************************/
    /** @var Hal_View */
    public $view = null;

    private $count = 0;

    private $existingTypes = array(
        'AreaChart',
        'BarChart',
        'BubbleChart',
        'CandlestickChart',
        'ColumnChart',
        'ComboChart',
        'Gauge',
        'GeoChart',
        'LineChart',
        'PieChart',
        'ScatterChart',
        'SteppedAreaChart',
        'Table',
        'TreeMap'
    );

    private $allowedTypes = array(
        'AreaChart',
        'BarChart',
        'BubbleChart',
        'ColumnChart',
        'ComboChart',
        'Gauge',
        'GeoChart',
        'LineChart',
        'PieChart',
        'ScatterChart',
        'SteppedAreaChart',
        'Table',
        'TreeMap'
    );
    /**
     * Ajoute le code necessaire a la vue pour traiter le graphe
     * Si returnJs est vrai, seul le code du graph est retourne, sans etre ajoute a la vue.
     * @param $data
     * @param string $type
     * @param bool $returnJs
     * @return string
     * @throws Exception
     */
    public function chart($data, $type = 'bar', $returnJs = false) {
        if (!in_array($type, $this->existingTypes)) {
            throw new Exception("Ce type de graphique n'existe pas : $type");
        }
        if (!in_array($type, $this->allowedTypes)) {
            throw new Exception("Ce type de graphique n'est pas supporté : $type");
        }
        if (!isset($data['content'])) {
            throw new Exception("Aucune donnée à afficher pour ce graphique : $type");
        }
        $this->count++;
        if ($returnJs) {
            // On veut juste le javascript, pas d'inscription de script dans le header...
            // Bon, pas sur que ce soit bien d'avoir ce flag...
            // Le traitement du cookieWatcher doit etre fait ici ou ailleurs...
            $script = '<script>';
            $script .= $this->toJs($data, $type);
            $script .=  '</script>';
            return $script;
        }
        // Mise en place du script google jsapi
        $this->view->headScript('https://www.gstatic.com/charts/loader.js');
        $this->view->jQuery()->addJavascript('google.charts.load("current", {packages: ["corechart"]});');
        $this->view->jQuery()->addJavascript('google.charts.setOnLoadCallback(drawChart);');
        $this->draw($data, $type);
        return "";
    }

    /**
     * Render le container et retourne le script necessaire.
     * @param $data
     * @param $type
     * @return string
     */
    private function toJs($data, $type) {
        $script = 'var data = google.visualization.arrayToDataTable(' . Zend_Json::encode($data['content']) . ');';
        if (isset($data['options'])) {
            $options = Zend_Json::encode($data['options']);
            $script .= "var options = $options;";
        } else {
            $script .= "var options = '';";
        }

        if (isset($data['container'])) {
            $container = $data['container'];
        } else {
            echo '<div id="chart_' . $this->count . '"></div>';
            $container = 'chart_' . $this->count;
        }

        $script .= "var chart = new google.visualization." . $type . "(document.getElementById('$container'));";
        $script .= "chart.draw(data, options);";
        $function = 'function draw' . $type . '() { ' . $script . '}' . "\n";
        $function .= 'google.charts.setOnLoadCallback(draw' . $type . ');';
        return $function;
    }

    /**
     * @param $data
     * @param $type
     */
    private function draw($data, $type)
    {
        // Load the Visualization API and the piechart package.
        if ($type == 'Gauge') {
            $package = 'gauge';
        } elseif ($type == 'GeoChart') {
            $package = 'geochart';
        } elseif ($type == 'Table') {
            $package = 'table';
        } elseif ($type == 'TreeMap') {
            $package = 'treemap';
        } else {
            $package = 'corechart';
        }

        // Set a callback to run when the Google Visualization API is loaded.
        $script = $this->toJs($data, $type);
        $this->view->jQuery()->addJavascript("google.charts.load('current', {packages: ['$package']});");
        $this->view->jQuery()->addJavascript($script);
    }
}