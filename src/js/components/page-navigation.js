const getPageName = (pathname) => {
  const pageName = pathname.split('/').filter(Boolean).at(-1);

  return pageName?.includes('.') ? pageName : 'index.html';
};

export const initPageNavigation = () => {
  const currentPage = getPageName(window.location.pathname);
  const navigationLinks = document.querySelectorAll('.header__title-a');
  const firstPageSection = document.querySelector('main > [id], body > [id]');
  const defaultHash = firstPageSection ? `#${firstPageSection.id}` : '';
  const burgerButton = document.querySelector('.header__burger');
  const headerNavigation = document.querySelector('.header__titles');

  const closeMenu = () => {
    if (!burgerButton || !headerNavigation) return;

    burgerButton.classList.remove('header__burger--active');
    headerNavigation.classList.remove('header__titles--open');
    burgerButton.setAttribute('aria-expanded', 'false');
    burgerButton.setAttribute('aria-label', 'Открыть меню');
  };

  if (burgerButton && headerNavigation) {
    burgerButton.addEventListener('click', () => {
      const isOpen = burgerButton.getAttribute('aria-expanded') === 'true';

      burgerButton.classList.toggle('header__burger--active', !isOpen);
      headerNavigation.classList.toggle('header__titles--open', !isOpen);
      burgerButton.setAttribute('aria-expanded', String(!isOpen));
      burgerButton.setAttribute('aria-label', isOpen ? 'Открыть меню' : 'Закрыть меню');
    });

    headerNavigation.addEventListener('click', (event) => {
      if (event.target.closest('.header__title-a')) closeMenu();
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeMenu();
    });

    window.matchMedia('(min-width: 998px)').addEventListener('change', (event) => {
      if (event.matches) closeMenu();
    });
  }

  document.body.dataset.page = currentPage.replace('.html', '');

  const setActiveLink = () => {
    navigationLinks.forEach((link) => {
      const linkUrl = new URL(link.href, window.location.href);
      const linkPage = getPageName(linkUrl.pathname);
      const isSamePage = linkUrl.origin === window.location.origin && linkPage === currentPage;
      const currentHash = window.location.hash || defaultHash;
      const isActive = isSamePage && (!linkUrl.hash || linkUrl.hash === currentHash);

      link.classList.toggle('active', isActive);

      if (isActive) {
        link.setAttribute('aria-current', linkUrl.hash ? 'location' : 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  };

  setActiveLink();
  window.addEventListener('hashchange', setActiveLink);
};
