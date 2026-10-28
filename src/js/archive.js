import '../scss/archive.scss';
import { initPageNavigation } from './components/page-navigation.js';
import { initCardReveal, onPageReady } from './components/card-reveal.js';

onPageReady(() => {
  initCardReveal('.seasons__card', 'seasons__card--visible', 200);
  initPageNavigation();
});