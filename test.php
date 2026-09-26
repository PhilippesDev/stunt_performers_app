<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Page Concept</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans text-gray-800">

    <header class="bg-white border-b sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3 flex items-center gap-6">
            <div class="text-2xl font-bold text-orange-500">AliClone</div>
            <div class="flex-grow max-w-2xl relative">
                <input type="text" placeholder="Rechercher sur le site..." class="w-full border-2 border-black rounded-full py-2 px-5 focus:outline-none">
                <button class="absolute right-0 top-0 bottom-0 bg-black text-white px-6 rounded-r-full">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </div>
            <div class="flex items-center gap-5 text-xl">
                <i class="fa-regular fa-user"></i>
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <div class="flex flex-col lg:flex-row gap-8 bg-white p-6 rounded-lg shadow-sm">
            
            <div class="w-full lg:w-1/2 flex gap-4">
                <div class="flex flex-col gap-2 w-20">
                    <img src="https://via.placeholder.com/80" class="border-2 border-orange-500 rounded cursor-pointer" alt="thumb">
                    <img src="https://via.placeholder.com/80" class="border hover:border-orange-500 rounded cursor-pointer" alt="thumb">
                    <img src="https://via.placeholder.com/80" class="border hover:border-orange-500 rounded cursor-pointer" alt="thumb">
                </div>
                <div class="flex-grow relative">
                    <img id="mainImage" src="https://via.placeholder.com/500" class="w-full rounded-lg" alt="Produit">
                    <button class="absolute top-4 right-4 bg-white/80 p-2 rounded-full shadow"><i class="fa-regular fa-heart"></i></button>
                </div>
            </div>

            <div class="w-full lg:w-1/2">
                <h1 class="text-xl font-bold mb-2">
                    HOCO W65 Plus ANC Réduction de bruit Bluetooth Casque Hifi Son Sport
                </h1>
                
                <div class="flex items-center gap-2 mb-4 text-sm">
                    <div class="flex text-yellow-400">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i>
                    </div>
                    <span class="font-bold underline">4.7</span>
                    <span class="text-gray-500 text-xs">341 Avis | +2 000 vendus</span>
                </div>

                <div class="bg-purple-50 p-4 rounded-lg mb-4 border border-purple-100">
                    <div class="text-purple-700 font-bold text-sm mb-1">Deal du Jour</div>
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl font-bold">US $18.99</span>
                        <span class="text-gray-400 line-through text-sm">US $20.20</span>
                    </div>
                    <p class="text-pink-600 text-xs font-semibold mt-1 italic">Prix le plus bas ces 90 derniers jours</p>
                </div>

                <div class="mb-6">
                    <p class="text-sm font-semibold mb-2">Couleur: <span id="colorName">Noir</span></p>
                    <div class="flex gap-2">
                        <button class="border-2 border-black p-1 rounded"><img src="https://via.placeholder.com/40" alt="black"></button>
                        <button class="border hover:border-black p-1 rounded"><img src="https://via.placeholder.com/40" alt="white"></button>
                        <button class="border hover:border-black p-1 rounded"><img src="https://via.placeholder.com/40" alt="blue"></button>
                    </div>
                </div>

                <div class="space-y-3">
                    <button class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-full transition">
                        Acheter maintenant
                    </button>
                    <button class="w-full border-2 border-blue-600 text-blue-600 font-bold py-3 rounded-full hover:bg-blue-50 transition">
                        Ajouter au panier
                    </button>
                </div>

                <div class="mt-6 border-t pt-4 text-sm text-gray-600 space-y-2">
                    <div class="flex justify-between">
                        <span>Livraison: <span class="font-bold text-gray-800">US $97.84</span></span>
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </div>
                    <div class="text-xs">Livraison estimée : 32-34 jours</div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Logique simple pour l'interaction
        const buttons = document.querySelectorAll('button');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                console.log("Action déclenchée : " + btn.innerText);
            });
        });
    </script>
</body>
</html>