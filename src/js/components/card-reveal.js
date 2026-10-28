// A single queue keeps cards in document order, including later scroll batches.
export function initCardReveal(selector, visibleClass, interval = 140) {
  const cards = [...document.querySelectorAll(selector)];
  if (!cards.length) return;
  if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    cards.forEach((card) => card.classList.add(visibleClass));
    return;
  }
  const queue = [];
  let running = false;
  const next = () => {
    const card = queue.shift();
    if (!card) { running = false; return; }
    running = true;
    card.classList.add(visibleClass);
    window.setTimeout(next, interval);
  };
  const observer = new IntersectionObserver((entries) => {
    entries.filter((entry) => entry.isIntersecting)
      .sort((a, b) => cards.indexOf(a.target) - cards.indexOf(b.target))
      .forEach(({ target }) => {
        observer.unobserve(target);
        queue.push(target);
      });
    if (!running) next();
  }, { threshold: 0.08 });
  cards.forEach((card) => {
    card.dataset.reveal = 'ready';
    observer.observe(card);
  });
}

export function onPageReady(callback) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback, { once: true });
  } else {
    callback();
  }
}
