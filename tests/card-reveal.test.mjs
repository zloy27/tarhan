import assert from 'node:assert/strict';
import { initCardReveal, onPageReady } from '../src/js/components/card-reveal.js';

const cards = Array.from({ length: 4 }, () => ({ dataset: {}, classList: new Set() }));
const timers = [];
let observe;
const removed = [];
globalThis.IntersectionObserver = class {
  constructor(callback) { observe = callback; }
  observe() {}
  unobserve(card) { removed.push(card); }
};
globalThis.window = {
  IntersectionObserver,
  matchMedia: () => ({ matches: false }),
  setTimeout: (callback) => timers.push(callback),
};
globalThis.document = { querySelectorAll: () => cards, readyState: 'complete' };
let ready = false;
onPageReady(() => { ready = true; });
assert.equal(ready, true, 'Late-loaded modules still initialize');
initCardReveal('.card', 'visible');
observe([{target:cards[1],isIntersecting:true},{target:cards[0],isIntersecting:true}]);
assert.equal(cards[0].classList.has('visible'), true);
assert.equal(cards[1].classList.has('visible'), false, 'Second card waits for cascade');
observe([{target:cards[2],isIntersecting:true}]);
timers.shift()();
assert.equal(cards[1].classList.has('visible'), true);
assert.equal(cards[2].classList.has('visible'), false, 'New batch does not overtake queue');
timers.shift()();
assert.equal(cards[2].classList.has('visible'), true);
assert.equal(cards[3].classList.has('visible'), false, 'Off-screen card stays pending');
assert.equal(removed.length, 3);
window.matchMedia = () => ({ matches: true });
initCardReveal('.card', 'visible');
assert.equal(cards.every((card) => card.classList.has('visible')), true);
console.log('Card reveal: document order, stagger, later batches, late loading and reduced motion passed.');
