# 🚀 Configuration de l'environnement Staging sur OVH

## ✅ Ce qui a été fait

L'environnement de staging a été créé sur le serveur OVH :

| Élément | Production | Staging |
|---------|------------|---------|
| **Dossier** | `~/www` | `~/www-staging` |
| **Document root** | `www/public` | `www-staging/public` |
| **APP_ENV** | `prod` | `prod` |
| **APP_DEBUG** | `0` | `1` (activé) |
| **Domaine** | `adnfamilyoffice.fr` | `staging.adnfamilyoffice.fr` (à configurer) |

## 📝 Configuration à faire dans OVH Manager

### 1. Ajouter le sous-domaine multisite

1. Connectez-vous à [OVH Manager](https://manager.eu.ovhcloud.com/)
2. Naviguez vers : **Web Cloud** → **Hébergements** → **yarpcur.cluster100.hosting.ovh.net** → **Multisite**
3. Cliquez sur **"Ajouter un domaine ou sous-domaine"**
4. Remplissez les champs :

   - **Type** : Sous-domaine existant
   - **Domaine** : `staging.adnfamilyoffice.fr`
   - **Dossier racine** : `www-staging/public`
   - **SSL** : ✅ Activer (Let's Encrypt gratuit)
   - **Firewall** : Désactivé ou selon vos préférences
   - **CDN** : Désactivé (recommandé pour staging)

5. Validez et attendez la propagation (~15-30 minutes)

### 2. Configuration DNS (si nécessaire)

Si le sous-domaine n'est pas automatiquement détecté, ajoutez une entrée DNS :

Dans **Zones DNS** pour `adnfamilyoffice.fr` :

```
staging    IN    CNAME    adnfamilyoffice.fr.
```

Ou en A record :

```
staging    IN    A        54.36.142.133
staging    IN    AAAA     (IPv6 du serveur si disponible)
```

## 🔧 Structure des fichiers

```
/home/yarpcur/
├── www/                      # 🌐 PRODUCTION
│   ├── public/              # Document root prod
│   ├── .env.local           # Config prod (APP_DEBUG=0)
│   └── ...
│
└── www-staging/              # 🧪 STAGING
    ├── public/              # Document root staging
    ├── .env.local           # Config staging (APP_DEBUG=1)
    └── ...
```

## 🖥️ Environnement local staging

Pour développer localement avec le même environnement que le staging OVH, créez un fichier `.env.staging` à la racine du projet et lancez les commandes avec `APP_ENV=staging`.

Exemple d’utilisation locale :

```bash
cp .env.staging .env.staging.local
# modifier .env.staging.local avec vos secrets locaux
APP_ENV=staging php bin/console cache:clear
```

La configuration commise pour le staging local doit contenir :

- `APP_ENV=staging`
- `APP_DEBUG=1`
- `DATABASE_URL` locale
- `MAILER_DSN=null://null` pour désactiver les emails

## 📊 Différences entre les environnements

| Aspect | Production | Staging |
|--------|------------|---------|
| **Branche Git** | `main` | `staging` |
| **Debug** | Désactivé | Activé (erreurs détaillées) |
| **Toolbar** | Non | Non (bundles dev non installés) |
| **Cache** | Optimisé | Standard |
| **Erreurs** | Page d'erreur générique | Stack trace complet |
| **URL** | `adnfamilyoffice.fr` | `staging.adnfamilyoffice.fr` |

## 🚀 Déploiement manuel vers Staging

Pour mettre à jour le staging depuis votre machine locale :

```bash
# 1. Se connecter en SSH
ssh yarpcur@ssh.cluster100.hosting.ovh.net -p 22

# 2. Aller dans le dossier staging
cd ~/www-staging

# 3. Mettre à jour le code (si Git est configuré)
git pull origin main

# 4. Installer les dépendances (si nécessaire)
composer install --no-dev --optimize-autoloader

# 5. Vider le cache
php bin/console cache:clear --env=prod
```

## 🔄 Déploiement avec rsync (alternative)

Depuis votre machine locale :

```bash
# Synchroniser le code vers staging
rsync -avz --exclude='var/cache/*' --exclude='var/log/*' --exclude='.env.local' \
    -e "ssh -p 22" \
    ./ yarpcur@ssh.cluster100.hosting.ovh.net:~/www-staging/
```

## ⚠️ Notes importantes

1. **Base de données** : Le staging utilise actuellement la **même base de données** que la production. Pour un environnement totalement isolé, créez une nouvelle BD dans OVH Manager.

2. **APP_SECRET** : Chaque environnement a son propre secret pour la sécurité.

3. **Emails** : Le staging utilise les mêmes credentials Mailjet. Attention aux emails envoyés en test !

4. **API externe** : Les clés API (TwelveData) sont partagées.

## 🎯 Prochaines étapes (CI/CD)

Une fois le staging fonctionnel, vous pourrez configurer :

1. **GitHub Actions** ou **GitLab CI** pour le déploiement automatique
2. **Webhook** OVH pour déclencher des déploiements
3. **Scripts de migration** automatiques

---

📅 Créé le : 15 décembre 2025
🔧 Environnement : OVH Web Hosting - yarpcur.cluster100.hosting.ovh.net

