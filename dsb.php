<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Dashboard - Irene Brooks</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F3F5F9; }
        .glass-header {
            background: linear-gradient(110deg, #E0C3FC 0%, #8EC5FC 100%);
            filter: blur(80px);
            opacity: 0.4;
        }
    </style>
</head>
<body class="min-h-screen pb-12">

    <nav class="flex items-center justify-between px-8 py-4 bg-white border-b sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <div class="text-blue-600 font-bold text-xl">✦ Portfolia</div>
        </div>
        <div class="hidden md:flex items-center gap-8 text-gray-600 font-medium">
            <a href="#" class="text-black border-b-2 border-black">Designers</a>
            <a href="#" class="hover:text-black">Explore</a>
            <a href="#" class="hover:text-black">Projects</a>
            <a href="#" class="hover:text-black">Work</a>
            <a href="#" class="text-purple-600 flex items-center gap-1">Go pro <span class="text-xs">⚡</span></a>
        </div>
        <div class="flex items-center gap-4">
            <button class="p-2 bg-gray-100 rounded-full">🔔</button>
            <button class="bg-indigo-600 text-white px-6 py-2 rounded-xl font-medium hover:bg-indigo-700 transition">Upload</button>
            <img src="https://i.pravatar.cc/150?u=irene" class="w-10 h-10 rounded-full border border-gray-200" alt="Profile">
        </div>
    </nav>

    <main class="max-w-7xl mx-auto mt-8 bg-white rounded-[40px] shadow-sm overflow-hidden relative">
        <div class="absolute top-0 left-0 right-0 h-64 glass-header -z-10"></div>

        <div class="px-12 pt-16 pb-8 flex flex-col md:flex-row items-end gap-8">
            <div class="relative">
                <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=200&h=200" 
                     class="w-48 h-48 rounded-[60px] object-cover border-8 border-white shadow-lg" alt="Irene Brooks">
            </div>

            <div class="flex-1 pb-4">
                <div class="flex items-center gap-3">
                    <h1 class="text-4xl font-bold text-gray-900">Irene Brooks</h1>
                    <span class="bg-indigo-100 text-indigo-600 px-3 py-1 rounded-lg text-sm font-bold flex items-center gap-1">PRO ⚡</span>
                </div>
                <p class="text-gray-500 mt-2 text-lg">Interface and Brand Designer <br> based in San Antonio</p>
                <div class="flex gap-3 mt-6">
                    <button class="bg-black text-white px-8 py-3 rounded-2xl font-semibold hover:bg-gray-800 transition">Follow</button>
                    <button class="border border-gray-300 px-8 py-3 rounded-2xl font-semibold hover:bg-gray-50 transition">Get in touch</button>
                </div>
            </div>

            <div class="flex gap-12 pb-4 pr-4">
                <div class="text-center">
                    <p class="text-gray-400 text-sm">Followers</p>
                    <p class="text-3xl font-bold">2,985</p>
                </div>
                <div class="text-center">
                    <p class="text-gray-400 text-sm">Following</p>
                    <p class="text-3xl font-bold">132</p>
                </div>
                <div class="text-center">
                    <p class="text-gray-400 text-sm">Likes</p>
                    <p class="text-3xl font-bold">548</p>
                </div>
                <div class="flex gap-2 items-center ml-4">
                    <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold">26</div>
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs font-bold">6</div>
                    <div class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-white text-xs font-bold">12</div>
                </div>
            </div>
        </div>

        <div class="px-12 border-b">
            <div class="flex gap-8">
                <button class="pb-4 border-b-2 border-black font-bold flex items-center gap-2">Work <span class="text-xs text-gray-400">54</span></button>
                <button class="pb-4 text-gray-500 hover:text-black transition">Moodboards</button>
                <button class="pb-4 text-gray-500 hover:text-black transition">Likes</button>
                <button class="pb-4 text-gray-500 hover:text-black transition">About</button>
            </div>
        </div>

        <div class="p-12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
            
            <div class="group cursor-pointer">
                <div class="bg-[#DCE9FF] rounded-[40px] p-8 h-[320px] relative overflow-hidden transition-transform hover:scale-[1.02]">
                    <img src="https://cdn-icons-png.flaticon.com/512/5164/5164023.png" class="absolute -right-10 -bottom-10 w-64 opacity-20" alt="">
                    <div class="flex gap-2 relative z-10">
                        <div class="w-16 h-28 bg-black rounded-2xl overflow-hidden border-2 border-white/20 shadow-xl">
                            <div class="h-full bg-gradient-to-b from-blue-600 to-indigo-900"></div>
                        </div>
                        <div class="w-16 h-28 bg-black rounded-2xl overflow-hidden border-2 border-white/20 shadow-xl mt-4">
                            <div class="h-full bg-blue-500"></div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-between items-start">
                    <div>
                        <h3 class="font-bold text-xl">VPN Mobile App</h3>
                        <p class="text-gray-500">Mobile UI, Research</p>
                    </div>
                    <div class="flex items-center gap-4 text-gray-400 font-medium">
                        <span class="flex items-center gap-1">❤️ 517</span>
                        <span class="flex items-center gap-1">👁️ 9.3k</span>
                    </div>
                </div>
            </div>

            <div class="group cursor-pointer">
                <div class="bg-[#F2F2F2] rounded-[40px] p-8 h-[320px] relative flex items-center justify-center transition-transform hover:scale-[1.02]">
                    <div class="absolute top-4 right-4 bg-orange-600 text-white text-[10px] font-bold px-2 py-1 rounded-md">UI</div>
                    <div class="w-full h-48 bg-white rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
                        <div class="p-4 border-b flex justify-between items-center">
                            <div class="w-20 h-2 bg-gray-100 rounded"></div>
                            <div class="flex gap-1"><div class="w-2 h-2 bg-gray-200 rounded-full"></div><div class="w-2 h-2 bg-gray-200 rounded-full"></div></div>
                        </div>
                        <div class="p-4 space-y-3">
                            <div class="w-full h-3 bg-gray-50 rounded"></div>
                            <div class="w-2/3 h-3 bg-gray-50 rounded"></div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-between items-start">
                    <div>
                        <h3 class="font-bold text-xl">Property Dashboard</h3>
                        <p class="text-gray-500">Web interface</p>
                    </div>
                    <div class="flex items-center gap-4 text-gray-400 font-medium">
                        <span class="flex items-center gap-1">❤️ 983</span>
                        <span class="flex items-center gap-1">👁️ 14k</span>
                    </div>
                </div>
            </div>

            <div class="group cursor-pointer">
                <div class="bg-[#E5F1FF] rounded-[40px] p-8 h-[320px] relative overflow-hidden transition-transform hover:scale-[1.02]">
                    <div class="absolute top-4 right-4 flex gap-1">
                        <span class="bg-orange-600 text-white text-[10px] font-bold px-2 py-1 rounded-md">UI</span>
                        <span class="bg-indigo-600 text-white text-[10px] font-bold px-2 py-1 rounded-md">Br</span>
                    </div>
                    <div class="flex gap-4 justify-center mt-4">
                        <div class="w-24 h-48 bg-white rounded-[20px] shadow-xl border border-blue-100"></div>
                        <div class="w-24 h-48 bg-white rounded-[20px] shadow-xl border border-blue-100 mt-6"></div>
                    </div>
                </div>
                <div class="mt-6 flex justify-between items-start">
                    <div>
                        <h3 class="font-bold text-xl">Healthcare Mobile App</h3>
                        <p class="text-gray-500">Mobile UI, Branding</p>
                    </div>
                    <div class="flex items-center gap-4 text-gray-400 font-medium">
                        <span class="flex items-center gap-1">❤️ 875</span>
                        <span class="flex items-center gap-1">👁️ 13.5k</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Optionnel : Ajout d'une petite interaction au scroll
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('nav');
            if (window.scrollY > 10) {
                nav.classList.add('shadow-md');
            } else {
                nav.classList.remove('shadow-md');
            }
        });
    </script>
</body>
</html>