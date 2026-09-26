<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/vendor/autoload.php';

use Meilisearch\Client;

$client = new Client(env_value('MEILISEARCH_URL'), env_value('MEILISEARCH_MASTER_KEY'));

/*


// 1. Connexion à ton instance
$index = $client->index('products');

// 2. Déclarer les champs qui seront utilisés dans le "Filter" de ton script de recherche
$index->updateFilterableAttributes([
    'id',
    'price', 
    'product_condition', 
    'discount_percent', 
    'regions', 
    'category', 
    'colors', 
    'shoe_sizes', 
    'child_sizes', 
    'adult_sizes'
]);

// 3. Déclarer les champs qui seront utilisés dans le "Sort" (ORDER BY)
$index->updateSortableAttributes([
    'price', 
    'created_at'
]);

echo "Configuration de l'index Meilisearch réussie ! Tu peux maintenant filtrer et trier.";

*/