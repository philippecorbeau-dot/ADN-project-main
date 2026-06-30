// Minimal, page-agnostic mobile header controller
document.addEventListener('DOMContentLoaded', () => {
  // Si le module header moderne gère déjà le menu, ne rien faire
  if (window.__adnHeaderModulePresent) return;
  const header = document.querySelector('.clean-modern-header');
  const mobileToggle = document.getElementById('mobileToggle');
  const mobileMenu = document.getElementById('mobileMenu');
  const mobileClose = document.getElementById('mobileClose');
  if (!header || !mobileToggle || !mobileMenu || !mobileClose) return;

  const openMenu = () => {
    mobileMenu.classList.add('active');
    mobileToggle.classList.add('active');
    document.body.style.overflow = 'hidden';
  };
  const closeMenu = () => {
    mobileMenu.classList.remove('active');
    mobileToggle.classList.remove('active');
    document.body.style.overflow = '';
    document.querySelectorAll('.mobile-dropdown.active').forEach(d => d.classList.remove('active'));
  };

  mobileToggle.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (mobileMenu.classList.contains('active')) closeMenu(); else openMenu();
  });
  mobileClose.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); closeMenu(); });
  mobileMenu.addEventListener('click', (e) => { if (e.target === mobileMenu) closeMenu(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && mobileMenu.classList.contains('active')) closeMenu(); });

  document.querySelectorAll('.mobile-dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const dropdown = toggle.closest('.mobile-dropdown');
      const isActive = dropdown.classList.contains('active');
      document.querySelectorAll('.mobile-dropdown.active').forEach(d => { if (d !== dropdown) d.classList.remove('active'); });
      dropdown.classList.toggle('active', !isActive);
    });
  });
});


