# 🏛️ Système d'Administration ADN Family Office

## 👑 Super Admin

### Création d'un Super Admin

Pour créer un utilisateur Super Admin, utilisez la commande :

```bash
php bin/console app:create-super-admin [email] [password] [firstName] [lastName]
```

**Exemple :**
```bash
php bin/console app:create-super-admin admin@adn.com "SuperAdmin123!" "Admin" "ADN"
```

### Identifiants de test

- **Email :** `admin@adn.com`
- **Mot de passe :** `SuperAdmin123!`
- **Rôle :** `ROLE_SUPER_ADMIN`

## 🔐 Sécurité

### Protection de l'interface d'administration

L'interface EasyAdmin (`/admin`) est maintenant protégée :

- ✅ **Access Control** : Seuls les utilisateurs avec `ROLE_SUPER_ADMIN` peuvent y accéder
- ✅ **Contrôleurs protégés** : Tous les contrôleurs Admin ont `#[IsGranted('ROLE_SUPER_ADMIN')]`
- ✅ **Bouton Admin** : Apparaît uniquement pour les Super Admins dans le header

### Rôles disponibles

```php
const ROLE_USER = 'ROLE_USER';                    // Utilisateur de base
const ROLE_USER_IDENTIFIED = 'ROLE_USER_IDENTIFIED';  // KYC validé
const ROLE_KYC_OUTDATED = 'ROLE_KYC_OUTDATED';    // KYC expiré
const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';      // Administrateur principal
const ROLE_USER_BLOCKED = 'ROLE_USER_BLOCKED';    // Utilisateur bloqué
const ROLE_PROFILE_AWARE = 'ROLE_PROFILE_AWARE';  // Profil conscient
const ROLE_PROFILE_BEGINNER = 'ROLE_PROFILE_BEGINNER'; // Profil débutant
const ROLE_ADMIN_MARKETING = 'ROLE_ADMIN_MARKETING'; // Admin marketing
```

## 🎯 Fonctionnalités

### Interface d'administration

1. **Accès :** Se connecter avec un compte Super Admin
2. **Bouton Admin :** Apparaît dans le header (violet avec couronne)
3. **URL :** `http://127.0.0.1:8000/admin`

### Menu d'administration

- 📊 **Dashboard** - Tableau de bord avec statistiques
- 👥 **Utilisateurs** - Gestion des utilisateurs
- 📋 **Documents KYC** - Validation des documents
- 📁 **Fichiers KYC** - Gestion des fichiers
- ℹ️ **Infos** - Informations utilisateurs
- 💼 **Pros** - Professionnels
- 📂 **Documents** - Documents généraux
- ⚙️ **Configs** - Configuration
- ✅ **Contrôles** - Contrôles de conformité

## 🔧 Configuration

### Fichiers modifiés

1. **`config/packages/security.yaml`** - Protection des routes
2. **`src/Controller/Admin/*`** - Protection des contrôleurs
3. **`templates/partials/header.html.twig`** - Bouton Admin
4. **`templates/partials/header_dashboard.html.twig`** - Bouton Admin dashboard
5. **`tailwind.config.js`** - Couleur purple-600

### Commandes utiles

```bash
# Créer un Super Admin
php bin/console app:create-super-admin [email] [password] [firstName] [lastName]

# Vérifier les utilisateurs Super Admin
php bin/console doctrine:query:sql "SELECT email, roles FROM users_adn WHERE roles LIKE '%ROLE_SUPER_ADMIN%'"

# Vider le cache
php bin/console cache:clear
```

## 🚨 Sécurité

### Points de contrôle

- ✅ Interface `/admin` protégée
- ✅ Tous les contrôleurs Admin protégés
- ✅ Bouton Admin visible uniquement pour les Super Admins
- ✅ Mot de passe correctement haché
- ✅ Validation des entités

### Recommandations

1. **Changer le mot de passe** du Super Admin après la première connexion
2. **Utiliser des mots de passe forts** pour les comptes admin
3. **Limiter l'accès** aux comptes Super Admin
4. **Auditer régulièrement** les accès à l'administration

## 🎨 Interface

### Bouton Administration

- **Couleur :** Gris discret (`text-gray-400`)
- **Icône :** Engrenage (`fas fa-cog`)
- **Style :** Intégré et subtil
- **Position :** Header principal et dashboard
- **Responsive :** S'adapte à tous les écrans

### Visibilité

Le bouton n'apparaît que si l'utilisateur a le rôle `ROLE_SUPER_ADMIN` :

```twig
{% if is_granted('ROLE_SUPER_ADMIN') %}
    <li class="nl-simple" aria-haspopup="true">
        <a href="{{ path('admin') }}" class="h-link text-gray-400 hover:text-homblue-normal transition-colors duration-200 flex items-center" title="Administration">
            <i class="fas fa-cog text-xs"></i>
        </a>
    </li>
{% endif %}
```

## 🔄 Workflow

1. **Créer un Super Admin** avec la commande
2. **Se connecter** avec les identifiants
3. **Voir le bouton Admin** dans le header
4. **Accéder à l'administration** via le bouton
5. **Gérer les utilisateurs** et données

---

*Système d'administration sécurisé pour ADN Family Office* 🏛️ 