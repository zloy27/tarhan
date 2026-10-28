import '../scss/ambassadors.scss';
import { initPageNavigation } from './components/page-navigation.js';
import { initCardReveal, onPageReady } from './components/card-reveal.js';

onPageReady(() => {
  initCardReveal('.ambassadors-main__card', 'ambassadors-main__card--visible', 140);
  initPageNavigation();
});