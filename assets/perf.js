// Global lightweight performance helpers
// - Force lazy-loading for non-critical images/iframes
// - Prefer async decoding to reduce main-thread blocking

// Add a Safari flag on <html> as early as possible to allow CSS workarounds
(() => {
  try {
    const ua = navigator.userAgent;
    const isSafari = /^((?!chrome|android).)*safari/i.test(ua);
    if (isSafari) {
      document.documentElement.classList.add('is-safari');
    }
  } catch (_) { /* no-op */ }
})();

document.addEventListener('DOMContentLoaded', () => {
  try {
    const images = Array.from(document.querySelectorAll('img'));
    for (const imageElement of images) {
      if (imageElement.hasAttribute('data-critical')) {
        continue;
      }
      if (!imageElement.hasAttribute('loading')) {
        imageElement.setAttribute('loading', 'lazy');
      }
      if (!imageElement.hasAttribute('decoding')) {
        imageElement.setAttribute('decoding', 'async');
      }
    }

    const iframes = Array.from(document.querySelectorAll('iframe'));
    for (const iframeElement of iframes) {
      if (!iframeElement.hasAttribute('loading')) {
        iframeElement.setAttribute('loading', 'lazy');
      }
    }
  } catch (error) {
    // Silently ignore; perf helpers must never break the page
  }
});


