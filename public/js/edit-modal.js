/**
 * Album-Bearbeitungs-Dialog
 * 
 * Stellt einen modalen Dialog zum Bearbeiten von Alben zur Verfügung
 */

document.addEventListener('DOMContentLoaded', () => {
    initEditModal();
});

/**
 * Initialisiert den Modal-Dialog für Album-Bearbeitung
 */
function initEditModal() {
    // Modal-Dialog erstellen, falls noch nicht vorhanden
    if (!document.getElementById('edit-modal')) {
        const modalHtml = `
            <div id="edit-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Album bearbeiten</h3>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="album-edit-form" method="post">
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="edit-album-name">Titel</label>
                                    <input type="text" id="edit-album-name" name="album_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit-album-description">Beschreibung</label>
                                    <textarea id="edit-album-description" rows="3"></textarea>
                                </div>
                            </div>

                            <div class="image-management">
                                <h4>Bilder verwalten</h4>
                                <div class="image-edit-grid"></div>
                            </div>

                            <div class="visibility-section">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="edit-album-public" name="is_public">
                                    <label for="edit-album-public">Öffentlich zugänglich</label>
                                </div>
                            </div>

                            <div class="danger-zone">
                                <h4>Gefahrenbereich</h4>
                                <div class="danger-content">
                                    <p>Durch das Löschen des Albums werden alle Bilder permanent entfernt.</p>
                                    <button type="button" class="btn btn-danger" id="delete-album-btn">
                                        Album komplett löschen
                                    </button>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                                <button type="button" class="btn btn-secondary" id="cancel-edit">Abbrechen</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    const modal = document.getElementById('edit-modal');
    const editButton = document.getElementById('albumEditBtn');
    const closeButton = modal.querySelector('.modal-close');
    const cancelButton = document.getElementById('cancel-edit');
    const albumEditForm = document.getElementById('album-edit-form');
    const tabButtons = modal.querySelectorAll('.tab-button');
    
    // Album-Bearbeiten-Button
    if (editButton) {
        editButton.addEventListener('click', (e) => {
            e.preventDefault();
            openEditModal();
        });
    }
    
    // Schließen-Buttons
    if (closeButton) {
        closeButton.addEventListener('click', closeEditModal);
    }
    
    if (cancelButton) {
        cancelButton.addEventListener('click', closeEditModal);
    }
    
    // Außerhalb des Modals klicken, um zu schließen
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeEditModal();
        }
    });
    
    // Bilder sofort laden
    loadImagesForEditing();
    
    // Formular-Übermittlung
    if (albumEditForm) {
        albumEditForm.addEventListener('submit', (e) => {
            e.preventDefault();
            submitAlbumEdit();
        });
    }
}

/**
 * Öffnet den Modal-Dialog mit den aktuellen Album-Daten
 */
function openEditModal() {
    const modal = document.getElementById('edit-modal');
    if (!modal) return;
    
    // Album-ID aus URL extrahieren
    const urlParams = new URLSearchParams(window.location.search);
    const albumId = urlParams.get('id');
    
    if (!albumId) return;
    
    // Formularfelder mit aktuellen Daten füllen
    document.getElementById('edit-album-id').value = albumId;
    
    // Album-Name aus der Seite extrahieren
    const albumNameElement = document.querySelector('.section-header h2');
    if (albumNameElement) {
        document.getElementById('edit-album-name').value = albumNameElement.textContent;
    }
    
    // Öffentlich-Status aus Badge ermitteln
    const publicBadge = document.querySelector('.public-badge');
    document.getElementById('edit-album-public').checked = !!publicBadge;
    
    // Titelbild-Auswahl aktualisieren
    updateCoverImageSelect();
    
    // Modal anzeigen
    modal.style.display = 'block';
    
    // Bilderliste direkt laden
    loadImagesForEditing();
}

/**
 * Aktualisiert die Titelbild-Auswahl mit allen verfügbaren Bildern
 */
function updateCoverImageSelect() {
    const select = document.getElementById('edit-cover-image');
    if (!select) return;
    
    // Aktuelle Auswahl speichern
    const currentValue = select.value;
    
    // Alle Optionen entfernen
    select.innerHTML = '<option value="">Kein Titelbild</option>';
    
    // Alle Bilder aus dem Album als Optionen hinzufügen
    const images = Array.from(document.querySelectorAll('.image-grid .image-card'));
    images.forEach(image => {
        const imageId = image.dataset.imageId;
        const imageName = image.dataset.imageName || 'Unbenanntes Bild';
        
        const option = document.createElement('option');
        option.value = imageId;
        option.textContent = imageName;
        option.selected = imageId === currentValue;
        
        select.appendChild(option);
    });
}

/**
 * Lädt die Bilder für die Bearbeitung
 */
function loadImagesForEditing() {
    const imageGrid = document.querySelector('.image-edit-grid');
    if (!imageGrid) return;
    
    // Leere das Grid
    imageGrid.innerHTML = '';
    
    // Bilder aus der Seite laden
    const images = Array.from(document.querySelectorAll('.image-grid .image-card'));
    
    if (images.length === 0) {
        imageGrid.innerHTML = '<p>Keine Bilder vorhanden.</p>';
        return;
    }
    
    // Jedes Bild mit Bearbeitungsoptionen hinzufügen
    images.forEach(image => {
        const imageId = image.dataset.imageId;
        const imageSrc = image.querySelector('img').src;
        const isPublic = image.querySelector('.public-badge') !== null;
        const rotation = parseInt(image.dataset.rotation || '0');
        const description = image.dataset.description || '';
        
        const imageHtml = `
            <div class="edit-image-card" data-image-id="${imageId}" data-rotation="${rotation}">
                <div class="edit-image-preview">
                    <img src="${imageSrc}" alt="Vorschau" style="transform: rotate(${rotation}deg);">
                </div>
                <div class="edit-image-info">
                    <textarea class="image-description" placeholder="Bildbeschreibung">${description}</textarea>
                </div>
                <div class="image-actions">
                    <button type="button" class="image-action-btn" title="Nach links drehen" data-action="rotate-left">↺</button>
                    <button type="button" class="image-action-btn" title="Nach rechts drehen" data-action="rotate-right">↻</button>
                    <button type="button" class="image-action-btn" title="Als Titelbild setzen" data-action="set-cover">⭐</button>
                    <button type="button" class="image-action-btn" title="Löschen" data-action="delete">🗑️</button>
                </div>
            </div>
        `;
        
        imageGrid.insertAdjacentHTML('beforeend', imageHtml);
    });
    
    // Event-Listener für Bild-Aktionen
    imageGrid.querySelectorAll('.image-action-btn').forEach(button => {
        button.addEventListener('click', function() {
            const card = this.closest('.edit-image-card');
            const action = this.dataset.action;
            
            switch(action) {
                case 'rotate-left':
                    rotateImageInEditor(card, -90);
                    break;
                case 'rotate-right':
                    rotateImageInEditor(card, 90);
                    break;
                case 'set-cover':
                    setCoverImage(card.dataset.imageId);
                    break;
                case 'delete':
                    if (confirm('Möchten Sie dieses Bild wirklich unwiderruflich löschen?')) {
                        deleteImageInEditor(card);
                    }
                    break;
            }
        });
    });
    
    // Event-Listener für Beschreibungen
    imageGrid.querySelectorAll('.image-description').forEach(textarea => {
        textarea.addEventListener('change', function() {
            updateImageDescription(
                this.closest('.edit-image-card').dataset.imageId,
                this.value.trim()
            );
        });
    });
}

/**
 * Rotiert ein Bild im Editor
 * 
 * @param {HTMLElement} imageCard Das Bild-Element
 * @param {number} degrees Die Rotationsgrad-Änderung
 */
function rotateImageInEditor(imageCard, degrees) {
    if (!imageCard) return;
    
    const imageId = imageCard.dataset.imageId;
    let currentRotation = parseInt(imageCard.dataset.rotation || '0');
    
    // Rotation anpassen
    currentRotation = (currentRotation + degrees + 360) % 360;
    imageCard.dataset.rotation = currentRotation;
    
    // Vorschaubild rotieren
    const img = imageCard.querySelector('img');
    if (img) {
        img.style.transform = `rotate(${currentRotation}deg)`;
    }
    
    // Rotation speichern mit API
    fetch('/app/api/rotate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            image_id: imageId,
            rotation: currentRotation
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Rotation gespeichert:', data.message);
            
            // Auch das Bild in der Hauptansicht aktualisieren
            const mainImageCard = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
            if (mainImageCard) {
                mainImageCard.dataset.rotation = currentRotation;
                const mainImg = mainImageCard.querySelector('img');
                if (mainImg) {
                    mainImg.style.transform = `rotate(${currentRotation}deg)`;
                }
            }
        } else {
            console.error('Fehler beim Speichern der Rotation:', data.message);
        }
    })
    .catch(error => console.error('API-Fehler:', error));
}

/**
 * Aktualisiert die Beschreibung eines Bildes
 * 
 * @param {string} imageId Die Bild-ID
 * @param {string} description Die neue Beschreibung
 */
function updateImageDescription(imageId, description) {
    fetch('/app/api/update-image.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            image_id: imageId,
            description: description
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Beschreibung gespeichert:', data.message);
            
            // Auch das Bild in der Hauptansicht aktualisieren
            const mainImageCard = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
            if (mainImageCard) {
                mainImageCard.dataset.description = description;
            }
        } else {
            console.error('Fehler beim Speichern der Beschreibung:', data.message);
        }
    })
    .catch(error => console.error('API-Fehler:', error));
}

/**
 * Setzt ein Bild als Titelbild
 *
 * @param {string} imageId Die Bild-ID
 */
function setCoverImage(imageId) {
    const select = document.getElementById('edit-cover-image');
    if (!select) return;
    
    select.value = imageId;
    showToast('Titelbild wurde aktualisiert');
}

/**
 * Löscht ein Bild im Editor
 *
 * @param {HTMLElement} imageCard Das Bild-Element
 */
function deleteImageInEditor(imageCard) {
    if (!imageCard) return;
    
    const imageId = imageCard.dataset.imageId;
    
    fetch('/app/api/update-image.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ image_id: imageId })
    })
    .then(response => {
        if (response.ok) {
            // Bild aus dem Editor entfernen
            imageCard.remove();
            
            // Auch das Bild in der Hauptansicht entfernen
            const mainImageCard = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
            if (mainImageCard) {
                mainImageCard.remove();
            }
            
            // Titelbild-Auswahl aktualisieren
            updateCoverImageSelect();
        } else {
            console.error('Fehler beim Löschen des Bildes');
        }
    })
    .catch(error => console.error('API-Fehler:', error));
}

/**
 * Sendet die Album-Bearbeitung an den Server
 */
function submitAlbumEdit() {
    const form = document.getElementById('album-edit-form');
    if (!form) return;
    
    const albumId = form.querySelector('#edit-album-id').value;
    const name = form.querySelector('#edit-album-name').value;
    const isPublic = form.querySelector('#edit-album-public').checked ? 1 : 0;
    const coverImageId = form.querySelector('#edit-cover-image').value;
    
    // Album über API aktualisieren
    fetch('/app/api/update-album.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            album_id: albumId,
            name: name,
            is_public: isPublic,
            cover_image_id: coverImageId || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Dialog schließen
            closeEditModal();
            
            // Seite neu laden, um Änderungen anzuzeigen
            window.location.reload();
        } else {
            console.error('Fehler beim Speichern des Albums:', data.message);
        }
    })
    .catch(error => console.error('API-Fehler:', error));
}

/**
 * Schließt den Modal-Dialog
 */
function closeEditModal() {
    const modal = document.getElementById('edit-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}