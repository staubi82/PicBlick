/**
 * Fotogalerie - Hauptskript
 * 
 * Stellt Funktionalitäten für Bildbetrachtung, Favoriten und Benutzeroberfläche bereit
 */

document.addEventListener('DOMContentLoaded', () => {
    // Initialisierung der UI-Komponenten
    initFullscreenViewer();
    initDropdownMenus();
    initFavoriteButtons();
    
    // Prüfen, ob wir auf der Upload-Seite sind
    if (document.querySelector('.upload-area')) {
        initUploadArea();
    }
});

/**
 * Initialisiert den Vollbild-Viewer für Bilder
 * Ermöglicht Zoomen und Verschieben
 */
function initFullscreenViewer() {
    // Viewer-Elemente
    const viewer = document.createElement('div');
    viewer.className = 'fullscreen-viewer';
    viewer.innerHTML = `
        <div class="viewer-header">
            <button class="viewer-close">&times;</button>
        </div>
        <div class="viewer-content">
            <div class="viewer-navigation">
                <button class="nav-button prev-button"><</button>
                <button class="nav-button next-button">></button>
            </div>
            <img src="" class="viewer-image" alt="">
        </div>
        <div class="viewer-footer">
            <div class="viewer-info"></div>
            <div class="viewer-actions">
                <button class="rotate-left-button" title="Links drehen" aria-label="Bild nach links drehen">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="white" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
                        <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/>
                    </svg>
                </button>
                <button class="rotate-right-button" title="Rechts drehen" aria-label="Bild nach rechts drehen">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="white" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
                        <path d="M16.59 15.41L12 10.83l-4.59 4.58L6 14l6-6 6 6z"/>
                    </svg>
                </button>
                <button class="favorite-toggle" title="Favorit umschalten" aria-label="Favorit umschalten">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                    </svg>
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(viewer);
    
    // Referenzen auf Elemente
    const viewerImage = viewer.querySelector('.viewer-image');
    const closeButton = viewer.querySelector('.viewer-close');
    const prevButton = viewer.querySelector('.prev-button');
    const nextButton = viewer.querySelector('.next-button');
    const viewerInfo = viewer.querySelector('.viewer-info');
    const favoriteToggle = viewer.querySelector('.favorite-toggle');
    const downloadButton = viewer.querySelector('.download-button');
    const rotateLeftButton = viewer.querySelector('.rotate-left-button');
    const rotateRightButton = viewer.querySelector('.rotate-right-button');
    
    // Zustandsvariablen
    let currentImages = [];
    let currentIndex = 0;
    let scale = 1;
    let translateX = 0;
    let translateY = 0;
    let rotation = 0; // Neue Variable für die Rotation in Grad
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let lastX = 0;
    let lastY = 0;
    
    // Event-Listener für Bildklicks in der Galerie
    document.querySelectorAll('.image-grid .image-card').forEach((card, index) => {
        card.addEventListener('click', (e) => {
            // Ignoriere Klicks auf Action-Buttons innerhalb der Karte
            if (e.target.closest('.image-actions')) {
                return;
            }
            
            // Sammle alle Bilder in der aktuellen Galerie
            const allImages = Array.from(document.querySelectorAll('.image-grid .image-card'));
            
            currentImages = allImages.map(imgCard => {
                return {
                    src: imgCard.dataset.fullSrc || imgCard.querySelector('img').src,
                    id: imgCard.dataset.imageId,
                    name: imgCard.dataset.imageName || '',
                };
            });
            currentIndex = index;
            scale = 1;
            translateX = 0;
            translateY = 0;
            rotation = 0;
            updateViewer();
            viewer.classList.add('active');
        });
    });
    
    // Weitere Funktionen für Viewer (updateViewer, close, navigation, zoom, drag, rotate, favorite toggle) hier...
}

// Weitere Funktionen initDropdownMenus, initFavoriteButtons, initUploadArea etc.

function initUploadArea() {
    const uploadArea = document.querySelector('.upload-area');
    const fileInput = document.querySelector('#file-input');
    const uploadForm = document.querySelector('form');
    const uploadPreview = document.querySelector('.upload-preview');
    const formContainer = document.querySelector('.form-container');
    const submitButton = document.querySelector('button[type="submit"]');
    const spinnerOverlay = document.getElementById('spinnerOverlay');
    
    // Array zur Verwaltung der ausgewählten Dateien
    let selectedFiles = [];
    
    // Maximale Anzahl der anzuzeigenden Vorschaubilder
    const MAX_PREVIEWS = 50;
    
    // Drag & Drop-Events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    
    // Reset-Button hinzufügen
    const resetButton = document.createElement('button');
    resetButton.type = 'button';
    resetButton.className = 'btn';
    resetButton.innerText = 'Zurücksetzen';
    resetButton.style.display = 'none';
    
    // Reset-Button vor dem Submit-Button einfügen
    if (submitButton && submitButton.parentNode) {
        submitButton.parentNode.insertBefore(resetButton, submitButton);
    }
    
    // Reset-Button Event-Listener
    resetButton.addEventListener('click', () => {
        fileInput.value = '';
        uploadPreview.innerHTML = '';
        selectedFiles = [];
        resetButton.style.display = 'none';
        // Scroll zum Upload-Bereich
        uploadArea.scrollIntoView({ behavior: 'smooth' });
    });
    
    // Dateien-auswählen-Button mit dem versteckten Datei-Input verknüpfen
    const browseBtn = document.querySelector('#browseBtn');
    if (browseBtn) {
        browseBtn.addEventListener('click', () => {
            fileInput.click();
        });
    }
    
    // Styling für Drag-Events
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => {
            uploadArea.classList.add('highlight');
        });
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, () => {
            uploadArea.classList.remove('highlight');
        });
    });
    
    // Drop-Ereignis verarbeiten
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
    
        const dt = e.dataTransfer;
        const files = dt.files;
    
        // Dateien zum selectedFiles hinzufügen
        const newFiles = Array.from(files);
        selectedFiles = [...selectedFiles, ...newFiles];
    
        // Vorschau aktualisieren
        appendPreview(newFiles);
    
        // FileInput aktualisieren
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        fileInput.files = dataTransfer.files;
    });
    
    // Klick-Event für manuelles Auswählen
    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });
    
    // Änderungen an der Dateiauswahl überwachen
    fileInput.addEventListener('change', () => {
        // Vorhandene Dateien behalten und neue hinzufügen
        const newFiles = Array.from(fileInput.files);
        
        // Zu bestehenden Dateien hinzufügen
        selectedFiles = [...selectedFiles, ...newFiles];
        
        // Vorschaubilder aktualisieren, ohne die vorherigen zu löschen
        appendPreview(newFiles);
        
        // FileInput zurücksetzen, damit derselbe Dialog erneut geöffnet werden kann
        fileInput.value = '';
    });
    
    // Formular absenden
    if (uploadForm) {
        uploadForm.addEventListener('submit', (e) => {
            e.preventDefault(); // Standardverhalten verhindern
            
            if (selectedFiles.length === 0) {
                alert('Bitte wählen Sie mindestens eine Datei aus.');
                return;
            }
            
            // Limitierung auf 20 Dateien entfernt, alle Dateien werden direkt hochgeladen
            // if (selectedFiles.length > 20 && !confirm(`Sie haben ${selectedFiles.length} Bilder ausgewählt. Möchten Sie alle auf einmal hochladen? (Klicken Sie auf Abbrechen, um stattdessen in kleineren Gruppen hochzuladen)`)) {
            //     startBatchUpload(selectedFiles);
            //     return;
            // }
            
            uploadFiles(selectedFiles);
        });
    }
    
    // Vorschaubilder hinzufügen ohne bestehende zu löschen
    function appendPreview(files) {
        if (!uploadPreview) return;
        
        // Zeige Reset-Button, wenn Dateien vorhanden sind
        if (selectedFiles.length > 0) {
            resetButton.style.display = 'inline-block';
        } else {
            resetButton.style.display = 'none';
        }
        
        // Informationstext für viele Dateien
        if (selectedFiles.length > 50) {
            let warningBox = document.querySelector('.upload-warning');
            if (!warningBox) {
                warningBox = document.createElement('div');
                warningBox.className = 'upload-warning';
                uploadPreview.appendChild(warningBox);
            }
            warningBox.innerHTML = `
                <p>Sie haben ${selectedFiles.length} Dateien ausgewählt. Das Hochladen vieler Dateien kann einige Zeit dauern.</p>
                <p>Tipp: Laden Sie die Bilder in kleineren Gruppen hoch für bessere Performance.</p>
            `;
        } else {
            let infoBox = document.querySelector('.upload-info');
            if (!infoBox) {
                infoBox = document.createElement('div');
                infoBox.className = 'upload-info';
                uploadPreview.appendChild(infoBox);
            }
            infoBox.textContent = `Sie haben ${selectedFiles.length} Dateien ausgewählt.`;
        }
        
        // Vorschaubilder hinzufügen (maximal MAX_PREVIEWS)
        const previewCount = Math.min(files.length, MAX_PREVIEWS);
        for (let i = 0; i < previewCount; i++) {
            const file = files[i];
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'upload-preview-item';
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = file.name;
                div.appendChild(img);
                // Zeige relative Pfadinformation, falls vorhanden
                if (file.webkitRelativePath) {
                    const pathInfo = document.createElement('div');
                    pathInfo.className = 'upload-preview-path';
                    pathInfo.textContent = file.webkitRelativePath;
                    div.appendChild(pathInfo);
                }
                uploadPreview.appendChild(div);
            };
            reader.readAsDataURL(file);
        }
    }