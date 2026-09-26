<!-- Footer -->
<footer style="background: var(--color-surface); border-top: 1px solid var(--color-border); margin-top: auto; padding: var(--space-12) 0 var(--space-6) 0;">
    <div class="container">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--color-text); margin-bottom: var(--space-4);">CASCADE</h3>
                <p style="color: var(--color-text-muted); font-size: var(--font-size-sm); margin-bottom: var(--space-4);">
                    Votre plateforme e-commerce moderne, rapide et sécurisée. Achetez et vendez en toute confiance.
                </p>
            </div>
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; color: var(--color-text); margin-bottom: var(--space-4);">Liens Rapides</h4>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: var(--space-2); font-size: var(--font-size-sm);">
                    <li><a href="index.php" style="color: var(--color-text-muted); text-decoration: none;">Accueil</a></li>
                    <li><a href="catalog.php" style="color: var(--color-text-muted); text-decoration: none;">Catalogue</a></li>
                    <li><a href="feed.php" style="color: var(--color-text-muted); text-decoration: none;">Fil d'actualité</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; color: var(--color-text); margin-bottom: var(--space-4);">Mon Compte</h4>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: var(--space-2); font-size: var(--font-size-sm);">
                    <li><a href="dashboard.php" style="color: var(--color-text-muted); text-decoration: none;">Tableau de bord</a></li>
                    <li><a href="edit_profile.php" style="color: var(--color-text-muted); text-decoration: none;">Profil</a></li>
                    <li><a href="settings.php" style="color: var(--color-text-muted); text-decoration: none;">Paramètres</a></li>
                </ul>
            </div>
            <div>
                <h4 style="font-size: 1rem; font-weight: 600; color: var(--color-text); margin-bottom: var(--space-4);">Support</h4>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: var(--space-2); font-size: var(--font-size-sm);">
                    <li><a href="term_condition.html" style="color: var(--color-text-muted); text-decoration: none;">Conditions d'utilisation</a></li>
                    <li><span style="color: var(--color-text-muted);">Email: support@ecascadeur.com</span></li>
                </ul>
            </div>
        </div>
        <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-6); text-align: center; color: var(--color-text-dim); font-size: var(--font-size-xs);">
            &copy; <?= date('Y') ?> Cascade E-Commerce. Tous droits réservés.
        </div>
    </div>
</footer>

</body>
</html>
