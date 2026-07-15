<template>
    <div class="flex-1 overflow-y-auto bg-gradient-to-br from-slate-50 via-[#f7faff] to-slate-100">
        <div class="mx-auto max-w-7xl px-8 py-8">

            <!-- ================= HERO ================= -->

            <section
                class="relative overflow-hidden rounded-[36px] border border-slate-200/80 bg-white shadow-[0_25px_80px_rgba(15,23,42,.08)]"
            >

                <!-- Background -->

                <div
                    class="absolute inset-0 bg-[radial-gradient(circle_at_top,#dbeafe_0%,transparent_45%)] opacity-70"
                ></div>

                <div
                    class="absolute -left-40 top-24 h-96 w-96 rounded-full bg-blue-200/20 blur-[140px]"
                ></div>

                <div
                    class="absolute -right-32 bottom-0 h-96 w-96 rounded-full bg-indigo-200/20 blur-[150px]"
                ></div>

                <div
                    class="absolute left-1/2 top-0 h-80 w-[650px] -translate-x-1/2 rounded-full bg-brand-300/10 blur-[120px]"
                ></div>

                <div class="relative z-10 px-14 py-14">

                    <!-- LOGO -->

                    <div class="flex justify-center">

                        <div class="relative">

                            <div
                                class="absolute left-1/2 top-1/2 h-52 w-52 -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-400/10 blur-3xl"
                            ></div>

                            <img
                                src="/logo.png"
                                alt="Nexora Agent IA"
                                class="relative z-10 h-56 w-auto object-contain transition duration-500 hover:scale-[1.02]"
                            />

                        </div>

                    </div>

                    <!-- TITLE -->

                    <div class="mx-auto mt-8 max-w-3xl text-center">

                        <span
                            class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-4 py-2 text-sm font-medium text-brand-600"
                        >
                            Plataforma Inteligente
                        </span>

                        <h1
                            class="mt-6 text-5xl font-bold tracking-tight text-slate-900"
                        >
                            Seu agente de
                            <span
                                class="bg-gradient-to-r from-brand-500 to-blue-700 bg-clip-text text-transparent"
                            >
                                Inteligência Artificial
                            </span>
                        </h1>

                        <p
                            class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-500"
                        >
                            Converse com sua IA, analise documentos, obtenha respostas
                            inteligentes e aumente sua produtividade em um único lugar.
                        </p>

                    </div>

                    <!-- FEATURE CARDS -->

                    <div
                        class="mt-12 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4"
                    >

                        <div
                            v-for="feature in features"
                            :key="feature.title"
                            class="group rounded-2xl border border-slate-100 bg-white/80 p-6 backdrop-blur transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl"
                        >

                            <div
                                class="flex h-14 w-14 items-center justify-center rounded-2xl transition duration-300 group-hover:scale-110"
                                :class="feature.iconBg"
                            >

                                <component
                                    :is="feature.icon"
                                    class="h-7 w-7"
                                    :class="feature.iconColor"
                                />

                            </div>

                            <h3
                                class="mt-6 text-lg font-semibold text-slate-900"
                            >
                                {{ feature.title }}
                            </h3>

                            <p
                                class="mt-3 text-sm leading-7 text-slate-500"
                            >
                                {{ feature.description }}
                            </p>

                        </div>

                    </div>

                </div>

            </section>

        </div>

    </div>

</template>


