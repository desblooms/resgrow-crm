<?php
// Resgrow CRM - Header Template
// Phase 13: Mobile-First Optimization

if (!defined('APP_NAME')) {
    require_once '../config.php';
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
                    },
                    screens: {
                        'xs': '475px',
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
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../public/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="../public/assets/images/icon-192.png">
    
    <!-- Custom Styles -->
    <style>
        /* Mobile-first responsive utilities */
        .mobile-menu {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-menu.open {
            transform: translateX(0);
        }
        
        /* Touch-friendly buttons */
        .touch-button {
            min-height: 44px;
            min-width: 44px;
        }
        
        /* Mobile-optimized tables */
        @media (max-width: 768px) {
            .mobile-table {
                display: block;
                width: 100%;
            }
            
            .mobile-table thead {
                display: none;
            }
            
            .mobile-table tbody {
                display: block;
            }
            
            .mobile-table tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                padding: 1rem;
                background: white;
            }
            
            .mobile-table td {
                display: block;
                text-align: left;
                padding: 0.5rem 0;
                border: none;
            }
            
            .mobile-table td:before {
                content: attr(data-label) ": ";
                font-weight: 600;
                color: #6b7280;
            }
        }
        
        /* Mobile-optimized forms */
        @media (max-width: 768px) {
            .mobile-form input,
            .mobile-form select,
            .mobile-form textarea {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <!-- Mobile Header -->
    <header class="bg-white shadow-sm border-b border-gray-200 lg:hidden">
        <div class="flex items-center justify-between px-4 py-3">
            <button id="mobileMenuBtn" class="touch-button p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            
            <h1 class="text-lg font-semibold text-gray-900"><?php echo APP_NAME; ?></h1>
            
            <div class="flex items-center space-x-2">
                <div class="relative">
                    <button id="mobileUserMenuBtn" class="touch-button p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </header>