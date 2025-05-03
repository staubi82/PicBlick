/**
 * 3D-Objekt für die Albenansicht
 */

document.addEventListener('DOMContentLoaded', () => {
    // Prüfen, ob das Container-Element existiert
    const container = document.querySelector('.album-3d-container');
    if (!container) return;
    
    // Three.js Szene initialisieren
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf5f5f5);
    
    // Kamera einrichten
    const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
    camera.position.z = 5;
    
    // Renderer einrichten
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container.clientWidth, container.clientHeight);
    container.appendChild(renderer.domElement);
    
    // Licht hinzufügen
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambientLight);
    
    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
    directionalLight.position.set(1, 1, 1);
    scene.add(directionalLight);
    
    // Würfel erstellen
    const geometry = new THREE.BoxGeometry(2, 2, 2);
    
    // Materialien für jede Seite des Würfels erstellen
    const textureLoader = new THREE.TextureLoader();
    const materials = [];
    
    // Standardtextur für jede Seite (falls keine Bilder verfügbar sind)
    for (let i = 0; i < 6; i++) {
        materials.push(new THREE.MeshStandardMaterial({ 
            color: 0x3498db,
            roughness: 0.5,
            metalness: 0.2
        }));
    }
    
    // Würfel erstellen
    const cube = new THREE.Mesh(geometry, materials);
    scene.add(cube);
    
    // Aktuelles Album-ID aus dem data-Attribut lesen
    const albumId = container.getAttribute('data-album-id');
    
    // Bilder des Albums laden, falls vorhanden
    if (albumId) {
        // Textures für jede Seite des Würfels laden
        // Hier könnten wir später Bilder aus dem Album laden
    }
    
    // Animation
    function animate() {
        requestAnimationFrame(animate);
        
        // Würfel rotieren
        cube.rotation.x += 0.005;
        cube.rotation.y += 0.01;
        
        renderer.render(scene, camera);
    }
    
    // Animation starten
    animate();
    
    // Responsive verhalten
    window.addEventListener('resize', () => {
        // Proportionen aktualisieren
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    });
});