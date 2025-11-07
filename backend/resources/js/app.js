import './bootstrap';
import Alpine from 'alpinejs';
import dashboardPendings from './components/dashboard-pendings';
import dashboardMetrics from './components/dashboard-metrics';
import dashboardAlerts from './components/dashboard-alerts';
import clinicalHistory from './components/clinical-history';
import 'trix';
import 'trix/dist/trix.css';

window.Alpine = Alpine;

Alpine.data('dashboardPendings', dashboardPendings);
Alpine.data('dashboardMetrics', dashboardMetrics);
Alpine.data('dashboardAlerts', dashboardAlerts);
Alpine.data('clinicalHistory', clinicalHistory);

Alpine.start();

document.addEventListener('trix-file-accept', (event) => {
    event.preventDefault();
});
