import '../scss/home.scss';
import { initAccordion } from './components/home-accrodion.js';
import { initPageNavigation } from './components/page-navigation.js';

const invitationVideo = document.querySelector('.competition-invitation__video video');

if (invitationVideo) {
  invitationVideo.muted = true;
  invitationVideo.defaultMuted = true;

  const visibilityObserver = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        invitationVideo.play().catch(() => {
          // Браузер может отложить автозапуск до первого взаимодействия.
        });
        return;
      }

      invitationVideo.pause();
    },
    {
      threshold: 0.35,
    },
  );

  visibilityObserver.observe(invitationVideo);
}

initAccordion();
initPageNavigation();
