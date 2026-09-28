<?php
/**
 * Route Definitions for Cascade E-Commerce
 */

require_once __DIR__ . '/Router.php';

// Frontend Page Routes
Router::any('/', 'home.php', 'home');
Router::any('/catalog', 'catalog.php', 'catalog');
Router::any('/login', 'login.php', 'login');
Router::any('/logout', 'logout.php', 'logout');
Router::any('/register', 'register.php', 'register');
Router::any('/forgot-password', 'forgot_password.php', 'forgot_password');
Router::any('/reset-password', 'reset_password.php', 'reset_password');
Router::any('/dashboard', 'dashboard.php', 'dashboard');
Router::any('/edit-profile', 'edit_profile.php', 'edit_profile');
Router::any('/feed', 'feed.php', 'feed');
Router::any('/category-products', 'produits_par_categorie.php', 'category_products');
Router::any('/search', 'recherche.php', 'search');
Router::any('/product/add', 'add_product.php', 'add_product');
Router::any('/product/buy', 'buy_product.php', 'buy_product');
Router::any('/product/modify', 'modify_product.php', 'modify_product');
Router::any('/category/add', 'add_category.php', 'add_category');
Router::any('/admin', 'admin_panel.php', 'admin_panel');
Router::any('/payment', 'payment.php', 'payment');
Router::any('/payment/confirm', 'confirm_payment.php', 'confirm_payment');
Router::any('/notifications', 'notifications.php', 'notifications');
Router::any('/propositions', 'propositions.php', 'propositions');
Router::any('/scan-qr', 'scan_qr.php', 'scan_qr');
Router::any('/seller-qr', 'seller_qr.php', 'seller_qr');
Router::any('/settings', 'settings.php', 'settings');
Router::any('/statistics', 'statistiques.php', 'statistics');
Router::any('/terms', 'term_condition.html', 'terms');
Router::any('/update-password', 'updatepassword.php', 'update_password');

// Backend / API Routes
Router::any('/api/feed', 'api/api_feed.php', 'api.feed');
Router::any('/api/notifications-poll', 'api/api_notifications_poll.php', 'api.notifications_poll');
Router::any('/api/callback', 'api/callback.php', 'api.callback');
Router::any('/api/cart', 'api/cart.php', 'api.cart');
Router::any('/api/catalog', 'api/catalog.php', 'api.catalog');
Router::any('/api/check-payment-status', 'api/check_payment_status.php', 'api.check_payment_status');
Router::any('/api/dashboard', 'api/dashboard.php', 'api.dashboard');
Router::any('/api/delete-product', 'api/delete_product.php', 'api.delete_product');
Router::any('/api/cancellation-details', 'api/get_cancellation_details.php', 'api.get_cancellation_details');
Router::any('/api/order-details', 'api/get_order_details.php', 'api.get_order_details');
Router::any('/api/logout-admin', 'api/logout_admin.php', 'api.logout_admin');
Router::any('/api/mark-as-read', 'api/mark_as_read.php', 'api.mark_as_read');
Router::any('/api/order-action', 'api/order_action.php', 'api.order_action');
Router::any('/api/submit-review', 'api/submit_review.php', 'api.submit_review');
Router::any('/api/suggestions', 'api/suggestions.php', 'api.suggestions');
