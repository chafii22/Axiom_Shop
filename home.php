<?php
session_start();
require_once 'config/connect_db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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

        @media (max-width: 576px) {
            body {
                font-size: 16px; /* Base font size */
            }
            h1 {
                font-size: 24px;
            }
            h2 {
                font-size: 20px;
            }

            /* Fix scroll snap on mobile */
            .snap-container {
                scroll-snap-type: y proximity; 
            }
            
            /* Improve touch targets */
            button, a {
                min-height: 44px;
                min-width: 44px;
            }
            
            /* Proper padding for content */
            .snap-section {
                padding: 1rem;
            }
            
            /* Adjust newsletter form for mobile */
            form input, form button {
                min-height: 48px;
            }

            
    /* Improve newsletter form on small screens */
            footer form {
                flex-direction: column;
                width: 100%;
            }
            
            footer form input {
                width: 100%;
                margin-bottom: 0.75rem;
            }
            
            footer form button {
                width: 100%;
                margin-left: 0;
            }
            
            /* Better spacing in footer */
            footer .flex-wrap > div {
                margin-bottom: 2rem;
            }
            
            /* Hero text on background image */
            .flex-col.justify-center.items-center.pt-20 {
                padding: 1rem !important;
            }
            
            .flex-col.justify-center.items-center.pt-20 h1 {
                font-size: 1.75rem !important;
                line-height: 1.3 !important;
            }
        }
             @media (min-width: 577px) and (max-width: 768px) {
                /* Second section header */
                .text-7xl.font-bold.tracking-widest {
                    font-size: 3rem;
                    line-height: 1.2;
                }
                
                /* Adjust third section padding */
                .flex-col.justify-center.items-center.pt-20 {
                    padding: 2rem 1.5rem !important;
                }
            }

        /* Optimize for landscape phones */
            @media (max-width: 767px) and (orientation: landscape) {
                .snap-section {
                    min-height: 120vh; /* Allow scrolling in landscape */
                }
            }

            @media (max-width: 480px) {
                .snap-container {
                    padding: 0.5rem !important;
                }
                
                .snap-section {
                    padding: 0.5rem !important;
                }
                
                /* Fix product grid on very small screens */
                .col-span-4, .col-span-8 {
                    grid-column: span 12 / span 12;
                }
            }

            @media (max-width: 380px) {
                header {
                    border-radius: 20px !important;
                    padding-left: 0.75rem !important;
                    padding-right: 0.75rem !important;
                }
                
                h1.heading-font {
                    font-size: 1rem;
                }
                
                #mobileMenuToggle svg {
                    width: 1.25rem;
                    height: 1.25rem;
                }
            }
        
        body {
            font-family: 'Noto Sans', sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
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

                <button id="mobileMenuToggle" aria-label="Mobile menu" class="text-white cursor-pointer hidden max-sm:block">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

            </header>

            <!-- Mobile menu - hidden by default -->
            <div id="mobileMenu" class="hidden bg-[#0f172a] rounded-xl mb-6 py-4 px-6 sm:hidden">
                <nav class="flex flex-col gap-4">
                    <a href="home.php" class="text-base text-white hover:text-green-200 transition-colors py-2">Home</a>
                    <a href="shop.php" class="text-base text-white hover:text-green-200 transition-colors py-2">Shop</a>
                    <a href="about.php" class="text-base text-white hover:text-green-200 transition-colors py-2">About Us</a>
                </nav>
            </div>

            <div class="flex gap-10 px-20 py-0 max-md:flex-col max-md:px-10 max-md:py-0 max-sm:px-4 max-sm:py-0">
                <section class="flex-1">
                    <h2 class="mb-6 text-7xl font-bold uppercase leading-[80px] text-slate-900 tracking-[2px] max-md:text-4xl max-md:leading-[60px] max-sm:text-3xl max-sm:leading-10 max-sm:mb-4 heading-font">
                        Find Your Future Fashion Today
                    </h2>
            
                    <p class="mb-14 text-base leading-6 max-w-[625px] text-neutral-600 max-sm:mb-8">
                        Dress our latest collection, curated for trendsetters seeking chic and timeless style. Elevate your wardrobe today!
                    </p>
            
                    <div class="flex gap-4 mb-20 max-sm:flex-col max-sm:mb-10">
                        <button class="px-8 py-5 text-xl font-semibold text-white uppercase bg-[#0f172a] hover:bg-gray-800 transition-colors cursor-pointer rounded-[100px] max-sm:py-4">
                            Buy Now
                        </button>
                        <button aria-label="Learn more" class="flex justify-center items-center w-16 h-16 bg-white hover:bg-gray-50 transition-colors rounded-full cursor-pointer max-sm:self-start">
                            <i class="ti ti-arrow-up-right text-xl text-slate-900">↗</i>
                        </button>
                    </div>
            
                    <section class="flex gap-10 max-sm:gap-4 max-sm:flex-wrap">
                        <article class="text-center max-sm:flex-1 max-sm:min-w-[130px]">
                            <p class="text-5xl font-bold tracking-widest leading-[60px] text-neutral-800 max-sm:text-3xl max-sm:leading-10">20+</p>
                            <p class="text-base uppercase text-neutral-600 max-sm:text-sm">Years Of experience</p>
                        </article>
            
                        <article class="text-center max-sm:flex-1 max-sm:min-w-[130px]">
                            <p class="text-5xl font-bold tracking-widest leading-[60px] text-neutral-800 max-sm:text-3xl max-sm:leading-10">21K+</p>
                            <p class="text-base uppercase text-neutral-600 max-sm:text-sm">Happy Customers</p>
                        </article>
            
                        <article class="text-center max-sm:flex-1 max-sm:min-w-[130px]">
                            <p class="text-5xl font-bold tracking-widest leading-[60px] text-neutral-800 max-sm:text-3xl max-sm:leading-10">150+</p>
                            <p class="text-base uppercase text-neutral-600 max-sm:text-sm">Product brand</p>
                        </article>
                    </section>
                </section>
            
                <section class="relative flex-1 max-md:mt-10">
                    <div class="overflow-hidden rounded-3xl bg-neutral-400 h-[700px] max-sm:h-[400px] max-sm:mx-auto">
                        <img src="assets/background/faceMask2.jpg" alt="Fashion model" class="object-cover w-full h-full border-0 hover:scale-105 transition-transform duration-500">
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
                                    
                        <div class="flex flex-wrap justify-between items-center gap-6 w-full text-white max-sm:gap-4">
                            <div class="flex-1 text-center px-3 min-w-[150px]">
                                <h2 class="text-3xl md:text-4xl font-bold uppercase tracking-widest max-sm:text-xl max-sm:tracking-wide">Fashion Week</h2>
                            </div>
                            
                            <img src="assets/divider.png" alt="Divider" class="hidden md:block h-20 object-contain">
                            
                            <div class="flex-1 text-center px-3 min-w-[150px]">
                                <h2 class="text-3xl md:text-4xl font-bold uppercase tracking-widest max-sm:text-xl max-sm:tracking-wide">Fashion Award</h2>
                            </div>
                            
                            <img src="assets/divider.png" alt="Divider" class="hidden md:block h-20 object-contain">
                            
                            <div class="flex-1 text-center px-3 min-w-[150px]">
                                <h2 class="text-3xl md:text-4xl font-bold uppercase tracking-widest max-sm:text-xl max-sm:tracking-wide">Fashion Show</h2>
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
                <div class="grid grid-cols-12 gap-6 mt-10 max-sm:gap-4">
                    <!-- Featured large product - spans full width on mobile -->
                    <article class="col-span-4 row-span-2 bg-white rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl max-md:col-span-12">
                        <div class="relative h-[800px] max-md:h-[500px] max-sm:h-[400px]">
                            <img
                                src="assets/hot_seller/bee.jpg"
                                alt="Pink suit jacket"
                                class="absolute inset-0 w-full h-full object-center object-cover"
                            />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-6 max-sm:p-4">
                                <div class="flex justify-between items-end">
                                    <h2 class="text-2xl font-bold text-white uppercase max-sm:text-xl">
                                        Arachno-Bot
                                    </h2>
                                    <p class="text-4xl font-bold text-white max-sm:text-3xl">
                                        $670
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                
                    <!-- Medium product - spans full width on mobile -->
                    <article class="col-span-8 bg-white rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl max-md:col-span-12">
                        <div class="relative h-[380px] max-md:h-[400px] max-sm:h-[350px]">
                            <img
                                src="assets/hot_seller/VM8.jpg"
                                alt="White hoodie"
                                class="absolute inset-0 w-full h-full object-cover"
                            />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4">
                                <div class="flex justify-between items-end">
                                    <h2 class="text-xl font-bold text-white uppercase max-sm:text-lg">
                                        Titanfall Unit
                                    </h2>
                                    <p class="text-3xl font-bold text-white max-sm:text-2xl">
                                        $899
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                
                    <!-- Small products - span 6 columns each on small tablets -->
                    <article class="col-span-4 bg-white rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl max-md:col-span-6 max-sm:col-span-12">
                        <div class="relative h-[400px] max-md:h-[350px] max-sm:h-[320px]">
                            <img
                                src="assets/hot_seller/shoeS4.jpg"
                                alt="Blue cowboy hat"
                                class="absolute inset-0 w-full h-full object-cover"
                            />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4">
                                <div class="flex justify-between items-end">
                                    <h2 class="text-lg font-bold text-white uppercase max-sm:text-base">
                                        Synapse Sneakers
                                    </h2>
                                    <p class="text-2xl font-bold text-white max-sm:text-xl">
                                        $650
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                
                    <article class="col-span-4 bg-white rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl max-md:col-span-6 max-sm:col-span-12">
                        <div class="relative h-[400px] max-md:h-[350px] max-sm:h-[320px]">
                            <img
                                src="assets/hot_seller/talonB4.jpg"
                                alt="Black shirt"
                                class="absolute inset-0 w-full h-full object-cover"
                            />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-4">
                                <div class="flex justify-between items-end">
                                    <h2 class="text-lg font-bold text-white uppercase max-sm:text-base">
                                        Crystalline Shard Heels
                                    </h2>
                                    <p class="text-2xl font-bold text-white max-sm:text-xl">
                                        $306
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
    <script>
        // Mobile menu toggle functionality
                document.getElementById('mobileMenuToggle').addEventListener('click', function() {
                    document.getElementById('mobileMenu').classList.toggle('hidden');
                });

                                // Add this to your existing script section
                document.addEventListener('DOMContentLoaded', function() {
                    // Mobile menu toggle functionality
                    document.getElementById('mobileMenuToggle').addEventListener('click', function() {
                        document.getElementById('mobileMenu').classList.toggle('hidden');
                    });
                    
                    // Add active state for touch feedback
                    const buttons = document.querySelectorAll('button, a');
                    buttons.forEach(button => {
                        button.addEventListener('touchstart', function() {
                            this.classList.add('active-touch');
                        });
                        button.addEventListener('touchend', function() {
                            this.classList.remove('active-touch');
                        });
                    });
                });
    </script>

</body>
</html>
