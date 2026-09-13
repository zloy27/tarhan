export const initAccordion = () => {
  const accordionButtons = document.querySelectorAll(
    '.competition-questions__accordion--bt',
  );

  accordionButtons.forEach((button) => {
    const panel = button.nextElementSibling;

    if (!panel?.classList.contains('competition-questions__accordion--panel')) {
      return;
    }

    button.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
    panel.style.maxHeight = '0px';

    button.addEventListener('click', function () {
      const isOpen = this.classList.toggle('active');

      this.setAttribute('aria-expanded', String(isOpen));
      panel.setAttribute('aria-hidden', String(!isOpen));
      panel.style.maxHeight = isOpen ? `${panel.scrollHeight}px` : '0px';
    });
  });

  window.addEventListener('resize', () => {
    accordionButtons.forEach((button) => {
      if (!button.classList.contains('active')) {
        return;
      }

      const panel = button.nextElementSibling;

      if (panel) {
        panel.style.maxHeight = `${panel.scrollHeight}px`;
      }
    });
  });
};
