<!-- Sortier-Dropdown -->
<?php if (!empty($images)): ?>
<div id="sort-dropdown" class="sort-dropdown">
    <div class="sort-dropdown-header">
        <h4>Sortieroptionen</h4>
    </div>
    <div class="sort-dropdown-content">
        <div class="sort-option-group">
            <h5>Sortieren nach:</h5>
            <div class="sort-option">
                <input type="radio" id="sort-upload_date" name="sort-type" value="upload_date" checked>
                <label for="sort-upload_date">Upload-Datum</label>
            </div>
            <div class="sort-option">
                <input type="radio" id="sort-name" name="sort-type" value="name">
                <label for="sort-name">Name</label>
            </div>
            <div class="sort-option">
                <input type="radio" id="sort-type" name="sort-type" value="type">
                <label for="sort-type">Medientyp</label>
            </div>
        </div>

        <div class="sort-option-group">
            <h5>Reihenfolge:</h5>
            <div class="sort-option" id="sort-direction-container">
                <div class="sort-direction-option">
                    <input type="radio" id="sort-DESC" name="sort-direction" value="DESC" checked>
                    <label for="sort-DESC" id="sort-desc-label">Neueste zuerst</label>
                </div>
                <div class="sort-direction-option">
                    <input type="radio" id="sort-ASC" name="sort-direction" value="ASC">
                    <label for="sort-ASC" id="sort-asc-label">Älteste zuerst</label>
                </div>
            </div>
        </div>

        <div class="sort-dropdown-footer">
            <button id="apply-sort" class="btn-primary">Anwenden</button>
            <button id="close-sort-dropdown" class="btn-secondary">Abbrechen</button>
        </div>
    </div>
</div>
<?php endif; ?>