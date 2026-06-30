/**
 * ADN Animation Interactive
 * Animation moderne pour les lettres A, D, N avec leurs significations
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialiser l'animation ADN
    initAdnAnimation();
    
    // Observer pour déclencher l'animation au scroll
    initScrollObserver();
});

function initAdnAnimation() {
    const letterBlocks = document.querySelectorAll('.adn-letter-block');
    
    letterBlocks.forEach((block, index) => {
        // Ajouter un effet de hover amélioré
        block.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-15px) scale(1.08)';
            this.style.filter = 'drop-shadow(0 25px 50px rgba(28, 41, 161, 0.4))';
            
            // Effet de pulsation sur la lettre
            const letter = this.querySelector('.adn-letter');
            letter.style.animation = 'letterGlow 0.5s ease-in-out, letterPulse 0.6s ease-in-out';
        });
        
        block.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.filter = '';
            
            // Restaurer l'animation normale
            const letter = this.querySelector('.adn-letter');
            letter.style.animation = 'letterGlow 3s ease-in-out infinite alternate';
        });
        
        // Effet de clic pour une interaction tactile
        block.addEventListener('click', function() {
            // Animation de "bounce" au clic
            this.style.animation = 'bounceClick 0.6s ease-out';
            
            // Réinitialiser l'animation après
            setTimeout(() => {
                this.style.animation = '';
            }, 600);
        });
    });
    
    // Ajouter les animations CSS dynamiques
    addDynamicStyles();
}

function initScrollObserver() {
    const animationContainer = document.querySelector('.adn-animation-container');
    
    if (!animationContainer) return;
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Déclencher l'animation quand l'élément devient visible
                entry.target.classList.add('animate-in-view');
                
                // Animer les lettres avec un délai séquentiel
                const letterBlocks = entry.target.querySelectorAll('.adn-letter-block');
                letterBlocks.forEach((block, index) => {
                    setTimeout(() => {
                        block.classList.add('animate-visible');
                    }, index * 300);
                });
            }
        });
    }, {
        threshold: 0.3,
        rootMargin: '0px 0px -100px 0px'
    });
    
    observer.observe(animationContainer);
}

function addDynamicStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes letterPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes bounceClick {
            0% { transform: scale(1); }
            25% { transform: scale(0.95); }
            50% { transform: scale(1.05); }
            75% { transform: scale(0.98); }
            100% { transform: scale(1); }
        }
        
        .adn-animation-container.animate-in-view {
            animation: containerFadeIn 1s ease-out;
        }
        
        @keyframes containerFadeIn {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .adn-letter-block.animate-visible {
            animation-play-state: running;
        }
        
        /* Amélioration de l'accessibilité */
        @media (prefers-reduced-motion: reduce) {
            .adn-letter-block,
            .adn-letter,
            .adn-definition,
            .adn-circle,
            .adn-line {
                animation: none !important;
                transition: none !important;
            }
            
            .adn-letter-block {
                opacity: 1;
                transform: none;
            }
            
            .adn-definition {
                opacity: 1;
                transform: none;
            }
        }
        
        /* Optimisation pour les appareils tactiles */
        @media (hover: none) and (pointer: coarse) {
            .adn-letter-block:hover {
                transform: none;
                filter: none;
            }
            
            .adn-letter-block:active {
                transform: scale(0.98);
                transition: transform 0.1s ease-out;
            }
        }
    `;
    
    document.head.appendChild(style);
}

// Fonction utilitaire pour détecter Safari
function isSafari() {
    return /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
}

// Optimisations spécifiques pour Safari
if (isSafari()) {
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.adn-animation-container');
        if (container) {
            container.style.willChange = 'transform';
            container.style.webkitTransform = 'translateZ(0)';
        }
    });
}
