/**
 * Album-Navigation mit Slide-Effekt
 * Ermöglicht nahtloses Wechseln zwischen Alben ohne Seitenneuladen
 */

document.addEventListener('DOMContentLoaded', function() {
    initAlbumNavigation();
    initSortControlsNew(); // Initialisierung des neuen Sortier-Menüs
});

/**
 * Initialisierung der Album-Navigation
 */
function initAlbumNavigation() {
    // Navigationsverlauf
    window.albumHistory = window.albumHistory || {
        currentAlbum: getCurrentAlbumId(),
        entries: [],
        addEntry: function(albumId, coverPath) {
            // Speichere das aktuelle Album bevor wir wechseln
            if (this.currentAlbum) {
                this.entries.push({
                    id: this.currentAlbum,
                    coverPath: getCurrentCoverPath()
                });
            }
            this.currentAlbum = albumId;
        },
        getPrevious: function() {
            if (this.entries.length > 0) {
                return this.entries.pop();
            }
            return null;
        }
    };

    // Alle Unteralben-Links abfangen
    attachSubalbumClickHandlers();
    
    // History API für Browser-Zurück-Button
    window.addEventListener('popstate', function(event) {
        if (event.state && event.state.albumId) {
            const albumId = event.state.albumId;
            const direction = event.state.direction || 'back';
            
            // Wenn wir zurückgehen, nutzen wir die gespeicherte History
            if (direction === 'back') {
                const previousEntry = window.albumHistory.getPrevious();
                if (previousEntry) {
                    slideToAlbum(previousEntry.id, true, previousEntry.coverPath);
                    return;
                }
            }
            
            // Falls keine History vorhanden, einfach zum Album navigieren
            slideToAlbum(albumId, true);
        }
    });
    
    // Initialen Zustand in History API speichern
    const currentAlbumId = getCurrentAlbumId();
    if (currentAlbumId) {
        window.history.replaceState(
            { albumId: currentAlbumId, direction: 'initial' }, 
            '', 
            `album.php?id=${currentAlbumId}`
        );
    }
}

/**
 * Click-Handler an alle Unteralben anhängen
 */
function attachSubalbumClickHandlers() {
    const subalbumLinks = document.querySelectorAll('.subalbum-card');
    
    subalbumLinks.forEach(link => {
        // Nur Handler hinzufügen, wenn noch keiner existiert
        if (!link.hasAttribute('data-navigation-initialized')) {
            link.setAttribute('data-navigation-initialized', 'true');
            
            link.addEventListener('click', function(e) {
                e.preventDefault(); // Normalen Seitenwechsel verhindern
                
                // Album-ID aus der href extrahieren
                const albumId = this.getAttribute('href').split('id=')[1];
                
                // Cover-Bild-Pfad aus dem img extrahieren
                const subalbumImage = this.querySelector('img').src;
                
                // Animation starten
                slideToAlbum(albumId, false, subalbumImage);
            });
        }
    });
}

/**
 * Animation und Laden des neuen Albums
 */
function slideToAlbum(albumId, isBackNavigation = false, customCoverPath = null) {
    // 1. Elemente referenzieren
    const mainCover = document.querySelector('.album-cover');
    const albumInfoContainer = document.querySelector('.album-info-container');
    const albumInfo = document.querySelector('.album-info');
    const subalbumSwiper = document.querySelector('.subalbums-swiper');
    const imageGrid = document.querySelector('.image-grid');
    
    // Cover-Pfad des aktuellen Albums speichern (für die Rücknavigation)
    const currentCoverPath = !isBackNavigation ? getCurrentCoverPath() : null;
    
    // Animation hinzufügen
    document.body.classList.add('album-transition-active');
    
    // 2. Ausblenden mit Animation
    if (mainCover) mainCover.classList.add('slide-out-left');
    if (albumInfo) albumInfo.classList.add('fade-out');
    if (subalbumSwiper) subalbumSwiper.classList.add('fade-out');
    if (imageGrid) imageGrid.classList.add('fade-out');
    
    // 3. AJAX-Anfrage für neue Album-Daten mit Sortierparametern
    const sortParams = getSortParameters();
    fetch(`../app/api/get-album-data.php?id=${albumId}${sortParams}`)
        .then(response => response.json())
        .then(albumData => {
            if (albumData.error) {
                console.error('Fehler:', albumData.error);
                window.location.href = `album.php?id=${albumId}`;
                return;
            }
            
            // History verwalten
            if (!isBackNavigation) {
                // Aktuelles Album zur History hinzufügen
                window.albumHistory.addEntry(albumId, currentCoverPath);
                
                // Browser-Historie aktualisieren (für den Zurück-Button)
                window.history.pushState(
                    { albumId: albumId, direction: 'forward' }, 
                    '', 
                    `album.php?id=${albumId}`
                );
            }
            
            // 4. Warten bis Animation fast abgeschlossen (400ms von 500ms)
            setTimeout(() => {
                // 5. Inhalte aktualisieren
                
                // Cover aktualisieren (entweder vom Custom-Pfad oder vom API-Response)
                if (mainCover) {
                    let coverImgSrc = customCoverPath || albumData.cover_path || 'img/default-album.jpg';
                    
                    // Wenn kein Cover vorhanden war, eines erstellen
                    if (mainCover.querySelector('img')) {
                        mainCover.querySelector('img').src = coverImgSrc;
                    } else {
                        mainCover.innerHTML = `<img src="${coverImgSrc}" alt="Album-Titelbild">`;
                    }
                    
                    // Neues Cover mit Animation einblenden
                    mainCover.classList.remove('slide-out-left');
                    mainCover.classList.add('slide-in-right');
                }
                
                // Album-Info aktualisieren
                if (albumInfo) {
                    // Titel und Beschreibung aktualisieren
                    const titleElement = albumInfo.querySelector('#album-title');
                    const descElement = albumInfo.querySelector('#album-description');
                    const createdInfoElement = albumInfo.querySelector('.created-info');
                    
                    if (titleElement) {
                        titleElement.innerHTML = escapeHtml(albumData.name);
                        if (albumData.is_public) {
                            titleElement.innerHTML += ' <span class="public-badge">Öffentlich</span>';
                        }
                    }
                    
                    if (descElement) {
                        descElement.textContent = albumData.description || 'Keine Beschreibung';
                    }
                    
                    if (createdInfoElement) {
                        createdInfoElement.innerHTML = `Erstellt von <strong>${escapeHtml(albumData.owner)}</strong> am ${albumData.created_at}`;
                    }
                    
                    // Versteckte Input-Felder aktualisieren
                    const titleInput = document.getElementById('original-album-title');
                    const descInput = document.getElementById('original-album-description');
                    
                    if (titleInput) titleInput.value = albumData.name;
                    if (descInput) descInput.value = albumData.description || '';
                    
                    // Animation zurücksetzen
                    albumInfo.classList.remove('fade-out');
                    albumInfo.classList.add('fade-in');
                }
                
                // Unteralben aktualisieren
                if (subalbumSwiper && albumData.subalbums) {
                    updateSubalbumsView(subalbumSwiper, albumData.subalbums);
                    
                    // Animation zurücksetzen
                    subalbumSwiper.classList.remove('fade-out');
                    subalbumSwiper.classList.add('fade-in');
                }
                
                // Bilder-Grid aktualisieren
                if (imageGrid && albumData.images) {
                    updateImagesGrid(imageGrid, albumData.images, albumData.is_owner);
                    
                    // Animation zurücksetzen
                    imageGrid.classList.remove('fade-out');
                    imageGrid.classList.add('fade-in');
                    
                    // Viewer und Click-Handler initialisieren
                    if (typeof initFullscreenViewer === 'function') {
                        setTimeout(() => {
                            initFullscreenViewer();
                        }, 100);
                    }
                }
                
                // Edit-Album-Button Sichtbarkeit 
                const editButton = document.getElementById('edit-album-toggle');
                if (editButton) {
                    editButton.style.display = albumData.is_owner ? 'block' : 'none';
                }
                
                // 6. Animation-Klassen nach Abschluss entfernen
                setTimeout(() => {
                    if (mainCover) mainCover.classList.remove('slide-in-right');
                    if (albumInfo) albumInfo.classList.remove('fade-in');
                    if (subalbumSwiper) subalbumSwiper.classList.remove('fade-in');
                    if (imageGrid) imageGrid.classList.remove('fade-in');
                    document.body.classList.remove('album-transition-active');
                    
                    // Event-Handler für neue Unteralben hinzufügen
                    attachSubalbumClickHandlers();
                    
                    // Dropdown-Menüs initialisieren
                    if (typeof initDropdownMenus === 'function') {
                        initDropdownMenus();
                    }
                    
                    // Favoriten-Buttons initialisieren
                    if (typeof initFavoriteButtons === 'function') {
                        initFavoriteButtons();
                    }
                }, 500);
            }, 400);
        })
        .catch(error => {
            console.error('Fehler beim Laden der Album-Daten:', error);
            // Bei Fehler Seite normal laden
            window.location.href = `album.php?id=${albumId}`;
        });
}

/**
 * Unteralben-Ansicht aktualisieren
 */
function updateSubalbumsView(swiperElement, subalbums) {
    const swiperWrapper = swiperElement.querySelector('.swiper-wrapper');
    if (!swiperWrapper) return;
    
    swiperWrapper.innerHTML = ''; // Leeren
    
    if (subalbums.length === 0) {
        swiperElement.style.display = 'none';
        return;
    } else {
        swiperElement.style.display = 'flex';
    }
    
    // Neue Unteralben einfügen
    subalbums.forEach(subalbum => {
        const slide = document.createElement('div');
        slide.className = 'swiper-slide';
        slide.innerHTML = `
            <a href="album.php?id=${subalbum.id}" class="subalbum-card">
                <div class="subalbum-image-container">
                    <img src="${subalbum.thumbnail}" alt="${escapeHtml(subalbum.name)}">
                    <div class="subalbum-title-overlay">${escapeHtml(subalbum.name)}</div>
                </div>
            </a>
        `;
        swiperWrapper.appendChild(slide);
    });
}

/**
 * Bilder-Grid aktualisieren
 */
function updateImagesGrid(gridElement, images, isOwner) {
    gridElement.innerHTML = ''; // Leeren
    
    if (images.length === 0) {
        const emptyState = document.createElement('div');
        emptyState.className = 'empty-state';
        emptyState.innerHTML = '<p>Dieses Album enthält noch keine Bilder.</p>';
        
        // Füge den Empty-State nach dem Grid ein
        gridElement.parentElement.insertBefore(emptyState, gridElement.nextSibling);
        gridElement.style.display = 'none';
        return;
    } else {
        // Entferne ggf. vorhandenen Empty-State
        const existingEmptyState = document.querySelector('.empty-state');
        if (existingEmptyState) existingEmptyState.remove();
        
        gridElement.style.display = 'grid';
    }
    
    // Neue Bilder einfügen
    images.forEach(image => {
        const card = document.createElement('div');
        card.className = 'image-card';
        card.dataset.imageId = image.id;
        card.dataset.fullSrc = image.full_url;
        card.dataset.imageName = image.filename;
        card.dataset.favorite = image.is_favorite ? 'true' : 'false';
        card.dataset.rotation = image.rotation;
        card.dataset.description = image.description;
        
        // Wichtig: Medientyp setzen für Videos
        const isVideo = image.media_type === 'video';
        card.dataset.mediaType = image.media_type || 'image';
        card.dataset.mimeType = image.mime_type || 'image/jpeg';
        
        let mediaHTML = '';
        if (isVideo) {
            mediaHTML = `
                <div class="video-thumbnail">
                    <img src="${image.thumbnail_url}"
                         alt="${escapeHtml(image.filename)}"
                         onerror="this.onerror=null;this.src='/public/img/default-video-thumb.png';">
                    <div class="video-play-icon">▶</div>
                </div>
            `;
        } else {
            mediaHTML = `
                <img src="${image.thumbnail_url}"
                     alt="${escapeHtml(image.filename)}"
                     onerror="this.onerror=null;this.src='/public/img/default-album.jpg';"
                     style="--rotation: ${image.rotation}deg;">
            `;
        }
        
        card.innerHTML = `
            ${mediaHTML}
            
            <div class="image-overlay">
                <div class="image-actions">
                    <button type="button" class="favorite-button ${image.is_favorite ? 'active' : ''}"
                            title="${image.is_favorite ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen'}">★</button>
                    
                    ${isOwner ? `
                        <div class="dropdown">
                            <button type="button" class="dropdown-toggle">⋮</button>
                            <ul class="dropdown-menu">
                                <li>
                                    <form method="post" action="">
                                        <input type="hidden" name="image_id" value="${image.id}">
                                        <input type="hidden" name="toggle_visibility" value="1">
                                        <button type="submit" class="text-button">
                                            ${image.is_public ? 'Auf privat setzen' : 'Öffentlich machen'}
                                        </button>
                                    </form>
                                </li>
                                <li>
                                    <form name="delete_image_form">
                                        <input type="hidden" name="image_id" value="${image.id}">
                                        <button type="submit" class="text-button">In Papierkorb</button>
                                    </form>
                                </li>
                                <li>
                                    <form name="delete_image_form">
                                        <input type="hidden" name="image_id" value="${image.id}">
                                        <input type="hidden" name="force_delete" value="1">
                                        <button type="submit" class="text-button text-danger">Löschen (endgültig)</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    ` : ''}
                </div>
            </div>
            
            ${image.is_public ? '<span class="public-badge small">Öffentlich</span>' : ''}
        `;
        
        gridElement.appendChild(card);
    });
}

/**
 * Hilfsfunktionen
 */
function getCurrentAlbumId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('id');
}

function getCurrentCoverPath() {
    const coverImg = document.querySelector('.album-cover img');
    return coverImg ? coverImg.src : null;
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * Initialisiert die neuen Sortierkontrollen mit Dropdown-Menü
 */
function initSortControlsNew() {
    console.log("Initialisiere Sortierkontrollen...");
    
    // Button zum Öffnen des Sortier-Dropdowns
    const sortButton = document.getElementById('sort-album-toggle');
    const sortDropdown = document.getElementById('sort-dropdown');
    const closeButton = document.getElementById('close-sort-dropdown');
    const applyButton = document.getElementById('apply-sort');
    
    // Überprüfe, ob die Elemente existieren und gib Debugmeldungen aus
    console.log("Sort Button gefunden:", sortButton !== null);
    console.log("Sort Dropdown gefunden:", sortDropdown !== null);
    
    if (!sortButton) {
        console.error("Sort Button nicht gefunden!");
        return;
    }
    
    if (!sortDropdown) {
        console.error("Sort Dropdown nicht gefunden!");
        return;
    }
    
    // Radio-Buttons für Sortiertyp und -richtung
    const sortTypeRadios = document.querySelectorAll('input[name="sort-type"]');
    const sortDirectionRadios = document.querySelectorAll('input[name="sort-direction"]');
    
    // Label-Elemente für Sortierrichtung
    const sortAscLabel = document.getElementById('sort-asc-label');
    const sortDescLabel = document.getElementById('sort-desc-label');
    
    // Dropdown öffnen/schließen mit verbessertem Event-Listener
    sortButton.addEventListener('click', function(e) {
        console.log("Sort Button wurde geklickt");
        e.preventDefault();
        e.stopPropagation();
        sortDropdown.classList.toggle('active');
    });
    
    // Dropdown schließen bei Klick auf Abbrechen
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            sortDropdown.classList.remove('active');
        });
    }
    
    // Dropdown schließen bei Klick außerhalb
    document.addEventListener('click', function(e) {
        if (sortDropdown.classList.contains('active') &&
            !sortDropdown.contains(e.target) &&
            e.target !== sortButton &&
            !sortButton.contains(e.target)) {
            sortDropdown.classList.remove('active');
        }
    });
    
    // Beschriftungen der Sortierrichtung je nach gewähltem Sortiertyp anpassen
    sortTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            updateSortDirectionLabels(this.value);
        });
    });
    
    // Sortierung anwenden
    if (applyButton) {
        applyButton.addEventListener('click', function() {
            applySorting();
            sortDropdown.classList.remove('active');
        });
    }
    
    // Initial einmal die Labels aktualisieren
    const selectedSortType = document.querySelector('input[name="sort-type"]:checked');
    if (selectedSortType) {
        updateSortDirectionLabels(selectedSortType.value);
    }
}

/**
 * Labels für Sortierrichtung aktualisieren
 */
function updateSortDirectionLabels(sortType) {
    const sortAscLabel = document.getElementById('sort-asc-label');
    const sortDescLabel = document.getElementById('sort-desc-label');
    
    if (!sortAscLabel || !sortDescLabel) return;
    
    if (sortType === 'name') {
        sortAscLabel.textContent = 'A bis Z';
        sortDescLabel.textContent = 'Z bis A';
    } else if (sortType === 'type') {
        sortAscLabel.textContent = 'Bilder zuerst';
        sortDescLabel.textContent = 'Videos zuerst';
    } else {
        sortAscLabel.textContent = 'Älteste zuerst';
        sortDescLabel.textContent = 'Neueste zuerst';
    }
}

/**
 * Sortiereinstellungen anwenden und Album neu laden
 */
function applySorting() {
    // Ausgewählten Sortiertyp ermitteln
    const sortTypeRadio = document.querySelector('input[name="sort-type"]:checked');
    const sortDirectionRadio = document.querySelector('input[name="sort-direction"]:checked');
    
    if (!sortTypeRadio || !sortDirectionRadio) return;
    
    // Aktuelles Album mit den neuen Sortierparametern neu laden
    reloadCurrentAlbum();
}

/**
 * Aktuelles Album mit den ausgewählten Sortieroptionen neu laden
 */
function reloadCurrentAlbum() {
    const currentAlbumId = getCurrentAlbumId();
    if (currentAlbumId) {
        // Animation starten, aber mit "in-place" Option (keine Navigation)
        slideToAlbum(currentAlbumId, true);
    }
}

/**
 * Aktuelle Sortierparameter als URL-Query-String abrufen
 */
function getSortParameters() {
    const sortTypeRadio = document.querySelector('input[name="sort-type"]:checked');
    const sortDirectionRadio = document.querySelector('input[name="sort-direction"]:checked');
    
    if (!sortTypeRadio || !sortDirectionRadio) return '';
    
    const imgSort = sortTypeRadio.value;
    const imgDir = sortDirectionRadio.value;
    
    return `&img_sort=${imgSort}&img_dir=${imgDir}`;
}