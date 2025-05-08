<!-- Album-Editor-Modal (per Default ausgeblendet) -->
<div id="album-edit-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Album bearbeiten</h3>
            <button class="modal-close" id="close-album-modal" aria-label="Schließen">×</button>
        </div>
        <div class="modal-body">
            <!-- Formular-Elemente -->
            <div class="edit-section">
                <div class="form-group">
                    <label for="edit-album-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Titel
                    </label>
                    <input type="text" id="edit-album-title" value="<?php echo htmlspecialchars($album['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="edit-album-description">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="21" y1="6" x2="3" y2="6"></line>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                            <line x1="17" y1="18" x2="3" y2="18"></line>
                        </svg>
                        Beschreibung
                    </label>
                    <textarea id="edit-album-description" rows="3"><?php echo htmlspecialchars($album['description'] ?? ''); ?></textarea>
                </div>

                <?php if ($hasParentColumn && isset($album['parent_album_id']) && $album['parent_album_id']): ?>
                <!-- Information über übergeordnetes Album, wenn dieses Album ein Unteralbum ist -->
                <div class="form-group">
                    <label>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                        Übergeordnetes Album
                    </label>
                    <div class="static-field">
                        <?php
                        $parentName = $db->fetchValue(
                            "SELECT name FROM albums WHERE id = :id",
                            ['id' => $album['parent_album_id']]
                        );
                        echo htmlspecialchars($parentName);
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="edit-album-public" class="toggle-label">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        Sichtbarkeit
                    </label>
                    <div class="toggle-switch">
                        <input type="checkbox" id="edit-album-public" <?php echo $album['is_public'] ? 'checked' : ''; ?>>
                        <label for="edit-album-public" class="toggle-slider"></label>
                        <span class="toggle-label-text"><?php echo $album['is_public'] ? 'Öffentlich' : 'Privat'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Titelbild-Auswahl -->
            <div class="edit-section">
                <h4>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    Titelbild
                </h4>
                
                <!-- Aktuelle Titelbild-Vorschau -->
                <div class="current-cover-preview">
                    <div class="cover-image-wrapper">
                        <?php if ($album['cover_image']): ?>
                            <img src="../storage/thumbs/<?php echo rtrim($album['path'], '/') . '/' . basename($album['cover_image']); ?>" alt="Aktuelles Titelbild">
                        <?php else: ?>
                            <div class="no-cover">Kein Titelbild</div>
                        <?php endif; ?>
                    </div>
                    <button id="select-cover-image" class="btn-action">
                        Bild auswählen
                    </button>
                </div>
                
                <!-- Miniaturansicht für Titelbild-Auswahl (wird via JS angezeigt) -->
                <div id="cover-selection-container" class="cover-selection" style="display: none;">
                    <div class="cover-selection-header">
                        <h5>Wählen Sie ein Titelbild</h5>
                        <button id="close-cover-selection" class="btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div id="cover-thumbnails" class="cover-thumbnails">
                        <!-- Hier werden die Miniaturansichten via JavaScript eingefügt -->
                    </div>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="edit-section danger-zone">
                <h4>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    Gefahrenbereich
                </h4>
                <div class="danger-description">
                    <p>Diese Aktion kann nicht rückgängig gemacht werden. Das Album und alle enthaltenen Bilder werden dauerhaft vom Server gelöscht.</p>
                </div>
                <button id="delete-album" class="btn-danger" data-album-id="<?php echo $albumId; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                    Album permanent löschen
                </button>
            </div>
            
            <!-- Formular-Aktionen -->
            <div class="form-actions">
                <button id="save-album-changes" class="btn-primary" data-album-id="<?php echo $albumId; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Speichern
                </button>
                <button id="cancel-album-changes" class="btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Abbrechen
                </button>
            </div>
        </div>
    </div>
</div>