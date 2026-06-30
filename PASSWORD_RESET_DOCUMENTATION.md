# 🔐 Système de Réinitialisation de Mot de Passe - Documentation

## Vue d'ensemble

Un système complet et moderne de réinitialisation de mot de passe a été implémenté pour remplacer l'ancienne modal "contacter le support".

## ✨ Fonctionnalités

### 1. Modal moderne sur la page de connexion
- Design épuré avec gradient bleu
- Formulaire fonctionnel avec validation
- Animation fluide (slide-up)
- Message de succès automatique
- Fermeture auto après 5 secondes
- Responsive (mobile/desktop)

### 2. Page de réinitialisation ultra-moderne
- Arrière-plan animé avec effets de flottement
- Indicateur de force du mot de passe en temps réel
- Validation des exigences (8 caractères, majuscules, minuscules, chiffres)
- Toggle pour afficher/masquer les mots de passe
- Feedback instantané sur la correspondance
- Design moderne et professionnel

### 3. Sécurité backend
- Tokens cryptographiquement sécurisés (64 caractères hex)
- Expiration automatique après 1 heure
- Protection contre les attaques par énumération
- Hash sécurisé via Symfony PasswordHasher
- Messages génériques pour éviter la divulgation d'informations

## 🔄 Flux utilisateur

### Étape 1 : Demande de réinitialisation

1. L'utilisateur clique sur "Mot de passe oublié ?" sur `/login`
2. Une modal moderne s'affiche
3. L'utilisateur entre son email
4. Clic sur "Envoyer le lien"
5. Message de succès affiché
6. Modal se ferme automatiquement après 5 secondes

### Étape 2 : Réception de l'email

1. Email envoyé automatiquement via `MailService`
2. Template : `templates/emails/reset_password.html.twig`
3. Lien sécurisé : `/reset-password/{token}`
4. Validité : 1 heure

### Étape 3 : Réinitialisation

1. Clic sur le lien → Page de réinitialisation
2. Saisie du nouveau mot de passe avec indicateur de force
3. Confirmation du mot de passe
4. Validation en temps réel des exigences
5. Soumission du formulaire
6. Redirection automatique vers `/login`
7. Message de succès via toast

## 📁 Fichiers modifiés/créés

### Backend

1. **`src/Entity/User/User.php`**
   - Ajout de `resetToken` (string, 100 caractères)
   - Ajout de `resetTokenExpiresAt` (DateTimeImmutable)
   - Méthode `isResetTokenValid()` pour vérifier la validité

2. **`src/Controller/SecurityController.php`**
   - Route `app_forgot_password` (POST) : Génère le token et envoie l'email
   - Route `app_reset_password` (GET/POST) : Affiche le formulaire et traite la réinitialisation

3. **`migrations/Version20251114100423.php`**
   - Migration pour ajouter les colonnes à la BDD
   - Exécutée avec succès

### Frontend

4. **`templates/security/login.html.twig`**
   - Fonction `showForgotPasswordModal()` modernisée
   - Fonction `handleForgotPassword()` pour l'envoi AJAX
   - Design des champs amélioré (coins arrondis, œil à l'intérieur)
   - Suppression des icônes surchargées

5. **`templates/security/reset_password.html.twig`**
   - Nouvelle page complète de réinitialisation
   - Arrière-plan animé avec effets de flottement
   - Indicateur de force du mot de passe
   - Validation en temps réel
   - Responsive design

6. **`templates/emails/reset_password.html.twig`**
   - Template email existant (déjà présent)

## 🔒 Sécurité

### Génération du token

```php
$token = bin2hex(random_bytes(32)); // 64 caractères hexadécimaux
$user->setResetToken($token);
$user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
```

### Validation du token

```php
public function isResetTokenValid(): bool
{
    if (!$this->resetToken || !$this->resetTokenExpiresAt) {
        return false;
    }
    return $this->resetTokenExpiresAt > new \DateTimeImmutable();
}
```

### Protection contre l'énumération

Le système renvoie toujours le même message de succès, que l'email existe ou non :

```
"Si cette adresse email existe dans notre système, vous recevrez un lien de réinitialisation."
```

### Hash du mot de passe

```php
$hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
$user->setPassword($hashedPassword);
```

## 🎨 Design des champs de saisie

### Améliorations apportées

1. **Coins arrondis** : `border-radius: 1.25rem` (20px)
2. **Suppression des icônes** : Plus épuré, pas de surcharge visuelle
3. **Œil à l'intérieur** : Bouton moderne avec fond gris et arrondi
4. **Effets glassmorphism** : `backdrop-filter: blur(10px)`
5. **Animations fluides** : Transitions cubic-bezier
6. **Focus state** : Anneau bleu et légère élévation
7. **Hover state** : Bordure plus prononcée

### Style CSS

```css
.form-input {
    padding: 1.125rem 1.25rem;
    border: 2px solid rgba(59, 130, 246, 0.15);
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.toggle-password-btn {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(241, 245, 249, 0.9);
    border-radius: 0.625rem;
    width: 36px;
    height: 36px;
    /* Effet hover et focus */
}
```

## 🧪 Tests

### Test du flux complet

1. **Accéder à** `/login`
2. **Cliquer** sur "Mot de passe oublié ?"
3. **Vérifier** :
   - ✅ Modal s'affiche avec animation
   - ✅ Design moderne et épuré
   - ✅ Champs arrondis sans icônes
   - ✅ Bouton œil à l'intérieur du champ

4. **Entrer** un email valide (ex: `pierre@adnfamilyoffice.fr`)
5. **Cliquer** "Envoyer le lien"
6. **Vérifier** :
   - ✅ Message de succès s'affiche
   - ✅ Modal se ferme après 5 secondes
   - ✅ Email envoyé (vérifier `var/mails/` en dev)

7. **Ouvrir** le lien dans l'email
8. **Vérifier** la page de réinitialisation :
   - ✅ Arrière-plan animé
   - ✅ Champs arrondis modernes
   - ✅ Œil à l'intérieur des champs
   - ✅ Indicateur de force fonctionne
   - ✅ Validation en temps réel

9. **Entrer** un nouveau mot de passe
10. **Vérifier** :
    - ✅ Barre de progression de force
    - ✅ Exigences se cochent en temps réel
    - ✅ Bouton activé/désactivé selon validation

11. **Soumettre** le formulaire
12. **Vérifier** :
    - ✅ Redirection vers `/login`
    - ✅ Toast de succès affiché
    - ✅ Connexion possible avec nouveau mot de passe

### Test de sécurité

1. **Token expiré** : Vérifier message d'erreur après 1 heure
2. **Token invalide** : Tester avec token aléatoire → erreur
3. **Email inexistant** : Vérifier message générique (pas de divulgation)
4. **Mot de passe faible** : Vérifier validation (min 8 caractères)
5. **Mots de passe différents** : Vérifier message d'erreur

## 📋 API JavaScript

### Modal de mot de passe oublié

```javascript
// Fonction globale accessible
async function handleForgotPassword(event) {
    // Gère la soumission AJAX du formulaire
    // Affiche les messages de succès/erreur
    // Ferme automatiquement la modal
}
```

### Page de réinitialisation

```javascript
// Toggle password visibility
function togglePassword(fieldId) { ... }

// Check password strength
function checkPasswordStrength() { ... }

// Check password match
function checkPasswordMatch() { ... }

// Update submit button
function updateSubmitButton() { ... }

// Handle form submission
async function handleResetPassword(event) { ... }
```

## 🎯 Indicateur de force du mot de passe

### Calcul de la force

```javascript
- Longueur ≥ 8 caractères : +25 points
- Longueur ≥ 12 caractères : +15 points
- Minuscules : +20 points
- Majuscules : +20 points
- Chiffres : +20 points
- Caractères spéciaux : +20 points
```

### Niveaux

- **< 40%** : ❌ Faible (rouge)
- **40-70%** : ⚠️ Moyen (orange)
- **70-90%** : ✅ Bon (vert)
- **> 90%** : 🎉 Excellent (vert foncé)

## 🔧 Configuration

### Durée de validité du token

Dans `SecurityController.php` :

```php
$user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
```

Pour modifier : changer `'+1 hour'` en `'+30 minutes'`, `'+2 hours'`, etc.

### Exigences du mot de passe

Dans `reset_password.html.twig` et la validation JavaScript :

```javascript
const isValid =
    password.length >= 8 &&
    /[a-z]/.test(password) &&
    /[A-Z]/.test(password) &&
    /[0-9]/.test(password);
```

## 📧 Email de réinitialisation

### Template

Le template existe déjà dans `templates/emails/reset_password.html.twig`.

### Variables disponibles

```twig
{{ user.firstname }}
{{ resetUrl }}
```

### Service d'envoi

```php
$mailService->resetPassword($user->getEmail(), [
    'user' => $user,
    'resetUrl' => $resetUrl
]);
```

## ⚠️ Points d'attention

1. **Environnement de développement** : Les emails sont enregistrés dans `var/mails/`
2. **Production** : Configurer le serveur SMTP dans `.env.local`
3. **Rate limiting** : Implémenter une limitation (ex: 3 demandes/heure/IP)
4. **Logs** : Surveiller les tentatives de réinitialisation
5. **HTTPS** : Obligatoire en production pour la sécurité

## 🆕 Différences avec l'ancien système

| Avant | Après |
|-------|-------|
| ❌ Modal basique "contacter le support" | ✅ Modal fonctionnelle avec formulaire |
| ❌ Pas de réinitialisation automatique | ✅ Flux complet de réinitialisation |
| ❌ Aucune sécurité | ✅ Tokens sécurisés avec expiration |
| ❌ Design daté | ✅ Design moderne et animé |
| ❌ Icônes surchargées | ✅ Design épuré |
| ❌ Œil en dehors du champ | ✅ Œil intégré dans le champ |
| ❌ Champs carrés | ✅ Champs arrondis (1.25rem) |

---

**Version** : 1.0.0  
**Date** : Novembre 2025  
**Status** : ✅ Production Ready  
**Auteur** : ADN Family Office - Équipe Développement

