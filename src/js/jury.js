import '../scss/jury.scss';
import { initPageNavigation } from './components/page-navigation.js';
import { initCardReveal, onPageReady } from './components/card-reveal.js';

onPageReady(() => {
  initCardReveal('.jury-main__card', 'jury-main__card--visible', 140);
  initPageNavigation();
});