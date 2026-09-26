document.getElementById('signup-form').addEventListener('submit', function(event) {
    event.preventDefault();

    // Récupérer les valeurs des mots de passe
    const password = document.querySelector('#password');
    const cpassword = document.querySelector('#cpassword');
    let hasError = false;

    // Validation des mots de passe
    if (password.value.trim() !== cpassword.value.trim()) {
        password.classList.add('error');
        cpassword.classList.add('error');
        hasError = true;
        alert('Les mots de passe ne correspondent pas');
    }

    if (!hasError) {
        // Afficher l'overlay (loading)
        document.getElementById('overlay').style.display = 'flex';

        // Simuler une soumission avec un délai de 20 secondes
        setTimeout(() => {
            document.getElementById('overlay').style.display = 'none'; // Cacher l'overlay
            document.getElementById('success-message').style.display = 'block'; // Afficher le message de succès
        
             // Délai de 2 secondes avant la redirection
        }, 3000);

        // Si une erreur se produit, affichons une alerte et rafraîchissons la page
       
    }
});

let profilepic = document.getElementById("profile-pic");
let inputFile = document.getElementById("input-file");

inputFile.onchange = function(){
profilepic.src = URL.createObjectURL(inputFile.files[0]);
}