<?php
// helpers/notification_helper.php

if (!function_exists('send_notification')) {
    /**
     * Envoie une notification à un utilisateur.
     *
     * @param mysqli $conn
     * @param int $user_id
     * @param string $title
     * @param string $message
     * @param string $type
     * @param string|null $link
     * @param string|null $action_data
     * @return bool
     */
    function send_notification($conn, $user_id, $title, $message, $type = 'general', $link = null, $action_data = null) {
        if (!$user_id || empty($message)) {
            return false;
        }

        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link, action_data, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
        if ($stmt) {
            $stmt->bind_param("isssss", $user_id, $title, $message, $type, $link, $action_data);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        }
        return false;
    }
}

if (!function_exists('get_unread_notifications_count')) {
    /**
     * Retourne le nombre de notifications non lues pour un utilisateur.
     *
     * @param mysqli $conn
     * @param int $user_id
     * @return int
     */
    function get_unread_notifications_count($conn, $user_id) {
        if (!$user_id) return 0;
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            return intval($count);
        }
        return 0;
    }
}

if (!function_exists('get_user_notifications')) {
    /**
     * Récupère les notifications récentes d'un utilisateur.
     *
     * @param mysqli $conn
     * @param int $user_id
     * @param int $limit
     * @param string $filter 'all' | 'unread' | 'orders'
     * @return array
     */
    function get_user_notifications($conn, $user_id, $limit = 15, $filter = 'all') {
        if (!$user_id) return [];

        $where = "user_id = ?";
        if ($filter === 'unread') {
            $where .= " AND is_read = 0";
        } elseif ($filter === 'orders') {
            $where .= " AND type IN ('order_request', 'order_warning', 'order_accepted', 'order_refused', 'order_expired', 'order_cancelled', 'order_refunded')";
        }

        $sql = "SELECT id, title, message, type, link, action_data, is_read, created_at FROM notifications WHERE $where ORDER BY created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $user_id, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $notifications = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $notifications;
        }
        return [];
    }
}

if (!function_exists('check_and_warn_approaching_orders')) {
    /**
     * Vérifie si le délai de 2 heures approche pour des commandes en attente (ex: commande passée il y a ~70-115 min).
     * Envoie une alerte urgente au fournisseur avec le montant de la commande et rappel de réagir vite.
     *
     * @param mysqli $conn
     * @return int Nombre d'alertes envoyées
     */
    function check_and_warn_approaching_orders($conn) {
        $sql = "
            SELECT o.id, o.seller_id, o.total_amount, o.currency, o.created_at, p.name AS product_name
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            WHERE o.status = 'pending' 
              AND (o.seller_status = 'pending' OR o.seller_status IS NULL)
              AND o.created_at <= (NOW() - INTERVAL 70 MINUTE)
              AND o.created_at >= (NOW() - INTERVAL 115 MINUTE)
            LIMIT 5
        ";

        $res = $conn->query($sql);
        if (!$res || $res->num_rows === 0) {
            return 0;
        }

        $warned = 0;
        while ($order = $res->fetch_assoc()) {
            $order_id = intval($order['id']);
            $seller_id = intval($order['seller_id']);
            $amount = $order['total_amount'];
            $currency = $order['currency'] ?: 'USD';
            $product_name = $order['product_name'] ?: 'un produit';

            if (!$seller_id) continue;

            // Vérifier si cette alerte a déjà été émise pour cette commande
            $check_stmt = $conn->prepare("SELECT id FROM notifications WHERE user_id = ? AND type = 'order_warning' AND action_data LIKE ?");
            if ($check_stmt) {
                $pattern = '%"order_id":' . $order_id . '%';
                $check_stmt->bind_param("is", $seller_id, $pattern);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();
                $already_sent = ($check_res && $check_res->num_rows > 0);
                $check_stmt->close();

                if (!$already_sent) {
                    $action_data = json_encode([
                        'order_id' => $order_id,
                        'amount' => $amount,
                        'currency' => $currency,
                        'accept_url' => "order_action.php?action=accept&order_id=$order_id",
                        'refuse_url' => "order_action.php?action=refuse&order_id=$order_id",
                        'is_warning' => true
                    ]);
                    $title = "⚠️ URGENT : 30 min pour réagir à la commande #$order_id !";
                    $message = "Dépêchez-vous ! Il ne vous reste que peu de temps pour réagir à la commande #$order_id d'un montant de $amount $currency pour \"$product_name\". Dépêchez-vous de valider ou refuser pour ne pas la rater, sinon elle sera automatiquement annulée, remboursée et votre réputation pénalisée.";

                    if (send_notification($conn, $seller_id, $title, $message, "order_warning", "notifications.php", $action_data)) {
                        $warned++;
                    }
                }
            }
        }
        return $warned;
    }
}

if (!function_exists('check_and_expire_pending_orders')) {
    /**
     * Vérifie et annule automatiquement les commandes en attente de réponse fournisseur
     * dont le délai de 2 heures a expiré. Rembourse le client à 100% et pénalise le fournisseur.
     *
     * @param mysqli $conn
     * @return int Nombre de commandes expirées et traitées
     */
    function check_and_expire_pending_orders($conn) {
        require_once __DIR__ . '/refund_helper.php';

        // 1. Envoyer les rappels urgents d'approche de délai (30 min restantes)
        check_and_warn_approaching_orders($conn);

        // 2. Sélectionner les commandes créées il y a plus de 2 heures sans acceptation du fournisseur
        $sql = "
            SELECT o.id, o.user_id, o.seller_id, o.product_id, o.customer_name, 
                   o.customer_phone, o.total_amount, o.currency, p.name AS product_name,
                   u_buyer.username AS buyer_username
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN users u_buyer ON o.user_id = u_buyer.id
            WHERE o.status = 'pending' 
              AND (o.seller_status = 'pending' OR o.seller_status IS NULL)
              AND o.created_at < (NOW() - INTERVAL 2 HOUR)
            ORDER BY o.created_at ASC
            LIMIT 2
        ";

        $res = $conn->query($sql);
        if (!$res || $res->num_rows === 0) {
            return 0;
        }

        $processed = 0;
        while ($order = $res->fetch_assoc()) {
            $order_id = intval($order['id']);
            $seller_id = intval($order['seller_id']);
            $buyer_id = intval($order['user_id']);
            $product_name = $order['product_name'] ?: 'votre article';
            $buyer_name = $order['customer_name'] ?: ($order['buyer_username'] ?: 'Client');
            $phone = $order['customer_phone'];

            // 1. Marquer immédiatement comme refusée par expiration pour ne pas re-boucler
            $update = $conn->prepare("UPDATE orders SET status = 'cancelled', seller_status = 'refused' WHERE id = ?");
            if ($update) {
                $update->bind_param("i", $order_id);
                $update->execute();
                $update->close();
            }

            // 2. Déclencher le remboursement B2C intégral et pénaliser la crédibilité du vendeur (-10)
            process_b2c_refund($order_id, $conn, "Délai de validation fournisseur (2h) dépassé", true);

            // 3. Notifier le fournisseur
            $seller_msg = "La commande #$order_id pour \"$product_name\" a été automatiquement annulée car vous n'avez pas réagi dans le délai de 2 heures. Votre score de crédibilité a été réduit de 10%.";
            send_notification($conn, $seller_id, "Commande expirée (2h dépassées)", $seller_msg, "order_expired", "notifications.php");

            // 4. Notifier l'acheteur
            $buyer_msg = "Désolé $buyer_name ; le fournisseur n'a pas répondu dans le délai imparti de 2 heures. La commande \"$product_name\" a été annulée et un remboursement intégral a été transmis vers votre numéro $phone.";
            send_notification($conn, $buyer_id, "Commande annulée et remboursée", $buyer_msg, "order_refunded", "notifications.php");

            $processed++;
        }

        return $processed;
    }
}

if (!function_exists('notify_supplier_new_order')) {
    /**
     * Notifie le fournisseur de l'arrivée d'une nouvelle commande avec délai de 2 heures.
     */
    function notify_supplier_new_order($conn, $order_id) {
        $order_id = intval($order_id);
        $stmt = $conn->prepare("
            SELECT o.id, o.seller_id, o.total_amount, o.currency, o.quantity, p.name AS product_name
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            WHERE o.id = ?
        ");
        if (!$stmt) return false;
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) return false;

        $seller_id = intval($order['seller_id']);
        $product_name = $order['product_name'] ?: 'un produit';
        $amount = $order['total_amount'];
        $currency = $order['currency'] ?: 'USD';

        $action_data = json_encode([
            'order_id' => $order_id,
            'amount' => $amount,
            'currency' => $currency,
            'accept_url' => "order_action.php?action=accept&order_id=$order_id",
            'refuse_url' => "order_action.php?action=refuse&order_id=$order_id",
            'created_at' => time()
        ]);

        $title = "Nouvelle commande reçue !";
        $message = "Vous avez reçu la commande #$order_id pour \"$product_name\" ($amount $currency). Vous avez 2 heures pour accepter ou refuser la commande, sinon elle sera automatiquement annulée et remboursée au client.";

        return send_notification($conn, $seller_id, $title, $message, "order_request", "notifications.php", $action_data);
    }
}

if (!function_exists('notify_buyer_order_accepted')) {
    /**
     * Notifie l'acheteur que le fournisseur a validé la commande.
     */
    function notify_buyer_order_accepted($conn, $order_id) {
        $stmt = $conn->prepare("
            SELECT o.user_id, o.customer_name, p.name AS product_name, u.username AS buyer_username
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        if (!$stmt) return false;
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return false;

        $buyer_id = intval($row['user_id']);
        $buyer_name = $row['customer_name'] ?: ($row['buyer_username'] ?: 'cher client');
        $product_name = $row['product_name'] ?: 'votre article';

        $title = "Commande validée !";
        $message = "Génial j'ai une bonne nouvelle $buyer_name ; la commande \"$product_name\" a été validée par le fournisseur, le produit vous sera livré dans les temps.";

        return send_notification($conn, $buyer_id, $title, $message, "order_accepted", "dashboard.php?tab=purchases");
    }
}

if (!function_exists('notify_buyer_order_refused')) {
    /**
     * Notifie l'acheteur que le fournisseur a refusé la commande et propose les alternatives.
     */
    function notify_buyer_order_refused($conn, $order_id) {
        $stmt = $conn->prepare("
            SELECT o.user_id, o.customer_name, p.name AS product_name, u.username AS buyer_username
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        if (!$stmt) return false;
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return false;

        $buyer_id = intval($row['user_id']);
        $buyer_name = $row['customer_name'] ?: ($row['buyer_username'] ?: 'cher client');
        $product_name = $row['product_name'] ?: 'votre article';

        $title = "Commande refusée par le fournisseur";
        $message = "Désolé $buyer_name ; le fournisseur a refusé la commande pour \"$product_name\" mais j'ai d'autres produits dans ce genre à vous proposer. Cliquez ici pour voir les propositions.";

        return send_notification($conn, $buyer_id, $title, $message, "order_refused", "propositions.php?order_id=$order_id");
    }
}

if (!function_exists('time_elapsed_string')) {
    /**
     * Calcule le temps écoulé sous forme textuelle conviviale (ex: 'il y a 5 min')
     */
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $string = [
            'y' => 'an',
            'm' => 'mois',
            'd' => 'jour',
            'h' => 'heure',
            'i' => 'min',
            's' => 'sec'
        ];
        
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 && $k !== 'm' && $k !== 'min' && $k !== 'sec' ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
        
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? 'il y a ' . implode(', ', $string) : 'à l\'instant';
    }
}
