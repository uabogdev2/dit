
# Backend Dots & Boxes

## 📌 Introduction
Ce backend supporte le jeu multijoueur **Dots & Boxes** avec :
- API REST Laravel 10
- Authentification Firebase (Google & Apple)
- Serveur WebSocket **Socket.IO** self-hosted
- Redis pour Pub/Sub et queues
- MySQL pour stockage persistant
- Admin panel pour notifications, mise à jour obligatoire et remote config

## 🛠️ Stack technique
- PHP 8.2 + Laravel 10
- Node.js + Socket.IO (WebSocket server)
- Redis (Pub/Sub + Queues)
- MySQL / MariaDB
- Firebase Admin SDK (PHP)
- Laravel Sanctum ou JWT pour token backend
- Filament/Nova pour l’admin panel
- Docker + docker-compose pour dev et prod

## 🔑 Authentification
1. Client se connecte via Firebase Auth (Google/Apple)
2. Récupère un `Firebase ID Token`
3. Appelle l’endpoint backend : `POST /api/v1/auth/firebase`
4. Backend valide le token via Firebase Admin SDK
5. Backend renvoie un **access_token** (JWT/Sanctum) pour utiliser toutes les routes API et se connecter au serveur WebSocket

## 🗄️ Architecture base de données
Tables principales :
- `users` : id, firebase_uid, display_name, avatar_url, stats (JSON)
- `matches` : id, code, grid_size, status, current_turn_user_id, winner_user_id, board_state (JSON)
- `match_players` : match_id, user_id, order_index, score
- `moves` : id, match_id, player_id, edge (JSON), move_idempotency_key
- `squares` : match_id, owner_user_id, coords (JSON)
- `device_tokens` : user_id, platform, token
- `notifications` : title, body, target, payload
- `app_configs` : key, value (JSON)

## 🌐 API REST
Endpoints principaux :
- `POST /api/v1/auth/firebase` → Auth Firebase → token backend
- `GET /api/v1/me` → info utilisateur
- `POST /api/v1/matches` → créer une partie
- `POST /api/v1/matches/{id}/join` → rejoindre une partie
- `GET /api/v1/matches/{id}` → état partie
- `GET /api/v1/matches/{id}/history` → historique des coups
- Admin :
  - `/api/v1/admin/notifications`
  - `/api/v1/admin/force-update`
  - `/api/v1/admin/config`

## 📡 WebSocket (Socket.IO)
### Connexion
```
wss://ws.dotsandboxes.example:443
```
Header :
```
Authorization: Bearer <backend_token>
```
### Rooms / Channels
- `match_<match_id>` → messages partie en cours
- `user_<user_id>` → notifications personnelles
- `lobby` → matchmaking public

### Events principaux
| Event | Description |
|-------|------------|
| `match.created` | Partie créée |
| `match.joined` | Joueur a rejoint |
| `move.play` | Joueur joue un trait |
| `move.played` | Trait validé et broadcast |
| `square.completed` | Carré fermé |
| `match.finished` | Partie terminée |
| `presence.update` | Statut des joueurs |

## 🧾 Admin Panel
- Gérer utilisateurs : modifier pseudo, avatar, bannir
- Notifications push ciblées / planifiées
- Forced update (min version iOS/Android)
- Remote config / feature flags
- Visualiser et modérer les parties en cours

## 🧪 Tests
- Unit tests : règles de jeu, idempotence moves
- Intégration : auth Firebase + backend token
- E2E : connexion WebSocket + séquence partie
- Load testing : 500 parties simultanées minimum

## 🚀 Déploiement
- Docker Compose services :
  - `api` : Laravel
  - `socket-server` : Node.js + Socket.IO
  - `worker` : Laravel queue worker
  - `redis`
  - `mysql`
- Lancement WebSocket : `node socket-server.js`
- Lancement queues : `php artisan queue:work --queue=redis`
- Nginx proxy pour WebSocket avec SSL

## 📦 Livrables attendus
1. Repo GitHub propre
2. API REST fonctionnelle
3. Serveur Socket.IO opérationnel
4. Redis Pub/Sub + Queues
5. OpenAPI 3.0 documentation + Postman collection
6. Guide intégration Flutter (auth + WebSocket)
7. Docker Compose pour dev et prod

## ⚠️ Notes
- Toutes les actions côté WebSocket doivent être idempotentes (`move_id`)
- Board hash validé pour synchronisation
- Respecter la sécurité et vérification token Firebase
- Les events Socket.IO doivent pouvoir être replay pour synchronisation client
