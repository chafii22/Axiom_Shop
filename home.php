<?php
session_start();
require_once 'config/connect_db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Axiom - Fashion Future</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @font-face {
            font-family: 'Albra';
            src: url('assets/fonts/Albra.ttf') format('truetype');
        }
        @font-face {
            font-family: 'incredible';
            src: url(assets/fonts/Incrediible-BF6814d5097d803.ttf) format('truetype');
        }
        
        body {
            font-family: 'Noto Sans', sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        .heading-font {
            font-family: 'Albra', sans-serif;
        }
        .play-icon path {
            fill: currentColor;
        }
        .grid-background {
            background-image: 
                linear-gradient(to right, rgba(0, 0, 0, 0.05) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(0, 0, 0, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
        }
        html {
            scroll-behavior: auto; /* Override smooth scroll */
        }
        .snap-container {
            height: 100vh;
            overflow-y: auto;
            scroll-snap-type: y mandatory;
        }
        .snap-section {
            scroll-snap-align: start;
            min-height: 100vh;
            width: 100%;
        }
    </style>
</head>
<body class="bg-gray-100 ">
    <main class="snap-container relative p-4 mx-auto max-w-none bg-gray-200 rounded-[24px] max-md:max-w-[991px] max-sm:flex max-sm:flex-col max-sm:max-w-screen-sm grid-background">
        <section class="snap-section min-h-screen p-4 grid-background">
            <header class="flex justify-between items-center px-12 py-5 mb-8 bg-[#0f172a] rounded-[40px] max-md:px-8 max-md:py-4 max-sm:px-4 max-sm:py-3">
                <h1 class="text-xl tracking-[0.3rem] font-bold text-green-200 heading-font">Axiom</h1>

                <nav class="flex gap-6 max-sm:hidden">
                    <a href="home.php" class="text-base text-white hover:text-green-200 transition-colors">Home</a>
                    <a href="shop.php" class="text-base text-white hover:text-green-200 transition-colors">Shop</a>
                    <a href="about.php" class="text-base text-white hover:text-green-200 transition-colors">About Us</a>
                </nav>

            </header>

            <button aria-label="Mobile menu" class="text-base text-white cursor-pointer max-sm:mx-auto sm:hidden">...</button>

            <div class="flex gap-10 px-20 py-0 max-md:flex-col max-md:px-10 max-md:py-0 max-sm:px-6 max-sm:py-0">
                <section class="flex-1">
                    <h2 class="mb-6 text-7xl font-bold uppercase leading-[80px] text-slate-900 tracking-[2px] max-md:text-4xl max-md:leading-[60px] max-sm:text-3xl max-sm:leading-10 heading-font">
                        Find Your Future Fashion Today
                    </h2>

                    <p class="mb-14 text-base leading-6 max-w-[625px] text-neutral-600">
                        Dress our latest collection, curated for trendsetters seeking chic and timeless style. Elevate your wardrobe today!
                    </p>

                    <div class="flex gap-4 mb-20 max-sm:flex-col">
                        <button class="px-8 py-5 text-xl font-semibold text-white uppercase bg-[#0f172a] hover:bg-gray-800 transition-colors cursor-pointer rounded-[100px]">
                            Buy Now
                        </button>
                        <button aria-label="Learn more" class="flex justify-center items-center w-16 h-16 bg-white hover:bg-gray-50 transition-colors rounded-full cursor-pointer">
                            <i class="ti ti-arrow-up-right text-xl text-slate-900">↗</i>
                        </button>
                    </div>

                    <section class="flex gap-10 max-sm:flex-col max-sm:gap-6">
                        <article class="text-center">
                            <p class="text-5xl font-bold tracking-widest leading-[60px] text-neutral-800">20+</p>
                            <p class="text-base uppercase text-neutral-600">Years Of experience</p>
                        </article>

                        <article class="text-center">
                            <p class="text-5xl font-bold tracking-widest leading-[60px] text-neutral-800">21K+</p>
                            <p class="text-base uppercase text-neutral-600">Happy Customers</p>
                        </article>

                        <article class="text-center">
                            <p class="text-5xl font-bold tracking-widest leading-[60px] text-neutral-800">150+</p>
                            <p class="text-base uppercase text-neutral-600">Product brand</p>
                        </article>
                    </section>
                </section>

                <section class="relative flex-1 max-md:mt-10 max-sm:hidden">
                    <div class="overflow-hidden rounded-3xl bg-neutral-400 h-[700px]">
                        <img src="assets/background/faceMask2.jpg" alt="Fashion model" class="object-cover w-full h-full border-0 hover:scale-105  transition-transform duration-500">
                    </div>
                </section>
            </div>
                    <!-- Banner section - improved version -->
                    <section class="mt-8 px-8 py-6 bg-slate-900 rounded-[20px] overflow-hidden relative grid-background-dark">
                        <!-- Optional: Add subtle grid lines to match main background -->
                        <div class="absolute inset-0 opacity-10 pointer-events-none" 
                            style="background-image: linear-gradient(to right, rgba(255, 255, 255, 0.2) 1px, transparent 1px), 
                                    linear-gradient(to bottom, rgba(255, 255, 255, 0.2) 1px, transparent 1px); 
                                    background-size: 50px 50px;"></div>
                                    
                        <div class="flex flex-wrap justify-between items-center gap-4 w-full text-white">
                            <div class="flex-1 text-center px-3">
                                <h2 class="text-3xl md:text-4xl font-bold uppercase tracking-widest">Fashion Week</h2>
                            </div>
                            
                            <img src="assets/divider.png" alt="Divider" class="hidden md:block h-20 object-contain">
                            
                            <div class="flex-1 text-center px-3">
                                <h2 class="text-3xl md:text-4xl font-bold uppercase tracking-widest">Fashion Award</h2>
                            </div>
                            
                            <img src="assets/divider.png" alt="Divider" class="hidden md:block h-20 object-contain">
                            
                            <div class="flex-1 text-center px-3">
                                <h2 class="text-3xl md:text-4xl font-bold uppercase tracking-widest">Fashion Show</h2>
                            </div>
                        </div>
                    </section>
        </section>
        
        <!--second section-->
        <section class="snap-section min-h-screen p-4 overflow-hidden bg-zinc-100 rounded-[32px] grid-background">
            <div class="flex relative flex-col px-20 py-12 w-full max-md:px-5 max-md:max-w-full">
                <header class="flex relative flex-col justify-center items-center self-center text-center max-md:max-w-full mb-12">
                    <h1 class="text-7xl font-bold tracking-widest leading-none uppercase text-slate-900 max-md:max-w-full max-md:text-4xl heading-font">
                        our hot selling products
                    </h1>
                    <p class="mt-4 text-base leading-6 text-neutral-600 max-w-3xl max-md:max-w-full">
                        Discover a fusion of trend and sophistication in our curated collection.
                        From chic essentials to statement pieces, our fashion embraces
                        individuality, ensuring every wardrobe reflects style, versatility, and
                        timeless elegance
                    </p>
                </header>
        
                <!-- New dynamic product grid -->
                <div class="grid grid-cols-12 gap-6 mt-10">
                    <!-- Featured large product - spans 6 columns -->
                    <article class="col-span-4 row-span-2 bg-white rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl max-md:col-span-12">
                        <div class="relative h-[800px] max-md:h-[500px]">
                            <img
                                src="assets/hot_seller/bee.jpg"
                                alt="Pink suit jacket"
                                class="absolute inset-0 w-full h-full object-center object-cover"
                            />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-6">
                                <div class="flex justify-between items-end">
                                    <h2 class="text-2xl font-bold text-white uppercase">
                                        Pink suit jacket
                                    </h2>
                                    <p class="text-4xl font-bold text-white">
                                        $105
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
        
                    <!-- Medium product - spans 4 columns -->
                    <article class="col-span-8 bg-white rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl max-md:col-span-12">
                        <div class="relative h-[380px] max-md:h-[400px]">
                            <img
                                src="assets/hot_seller/VM8.jpg"
                                alt="White hoodie"
                                class="absolute inset-0 w-full h-full object-cover"
                            />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4">
                                <div class="flex justify-between items-end">
                                    <h2 class="text-xl font-bold text-white uppercase">
                                        White hoodie
                                    </h2>
                                    <p class="text-3xl font-bold text-white">
                                        $50
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>

        
                    <!-- Small product - spans 4 columns -->
                    <article class="col-span-4 bg-white rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl max-md:col-span-12 max-md:col-start-1">
                        <div class="relative h-[400px] max-md:h-[350px]">
                            <img
                                src="assets/hot_seller/shoeS4.jpg"
                                alt="Blue cowboy hat"
                                class="absolute inset-0 w-full h-full object-cover"
                            />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4">
                                <div class="flex justify-between items-end">
                                    <h2 class="text-lg font-bold text-white uppercase">
                                        Blue cowboy hat
                                    </h2>
                                    <p class="text-2xl font-bold text-white">
                                        $25
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
        
                    <!-- Medium-small product - spans 4 columns -->
                    <article class="col-span-4 bg-white rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl max-md:col-span-12">
                        <div class="relative h-[400px] max-md:h-[350px]">
                            <img
                                src="assets/hot_seller/talonB4.jpg"
                                alt="Black shirt"
                                class="absolute inset-0 w-full h-full object-cover"
                            />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4">
                                <div class="flex justify-between items-end">
                                    <h2 class="text-lg font-bold text-white uppercase">
                                        Black shirt
                                    </h2>
                                    <p class="text-2xl font-bold text-white">
                                        $15
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
                <!--ALL product button -->
                <div class="flex justify-center mt-12 mb-6">
                    <a href="shop.php" class="px-8 py-4 text-xl font-semibold text-white uppercase bg-black hover:bg-gray-800 transition-colors cursor-pointer rounded-[100px] flex items-center gap-2">
                        View All Products
                        <span class="text-white text-xl">→</span>
                    </a>
                </div>
            </div>
        </section>

        <!--third section-->
        <section class="snap-section min-h-screen overflow-hidden rounded-[32px]">
            <!-- Background container -->
            <div class="relative flex flex-col justify-between w-full h-full">
                <!-- Background image -->
                <img
                    src="assets/background/city_v2.jpg"
                    alt="Background image"
                    class="object-cover absolute inset-0 w-full h-full"
                />
        
                <!-- Dark overlay -->
                <div class="absolute inset-0 bg-black opacity-30"></div>
        
                <!-- Main content area -->
                <div class="relative flex flex-col justify-between h-full">
                    <!-- Hero content -->
                    <div class="flex flex-col justify-center items-center pt-20 px-20 pb-10 w-full max-md:px-5">
                        <div class="flex flex-col items-center text-center text-white max-w-6xl mx-auto">
                            <h1 class="text-5xl font-bold tracking-widest uppercase leading-[71px] max-md:text-4xl max-md:leading-[53px] heading-font">
                                Embrace Style with Our Exclusive Collection
                            </h1>
                            <p class="mt-4 text-base leading-6 max-w-[930px] max-md:w-full">
                                Elevate your fashion game today! Explore our curated collection and make a statement with timeless style. Embrace the extraordinary – shop now for a wardrobe that speaks volumes
                            </p>
                        </div>
                        <div class="mt-14 max-w-full w-[343px] max-md:mt-10">
                            <button class="px-8 py-7 w-full text-xl font-semibold tracking-wide text-center uppercase bg-white bg-opacity-90 rounded-[200px] text-slate-900 hover:bg-white transition-colors">
                                Buy Now
                            </button>
                        </div>
                    </div>
                    
                    <!-- Footer area with proper flex layout -->
                    <footer class="relative mt-auto px-20 py-10 bg-black bg-opacity-50 max-md:px-5">
                        <div class="flex flex-wrap justify-between gap-10 max-md:flex-col">
                            <!-- Quick links column -->
                            <nav class="flex-1 text-white uppercase min-w-[200px] max-w-[300px]">
                                <h2 class="text-3xl font-bold tracking-wider leading-none text-white">
                                    Quick links
                                </h2>
                                <ul class="flex flex-col items-start mt-10 w-full text-base font-medium">
                                    <li class="gap-1 text-white">
                                        <a href="#about" class="hover:text-green-200">→ About us</a>
                                    </li>
                                    <li class="gap-1 mt-6 text-white whitespace-nowrap">
                                        <a href="#shop" class="hover:text-green-200">→ Shop</a>
                                    </li>
                                    <li class="gap-1 mt-6 text-white whitespace-nowrap">
                                        <a href="#blog" class="hover:text-green-200">→ Blog</a>
                                    </li>
                                </ul>
                            </nav>
        
                            <!-- Contact us column -->
                            <div class="flex-1 text-white uppercase min-w-[200px] max-w-[300px]">
                                <h2 class="text-3xl font-bold tracking-wider leading-none text-white uppercase">
                                    Contact us
                                </h2>
                                <div class="mt-10 w-full">
                                    <a href="mailto:hello@website.com" class="flex gap-2 items-center w-full text-base font-medium text-white uppercase whitespace-nowrap hover:text-green-200">
                                        <img
                                            src="https://cdn.builder.io/api/v1/image/assets/TEMP/c0a511cf69818b85cd914b6f13c37c594adae6be?placeholderIfAbsent=true&apiKey=7a8113b9af344fa3aa3771a4d019d0a3"
                                            alt="Email icon"
                                            class="object-contain shrink-0 self-stretch my-auto w-4 aspect-square"
                                        />
                                        <span class="flex-1 shrink self-stretch my-auto text-white basis-0">
                                            hello@website.com
                                        </span>
                                    </a>
                                    <address class="flex gap-2 items-start mt-4 w-full not-italic">
                                        <div class="flex gap-2 items-start pt-1 w-4">
                                            <img
                                                src="https://cdn.builder.io/api/v1/image/assets/TEMP/7f769f10123f49ef7fffb7b3605a3c9dd048fff1?placeholderIfAbsent=true&apiKey=7a8113b9af344fa3aa3771a4d019d0a3"
                                                alt="Location icon"
                                                class="object-contain w-4 aspect-square"
                                            />
                                        </div>
                                        <p class="flex-1 shrink text-base font-medium leading-6 text-white uppercase basis-0">
                                            Riverside Building, London SE1 7PB, UK
                                        </p>
                                    </address>
                                    <a href="tel:+025421234560" class="flex gap-2 items-center mt-4 w-full text-base font-medium text-white uppercase hover:text-green-200">
                                        <img
                                            src="https://cdn.builder.io/api/v1/image/assets/TEMP/94709498efec83f0da0a90cddef3a6a83f94c4f7?placeholderIfAbsent=true&apiKey=7a8113b9af344fa3aa3771a4d019d0a3"
                                            alt="Phone icon"
                                            class="object-contain shrink-0 self-stretch my-auto w-4 aspect-square"
                                        />
                                        <span class="flex-1 shrink self-stretch my-auto text-white basis-0">
                                            +02 5421234560
                                        </span>
                                    </a>
                                </div>
                            </div>
        
                            <!-- Newsletter column -->
                            <div class="flex-1 text-white uppercase min-w-[300px]">
                                <h2 class="text-3xl font-bold tracking-wider leading-none text-white uppercase">
                                    Newsletter
                                </h2>
                                <div class="flex flex-col mt-10 w-full">
                                    <form class="flex items-start w-full uppercase">
                                        <input
                                            type="email"
                                            placeholder="Enter email address"
                                            class="flex flex-1 shrink items-start p-4 text-base font-medium bg-white rounded-lg basis-4 text-neutral-600"
                                            required
                                        />
                                        <button
                                            type="submit"
                                            class="overflow-hidden gap-2 self-stretch px-6 py-4 text-xl font-semibold tracking-wide leading-none text-center whitespace-nowrap bg-green-200 rounded-[25px] text-slate-900 max-md:px-5 hover:bg-green-300 ml-[4px]"
                                        >
                                            Subscribe
                                        </button>
                                    </form>
                                    <div class="flex gap-4 items-start self-start mt-6">
                                        <a href="#" class="flex items-center p-2 w-10 h-10 bg-green-200 rounded-3xl hover:bg-green-300">
                                            <img
                                                src="https://cdn.builder.io/api/v1/image/assets/TEMP/df62ec7bbad53f9ae7be437010808cef906cd886?placeholderIfAbsent=true&apiKey=7a8113b9af344fa3aa3771a4d019d0a3"
                                                alt="Social media icon"
                                                class="object-contain w-6 aspect-square"
                                            />
                                        </a>
                                        <a href="#" class="flex items-center p-2 w-10 h-10 rounded-3xl border border-solid border-[color:var(--color-accent-3,#BBF6BE)] hover:bg-green-200">
                                            <img
                                                src="https://cdn.builder.io/api/v1/image/assets/TEMP/acffc699edb2bbe2b36cd8bf03a568ac1507f1ee?placeholderIfAbsent=true&apiKey=7a8113b9af344fa3aa3771a4d019d0a3"
                                                alt="Social media icon"
                                                class="object-contain w-6 aspect-square"
                                            />
                                        </a>
                                        <a href="#" class="flex items-center p-2 w-10 h-10 rounded-3xl border border-solid border-[color:var(--color-accent-3,#BBF6BE)] hover:bg-green-200">
                                            <img
                                                src="https://cdn.builder.io/api/v1/image/assets/TEMP/bef05beadd16bb319b1f833dfd3a54f213bcf6d4?placeholderIfAbsent=true&apiKey=7a8113b9af344fa3aa3771a4d019d0a3"
                                                alt="Social media icon"
                                                class="object-contain w-6 aspect-square"
                                            />
                                        </a>
                                        <a href="#" class="flex items-center p-2 w-10 h-10 rounded-3xl border border-solid border-[color:var(--color-accent-3,#BBF6BE)] hover:bg-green-200">
                                            <img
                                                src="https://cdn.builder.io/api/v1/image/assets/TEMP/4121983d570d64bc2001ae94ca1ad1a97bed1ace?placeholderIfAbsent=true&apiKey=7a8113b9af344fa3aa3771a4d019d0a3"
                                                alt="Social media icon"
                                                class="object-contain w-6 aspect-square"
                                            />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </footer>
                </div>
            </div>
        </section>
    </main>
    
    <?php include 'sticky_nav.php'; ?>

</body>
</html>
