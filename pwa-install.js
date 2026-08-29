// Gestion de l'installation PWA Mobile & Desktop
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    // Empêcher l'affichage de la mini-infobulle par défaut
    e.preventDefault();
    deferredPrompt = e;
    
    // Afficher tous les boutons d'installation sur la page
    const installBtns = document.querySelectorAll('.pwa-install-btn');
    installBtns.forEach(btn => {
        btn.style.display = 'inline-flex';
    });

    const installBanner = document.getElementById('pwa-install-banner');
    if (installBanner) {
        installBanner.style.display = 'block';
    }
});

function installerPWA() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('Installation de Business Moses acceptée !');
                const installBanner = document.getElementById('pwa-install-banner');
                if (installBanner) installBanner.style.display = 'none';
            }
            deferredPrompt = null;
        });
    }
}

window.addEventListener('appinstalled', () => {
    console.log('Business Moses a été installée avec succès !');
    const installBanner = document.getElementById('pwa-install-banner');
    if (installBanner) installBanner.style.display = 'none';
});
