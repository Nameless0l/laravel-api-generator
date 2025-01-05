# Mises à jour futures:
## HTTP
- Authentification avec JWT 
  - Erire les UseCase de Login pour Organization et User
  - Rajouter La sécurité (SecurityConfig) pour proteger les endpoints
- Gestion des modes de conversation:
  - [BLOCKED] Rajouter un endpoint pout changer le mode d'une conversation
  - Update le usecase d'envoie  de messages `@[SendMessageUseCase]` pour qu'il envoie le message à Alan selon le mode
- Gestion des Media:
  - Ecrire la feature pour uploader les media

## Integration de Netty
- [PENDING] Ranger les projets par namespace; un namespace par projet pour isoler les communications
- [OK] Configuration du serveur WS
- [OK] Gestion des connexion et deconnexions d'utilisateurs
- [OK] Gestion de l'envoie des messages
- [PENDING] Accusé de reception de message
- [PENDING] Accusé de lecture de message
- [TODO] add project id apres authentification