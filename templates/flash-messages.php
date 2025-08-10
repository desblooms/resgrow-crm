<?php
// Resgrow CRM - Flash Messages Template
// Reusable template for displaying flash messages

$flash_messages = get_flash_messages();

if (!empty($flash_messages)): ?>
    <div class="mb-6 space-y-4">
        <?php foreach ($flash_messages as $message): ?>
            <div class="border px-4 py-3 rounded-md <?php 
                switch($message['type']) {
                    case 'success':
                        echo 'bg-green-100 border-green-400 text-green-700';
                        break;
                    case 'error':
                        echo 'bg-red-100 border-red-400 text-red-700';
                        break;
                    case 'warning':
                        echo 'bg-yellow-100 border-yellow-400 text-yellow-700';
                        break;
                    case 'info':
                    default:
                        echo 'bg-blue-100 border-blue-400 text-blue-700';
                        break;
                }
            ?>" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <?php if ($message['type'] === 'success'): ?>
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        <?php elseif ($message['type'] === 'error'): ?>
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        <?php elseif ($message['type'] === 'warning'): ?>
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        <?php else: ?>
                            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">
                            <?php echo htmlspecialchars($message['message']); ?>
                        </p>
                    </div>
                    <div class="ml-auto pl-3">
                        <div class="-mx-1.5 -my-1.5">
                            <button type="button" class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2 <?php 
                                switch($message['type']) {
                                    case 'success':
                                        echo 'bg-green-100 text-green-500 hover:bg-green-200 focus:ring-green-600';
                                        break;
                                    case 'error':
                                        echo 'bg-red-100 text-red-500 hover:bg-red-200 focus:ring-red-600';
                                        break;
                                    case 'warning':
                                        echo 'bg-yellow-100 text-yellow-500 hover:bg-yellow-200 focus:ring-yellow-600';
                                        break;
                                    case 'info':
                                    default:
                                        echo 'bg-blue-100 text-blue-500 hover:bg-blue-200 focus:ring-blue-600';
                                        break;
                                }
                            ?>" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()">
                                <span class="sr-only">Dismiss</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
