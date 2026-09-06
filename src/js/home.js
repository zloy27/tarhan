import '../scss/home.scss';

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
