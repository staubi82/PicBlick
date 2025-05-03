/**
 * Papierkorb-Handler für Bilder
 * 
 * Behandelt das Verschieben von Bildern in den Papierkorb
 * und das endgültige Löschen von Bildern
 */

document.addEventListener('DOMContentLoaded', function() {
    // Alle Lösch-Formulare abfangen
    const deleteImageForms = document.querySelectorAll('form[name="delete_image_form"]');
    
    deleteImageForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const imageId = this.querySelector('input[name="image_id"]').value;
            const forceDelete = this.querySelector('input[name="force_delete"]')?.value === '1';
            
            if (forceDelete) {
                if (!confirm('Bild wirklich endgültig löschen? Dies kann nicht rückgängig gemacht werden!')) {
                    return;
                }
            } else {
                if (!confirm('Bild wirklich in den Papierkorb verschieben?')) {
                    return;
                }
            }
            
            // API-Anfrage zum Löschen/Verschieben des Bildes
            fetch('../app/api/trash.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image_id: imageId,
                    force_delete: forceDelete
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Bild aus der Anzeige entfernen
                    const imageCard = document.querySelector(`.image-card[data-image-id="${imageId}"]`);
                    if (imageCard) {
                        imageCard.remove();
                    }
                    
                    // Nachricht anzeigen
                    alert(result.message);
                    
                    // Wenn keine Bilder mehr übrig sind, Seite neu laden
                    const remainingImages = document.querySelectorAll('.image-card');
                    if (remainingImages.length === 0) {
                        window.location.reload();
                    }
                } else {
                    alert('Fehler: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Fehler beim Senden der Anfrage:', error);
                alert('Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.');
            });
        });
    });
    
    // Papierkorb wiederherstellen-Button (falls vorhanden)
    const restoreButtons = document.querySelectorAll('.restore-from-trash-button');
    restoreButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Implementierung für die Wiederherstellung aus dem Papierkorb
            // In einer zukünftigen Version
        });
    });
});