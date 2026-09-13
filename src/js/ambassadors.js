import '../scss/ambassadors.scss';
import { initPageNavigation } from './components/page-navigation.js';

document.addEventListener('DOMContentLoaded', () => {
  const cards = [...document.querySelectorAll('.ambassadors-main__card')];
  const revealQueue = [];
  const revealInterval = 140;
  let isRevealing = false;

  const revealNextCard = () => {
    const card = revealQueue.shift();

    if (!card) {
      isRevealing = false;
      return;
    }

    isRevealing = true;
    card.classList.add('ambassadors-main__card--visible');
    setTimeout(revealNextCard, revealInterval);
  };

  const cardObserver = new IntersectionObserver((entries, observer) => {
    const visibleCards = entries
      .filter((entry) => entry.isIntersecting)
      .sort((firstEntry, secondEntry) => (
        cards.indexOf(firstEntry.target) - cards.indexOf(secondEntry.target)
      ));

    visibleCards.forEach((entry) => {
      observer.unobserve(entry.target);
      revealQueue.push(entry.target);
    });

    if (!isRevealing) revealNextCard();
  }, {
    threshold: 0.08,
    rootMargin: '0px 0px 30% 0px',
  });

  cards.forEach((card) => cardObserver.observe(card));

  initPageNavigation();
});
