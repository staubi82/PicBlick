/**
 * Album-Bearbeitungsmodul mit modernem Overlay-Ansatz
 * Ermöglicht Bearbeitung von Titel, Beschreibung, Sichtbarkeit und Titelbild
 */

document.addEventListener('DOMContentLoaded', function() {
    // DOM-Elemente referenzieren
    const editBtn = document.getElementById('edit-album-toggle');
    const albumModal = document.getElementById('album-edit-modal');
    const saveBtn = document.getElementById('save-album-changes');
    const cancelBtn = document.getElementById('cancel-album-changes');
    const deleteBtn = document.getElementById('delete-album');
    const closeModalBtn = document.getElementById('close-album-modal');
    const selectCoverBtn = document.getElementById('select-cover-image');
    const closeCoverSelectionBtn = document.getElementById('close-cover-selection');
    const coverSelectionContainer = document.getElementById('cover-selection-container');
    const coverThumbnailsContainer = document.getElementById('cover-thumbnails');
    const editAlbumTitleInput = document.getElementById('edit-album-title');
    const editAlbumDescriptionInput = document.getElementById('edit-album-description');
    const editAlbumPublicToggle = document.getElementById('edit-album-public');
    
    // Ursprüngliche Werte und Zustandsvariablen
    let originalValues = {};
    let isCoverSelectionMode = false;
    
    // Modal öffnen/schließen
    function openModal() {
        // Aktuelle Werte in die Formularfelder setzen
        const albumTitle = document.getElementById('album-title');
        const albumDescription = document.getElementById('album-description');
        
        originalValues = {
            title: albumTitle ? albumTitle.textContent.trim() : '',
            description: albumDescription ? albumDescription.textContent.trim() : '',
            isPublic: editAlbumPublicToggle ? editAlbumPublicToggle.checked : false
        };
        
        if (editAlbumTitleInput) {
            editAlbumTitleInput.value = originalValues.title;
        }
        
        if (editAlbumDescriptionInput) {
            editAlbumDescriptionInput.value = originalValues.description;
        }
        
        // Toggle-Label aktualisieren
        updateToggleLabel();
        
        // Modal anzeigen
        if (albumModal) {
            albumModal.style.display = 'block';
            document.body.classList.add('modal-open');
        }
    }
    
    function closeModal() {
        // Modal schließen
        if (albumModal) {
            albumModal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        // Titelbild-Auswahl zurücksetzen
        if (coverSelectionContainer) {
            coverSelectionContainer.style.display = 'none';
        }
        isCoverSelectionMode = false;
    }
    
    // Toggle-Label aktualisieren basierend auf Checkbox-Status
    function updateToggleLabel() {
        if (editAlbumPublicToggle) {
            const labelText = editAlbumPublicToggle.checked ? 'Öffentlich' : 'Privat';
            const labelElement = editAlbumPublicToggle.parentElement.querySelector('.toggle-label-text');
            if (labelElement) {
                labelElement.textContent = labelText;
            }
        }
    }
    
    // Album-Änderungen speichern
    function saveChanges() {
        const albumId = saveBtn.dataset.albumId;
        const newTitle = editAlbumTitleInput ? editAlbumTitleInput.value.trim() : '';
        const newDesc = editAlbumDescriptionInput ? editAlbumDescriptionInput.value.trim() : '';
        const isPublic = editAlbumPublicToggle ? editAlbumPublicToggle.checked : false;
        
        if (!newTitle) {
            alert('Der Album-Titel darf nicht leer sein.');
            editAlbumTitleInput.focus();
            return;
        }
        
        // API-Anfrage zum Aktualisieren des Albums
        fetch('../app/api/update-album.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                album_id: albumId,
                name: newTitle,
                description: newDesc,
                is_public: isPublic ? 1 : 0
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // UI mit neuen Werten aktualisieren
                const albumTitleElement = document.getElementById('album-title');
                const albumDescriptionElement = document.getElementById('album-description');
                
                if (albumTitleElement) {
                    albumTitleElement.textContent = newTitle;
                }
                
                if (albumDescriptionElement) {
                    albumDescriptionElement.textContent = newDesc;
                }
                
                alert('Album erfolgreich aktualisiert.');
                closeModal();
            } else {
                alert('Fehler: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            alert('Ein Fehler ist aufgetreten.');
        });
    }
    
    // Album löschen
    function deleteAlbum() {
        if (confirm('ACHTUNG: Sind Sie sicher, dass Sie das Album permanent löschen möchten? Alle Bilder werden vom Server gelöscht. Diese Aktion kann nicht rückgängig gemacht werden.')) {
            const albumId = deleteBtn.dataset.albumId;
            
            fetch('../app/api/update-album.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    album_id: albumId,
                    delete: true
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Album wurde erfolgreich gelöscht.');
                    window.location.href = '/public/index.php';
                } else {
                    alert('Fehler beim Löschen des Albums: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Fehler beim Senden der Anfrage:', error);
                alert('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.');
            });
        }
    }
    
    // Titelbild-Auswahl zeigen
    function showCoverSelection() {
        if (!coverSelectionContainer || !coverThumbnailsContainer) return;
        
        // Status setzen
        isCoverSelectionMode = true;
        
        // Miniaturansichten der Album-Bilder laden
        loadCoverThumbnails();
        
        // Container anzeigen
        coverSelectionContainer.style.display = 'block';
    }
    
    // Titelbild-Auswahl ausblenden
    function hideCoverSelection() {
        if (coverSelectionContainer) {
            coverSelectionContainer.style.display = 'none';
        }
        isCoverSelectionMode = false;
    }
    
    // Miniaturansichten für Titelbild-Auswahl laden
    function loadCoverThumbnails() {
        if (!coverThumbnailsContainer) return;
        
        // Container leeren
        coverThumbnailsContainer.innerHTML = '';
        
        // Alle Bilder im Album finden
        const albumImages = document.querySelectorAll('.image-card');
        
        if (albumImages.length === 0) {
            coverThumbnailsContainer.innerHTML = '<p class="empty-state">Keine Bilder im Album vorhanden.</p>';
            return;
        }
        
        // Für jedes Bild eine Miniaturansicht erstellen
        albumImages.forEach(imageCard => {
            const imageId = imageCard.dataset.imageId;
            const imageSrc = imageCard.querySelector('img').src;
            
            const thumbnail = document.createElement('div');
            thumbnail.className = 'cover-thumbnail';
            thumbnail.dataset.imageId = imageId;
            
            const img = document.createElement('img');
            img.src = imageSrc;
            img.alt = 'Titelbild-Option';
            
            thumbnail.appendChild(img);
            coverThumbnailsContainer.appendChild(thumbnail);
            
            // Event-Listener für Klick auf Miniaturansicht
            thumbnail.addEventListener('click', function() {
                selectCoverImage(imageId, imageSrc);
            });
        });
    }
    
    // Titelbild auswählen
    function selectCoverImage(imageId, imageSrc) {
        const albumId = saveBtn.dataset.albumId;
        
        // API-Anfrage zum Setzen des Titelbilds
        fetch('../app/api/update-album.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                album_id: albumId,
                cover_image_id: imageId
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Ausgewähltes Bild markieren
                const thumbnails = document.querySelectorAll('.cover-thumbnail');
                thumbnails.forEach(thumb => {
                    thumb.classList.remove('selected');
                    if (thumb.dataset.imageId === imageId) {
                        thumb.classList.add('selected');
                    }
                });
                
                // Vorschau aktualisieren
                const previewImg = document.querySelector('.cover-image-wrapper img');
                if (previewImg) {
                    previewImg.src = imageSrc;
                } else {
                    const noPreview = document.querySelector('.no-cover');
                    if (noPreview) {
                        const coverWrapper = noPreview.parentElement;
                        noPreview.remove();
                        
                        const img = document.createElement('img');
                        img.src = imageSrc;
                        img.alt = 'Aktuelles Titelbild';
                        coverWrapper.appendChild(img);
                    }
                }
                
                // Titelbild auf der Hauptseite aktualisieren
                const albumCover = document.querySelector('.album-cover img');
                if (albumCover) {
                    albumCover.src = imageSrc;
                } else {
                    const albumHeader = document.querySelector('.album-header');
                    if (albumHeader) {
                        const coverDiv = document.createElement('div');
                        coverDiv.className = 'album-cover';
                        
                        const img = document.createElement('img');
                        img.src = imageSrc;
                        img.alt = 'Album-Titelbild';
                        
                        coverDiv.appendChild(img);
                        albumHeader.insertBefore(coverDiv, albumHeader.firstChild);
                    }
                }
                
                alert('Album-Titelbild erfolgreich aktualisiert.');
                hideCoverSelection();
            } else {
                alert('Fehler: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Fehler:', error);
            alert('Ein Fehler ist aufgetreten.');
        });
    }
    
    // Event-Listener
    if (editBtn) {
        editBtn.addEventListener('click', openModal);
    }
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }
    
    if (saveBtn) {
        saveBtn.addEventListener('click', saveChanges);
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeModal);
    }
    
    if (deleteBtn) {
        deleteBtn.addEventListener('click', deleteAlbum);
    }
    
    if (selectCoverBtn) {
        selectCoverBtn.addEventListener('click', showCoverSelection);
    }
    
    if (closeCoverSelectionBtn) {
        closeCoverSelectionBtn.addEventListener('click', hideCoverSelection);
    }
    
    if (editAlbumPublicToggle) {
        editAlbumPublicToggle.addEventListener('change', updateToggleLabel);
    }
    
    // Modal bei ESC-Taste schließen
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && albumModal && albumModal.style.display === 'block') {
            closeModal();
        }
    });
    
    // Modal bei Klick außerhalb des Inhalts schließen
    window.addEventListener('click', function(event) {
        if (event.target === albumModal) {
            closeModal();
        }
    });
});