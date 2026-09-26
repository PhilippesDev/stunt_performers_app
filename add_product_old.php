<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Produit | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="https://cdn.jsdelivr.net/npm/preline@2.0.3/dist/preline.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full pb-20" x-data="productForm()">

<div class="max-w-5xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-8 reveal">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Nouveau Produit</h1>
            <p class="text-gray-500">Optimisez votre annonce pour vendre plus vite.</p>
        </div>
        <div class="hidden md:block">
            <div class="flex items-center gap-4 bg-white p-3 rounded-2xl border border-gray-100 shadow-sm">
                <div class="relative size-16">
                    <svg class="rotate-[135deg] size-full" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="16" fill="none" class="stroke-current text-gray-100" stroke-width="2" stroke-dasharray="75 100"></circle>
                        <circle cx="18" cy="18" r="16" fill="none" class="stroke-current transition-all duration-1000" 
                                :class="score > 70 ? 'text-green-500' : 'text-orange-500'" 
                                stroke-width="2" :stroke-dasharray="`${(score * 0.75)} 100`" stroke-linecap="round"></circle>
                    </svg>
                    <div class="absolute top-1/2 start-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center">
                        <span class="text-lg font-bold text-gray-800" x-text="score"></span>
                    </div>
                </div>
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Score d'efficacité</span>
            </div>
        </div>
    </div>

    <form @submit.prevent="submitForm" class="space-y-8">
        
        <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-gray-100 reveal">
            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                <span class="size-8 bg-orange-100 text-orange-600 rounded-lg flex items-center justify-center text-sm">1</span>
                Détails du produit
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Nom du produit</label>
                    <input type="text" x-model="formData.name" placeholder="Ex: iPhone 15 Pro Max" class="w-full rounded-2xl border-gray-200 bg-gray-50 px-4 py-3.5 focus:bg-white focus:ring-2 focus:ring-orange-500 outline-none border transition-all">
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Régions de vente</label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <template x-for="r in selectedRegions" :key="r">
                            <span class="inline-flex items-center gap-x-1.5 py-1.5 ps-3 pe-2 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <span x-text="r"></span>
                                <button type="button" @click="removeRegion(r)" class="hover:bg-blue-200 rounded-full p-0.5">
                                    <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round"/></svg>
                                </button>
                            </span>
                        </template>
                    </div>
                    <input list="regions-list" @change="addRegion($event)" placeholder="Ajouter une ville..." class="w-full rounded-2xl border-gray-200 bg-gray-50 px-4 py-3.5 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none border transition-all">
                    <datalist id="regions-list">
                        <option value="Goma">
                        <option value="Kinshasa">
                        <option value="Bukavu">
                        <option value="Lubumbashi">
                    </datalist>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Prix</label>
                    <div class="flex items-center gap-2">
                        <select x-model="formData.currency" class="w-24 rounded-2xl border-gray-200 bg-white px-3 py-3.5 border focus:ring-2 focus:ring-orange-500 outline-none">
                            <option value="USD">$ USD</option>
                            <option value="CDF">FC</option>
                        </select>
                        <div class="flex-1 flex items-center bg-gray-50 rounded-2xl border border-gray-200 px-2">
                            <button type="button" @click="formData.price--" class="p-2 hover:bg-gray-200 rounded-xl text-gray-600">-</button>
                            <input type="number" x-model="formData.price" class="flex-1 bg-transparent border-none text-center focus:ring-0 font-bold">
                            <span class="text-gray-400 font-bold px-2" x-text="formData.currency === 'USD' ? '$' : 'Fc'"></span>
                            <button type="button" @click="formData.price++" class="p-2 hover:bg-gray-200 rounded-xl text-gray-600">+</button>
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700">Unité de mesure</label>
                    <div class="flex bg-gray-100 p-1 rounded-2xl gap-1">
                        <template x-for="u in ['Pcs', 'Kg', 'Mètres', 'Litres']">
                            <button type="button" @click="formData.unit = u" 
                                    :class="formData.unit === u ? 'bg-white shadow-sm text-orange-600 scale-[1.02]' : 'text-gray-500 hover:bg-gray-200'"
                                    class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all" x-text="u"></button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="mt-6 space-y-2">
                <label class="text-sm font-bold text-gray-700">Description (Format: # Titre, *Gras*)</label>
                <textarea x-model="formData.description" rows="4" class="w-full rounded-2xl border-gray-200 bg-gray-50 px-4 py-3.5 focus:bg-white focus:ring-2 focus:ring-orange-500 outline-none border transition-all" placeholder="Décrivez votre produit..."></textarea>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-gray-100 reveal">
            <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                <span class="size-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center text-sm">2</span>
                Gallerie Photos (5 min - 30 max)
            </h3>
            
            <div class="flex items-center justify-center w-full">
                <label class="flex flex-col items-center justify-center w-full h-40 border-2 border-gray-300 border-dashed rounded-[2rem] cursor-pointer bg-gray-50 hover:bg-gray-100 transition-all">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <svg class="w-10 h-10 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <p class="mb-2 text-sm text-gray-500 font-semibold text-center px-4">Cliquez pour sélectionner vos photos</p>
                    </div>
                    <input type="file" multiple class="hidden" @change="handleFiles($event)" accept="image/*" />
                </label>
            </div>

            <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <template x-for="(file, index) in files" :key="index">
                    <div class="p-4 border border-gray-100 rounded-2xl bg-white shadow-sm flex items-center gap-3">
                        <div class="size-12 bg-gray-100 rounded-lg flex-shrink-0 overflow-hidden">
                            <img :src="file.preview" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-gray-800 truncate" x-text="file.name"></p>
                            <div class="mt-1 flex items-center gap-2">
                                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-teal-500 transition-all duration-1000" :style="`width: ${file.progress}%`"></div>
                                </div>
                                <span class="text-[10px] text-gray-400" x-text="file.progress + '%'"></span>
                            </div>
                        </div>
                        <button type="button" @click="removeFile(index)" class="text-gray-400 hover:text-red-500">
                            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <div class="reveal">
            <button type="button" class="hs-collapse-toggle w-full p-6 flex justify-between items-center bg-gray-900 text-white rounded-[2rem] shadow-xl shadow-gray-200" id="hs-shoe-collapse" data-hs-collapse="#shoe-content">
                <span class="font-bold">Tailles, Couleurs & État</span>
                <svg class="hs-collapse-open:rotate-180 transition-transform size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round"/></svg>
            </button>

            <div id="shoe-content" class="hs-collapse hidden w-full overflow-hidden transition-[height] duration-300">
                <div class="mt-4 bg-white rounded-[2rem] p-8 border border-gray-100 space-y-8">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="text-sm font-bold text-gray-700 block mb-3">Condition</label>
                            <div class="flex gap-4">
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" x-model="formData.condition" value="new" class="hidden peer">
                                    <div class="p-4 border-2 rounded-2xl text-center peer-checked:border-orange-500 peer-checked:bg-orange-50 transition-all font-bold text-gray-600">Neuf</div>
                                </label>
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" x-model="formData.condition" value="used" class="hidden peer">
                                    <div class="p-4 border-2 rounded-2xl text-center peer-checked:border-orange-500 peer-checked:bg-orange-50 transition-all font-bold text-gray-600">Seconde main</div>
                                </label>
                            </div>
                        </div>
                        <div x-show="formData.condition === 'used'" x-transition>
                            <label class="text-sm font-bold text-gray-700 block mb-2">Défauts constatés</label>
                            <textarea x-model="formData.defects" placeholder="Ex: Petite rayure sur le côté..." class="w-full rounded-2xl border-gray-200 bg-gray-50 px-4 py-3 focus:bg-white outline-none border"></textarea>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700 block">Tailles Chaussures (Manuel)</label>
                            <input type="text" @keydown.enter.prevent="addSize" placeholder="Tapez 42 puis Entrée" class="w-full rounded-2xl border-gray-200 bg-gray-50 px-4 py-3.5 focus:bg-white outline-none border">
                            <div class="flex flex-wrap gap-2 mt-2">
                                <template x-for="s in sizes" :key="s">
                                    <span class="bg-gray-100 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-2">
                                        <span x-text="s"></span>
                                        <button type="button" @click="sizes = sizes.filter(i => i !== s)">&times;</button>
                                    </span>
                                </template>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-gray-700 block">Tailles Habits (Suggestions)</label>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="sz in ['S', 'M', 'L', 'XL', 'XXL']">
                                    <button type="button" @click="toggleLetterSize(sz)" 
                                            :class="letterSizes.includes(sz) ? 'bg-orange-500 text-white' : 'bg-gray-50 text-gray-600'"
                                            class="px-4 py-2 rounded-xl text-xs font-bold transition-all" x-text="sz"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div class="space-y-4">
                        <label class="text-sm font-bold text-gray-700 block">Couleurs & Photos associées</label>
                        <div class="flex flex-wrap gap-4">
                            <input type="color" @change="addColor($event)" class="size-12 rounded-xl border-none cursor-pointer">
                            <template x-for="(c, idx) in colors" :key="idx">
                                <div class="flex items-center gap-2 bg-gray-50 p-2 rounded-xl border">
                                    <div class="size-6 rounded-full" :style="`background: ${c.hex}`"></div>
                                    <input type="text" x-model="c.label" placeholder="Nom couleur" class="bg-transparent border-none text-xs focus:ring-0 w-24">
                                    <button type="button" @click="colors.splice(idx, 1)" class="text-red-400">&times;</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] p-8 shadow-sm border border-gray-100 reveal">
            <h3 class="text-lg font-bold text-gray-900 mb-6">Caractéristiques techniques</h3>
            <div class="space-y-4">
                <template x-for="(spec, idx) in specifications" :key="idx">
                    <div class="flex gap-4 items-center animate-in fade-in slide-in-from-left-2">
                        <input type="text" x-model="spec.key" placeholder="Ex: Marque" class="flex-1 rounded-2xl border-gray-200 bg-gray-50 px-4 py-3 outline-none border">
                        <input type="text" x-model="spec.value" placeholder="Ex: Lenovo" class="flex-1 rounded-2xl border-gray-200 bg-gray-50 px-4 py-3 outline-none border">
                        <button type="button" @click="specifications.splice(idx, 1)" class="text-red-500 p-2">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </template>
                <button type="button" @click="specifications.push({key:'', value:''})" class="text-sm font-bold text-orange-600 hover:text-orange-700 flex items-center gap-2">
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"/></svg>
                    Ajouter une caractéristique
                </button>
            </div>
        </div>

        <div class="reveal">
            <button type="submit" 
                    :disabled="loading"
                    class="w-full py-4 bg-gray-900 text-white rounded-[2rem] font-bold text-lg shadow-xl shadow-gray-200 hover:bg-orange-600 transition-all flex items-center justify-center gap-3 active:scale-95 disabled:opacity-70">
                <template x-if="!loading">
                    <span>Mettre en vente</span>
                </template>
                <template x-if="loading">
                    <div class="flex items-center gap-3">
                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span>Analyse du système...</span>
                    </div>
                </template>
            </button>
        </div>
    </form>
</div>

<div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm" 
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100">
    <div class="bg-white rounded-[3rem] p-10 max-w-sm w-full text-center shadow-2xl">
        <div :class="modalType === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'" class="size-20 rounded-full flex items-center justify-center mx-auto mb-6">
            <template x-if="modalType === 'success'">
                <svg class="size-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </template>
            <template x-if="modalType === 'error'">
                <svg class="size-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </template>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-2" x-text="modalTitle"></h2>
        <p class="text-gray-500 mb-8" x-text="modalDesc"></p>
        <button @click="showModal = false" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-bold">Fermer</button>
    </div>
</div>

<script>
    function productForm() {
        return {
            loading: false,
            score: 25,
            showModal: false,
            modalType: 'success',
            modalTitle: '',
            modalDesc: '',
            files: [],
            selectedRegions: [],
            sizes: [],
            letterSizes: [],
            colors: [],
            specifications: [{key: 'Marque', value: ''}],
            formData: {
                name: '',
                price: 0,
                currency: 'USD',
                unit: 'Pcs',
                condition: 'new',
                description: '',
                defects: ''
            },
            
            init() {
                this.$watch('formData', () => this.calculateScore(), {deep: true});
                this.$watch('files', () => this.calculateScore());
                ScrollReveal().reveal('.reveal', { 
                    distance: '30px', 
                    origin: 'bottom', 
                    opacity: 0, 
                    duration: 800, 
                    interval: 100 
                });
            },

            calculateScore() {
                let s = 10;
                if(this.formData.name.length > 5) s += 15;
                if(this.formData.description.length > 20) s += 20;
                if(this.files.length >= 5) s += 30;
                if(this.specifications.length > 2) s += 15;
                if(this.selectedRegions.length > 0) s += 10;
                this.score = Math.min(s, 100);
            },

            addRegion(e) {
                const val = e.target.value;
                if(val && !this.selectedRegions.includes(val)) {
                    this.selectedRegions.push(val);
                }
                e.target.value = '';
            },
            removeRegion(r) {
                this.selectedRegions = this.selectedRegions.filter(i => i !== r);
            },

            handleFiles(e) {
                const newFiles = Array.from(e.target.files);
                newFiles.forEach(file => {
                    if(this.files.length < 30) {
                        const reader = new FileReader();
                        reader.onload = (ex) => {
                            this.files.push({
                                name: file.name,
                                preview: ex.target.result,
                                progress: 0
                            });
                            // Simuler progression
                            let idx = this.files.length - 1;
                            let interval = setInterval(() => {
                                if(this.files[idx].progress >= 100) clearInterval(interval);
                                else this.files[idx].progress += 25;
                            }, 200);
                        }
                        reader.readAsDataURL(file);
                    }
                });
            },
            removeFile(idx) { this.files.splice(idx, 1); },

            addSize(e) {
                if(e.target.value && !this.sizes.includes(e.target.value)) {
                    this.sizes.push(e.target.value);
                }
                e.target.value = '';
            },

            toggleLetterSize(sz) {
                if(this.letterSizes.includes(sz)) this.letterSizes = this.letterSizes.filter(i => i !== sz);
                else this.letterSizes.push(sz);
            },

            addColor(e) {
                this.colors.push({ hex: e.target.value, label: '' });
            },

            async submitForm() {
                if(this.files.length < 5) {
                    this.modalTitle = "Oups !";
                    this.modalDesc = "Il faut au moins 5 images pour une vente efficace.";
                    this.modalType = "error";
                    this.showModal = true;
                    return;
                }

                this.loading = true;
                // Simulation d'envoi API
                setTimeout(() => {
                    this.loading = false;
                    this.modalTitle = "Produit Propulsé !";
                    this.modalDesc = "Votre produit est maintenant en ligne et prêt à être vendu.";
                    this.modalType = "success";
                    this.showModal = true;
                }, 2500);
            }
        }
    }
</script>
</body>
</html>