import './bootstrap';
import Alpine from 'alpinejs';
import dashboardPendings from './components/dashboard-pendings';
import 'trix';
import 'trix/dist/trix.css';

window.Alpine = Alpine;

Alpine.data('dashboardPendings', dashboardPendings);

Alpine.start();

document.addEventListener('trix-file-accept', (event) => {
    event.preventDefault();
});
