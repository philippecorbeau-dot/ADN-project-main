# 📈 Tracking des Opportunités d'Investissement

## 🎯 Vue d'ensemble

Un système complet de tracking des clics sur les opportunités d'investissement a été ajouté au back-office moderne, permettant de suivre l'engagement des utilisateurs avec les différents produits financiers.

## 🏗️ Architecture Technique

### 📊 **Entité `InvestmentOpportunityClick`**
- Tracking de chaque clic avec métadonnées complètes
- Support des produits : SCPI, PEA-PME, Assurance-vie, PER
- Actions trackées : "Découvrir" et "Documents"
- Données collectées : utilisateur, IP, user-agent, timestamp, referrer

### 🗄️ **Base de données**
```sql
investment_opportunity_clicks
├── id (PK)
├── user_id (FK, nullable)
├── product_type (SCPI|PEA-PME|Assurance-vie|PER)
├── action (discover|documents)
├── ip_address
├── user_agent
├── clicked_at
└── referrer
```

### 🔄 **API de tracking**
- **Endpoint** : `POST /api/investment/track-click`
- **Payload** : `{"product": "SCPI", "action": "discover"}`
- **Sécurité** : Tracking automatique des métadonnées (IP, user-agent)

## 📊 **Statistiques disponibles dans le Dashboard**

### 🎯 **Métriques principales**
- **Total des clics** sur toutes les opportunités
- **Clics par produit** (SCPI, PEA-PME, Assurance-vie, PER)
- **Clics par action** (Découvrir vs Documents)
- **Évolution temporelle** (7 jours, 30 jours)

### 📈 **Interface Dashboard**
```
📈 Opportunités d'Investissement - Clics
┌─────────────────────────────────────────┐
│ 🏢 SCPI        📈 PEA-PME             │
│    109 clics      92 clics             │
│                                         │
│ 🛡️ Assurance-vie  ⏰ PER              │
│    99 clics        101 clics           │
└─────────────────────────────────────────┘

Actions :
• Boutons "Découvrir" : XXX clics
• Boutons "Documents" : XXX clics  
• Clics (7 derniers jours) : XXX
```

## 🎨 **Améliorations UI/UX**

### 📋 **Graphique KYC amélioré**
- **Légende explicative** : "Documents KYC (Know Your Customer)"
- **Description complète** : Documents d'identité, justificatifs, KBIS, UBO
- **Statistiques visuelles** : Cards colorées par statut
- **Meilleure compréhension** : Explications contextuelles

### 🎯 **Tracking Frontend**
- **JavaScript automatique** dans le dashboard d'investissement
- **Fonction `trackClick(product, action)`** 
- **Tracking non-bloquant** : N'interfère pas avec l'UX
- **Gestion d'erreurs** : Logging console pour debugging

## 🔧 **Fonctionnalités techniques**

### 📊 **Repository avancé**
```php
// Statistiques globales
$repository->getTotalClicks()
$repository->getTotalClicksSince($date)

// Analyse par produit
$repository->getClickStatsByProduct()
$repository->getTopProducts($limit)

// Analyse temporelle
$repository->getClicksPerDayLast30Days()

// Enregistrement
$repository->recordClick($product, $action, $user, $ip, $userAgent)
```

### 🎯 **API Admin**
- **Endpoint stats** : `GET /api/investment/stats` (ROLE_SUPER_ADMIN)
- **Données complètes** : Tous les indicateurs en JSON
- **Sécurité** : Accès restreint aux administrateurs

## 📱 **Implementation Frontend**

### 🔗 **Tracking des clics**
Chaque bouton d'opportunité d'investissement appelle automatiquement :
```javascript
onclick="trackClick('SCPI', 'discover')"
onclick="trackClick('PEA-PME', 'documents')"
```

### 📡 **API Call**
```javascript
function trackClick(product, action) {
    fetch('/api/investment/track-click', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            product: product,
            action: action
        })
    })
}
```

## 📈 **Produits trackés**

### 🏢 **SCPI** 
- Sociétés Civiles de Placement Immobilier
- Rendement 6-8%
- Icône : 🏢 (vert)

### 📈 **PEA-PME**
- Plan d'Épargne en Actions PME  
- Avantages fiscaux
- Icône : 📈 (bleu)

### 🛡️ **Assurance-vie**
- Contrat d'assurance-vie
- Transmission
- Icône : 🛡️ (violet)

### ⏰ **PER**
- Plan d'Épargne Retraite
- Retraite
- Icône : ⏰ (orange)

## 🛠️ **Commandes de gestion**

### 🎲 **Génération de données test**
```bash
php bin/console app:generate-test-clicks
```
Génère des clics de test pour les 30 derniers jours avec distribution réaliste.

### 📊 **Statistiques en console**
Affiche un tableau récapitulatif des clics par produit après génération.

## 🔍 **Analytics et insights**

### 📊 **Métriques business**
- **Produit le plus populaire** : Identification des préférences
- **Action préférée** : Découvrir vs Documentation
- **Tendances temporelles** : Pics d'activité, saisonnalité
- **Engagement utilisateur** : Mesure de l'intérêt

### 🎯 **Utilisation des données**
- **Optimisation produits** : Focus sur les plus populaires
- **Amélioration UX** : Adaptation selon les comportements
- **Décisions business** : Data-driven product development
- **ROI marketing** : Mesure de l'efficacité des campagnes

## 🚀 **Avantages**

### 📈 **Pour le business**
- **Compréhension utilisateur** : Quels produits intéressent le plus
- **Optimisation offre** : Adapter selon la demande
- **Mesure performance** : ROI des opportunités proposées

### 🎯 **Pour l'équipe**
- **Dashboard centralisé** : Toutes les métriques en un coup d'œil
- **Données temps réel** : Suivi immédiat des tendances
- **Interface intuitive** : Graphiques et cartes visuelles

### 🔧 **Pour les développeurs**
- **Code modulaire** : Facilement extensible
- **API propre** : Endpoints RESTful standards
- **Documentation complète** : Guide d'utilisation détaillé

## 🎉 **Résultat final**

Le dashboard du back-office moderne affiche maintenant :

✅ **Tracking complet** des opportunités d'investissement  
✅ **Statistiques visuelles** par produit et action  
✅ **Graphique KYC amélioré** avec légendes explicatives  
✅ **Interface responsive** et moderne  
✅ **Données temps réel** mises à jour automatiquement  

**Le système est opérationnel et prêt à collecter des insights précieux sur l'engagement utilisateur !** 🚀

