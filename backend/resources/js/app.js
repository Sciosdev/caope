import './bootstrap';
import Alpine from 'alpinejs';
import dashboardPendings from './components/dashboard-pendings';
import dashboardMetrics from './components/dashboard-metrics';
import dashboardAlerts from './components/dashboard-alerts';
import 'trix';
import 'trix/dist/trix.css';

window.Alpine = Alpine;

Alpine.data('dashboardPendings', dashboardPendings);
Alpine.data('dashboardMetrics', dashboardMetrics);
Alpine.data('dashboardAlerts', dashboardAlerts);

Alpine.start();

document.addEventListener('trix-file-accept', (event) => {
    event.preventDefault();
});
