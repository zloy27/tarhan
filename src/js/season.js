import '../scss/season.scss';
import { initPageNavigation } from './components/page-navigation.js';

initPageNavigation();

document.querySelectorAll('.header__title-a').forEach((link) => {
    const isArchive = /\/(archive(?:\.html)?\/?)(?:#.*)?$/.test(new URL(link.href, location.href).pathname);
    link.classList.toggle('active', isArchive);
    if (isArchive) link.setAttribute('aria-current', 'true');
    else link.removeAttribute('aria-current');
});

// Vite преобразует src изображения при сборке. Открываем этот же файл по ссылке.
document.querySelectorAll('.season__gallery a').forEach((link) => {
    const photo = link.querySelector('img');
    if (photo && link.getAttribute('href')?.startsWith('./src/assets/')) link.href = photo.src;
});
