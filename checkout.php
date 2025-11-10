<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Coffee Shop</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Load Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Custom colors matching the dark theme */
        :root {
            --primary-orange: #ff9933;
            --dark-bg: #1e1e1e;
            --dark-card: #2a2a2a;
            --text-light: #f3f4f6;
            --text-faded: #9ca3af;
            --status-pending: #4a4a4a;
            --status-preparing: var(--primary-orange);
            --status-completed: #34d399; /* Emerald/Green */
            --status-cancelled-bg: #993333; /* Dark Red */
            --status-completed-bg: #10b981; /* Dark Green */
        }

        /* Configure Tailwind to use custom colors */
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-orange': 'var(--primary-orange)',
                        'dark-bg': 'var(--dark-bg)',
                        'dark-card': 'var(--dark-card)',
                        'text-faded': 'var(--text-faded)',
                        'status-pending': 'var(--status-pending)',
                        'status-preparing': 'var(--status-preparing)',
                        'status-completed': 'var(--status-completed)',
                        'status-cancelled-bg': 'var(--status-cancelled-bg)',
                        'status-completed-bg': 'var(--status-completed-bg)',
                    }
                }
            }
        }
        
        /* Base styles */
        body {
            background-color: var(--dark-bg);
            color: var(--text-light);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }

        /* Status Tracker Styling */
        .status-tracker {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 40px 0;
            padding: 0 10%; /* Padding to shrink the tracker line */
        }

        /* Tracker Line */
        .status-tracker::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 4px;
            background-color: #4a4a4a;
            transform: translateY(-50%);
            z-index: 10;
        }

        .status-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 20;
            cursor: pointer;
        }

        .status-dot {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background-color: var(--status-pending);
            border: 2px solid var(--dark-card); /* Border to show above the line */
            transition: background-color 0.3s, border-color 0.3s;
        }
        
        /* Text label below dot */
        .status-label {
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--text-faded);
            font-weight: 500;
        }

        /* Active State Styles */
        .status-step.active .status-dot {
            background-color: var(--status-preparing);
            border-color: var(--dark-card);
            box-shadow: 0 0 0 4px var(--dark-card); /* Outer ring glow effect */
        }

        .status-step.active .status-label {
            color: var(--text-light);
        }

        /* Completed State (if used) */
        .status-step.completed .status-dot {
            background-color: var(--status-completed);
        }

        /* Status tag for history table */
        .history-tag {
            padding: 4px 12px;
            border-radius: 9999px;
            font-weight: bold;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="bg-dark-bg min-h-screen">

    <!-- Customer Header -->
    <header class="bg-dark-bg border-b border-gray-700/50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center py-3">
            <div class="flex items-center space-x-8">
                <div class="flex items-center text-xl font-bold text-white">
                    <i class="fas fa-mug-hot text-primary-orange mr-2"></i>
                </div>
                <nav class="hidden md:flex space-x-6 text-sm font-medium">
                    <a href="#" class="text-white hover:text-primary-orange transition duration-150">HOME</a>
                    <a href="#" class="text-white hover:text-primary-orange transition duration-150">MENU</a>
                    <!-- Active Link -->
                    <a href="#" class="text-primary-orange transition duration-150 border-b-2 border-primary-orange pb-3">MY ORDERS</a>
                    <a href="#" class="text-white hover:text-primary-orange transition duration-150">PROFILE</a>
                </nav>
            </div>
            <div class="flex items-center space-x-4">
                <button class="bg-primary-orange text-dark-bg font-bold px-4 py-2 rounded-full text-sm hover:bg-orange-400 transition">LOGOUT</button>
                <div class="w-8 h-8 rounded-full bg-gray-500 overflow-hidden border-2 border-primary-orange">
                    <!-- Placeholder for user avatar -->
                    <img src="https://placehold.co/32x32/d1d5db/374151?text=U" alt="User" onerror="this.src='https://placehold.co/32x32/d1d5db/374151?text=U'">
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="text-4xl font-extrabold text-white tracking-wide mb-10">MY ORDERS</h1>

        <!-- CURRENT ORDER SECTION -->
        <section class="mb-12">
            <h2 class="text-xl font-bold text-white mb-6 border-b border-gray-700/50 pb-2">CURRENT ORDER</h2>
            
            <div class="bg-dark-card p-6 rounded-xl shadow-xl">
                
                <!-- Order Details -->
                <div class="flex items-center mb-6">
                    <img src="https://placehold.co/80x80/505050/FFFFFF?text=Coffee" alt="Order Image" class="w-20 h-20 object-cover rounded-lg mr-4">
                    <div class="flex-grow">
                        <p class="font-bold text-lg text-white">ORDER #2023-00123</p>
                        <p class="text-text-faded text-sm">2x Iced Caramel Macchiato</p>
                        <p class="text-text-faded text-sm">1x Cinnamon Swirl Pastry</p>
                        <p class="text-text-faded text-sm">1x Cold Brew</p>
                    </div>
                    <p class="text-text-faded text-sm text-right">TOTAL</p>
                </div>
                
                <!-- Status Tracker -->
                <div class="status-tracker">
                    <div class="status-step">
                        <div class="status-dot"></div>
                        <span class="status-label">PENDING</span>
                    </div>
                    <div class="status-step active">
                        <div class="status-dot"></div>
                        <span class="status-label">PREPARING</span>
                    </div>
                    <div class="status-step">
                        <div class="status-dot"></div>
                        <span class="status-label">COMPLETED</span>
                    </div>
                </div>

                <!-- Cancel Button and Total -->
                <div class="flex justify-between items-center bg-gray-700/50 p-4 rounded-lg mt-6">
                    <button class="bg-transparent border border-primary-orange text-primary-orange font-bold px-6 py-3 rounded-full hover:bg-primary-orange hover:text-dark-bg transition duration-200 text-sm md:text-base">
                        CANCEL ORDER
                    </button>
                    <span class="text-2xl font-extrabold text-white">$21.75</span>
                </div>
            </div>
        </section>


        <!-- ORDER HISTORY SECTION -->
        <section>
            <div class="flex justify-between items-center mb-6 border-b border-gray-700/50 pb-2">
                <h2 class="text-xl font-bold text-white">ORDER HISTORY</h2>
                <button class="text-primary-orange text-sm font-medium hover:underline">View Details</button>
            </div>
            
            <div class="bg-dark-card p-4 rounded-xl shadow-xl overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-text-faded text-sm uppercase tracking-wider border-b border-gray-700">
                            <th class="py-3 px-4 w-1/5">Order #</th>
                            <th class="py-3 px-4 w-1/5">Date</th>
                            <th class="py-3 px-4 w-1/5">Total Amount</th>
                            <th class="py-3 px-4 w-1/5">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Completed Order Example -->
                        <tr class="border-b border-gray-700 hover:bg-gray-700/20">
                            <td class="py-4 px-4 font-medium">#2023-00122</td>
                            <td class="py-4 px-4 text-text-faded">Oct 26, 2023</td>
                            <td class="py-4 px-4 text-white">$15.50</td>
                            <td class="py-4 px-4">
                                <span class="history-tag bg-status-completed-bg text-dark-bg">COMPLETED</span>
                            </td>
                        </tr>
                        <!-- Cancelled Order Example -->
                        <tr class="border-b border-gray-700 hover:bg-gray-700/20">
                            <td class="py-4 px-4 font-medium">#2023-00121</td>
                            <td class="py-4 px-4 text-text-faded">Oct 26, 2023</td>
                            <td class="py-4 px-4 text-white">$32.90</td>
                            <td class="py-4 px-4">
                                <span class="history-tag bg-status-cancelled-bg text-white">CANCELLED</span>
                            </td>
                        </tr>
                        <!-- Another Completed Order Example -->
                        <tr class="hover:bg-gray-700/20">
                            <td class="py-4 px-4 font-medium">#2023-00120</td>
                            <td class="py-4 px-4 text-text-faded">Oct 25, 2023</td>
                            <td class="py-4 px-4 text-white">$13.90</td>
                            <td class="py-4 px-4">
                                <span class="history-tag bg-status-completed-bg text-dark-bg">COMPLETED</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

</body>
</html>
