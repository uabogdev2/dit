# Guide d'intégration Flutter - Dots & Boxes

Ce guide explique comment connecter l'application Flutter au backend Laravel et au serveur Socket.IO.

## 1. Authentification

Le backend utilise l'authentification Firebase. Le client Flutter doit :

1.  Se connecter à Firebase Auth (Google/Apple) côté client.
2.  Récupérer le `idToken` de l'utilisateur courant :
    ```dart
    User? user = FirebaseAuth.instance.currentUser;
    String? token = await user?.getIdToken();
    ```
3.  Envoyer ce token au backend pour obtenir un **Access Token** API :

    *   **Endpoint** : `POST /api/v1/auth/firebase`
    *   **Body** : `{ "token": "FIREBASE_ID_TOKEN" }`
    *   **Response** :
        ```json
        {
            "access_token": "1|abcdef...",
            "token_type": "Bearer",
            "user": { ... }
        }
        ```

4.  Stocker cet `access_token`. Il devra être envoyé dans le header `Authorization` pour toutes les requêtes API suivantes et pour la connexion Socket.IO.

## 2. API REST

Base URL : `https://votre-backend.com/api/v1` (ou `http://10.0.2.2:8000/api/v1` pour émulateur Android).

### Headers
```
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

### Endpoints Principaux

*   **Lister mes parties** : `GET /matches`
*   **Créer une partie** : `POST /matches`
    *   Body : `{ "grid_size": 3 }`
*   **Rejoindre une partie** : `POST /matches/{code}/join`
*   **Détails partie** : `GET /matches/{id}`
*   **Jouer un coup** : `POST /matches/{id}/moves`
    *   Body :
        ```json
        {
            "r": 0,
            "c": 0,
            "o": "h", // "h" (horizontal) ou "v" (vertical)
            "move_idempotency_key": "uuid-v4"
        }
        ```

## 3. WebSocket (Socket.IO)

URL : `wss://votre-backend.com` (port 443 ou 3000).

### Connexion
Vous devez passer le token dans les options d'authentification :

```dart
IO.Socket socket = IO.io('https://votre-backend.com', <String, dynamic>{
    'transports': ['websocket'],
    'autoConnect': false,
    'auth': {
      'token': 'Bearer <access_token>'
    }
});
socket.connect();
```

### Events à écouter

*   `match.created` : Nouvelle partie (si abonné au lobby).
*   `match.joined` : Un joueur a rejoint.
    *   Payload : Objet Match complet.
*   `move.played` : Un coup a été joué.
    *   Payload :
        ```json
        {
            "move": { "r": 0, "c": 0, "o": "h", "user_id": 1 },
            "next_turn_user_id": 2,
            "squares_completed": [ { "r": 0, "c": 0 } ]
        }
        ```
*   `match.finished` : Partie terminée.
    *   Payload : Objet Match avec `winner_user_id`.

### Rooms
*   `join_lobby` : Pour recevoir les notifs de création de partie publique.
*   `join_match` : Envoyer `{matchId}` pour écouter les événements d'une partie spécifique.
    *   Exemple : `socket.emit('join_match', matchId);`
