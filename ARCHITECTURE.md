# Fotogalerie-Architektur Dokumentation

## Favoriten-System

### Datenbank-Erweiterung
```mermaid
erDiagram
    FAVORITES {
        int id PK
        int user_id FK
        int image_id FK
        datetime created_at
    }
    USERS ||--o{ FAVORITES : "hat"
    FAVORITES }|--|| IMAGES : "referenziert"
```

### API-Endpoints
```plaintext
POST /favorites/{image_id}    - Fügt Bild zu Favoriten hinzu
DELETE /favorites/{image_id}  - Entfernt Bild aus Favoriten
GET /favorites                - Listet alle Favoriten des Users
```

### Dateisystem-Integration
- Symlinks im Benutzerordner: /users/user_123/favorites -> /storage/favorites/user_123
- Keine physischen Kopien zur Speicherplatzoptimierung

### UI-Komponenten
- ★-Icon in Bildvorschau (ausgefüllt bei Favorit)
- Favoriten-Filter in der Albumübersicht
- Drag & Drop zur Favoritenverwaltung

[Rest der Architektur unverändert]