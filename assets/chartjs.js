// Chart.js 4, previously loaded from jsDelivr (VENDOR_CHART), now self-hosted via webpack.
// Only used on the journal statistics page (stats/index). Exposed as window.Chart, used as a
// bare global in public/js/stats/*.js. The "auto" entry registers every controller, element,
// scale and plugin, as the UMD build loaded from the CDN did. Chart.js 4 ships no CSS.
import Chart from 'chart.js/auto';
// Chart.js Datalabels plugin 2, previously loaded from jsDelivr (VENDOR_CHART_PLUGIN_DATALABELS).
// It no longer registers itself: public/js/stats/common.js adds it to the charts showing their
// values, through the window.ChartDataLabels global.
import ChartDataLabels from 'chartjs-plugin-datalabels';

window.Chart = Chart;
window.ChartDataLabels = ChartDataLabels;
