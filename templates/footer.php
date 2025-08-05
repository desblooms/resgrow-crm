<!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center">
                <div class="text-sm text-gray-500">
                    © <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
                </div>
                <div class="flex items-center space-x-4 mt-2 sm:mt-0">
                    <span class="text-sm text-gray-500">Version 1.0</span>
                    <?php if (SessionManager::isLoggedIn()): ?>
                    <span class="text-sm text-gray-500">
                        Welcome, <?php echo htmlspecialchars(SessionManager::getUsername()); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Global JavaScript utilities
        
        // Mobile menu toggle
        function toggleMobileMenu() {
            const overlay = document.getElementById('mobile-menu-overlay');
            const sidebar = document.getElementById('mobile-sidebar');
            
            if (overlay && sidebar) {
                overlay.classList.toggle('hidden');
                sidebar.classList.toggle('-translate-x-full');
            }
        }

        // Close mobile menu when clicking overlay
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('mobile-menu-overlay');
            if (overlay) {
                overlay.addEventListener('click', toggleMobileMenu);
            }
        });

        // Confirm dialogs
        function confirmAction(message = 'Are you sure?') {
            return confirm(message);
        }

        // Loading states
        function showLoading(element) {
            const originalText = element.innerHTML;
            element.innerHTML = '<div class="loading"></div> Loading...';
            element.disabled = true;
            element.dataset.originalText = originalText;
        }

        function hideLoading(element) {
            if (element.dataset.originalText) {
                element.innerHTML = element.dataset.originalText;
                element.disabled = false;
            }
        }

        // Form validation helpers
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function validatePhone(phone) {
            const re = /^(\+974|974|00974)?[3-9]\d{7}$/;
            return re.test(phone.replace(/[^0-9+]/g, ''));
        }

        // Format currency
        function formatCurrency(amount, currency = 'QAR') {
            return new Intl.NumberFormat('en-QA', {
                style: 'currency',
                currency: currency
            }).format(amount);
        }

        // Auto-resize textareas
        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        // Initialize auto-resize for all textareas
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = document.querySelectorAll('textarea[data-auto-resize]');
            textareas.forEach(function(textarea) {
                textarea.addEventListener('input', function() {
                    autoResize(textarea);
                });
                autoResize(textarea); // Initial resize
            });
        });

        // CSRF token helper
        function getCSRFToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save forms
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const submitBtn = document.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn) {
                    submitBtn.click();
                }
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal[style*="block"]');
                modals.forEach(modal => modal.style.display = 'none');
            }
        });

        // Session timeout warning
        <?php if (SessionManager::isLoggedIn()): ?>
        let sessionWarningShown = false;
        const sessionTimeout = <?php echo SESSION_TIMEOUT; ?> * 1000; // Convert to milliseconds
        const warningTime = sessionTimeout - (5 * 60 * 1000); // 5 minutes before timeout
        
        setTimeout(function() {
            if (!sessionWarningShown) {
                sessionWarningShown = true;
                if (confirm('Your session will expire in 5 minutes. Click OK to extend your session.')) {
                    // Ping server to extend session
                    fetch('<?php echo BASE_URL; ?>/public/ping.php')
                        .then(() => location.reload())
                        .catch(() => location.href = 'login.php');
                }
            }
        }, warningTime);
        <?php endif; ?>

        // Service Worker for PWA (Phase 13)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('SW registered: ', registration);
                    })
                    .catch(function(registrationError) {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
    </script>

</body>
</html>