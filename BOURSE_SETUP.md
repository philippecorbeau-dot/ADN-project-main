# 📈 Configuration de la page Bourse - ADN Family Office

## 🎯 Objectif
Intégration d'une page "Bourse" dans le back-office EasyAdmin avec données en temps réel via l'API Twelve Data.

## 🛠️ Fonctionnalités implémentées

### ✅ Contrôleur BourseController
- **Route :** `/admin/bourse`
- **Sécurité :** Accès restreint aux `ROLE_SUPER_ADMIN`
- **API :** Intégration Twelve Data avec gestion d'erreurs

### ✅ Template responsive
- **Design :** Tailwind CSS avec cartes modernes
- **Responsive :** Grid adaptatif (1→2→3→4 colonnes)
- **Couleurs :** Vert pour hausse, rouge pour baisse
- **Auto-refresh :** Actualisation automatique toutes les 30 secondes

### ✅ Header conditionnel
- **Super Admin :** Header violet avec boutons "Bourse" et "Site"
- **Autres rôles :** Header standard EasyAdmin

### ✅ Service TwelveDataService
- **Gestion API :** Clé configurée via `.env`
- **Logging :** Gestion des erreurs avec PSR Logger
- **Timeout :** 10 secondes par requête

### ✅ Entité Stock
- **Base de données :** Table `stocks` avec timestamps
- **CRUD :** Interface EasyAdmin complète
- **Repository :** Méthodes de mise à jour optimisées

## 📋 Installation

### 1. Configuration de l'API
```bash
# Ajouter dans .env.local
TWELVEDATA_API_KEY=votre_cle_api_ici
```

### 2. Migration de la base de données
```bash
php bin/console doctrine:migrations:migrate
```

### 3. Synchronisation initiale des données
```bash
php bin/console app:sync-stock-data
```

### 4. Vérification
- Accéder à `/admin`
- Vérifier le menu "📈 MARCHÉS"
- Tester la page "📊 Bourse"

## 🎨 Interface utilisateur

### Header Super Admin
- **Couleur :** Gradient violet → indigo
- **Boutons :** "Bourse" et "Site" avec icônes
- **Badge :** "Super Admin" en violet

### Page Bourse
- **Actions affichées :** AAPL, TSLA, MSFT, GOOGL, AMZN
- **Données :** Prix, variation, volume, plus haut/bas
- **Indicateurs visuels :** Flèches vertes/rouges
- **Responsive :** Adaptation mobile/tablette/desktop

## 🔧 Configuration avancée

### Ajouter de nouvelles actions
```php
// Dans TwelveDataService.php
public function getDefaultStocks(): array
{
    $defaultSymbols = ['AAPL', 'TSLA', 'MSFT', 'GOOGL', 'AMZN', 'NFLX']; // Ajouter NFLX
    return $this->getMultipleStocks($defaultSymbols);
}
```

### Modifier l'auto-refresh
```javascript
// Dans bourse.html.twig
setInterval(function() {
    window.location.reload();
}, 60000); // 60 secondes au lieu de 30
```

### Personnaliser le header
```twig
{# Dans layout.html.twig #}
{% if is_granted('ROLE_SUPER_ADMIN') %}
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600">
        <!-- Personnalisation ici -->
    </div>
{% endif %}
```

## 🚀 Utilisation

### Accès à la page
1. Se connecter en tant que Super Admin
2. Aller dans `/admin`
3. Cliquer sur "📊 Bourse" dans le menu

### Synchronisation automatique
```bash
# Ajouter au cron pour synchronisation automatique
*/5 * * * * php bin/console app:sync-stock-data
```

### Monitoring
- **Logs :** Erreurs API dans `var/log/dev.log`
- **Base :** Données stockées en base avec timestamps
- **Cache :** Pas de cache pour données temps réel

## 🔒 Sécurité

### Permissions
- **Page Bourse :** `ROLE_SUPER_ADMIN` uniquement
- **CRUD Actions :** `ROLE_SUPER_ADMIN` uniquement
- **API Key :** Stockée dans `.env.local` (non versionnée)

### Validation
- **Données API :** Validation des réponses
- **Timeout :** 10 secondes max par requête
- **Erreurs :** Gestion gracieuse des échecs

## 📊 Données affichées

### Pour chaque action
- **Symbole :** AAPL, TSLA, etc.
- **Nom :** Nom complet de l'entreprise
- **Prix actuel :** Prix de clôture
- **Variation :** Changement en valeur et pourcentage
- **Volume :** Nombre d'actions échangées
- **Plus haut/bas :** Extrema de la journée
- **Ouverture :** Prix d'ouverture

### Indicateurs visuels
- **Flèche verte :** Hausse
- **Flèche rouge :** Baisse
- **Couleurs :** Vert/rouge selon la variation
- **Badges :** Statut Super Admin

## 🎯 Prochaines améliorations

1. **Graphiques :** Intégration Chart.js pour historiques
2. **Alertes :** Notifications sur seuils de prix
3. **Portefeuille :** Suivi de positions utilisateurs
4. **Plus d'actions :** Interface pour ajouter/supprimer
5. **WebSocket :** Mise à jour temps réel sans refresh

## 📝 Notes techniques

- **API Twelve Data :** Limite de requêtes selon le plan
- **Performance :** 5 requêtes simultanées max
- **Responsive :** Testé sur mobile/tablette/desktop
- **Accessibilité :** Contrastes et tailles conformes WCAG 