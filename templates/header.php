<?php
// Resgrow CRM - Header Template
// Phase 1: Project Setup & Auth

if (!defined('APP_NAME')) {
    require_once '../config.php';
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom TailwindCSS Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        success: {
                            50: '#f0fdf4',
                            500: '#10b981',
                            600: '#059669',
                        },
                        warning: {
                            50: '#fffbeb',
                            500: '#f59e0b',
                            600: '#d97706',
                        },
                        danger: {
                            50: '#fef2f2',
                            500: '#ef4444',
                            600: '#dc2626',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Meta Tags -->
    <meta name="description" content="Resgrow CRM - Marketing and Sales Management System for Qatar F&B Market">
    <meta name="keywords" content="CRM, Marketing, Sales, Qatar, F&B, Lead Management">
    <meta name="author" content="Resgrow CRM">
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Resgrow CRM">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../public/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="../public/assets/images/icon-192.png">
    
    <!-- Custom Styles -->
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Mobile-first responsive adjustments */
        @media (max-width: 640px) {
            .mobile-padding {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased">
    
    <!-- Mobile Menu Overlay -->
    <div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>
    
    <?php 
    // Show flash messages
    $flash_messages = get_flash_messages();
    if (!empty($flash_messages)): 
    ?>
    <div id="flash-messages" class="fixed top-4 right-4 z-50 space-y-2">
        <?php foreach ($flash_messages as $flash): ?>
        <div class="flash-message bg-<?php echo $flash['type'] === 'error' ? 'red' : ($flash['type'] === 'success' ? 'green' : 'blue'); ?>-500 text-white px-6 py-3 rounded-lg shadow-lg max-w-sm">
            <div class="flex items-center justify-between">
                <span><?php echo htmlspecialchars($flash['message']); ?></span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        // Auto-hide flash messages after 5 seconds
        setTimeout(function() {
            const flashMessages = document.getElementById('flash-messages');
            if (flashMessages) {
                flashMessages.style.transition = 'opacity 0.5s';
                flashMessages.style.opacity = '0';
                setTimeout(() => flashMessages.remove(), 500);
            }
        }, 5000);
    </script>
    <?php endif; ?>