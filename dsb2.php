<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Lucas Bennett</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #FDF2F0; }
        .bg-custom-teal { background-color: #008B7D; }
        .text-custom-orange { color: #FF5C01; }
        .bg-custom-orange { background-color: #FF5C01; }
    </style>
</head>
<body class="p-4 md:p-10 flex flex-wrap justify-center gap-10">

    <div class="w-[375px] h-[812px] bg-[#F2F2F2] rounded-[3rem] overflow-hidden shadow-2xl border-[8px] border-black relative">
        <div class="h-10 flex justify-between px-8 pt-4 items-center">
            <span class="text-xs font-bold">9:41</span>
            <div class="w-24 h-6 bg-black rounded-full"></div> <div class="flex gap-1 text-xs">📶 🔋</div>
        </div>

        <div class="bg-custom-teal h-40 relative px-6 pt-4">
            <div class="flex justify-between items-center">
                <button class="bg-white/90 p-2 rounded-xl shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </button>
                <button class="bg-white/90 p-2 rounded-xl shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z" /></svg>
                </button>
            </div>
            
            <div class="absolute -bottom-10 left-6">
                <img src="https://i.pravatar.cc/150?u=lucas" alt="Lucas" class="w-24 h-24 rounded-full border-4 border-white object-cover shadow-md">
            </div>
        </div>

        <div class="mt-12 px-6 flex justify-between items-start">
            <div>
                <h1 class="text-xl font-bold text-gray-800">Lucas Bennett</h1>
                <p class="text-sm text-gray-500">lucasbennett@gmail.com</p>
            </div>
            <button class="border border-custom-orange text-custom-orange px-4 py-1.5 rounded-xl text-sm font-medium">
                + Friends
            </button>
        </div>

        <div class="flex px-6 mt-6 gap-8">
            <div class="text-center">
                <span class="block font-bold">1.5K</span>
                <span class="text-xs text-gray-500">Followers</span>
            </div>
            <div class="text-center">
                <span class="block font-bold">0</span>
                <span class="text-xs text-gray-500">Following</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 px-6 mt-6">
            <div class="bg-white p-4 rounded-3xl shadow-sm relative">
                <div class="bg-yellow-100 w-8 h-8 rounded-lg flex items-center justify-center mb-4">⭐</div>
                <div class="text-2xl font-bold">51</div>
                <div class="text-xs text-gray-400">Balance</div>
            </div>
            <div class="bg-white p-4 rounded-3xl shadow-sm relative">
                <div class="bg-orange-100 w-8 h-8 rounded-lg flex items-center justify-center mb-4">🏆</div>
                <div class="text-2xl font-bold">1</div>
                <div class="text-xs text-gray-400">Level</div>
                <span class="absolute top-2 right-2 bg-orange-100 text-[10px] text-custom-orange px-2 py-0.5 rounded-full">Record</span>
            </div>
            <div class="bg-white p-4 rounded-3xl shadow-sm">
                <div class="bg-red-50 w-8 h-8 rounded-lg flex items-center justify-center mb-4">👣</div>
                <div class="text-sm font-bold">Barefoot</div>
                <div class="text-[10px] text-gray-400 uppercase tracking-wider">Current League</div>
            </div>
            <div class="bg-white p-4 rounded-3xl shadow-sm">
                <div class="bg-yellow-50 w-8 h-8 rounded-lg flex items-center justify-center mb-4">⚡</div>
                <div class="text-2xl font-bold">30</div>
                <div class="text-[10px] text-gray-400 uppercase tracking-wider">Total XP</div>
            </div>
        </div>

        <div class="mx-6 mt-6 p-5 bg-white rounded-3xl shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-sm">Weekly XP</h3>
                <span class="text-custom-orange text-xs font-bold">30 ⚡</span>
            </div>
            <div class="flex items-end justify-between h-24 gap-1">
                <div class="bg-orange-200 w-full rounded-t-lg h-[40%]"></div>
                <div class="bg-custom-orange w-full rounded-t-lg h-[80%]"></div>
                <div class="bg-orange-200 w-full rounded-t-lg h-[30%]"></div>
                <div class="bg-orange-200 w-full rounded-t-lg h-[50%]"></div>
                <div class="bg-orange-100 w-full rounded-t-lg h-[20%] border-t border-dashed border-gray-300"></div>
                <div class="bg-orange-100 w-full rounded-t-lg h-[20%] border-t border-dashed border-gray-300"></div>
                <div class="bg-orange-100 w-full rounded-t-lg h-[20%] border-t border-dashed border-gray-300"></div>
            </div>
        </div>

        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-32 h-1 bg-black rounded-full"></div>
    </div>

    <div class="w-[375px] h-[812px] bg-[#F2F2F2] rounded-[3rem] overflow-hidden shadow-2xl border-[8px] border-black relative">
        <div class="h-10 flex justify-between px-8 pt-4 items-center">
            <span class="text-xs font-bold">9:41</span>
            <div class="w-24 h-6 bg-black rounded-full"></div>
            <div class="flex gap-1 text-xs">📶 🔋</div>
        </div>

        <div class="flex items-center px-6 py-4">
            <button class="bg-white p-2 rounded-xl shadow-sm mr-20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <h2 class="text-lg font-bold">Settings</h2>
        </div>

        <div class="mx-6 bg-white rounded-[2rem] overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-gray-50">
                <div class="flex items-center gap-3">
                    <span class="text-gray-400">📧</span>
                    <span class="text-sm font-medium">Email</span>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </div>
            <div class="flex items-center justify-between p-4 border-b border-gray-50">
                <div class="flex items-center gap-3">
                    <span class="text-gray-400">👤</span>
                    <span class="text-sm font-medium">Username</span>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </div>
            <div class="flex items-center justify-between p-4">
                <div class="flex items-center gap-3">
                    <span class="text-gray-400">📊</span>
                    <span class="text-sm font-medium">Step data source</span>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </div>
        </div>

        <div class="mx-6 mt-6 bg-white rounded-2xl p-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <span class="text-blue-500">💎</span>
                <span class="text-sm font-medium">Premium Status</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-xs text-orange-400">Inactive</span>
                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </div>
        </div>

        <div class="mx-6 mt-6 bg-custom-orange rounded-3xl p-4 relative h-28 overflow-hidden text-white">
            <div class="relative z-10">
                <h4 class="font-bold text-sm">Refer a friend</h4>
                <div class="mt-2 bg-white/20 backdrop-blur-md inline-flex items-center gap-2 px-3 py-1 rounded-full text-[10px] font-bold">
                    <span class="bg-yellow-400 text-black rounded-full px-1">₩</span> 50 /referral
                </div>
            </div>
            <div class="absolute right-2 bottom-0 flex gap-2 scale-75 origin-bottom-right">
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-xl">🐼</div>
                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-xl">🐼</div>
            </div>
        </div>

        <div class="mx-6 mt-6 space-y-3">
            <div class="bg-white rounded-2xl p-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="text-orange-600 bg-orange-100 p-1 rounded-lg">🦊</span>
                    <span class="text-sm font-medium">App Icon</span>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </div>
            <div class="bg-white rounded-2xl p-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <span class="text-gray-500">🔲</span>
                    <span class="text-sm font-medium">Widget</span>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </div>
        </div>

        <div class="absolute bottom-10 left-6 right-6">
            <button class="w-full bg-white py-4 rounded-2xl flex items-center justify-center gap-2 font-bold text-sm">
                <span>+</span> Add your friends
            </button>
        </div>

        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-32 h-1 bg-black rounded-full"></div>
    </div>

</body>
</html>