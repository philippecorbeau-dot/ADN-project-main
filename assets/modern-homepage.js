/* ==========================================
   ADN FAMILY OFFICE - MODERN HOMEPAGE JS
   Animations et interactions modernes
   ========================================== */

class ModernHomepage {
    constructor() {
        this.init();
    }

    init() {
        this.setupScrollAnimations();
        this.setupNavigation();
        this.setupCounters();
        this.setupParallax();
        this.setupSmoothScroll();
        this.setupLoadingAnimations();
    }

    // Configuration des animations au scroll
    setupScrollAnimations() {
        // Configuration de l'Intersection Observer
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    element.classList.add('in-view');
                    
                    // Animation spécifique selon le type d'élément
                    if (element.classList.contains('animate-fade-up')) {
                        element.style.animation = 'fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards';
                    } else if (element.classList.contains('animate-fade-left')) {
                        element.style.animation = 'fadeInLeft 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards';
                    } else if (element.classList.contains('animate-fade-right')) {
                        element.style.animation = 'fadeInRight 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards';
                    } else {
                        element.style.animation = 'fadeInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1) forwards';
                    }
                }
            });
        }, observerOptions);

        // Observer tous les éléments avec animation
        document.querySelectorAll('.animate-on-scroll').forEach(el => {
            observer.observe(el);
        });
    }

    // Navigation moderne avec effet de scroll
    setupNavigation() {
        const nav = document.getElementById('modernNav') || document.querySelector('.clean-modern-header');
        if (!nav) return; // Sécurité si l'élément n'existe pas
        let lastScrollY = window.scrollY;
        let scrollTimeout;

        const handleScroll = () => {
            const currentScrollY = window.scrollY;
            
            // Ajouter la classe scrolled après 50px
            if (currentScrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }

            // Masquer/afficher la navigation selon la direction du scroll
            if (currentScrollY > lastScrollY && currentScrollY > 200) {
                nav.style.transform = 'translateY(-100%)';
            } else {
                nav.style.transform = 'translateY(0)';
            }

            lastScrollY = currentScrollY;

            // Débounce pour les performances
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                nav.style.transform = 'translateY(0)';
            }, 150);
        };

        window.addEventListener('scroll', handleScroll, { passive: true });

        // Navigation smooth pour les ancres - ne bloque pas les liens absolus/externes
        document.querySelectorAll('a').forEach(anchor => {
            const href = anchor.getAttribute('href') || '';
            if (href.startsWith('#')) {
                anchor.addEventListener('click', (e) => {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }
        });
    }

    // Animation des compteurs
    setupCounters() {
        const counters = document.querySelectorAll('[data-target]');
        
        const animateCounter = (counter) => {
            const target = parseInt(counter.getAttribute('data-target'));
            const duration = 2000; // 2 secondes
            const steps = 60; // 60 FPS
            const increment = target / (duration / (1000 / steps));
            let current = 0;
            
            // Vérifier si le compteur contient déjà un symbole €
            const hasEuro = counter.textContent.includes('€');

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                // Formatage en français avec le symbole € si nécessaire
                const formattedNumber = Math.floor(current).toLocaleString('fr-FR');
                counter.textContent = hasEuro ? `${formattedNumber}€` : formattedNumber;
            }, 1000 / steps);
        };

        // Observer pour déclencher l'animation
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        });

        counters.forEach(counter => {
            counterObserver.observe(counter);
        });
    }

    // Effet parallax subtil
    setupParallax() {
        const parallaxElements = document.querySelectorAll('.modern-hero-image');
        if (!parallaxElements.length) return;
        
        const handleParallax = () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.3;

            parallaxElements.forEach(element => {
                element.style.transform = `translateY(${rate}px)`;
            });
        };

        window.addEventListener('scroll', handleParallax, { passive: true });
    }

    // Smooth scroll amélioré
    setupSmoothScroll() {
        // Polyfill pour les navigateurs qui ne supportent pas scroll-behavior
        if (!CSS.supports('scroll-behavior', 'smooth')) {
            const links = document.querySelectorAll('a[href^="#"]');
            
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const target = document.querySelector(link.getAttribute('href'));
                    
                    if (target) {
                        const targetPosition = target.offsetTop - 80; // Offset pour la nav
                        const startPosition = window.pageYOffset;
                        const distance = targetPosition - startPosition;
                        const duration = 800;
                        let start = null;

                        const animation = (currentTime) => {
                            if (start === null) start = currentTime;
                            const timeElapsed = currentTime - start;
                            const run = this.easeInOutQuad(timeElapsed, startPosition, distance, duration);
                            window.scrollTo(0, run);
                            
                            if (timeElapsed < duration) {
                                requestAnimationFrame(animation);
                            }
                        };

                        requestAnimationFrame(animation);
                    }
                });
            });
        }
    }

    // Fonction d'easing
    easeInOutQuad(t, b, c, d) {
        t /= d / 2;
        if (t < 1) return c / 2 * t * t + b;
        t--;
        return -c / 2 * (t * (t - 2) - 1) + b;
    }

    // Animations de chargement
    setupLoadingAnimations() {
        // Animation en cascade pour les éléments du hero
        const heroElements = document.querySelectorAll('.modern-hero-content > *');
        heroElements.forEach((element, index) => {
            element.style.animationDelay = `${index * 0.2}s`;
        });

        // Animation des cartes avec délai
        const cards = document.querySelectorAll('.modern-stat-card, .group');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });

        // Effet de typing pour le titre principal
        const mainTitle = document.querySelector('.modern-hero-title');
        if (mainTitle) {
            this.typeWriter(mainTitle);
        }
    }

    // Effet de machine à écrire
    typeWriter(element) {
        const text = element.textContent;
        element.textContent = '';
        element.style.borderRight = '2px solid white';
        
        let i = 0;
        const timer = setInterval(() => {
            if (i < text.length) {
                element.textContent += text.charAt(i);
                i++;
            } else {
                clearInterval(timer);
                element.style.borderRight = 'none';
            }
        }, 50);
    }

    // Méthodes utilitaires
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Animation des boutons avec effet ripple
    setupRippleEffect() {
        const buttons = document.querySelectorAll('.btn-modern-primary, .btn-modern-secondary');
        
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = button.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                button.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    }

    // Gestion des performances
    optimizePerformance() {
        // Réduire les animations sur les appareils à faible performance
        if (navigator.hardwareConcurrency < 4) {
            document.documentElement.style.setProperty('--animation-duration', '0.3s');
        }

        // Désactiver les animations si l'utilisateur préfère le mouvement réduit
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.style.setProperty('--animation-duration', '0.01ms');
        }
    }
}

// Gestion du menu mobile (hamburger)
class MobileMenu {
    constructor() {
        this.setupMobileMenu();
    }

    setupMobileMenu() {
        // Créer le bouton hamburger (legacy - skip if header module structure non présente)
        const nav = document.querySelector('.modern-nav-container');
        const menu = document.querySelector('.modern-nav-menu');
        if (!nav || !menu) {
            // Le header moderne du projet gère déjà #mobileToggle/#mobileMenu via Twig/JS
            return;
        }

        if (window.innerWidth <= 768) {
            this.createHamburgerButton(nav, menu);
        }

        // Gérer le redimensionnement
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                this.createHamburgerButton(nav, menu);
            } else {
                this.removeHamburgerButton();
            }
        });
    }

    createHamburgerButton(nav, menu) {
        if (!nav || !menu) return;
        if (document.querySelector('.hamburger-button')) return;

        const hamburger = document.createElement('button');
        hamburger.className = 'hamburger-button';
        hamburger.innerHTML = `
            <span></span>
            <span></span>
            <span></span>
        `;

        hamburger.addEventListener('click', () => {
            menu.classList.toggle('mobile-open');
            hamburger.classList.toggle('active');
        });

        nav.appendChild(hamburger);
    }

    removeHamburgerButton() {
        const hamburger = document.querySelector('.hamburger-button');
        if (hamburger) {
            hamburger.remove();
        }
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new ModernHomepage();
    new MobileMenu();
});

// Gestion du chargement
window.addEventListener('load', () => {
    // Masquer le spinner de chargement si présent
    const spinner = document.querySelector('.preload-spinner');
    if (spinner) {
        spinner.style.opacity = '0';
        setTimeout(() => {
            spinner.style.display = 'none';
        }, 300);
    }

    // Déclencher les animations finales
    document.body.classList.add('loaded');
});

// CSS additionnel pour les effets
const additionalCSS = `
.ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple-animation 0.6s linear;
    pointer-events: none;
}

@keyframes ripple-animation {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

.hamburger-button {
    display: flex;
    flex-direction: column;
    width: 30px;
    height: 30px;
    background: none;
    border: none;
    cursor: pointer;
    z-index: 1001;
}

.hamburger-button span {
    width: 100%;
    height: 3px;
    background: var(--adn-primary);
    margin: 3px 0;
    transition: 0.3s;
}

.hamburger-button.active span:nth-child(1) {
    transform: rotate(-45deg) translate(-6px, 6px);
}

.hamburger-button.active span:nth-child(2) {
    opacity: 0;
}

.hamburger-button.active span:nth-child(3) {
    transform: rotate(45deg) translate(-6px, -6px);
}

.modern-nav-menu.mobile-open {
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 80px;
    left: 0;
    right: 0;
    background: white;
    box-shadow: var(--shadow-large);
    padding: 2rem;
    z-index: 1000;
}

@media (max-width: 768px) {
    .modern-nav-menu {
        display: none;
    }
    
    .modern-nav-actions {
        display: none;
    }
}

/* Optimisations de performance */
.gpu-accelerated {
    transform: translateZ(0);
    will-change: transform;
}

/* Animation de chargement */
.loading-fade {
    animation: loadingFade 0.8s ease forwards;
}

@keyframes loadingFade {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
`;

// Injecter le CSS additionnel
const style = document.createElement('style');
style.textContent = additionalCSS;
document.head.appendChild(style);
