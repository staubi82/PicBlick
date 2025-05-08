/**
 * Vollbild-Viewer für Bilder
 * Ermöglicht das Anzeigen, Zoomen, Drehen und Navigieren durch Bilder
 */

document.addEventListener('DOMContentLoaded', () => {
    initFullscreenViewer();
});

function initFullscreenViewer() {
    // Viewer-Elemente erstellen, falls sie noch nicht existieren
    if (!document.querySelector('.fullscreen-viewer')) {
        const viewer = document.createElement('div');
        viewer.className = 'fullscreen-viewer';
        viewer.innerHTML = `
            <div class="viewer-header">
                <button class="viewer-close">&times;</button>
            </div>
            <div class="viewer-content">
                <div class="viewer-navigation">
                    <button class="nav-button prev-button">&lt;</button>
                    <button class="nav-button next-button">&gt;</button>
                </div>
                <!-- Container für Medieninhalt (Bild oder Video) -->
                <div class="media-container">
                    <img src="" class="viewer-image" alt="">
                    <div class="video-wrapper" style="display: none;">
                        <video class="viewer-video" controls>
                            <source src="" type="">
                            Ihr Browser unterstützt das Video-Tag nicht.
                        </video>
                    </div>
                </div>
                <div class="control-pill">
                    <button class="rotate-left-button" title="Links drehen" aria-label="Bild nach links drehen">↺</button>
                    <button class="favorite-toggle" title="Favorit umschalten" aria-label="Favorit umschalten">★</button>
                    <button class="rotate-right-button" title="Rechts drehen" aria-label="Bild nach rechts drehen">↻</button>
                </div>
            </div>
        `;
        document.body.appendChild(viewer);
    }
    
    // Referenzen auf Elemente
    const viewer = document.querySelector('.fullscreen-viewer');
    const mediaContainer = viewer.querySelector('.media-container');
    const viewerImage = viewer.querySelector('.viewer-image');
    const videoWrapper = viewer.querySelector('.video-wrapper');
    const viewerVideo = viewer.querySelector('.viewer-video');
    const videoSource = viewerVideo.querySelector('source');
    const closeButton = viewer.querySelector('.viewer-close');
    const prevButton = viewer.querySelector('.prev-button');
    const nextButton = viewer.querySelector('.next-button');
    const favoriteToggle = viewer.querySelector('.favorite-toggle');
    const rotateLeftButton = viewer.querySelector('.rotate-left-button');
    const rotateRightButton = viewer.querySelector('.rotate-right-button');
    
    // Zustandsvariablen
    let currentImages = [];
    let currentIndex = 0;
    let scale = 1;
    let translateX = 0;
    let translateY = 0;
    let rotation = 0;
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let lastX = 0;
    let lastY = 0;
    
    // Event-Listener für Medienklicks in der Galerie
    // Zuerst alte Event-Listener entfernen, indem wir alle Event-Handler neu zuweisen
    document.querySelectorAll('.image-grid .image-card').forEach((card) => {
        // Alten Listener entfernen, indem wir ein neues Element klonen und ersetzen
        // Dies ist notwendig, da wir keine direkte Möglichkeit haben, anonyme Event-Listener zu entfernen
        const newCard = card.cloneNode(true);
        card.parentNode.replaceChild(newCard, card);
    });
    
    // Neue Event-Listener hinzufügen
    document.querySelectorAll('.image-grid .image-card').forEach((card, index) => {
        card.addEventListener('click', (e) => {
            // Ignoriere Klicks auf Action-Buttons innerhalb der Karte
            if (e.target.closest('.image-actions')) {
                return;
            }
            
            // Sammle alle Medien in der aktuellen Galerie
            const allMedia = Array.from(document.querySelectorAll('.image-grid .image-card'));
            
            currentImages = allMedia.map(mediaCard => {
                const rotation = parseInt(mediaCard.dataset.rotation || '0', 10);
                
                // Bestimme Medientyp (Video oder Bild) aus den data-Attributen
                // Wir verwenden mehrere Wege, um den Typ zu erkennen, für mehr Robustheit
                const hasVideoThumbnail = mediaCard.querySelector('.video-thumbnail') !== null;
                const isVideoByType = mediaCard.dataset.mediaType === 'video';
                const isVideoByMime = (mediaCard.dataset.mimeType || '').startsWith('video/');
                const isVideo = hasVideoThumbnail || isVideoByType || isVideoByMime;
                
                // Ermittle MIME-Typ für Videos - nutze das data-Attribut wenn vorhanden
                let mimeType = mediaCard.dataset.mimeType || 'video/mp4'; // Wert aus dem data-Attribut
                
                // Fallback zur Extension-basierten Erkennung, wenn kein MIME-Typ gefunden wurde
                if (isVideo && !mimeType.startsWith('video/')) {
                    const filename = mediaCard.dataset.imageName || '';
                    const extension = filename.split('.').pop().toLowerCase();
                    const mimeMap = {
                        'mp4': 'video/mp4',
                        'mov': 'video/quicktime',
                        'avi': 'video/x-msvideo',
                        'mkv': 'video/x-matroska',
                        'webm': 'video/webm'
                    };
                    mimeType = mimeMap[extension] || 'video/mp4';
                }
                
                return {
                    src: mediaCard.dataset.fullSrc || mediaCard.querySelector('img').src,
                    id: mediaCard.dataset.imageId,
                    name: mediaCard.dataset.imageName || '',
                    rotation: rotation,
                    description: mediaCard.dataset.description || '',
                    isFavorite: mediaCard.dataset.favorite === 'true',
                    isVideo: isVideo,
                    mimeType: mimeType
                };
            });
            currentIndex = index;
            openViewer();
        });
    });
    
    // Bild im Vollbild öffnen
    function openViewer() {
        // Reset der Transformationen
        scale = 1;
        translateX = 0;
        translateY = 0;
        rotation = currentImages[currentIndex].rotation || 0;
        
        // Viewer anzeigen und aktualisieren
        updateViewer();
        viewer.classList.add('active');
        
        // Verhindern des Scrollens im Hintergrund
        document.body.style.overflow = 'hidden';
    }
    
    // Viewer aktualisieren
    function updateViewer() {
        const currentImage = currentImages[currentIndex];
        
        // Entscheiden, ob Video oder Bild angezeigt werden soll
        if (currentImage.isVideo) {
            // Video anzeigen, Bild ausblenden
            viewerImage.style.display = 'none';
            videoWrapper.style.display = 'flex';
            videoSource.src = currentImage.src;
            videoSource.type = currentImage.mimeType;
            viewerVideo.load(); // Video neu laden
        } else {
            // Bild anzeigen, Video ausblenden
            viewerImage.style.display = 'block';
            videoWrapper.style.display = 'none';
            viewerImage.src = currentImage.src;
            viewerImage.alt = currentImage.name;
        }
        
        // Transformation zurücksetzen (nur für Bilder relevant)
        updateImageTransform();
        
        // Favoriten-Status aktualisieren
        if (favoriteToggle) {
            favoriteToggle.classList.toggle('active', currentImage.isFavorite);
        }
        
        // Navigation-Buttons anzeigen/ausblenden
        prevButton.style.visibility = currentIndex > 0 ? 'visible' : 'hidden';
        nextButton.style.visibility = currentIndex < currentImages.length - 1 ? 'visible' : 'hidden';
        
        // Rotations-Buttons für Videos ausblenden
        if (rotateLeftButton && rotateRightButton) {
            rotateLeftButton.style.display = currentImage.isVideo ? 'none' : 'block';
            rotateRightButton.style.display = currentImage.isVideo ? 'none' : 'block';
        }
    }
    
    // Schließen des Viewers
    function closeViewer() {
        // Falls ein Video läuft, stoppen
        if (viewerVideo && !viewerVideo.paused) {
            viewerVideo.pause();
        }
        
        viewer.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Bild transformieren
    function updateImageTransform() {
        viewerImage.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale}) rotate(${rotation}deg)`;
    }
    
    // Zum nächsten Bild/Video navigieren
    function nextImage() {
        if (currentIndex < currentImages.length - 1) {
            // Falls aktuell ein Video läuft, pausieren
            if (viewerVideo && !viewerVideo.paused) {
                viewerVideo.pause();
            }
            
            currentIndex++;
            scale = 1;
            translateX = 0;
            translateY = 0;
            rotation = currentImages[currentIndex].rotation || 0;
            updateViewer();
        }
    }
    
    // Zum vorherigen Bild/Video navigieren
    function prevImage() {
        if (currentIndex > 0) {
            // Falls aktuell ein Video läuft, pausieren
            if (viewerVideo && !viewerVideo.paused) {
                viewerVideo.pause();
            }
            
            currentIndex--;
            scale = 1;
            translateX = 0;
            translateY = 0;
            rotation = currentImages[currentIndex].rotation || 0;
            updateViewer();
        }
    }
    
    // Bild rotieren
    function rotateImage(direction) {
        // Rotation in 90-Grad-Schritten
        rotation += direction * 90;
        
        // Rotation auf Server speichern
        const imageId = currentImages[currentIndex].id;
        
        // API-Aufruf zum Speichern der Rotation
        fetch('../app/api/rotate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                image_id: imageId,
                rotation: rotation
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Aktualisiere die Rotation im currentImages-Array
                currentImages[currentIndex].rotation = rotation;
                
                // Aktualisiere auch das data-rotation-Attribut in der Bildkarte
                const imageCard = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
                if (imageCard) {
                    imageCard.dataset.rotation = rotation.toString();
                    const img = imageCard.querySelector('img');
                    if (img) {
                        img.style.setProperty('--rotation', `${rotation}deg`);
                    }
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Speichern der Rotation:', error);
        });
        
        updateImageTransform();
    }
    
    // Favoriten-Status umschalten
    function toggleFavorite() {
        const currentImage = currentImages[currentIndex];
        const imageId = currentImage.id;
        
        // API-Aufruf für Favoriten-Toggle
        fetch('../app/api/favorite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                image_id: imageId,
                action: currentImage.isFavorite ? 'remove' : 'add'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Status umkehren
                currentImage.isFavorite = !currentImage.isFavorite;
                
                // UI aktualisieren
                favoriteToggle.classList.toggle('active', currentImage.isFavorite);
                
                // Favoriten-Status in der Galerie aktualisieren
                const imageCard = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
                if (imageCard) {
                    imageCard.dataset.favorite = currentImage.isFavorite.toString();
                    const favButton = imageCard.querySelector('.favorite-button');
                    if (favButton) {
                        favButton.classList.toggle('active', currentImage.isFavorite);
                        favButton.title = currentImage.isFavorite ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen';
                    }
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Aktualisieren des Favoriten-Status:', error);
        });
    }
    
    // Maus-Events für das Verschieben des Bildes
    viewerImage.addEventListener('mousedown', (e) => {
        if (e.button === 0) { // Nur bei linker Maustaste
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            lastX = translateX;
            lastY = translateY;
            viewerImage.style.cursor = 'grabbing';
        }
    });
    
    document.addEventListener('mousemove', (e) => {
        if (isDragging) {
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            translateX = lastX + dx;
            translateY = lastY + dy;
            updateImageTransform();
        }
    });
    
    document.addEventListener('mouseup', () => {
        isDragging = false;
        viewerImage.style.cursor = 'grab';
    });
    
    // Verbesserte Zoom-Funktion mit korrektem Zoom zum Mauszeiger
    viewerImage.addEventListener('wheel', (e) => {
        e.preventDefault();
        const zoomDirection = e.deltaY < 0 ? 1 : -1;
        const zoomSpeed = 0.1;
        
        // Begrenze Zoom-Bereich
        const newScale = Math.max(0.5, Math.min(5, scale + zoomDirection * zoomSpeed));
        
        // Zoom nur ändern, wenn wir innerhalb der Grenzen sind
        if (newScale !== scale) {
            // Position des Bildes im Viewport
            const rect = viewerImage.getBoundingClientRect();
            
            // Position des Mauszeigers im Viewport
            const mouseX = e.clientX;
            const mouseY = e.clientY;
            
            // Position des Mauszeigers relativ zum Zentrum des Bildes
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            
            // Differenz zwischen Mausposition und Bildzentrum
            const dx = (mouseX - centerX) / scale;
            const dy = (mouseY - centerY) / scale;
            
            // Skalierungsdifferenz
            const scaleDiff = newScale / scale;
            
            // Anpassung der Transformation, um den Punkt unter dem Mauszeiger zu behalten
            translateX = translateX - dx * (scaleDiff - 1);
            translateY = translateY - dy * (scaleDiff - 1);
            
            // Neue Skalierung anwenden
            scale = newScale;
            updateImageTransform();
        }
    });
    
    // Event-Listener für UI-Kontrollen
    closeButton.addEventListener('click', closeViewer);
    prevButton.addEventListener('click', prevImage);
    nextButton.addEventListener('click', nextImage);
    rotateLeftButton.addEventListener('click', () => rotateImage(-1));
    rotateRightButton.addEventListener('click', () => rotateImage(1));
    favoriteToggle.addEventListener('click', toggleFavorite);
    
    // Tastatur-Navigation
    document.addEventListener('keydown', (e) => {
        if (!viewer.classList.contains('active')) return;
        
        switch (e.key) {
            case 'Escape':
                closeViewer();
                break;
            case 'ArrowLeft':
                prevImage();
                break;
            case 'ArrowRight':
                nextImage();
                break;
            case 'ArrowUp':
                rotateImage(1); // Rechts drehen
                break;
            case 'ArrowDown':
                rotateImage(-1); // Links drehen
                break;
        }
    });
    
    // Doppelklick zum Zurücksetzen von Zoom und Position
    viewerImage.addEventListener('dblclick', () => {
        scale = 1;
        translateX = 0;
        translateY = 0;
        updateImageTransform();
    });
}

// Styles für den Vollbild-Viewer
const style = document.createElement('style');
style.textContent = `
// Styles für den Video-Player und Media-Container
.media-container {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    max-width: 90%;
    max-height: 80vh;
}

.video-wrapper {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    max-width: 90%;
    max-height: 80vh;
}

.viewer-video {
    max-width: 100%;
    max-height: 80vh;
    background-color: black;
    border-radius: 4px;
}


.fullscreen-viewer {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.fullscreen-viewer.active {
    opacity: 1;
    visibility: visible;
}

.viewer-header {
    position: absolute;
    top: 0;
    right: 0;
    padding: 15px;
    z-index: 10;
}

.viewer-close {
    background: none;
    border: none;
    color: white;
    font-size: 30px;
    cursor: pointer;
    padding: 5px 10px;
    background-color: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
}

.viewer-content {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    position: relative;
    overflow: hidden;
}

.viewer-image {
    max-width: 90%;
    max-height: 80vh;
    object-fit: contain;
    cursor: grab;
    user-select: none;
    -webkit-user-drag: none;
    transition: transform 0.1s ease;
}

.viewer-navigation {
    position: absolute;
    width: 100%;
    display: flex;
    justify-content: space-between;
    padding: 0 20px;
    z-index: 5;
}

.nav-button {
    background-color: rgba(0, 0, 0, 0.5);
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.3s ease;
}

.nav-button:hover {
    background-color: rgba(0, 0, 0, 0.8);
}

.control-pill {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 15px;
    background-color: rgba(0, 0, 0, 0.6);
    padding: 8px 15px;
    border-radius: 30px;
    z-index: 10;
}

.control-pill button {
    background: none;
    border: none;
    color: white;
    font-size: 22px;
    cursor: pointer;
    padding: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.control-pill button:hover {
    transform: scale(1.2);
}

.favorite-toggle.active {
    color: gold;
}

/* Mobile-Anpassungen */
@media (max-width: 768px) {
    .viewer-image {
        max-width: 100%;
    }
    
    .nav-button {
        width: 30px;
        height: 30px;
        font-size: 16px;
    }
    
    .control-pill {
        padding: 6px 12px;
    }
    
    .control-pill button {
        font-size: 18px;
    }
}
`;

document.head.appendChild(style);