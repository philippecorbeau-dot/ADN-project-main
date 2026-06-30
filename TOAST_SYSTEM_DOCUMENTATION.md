# 🎨 Système de Toast Unifié - Documentation

## Vue d'ensemble

Le nouveau système de toast est maintenant unifié dans toute l'application ADN Family Office. Il offre une expérience utilisateur moderne, responsive et accessible.

## ✨ Caractéristiques

### Design Moderne et Compact
- **Animations ultra-rapides** : 200ms apparition, 150ms disparition
- **Design épuré** : Une seule ligne de message, pas de titre
- **Barre de progression fine** : Visualisation discrète du temps restant
- **Gradients semi-transparents** : Effet glassmorphism moderne
- **Positionnement intelligent** : Sous le header (top-20 / 80px) pour ne jamais être caché

### Responsive
- **Desktop** : Positionné en haut à droite (sous le header)
- **Mobile** : S'adapte à la largeur de l'écran
- **Largeur** : min-width 280px, max-width adaptative
- **Z-index** : 99999 (toujours au-dessus)

### Fonctionnalités
- **Rapide** : Disparaît en 3-4 secondes (au lieu de 5-7s)
- **Pause au survol** : Le toast se met en pause quand la souris passe dessus
- **Fermeture manuelle** : Bouton × accessible et visible
- **Limite de 3 toasts** : Maximum pour éviter la surcharge
- **Anti-doublons** : Empêche l'affichage de messages identiques (30s)
- **Accessibilité** : Attributs ARIA pour les lecteurs d'écran

## 🎯 Types de Toast

Le système supporte 4 types de messages :

### 1. Succès (Success)
- **Couleur** : Vert (emerald-500/95)
- **Icône** : Checkmark simple
- **Durée** : **3 secondes**
- **Utilisation** : Confirmation d'actions réussies

### 2. Erreur (Error)
- **Couleur** : Rouge (red-500/95)
- **Icône** : Croix simple
- **Durée** : **4 secondes**
- **Utilisation** : Messages d'erreur, échecs d'opération

### 3. Avertissement (Warning)
- **Couleur** : Ambre (amber-500/95)
- **Icône** : Point d'exclamation
- **Durée** : **3.5 secondes**
- **Utilisation** : Avertissements, actions à confirmer

### 4. Information (Info)
- **Couleur** : Bleu (blue-500/95)
- **Icône** : i minuscule
- **Durée** : **3 secondes**
- **Utilisation** : Messages informatifs

## 📝 Utilisation

### Intégration automatique avec Symfony Flash Messages

Le système est automatiquement intégré avec les flash messages Symfony. Il suffit d'utiliser `addFlash()` dans vos contrôleurs :

```php
// Succès
$this->addFlash('success', 'Produit enregistré avec succès !');

// Erreur
$this->addFlash('error', 'Une erreur est survenue lors de la suppression.');

// Avertissement
$this->addFlash('warning', 'Attention, cette action est irréversible.');

// Information
$this->addFlash('info', 'Votre demande a été prise en compte.');
```

### API JavaScript

Le système expose également une API JavaScript globale pour afficher des toasts depuis le frontend :

#### Méthode générique
```javascript
window.showToast(message, type, duration);

// Exemple
window.showToast('Fichier téléchargé !', 'success', 3000);
```

#### Raccourcis pratiques (RECOMMANDÉ)
```javascript
// Succès (3 secondes par défaut)
window.toast.success('Opération réussie !');

// Erreur (4 secondes par défaut)
window.toast.error('Échec de l\'opération');

// Avertissement (3.5 secondes par défaut)
window.toast.warning('Action requise');

// Information (3 secondes par défaut)
window.toast.info('Nouvelle mise à jour disponible');

// Avec durée personnalisée
window.toast.success('Message important', 6000);
```

#### Événements personnalisés
```javascript
// Déclencher un toast via un événement
window.dispatchEvent(new CustomEvent('toast', {
    detail: {
        message: 'Message personnalisé',
        type: 'info',
        title: 'Titre',
        duration: 5000
    }
}));
```

## 🎨 Personnalisation

### Modifier les couleurs

Les couleurs sont définies dans le template `templates/components/toast_system.html.twig` :

```twig
:class="{
    'bg-gradient-to-r from-emerald-500 to-emerald-600': toast.type === 'success',
    'bg-gradient-to-r from-red-500 to-red-600': toast.type === 'error',
    'bg-gradient-to-r from-amber-500 to-amber-600': toast.type === 'warning',
    'bg-gradient-to-r from-blue-500 to-blue-600': toast.type === 'info'
}"
```

### Modifier les durées par défaut

Dans la fonction JavaScript `toastSystem()` :

```javascript
window.toast = {
    success: (message, title = 'Succès', duration = 5000) => { ... },
    error: (message, title = 'Erreur', duration = 7000) => { ... },
    warning: (message, title = 'Attention', duration = 6000) => { ... },
    info: (message, title = 'Information', duration = 5000) => { ... }
};
```

### Modifier le nombre maximum de toasts

Dans la fonction `addToast()` :

```javascript
// Limiter à 5 toasts maximum
if (this.toasts.length > 5) {
    this.removeToast(this.toasts[0].id);
}
```

## 🧪 Tests

### Test Manuel

#### Sur Desktop
1. Ouvrir l'application sur un navigateur desktop
2. Déclencher une action qui affiche un flash message
3. Vérifier que le toast apparaît en haut à droite
4. Vérifier que la barre de progression fonctionne
5. Survoler le toast → doit se mettre en pause
6. Retirer la souris → doit reprendre le compte à rebours
7. Cliquer sur le bouton × → doit fermer immédiatement

#### Sur Mobile
1. Ouvrir l'application sur un mobile ou en mode responsive
2. Déclencher une action qui affiche un flash message
3. Vérifier que le toast apparaît en haut de l'écran (pleine largeur)
4. Vérifier que le texte n'est pas coupé
5. Vérifier que tous les éléments sont visibles et accessibles
6. Tester la fermeture manuelle

### Test via Console JavaScript

Ouvrir la console développeur et exécuter :

```javascript
// Test succès
window.toast.success('Produit enregistré !');

// Test erreur
window.toast.error('Impossible de supprimer cet élément.');

// Test avertissement
window.toast.warning('Cette action est irréversible.');

// Test information
window.toast.info('Mise à jour disponible.');

// Test de plusieurs toasts simultanés (max 3 affichés)
window.toast.success('Message 1');
window.toast.error('Message 2');
window.toast.warning('Message 3');
window.toast.info('Message 4'); // Le premier disparaîtra

// Test de durée personnalisée
window.toast.info('Message personnalisé de 6 secondes', 6000);
```

### Test d'accessibilité

1. **Lecteur d'écran** : Activer un lecteur d'écran (NVDA, JAWS) et vérifier que les toasts sont annoncés
2. **Navigation au clavier** : Vérifier que le bouton de fermeture est accessible via Tab
3. **Contraste** : Vérifier que le contraste des couleurs est suffisant (ratio WCAG AA/AAA)

## 🔧 Dépendances

- **Alpine.js 3.x** : Pour la réactivité et les animations
- **Tailwind CSS** : Pour les styles utilitaires
- **Symfony Flash Messages** : Pour l'intégration backend

## 📦 Fichiers modifiés

1. **Nouveau composant** : `templates/components/toast_system.html.twig`
2. **Back-office admin** : `templates/admin_modern/base.html.twig` (ligne 415)
3. **Front public** : `templates/base.html.twig` (ligne 355)
4. **Dashboard utilisateur** : `templates/front/user/dashboard/base.html.twig` (ligne 265)

## 🎯 Avantages par rapport à l'ancien système

### Avant (Notyf)
- ❌ Design différent entre admin et front
- ❌ Dépendance externe (CDN)
- ❌ Peu personnalisable
- ❌ Pas de barre de progression
- ❌ Toast parfois coupés sur mobile

### Après (Système unifié)
- ✅ Design cohérent partout
- ✅ Aucune dépendance externe (Alpine.js déjà présent)
- ✅ Entièrement personnalisable
- ✅ Barre de progression interactive
- ✅ Responsive parfait sur mobile
- ✅ Pause au survol
- ✅ Anti-doublons intégré
- ✅ Limite automatique de toasts

## 🐛 Résolution de problèmes

### Les toasts ne s'affichent pas

1. Vérifier que Alpine.js est chargé : `console.log(window.Alpine)`
2. Vérifier que le composant est inclus dans le template
3. Vérifier la console pour les erreurs JavaScript

### Les toasts sont coupés

1. Vérifier le z-index : doit être `z-[9999]`
2. Vérifier qu'aucun élément parent n'a `overflow: hidden`
3. Sur mobile, vérifier la viewport meta tag

### Les animations ne fonctionnent pas

1. Vérifier que Tailwind CSS est correctement configuré
2. Vérifier que les classes de transition sont présentes
3. Désactiver les extensions de navigateur qui peuvent bloquer les animations

## 📱 Compatibilité

- ✅ Chrome/Edge (90+)
- ✅ Firefox (88+)
- ✅ Safari (14+)
- ✅ Mobile Safari (iOS 14+)
- ✅ Chrome Android (90+)

## 🚀 Améliorations futures possibles

1. **Sons** : Ajouter des sons optionnels pour chaque type de toast
2. **Positions** : Permettre de choisir la position (top-left, bottom-right, etc.)
3. **Actions** : Ajouter des boutons d'action dans les toasts
4. **Persistence** : Sauvegarder certains toasts dans localStorage
5. **Queue intelligente** : Prioriser les messages critiques
6. **Thème sombre** : Support automatique du dark mode

---

**Version** : 1.0.0  
**Date** : Novembre 2025  
**Auteur** : ADN Family Office - Équipe Développement

