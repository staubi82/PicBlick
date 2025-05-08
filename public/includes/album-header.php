<!-- Neues horizontales Layout mit drei Spalten -->
<div class="album-header-row">
    <!-- 1. Spalte: Album-Cover und Infos -->
    <div class="album-cover-info">
        <?php if ($album['cover_image']): ?>
            <div class="album-cover">
                <img src="../storage/thumbs/<?php echo rtrim($album['path'], '/') . '/' . basename($album['cover_image']); ?>"
                    alt="Album-Titelbild">
            </div>
        <?php endif; ?>
        <div class="album-info">
            <h2 id="album-title" data-editable="false" class="it-style-title">
                <?php if (isset($parentAlbum)): ?>
                <a href="album.php?id=<?php echo $parentAlbum['id']; ?>" class="parent-album-link"><?php echo htmlspecialchars($parentAlbum['name']); ?></a>
                <span class="album-separator">›</span>
                <?php endif; ?>
                <?php echo htmlspecialchars($album['name']); ?>
                <?php if ($album['is_public']): ?>
                    <span class="public-badge">Öffentlich</span>
                <?php endif; ?>
            </h2>
            <p id="album-description" data-editable="false"><?php echo htmlspecialchars($album['description'] ?? 'Keine Beschreibung'); ?></p>
            <p class="created-info">Erstellt von <strong><?php echo htmlspecialchars($owner['username']); ?></strong> am <?php echo isset($album['created_at']) ? date('d.m.Y', strtotime($album['created_at'])) : '01.01.2003'; ?></p>
            <!-- Versteckte Input-Felder für die ursprünglichen Werte -->
            <input type="hidden" id="original-album-title" value="<?php echo htmlspecialchars($album['name']); ?>">
            <input type="hidden" id="original-album-description" value="<?php echo htmlspecialchars($album['description'] ?? ''); ?>">
        </div>
    </div>
    
    <!-- 2. Spalte: Unteralben -->
    <?php if ($hasParentColumn && !empty($subAlbums)): ?>
    <div class="subalbums-swiper">
        <div class="swiper-wrapper">
            <?php foreach ($subAlbums as $subAlbum): ?>
                <div class="swiper-slide">
                    <a href="album.php?id=<?php echo $subAlbum['id']; ?>" class="subalbum-card">
                        <div class="subalbum-image-container">
                            <img src="<?php echo htmlspecialchars($subAlbum['thumbnail']); ?>" alt="<?php echo htmlspecialchars($subAlbum['name']); ?>">
                            <div class="subalbum-title-overlay"><?php echo htmlspecialchars($subAlbum['name']); ?></div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
    </div>
    <?php endif; ?>
    
    <!-- 3. Spalte: Action-Buttons -->
    <div class="album-action-buttons">
        <!-- Sortier-Button für alle Benutzer -->
        <button id="sort-album-toggle" class="album-action-btn" aria-label="Sortieren" title="Sortieren">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 5h10"></path>
                <path d="M11 9h7"></path>
                <path d="M11 13h4"></path>
                <path d="M3 17h18"></path>
                <path d="M3 12V5l4 8-4-1"></path>
            </svg>
        </button>
        <?php if ($isOwner): ?>
        <button id="edit-album-toggle" class="album-action-btn" aria-label="Album bearbeiten" title="Album bearbeiten">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="1"></circle>
                <circle cx="12" cy="5" r="1"></circle>
                <circle cx="12" cy="19" r="1"></circle>
            </svg>
        </button>
        <?php endif; ?>
    </div>
</div>