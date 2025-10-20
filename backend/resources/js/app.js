import './bootstrap';
import Alpine from 'alpinejs';
import 'trix';
import 'trix/dist/trix.css';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('trix-file-accept', (event) => {
    event.preventDefault();
});
