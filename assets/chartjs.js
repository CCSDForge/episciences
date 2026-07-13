// Chart.js 2.9.4 CSS, previously loaded from cdnjs (VENDOR_CHART_CSS), now self-hosted via webpack.
// Only used on the journal statistics page (stats/index).
import 'chart.js/dist/Chart.min.css';
// Chart.js 2.9.4 JS, previously loaded from cdnjs (VENDOR_CHART). Exposed as window.Chart, used as a
// bare global in public/js/stats/common.js.
import Chart from 'chart.js';
// Chart.js Datalabels plugin 0.7.0, previously loaded from cdnjs (VENDOR_CHART_PLUGIN_DATALABELS).
// Self-registers against the Chart.js instance it requires internally, which webpack resolves to the
// same bundled chart.js module as the import above.
import 'chartjs-plugin-datalabels';

window.Chart = Chart;
