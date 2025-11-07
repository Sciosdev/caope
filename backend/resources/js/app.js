import './bootstrap';
import Alpine from 'alpinejs';
import dashboardPendings from './components/dashboard-pendings';
import dashboardMetrics from './components/dashboard-metrics';
import dashboardAlerts from './components/dashboard-alerts';
import hereditaryHistory from './components/hereditary-history';
import 'trix';
import 'trix/dist/trix.css';

window.Alpine = Alpine;

Alpine.data('dashboardPendings', dashboardPendings);
Alpine.data('dashboardMetrics', dashboardMetrics);
Alpine.data('dashboardAlerts', dashboardAlerts);
Alpine.data('hereditaryHistory', hereditaryHistory);

Alpine.start();

document.addEventListener('trix-file-accept', (event) => {
    event.preventDefault();
});
