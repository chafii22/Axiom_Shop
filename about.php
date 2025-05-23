<?php

require_once 'config/connect_db.php';
include_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | Axiom</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;700&family=Noto+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'space': ['"Space Grotesk"', 'sans-serif'],
                        'noto': ['"Noto Sans"', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Noto Sans', sans-serif;
            background-color: #0f172a;
            color: #ffffff;
        }

        h1, h2, h3, h4 {
            font-family: 'Space Grotesk', sans-serif;
        }

        /* Grid background pattern for all sections */
        .grid-bg {
            background-color: #0f172a;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 20px 20px;
            position: relative;
        }

        /* Hero section styling */
        .hero-section {
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(187, 246, 190, 0.2), transparent 70%);
            z-index: 1;
        }

        .hero-overlay {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(3px);
        }

        /* Different colored overlays for visual section separation */
        .philosophy-overlay {
            background: linear-gradient(to bottom right, rgba(15, 23, 42, 0.8), rgba(30, 41, 59, 0.8));
        }
        
        .tech-overlay {
            background: linear-gradient(to right, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.5));
        }

        .glass-card {
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(148, 163, 184, 0.1);
        }

        .award-icon {
            color: #bbf6be;
        }

        .gradient-text {
            background: linear-gradient(45deg, #bbf6be, #86efac);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gradient-purple {
            background: linear-gradient(45deg, #059669, #10b981);
        }

        .accent-color {
            color: #bbf6be;
        }

        .scroll-reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .scroll-reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section grid-bg h-screen flex items-center justify-center">
        <div class="hero-overlay absolute inset-0"></div>
        <div class="container mx-auto px-6 z-10 text-center">
            <h1 class="text-6xl md:text-7xl font-bold mb-6 tracking-tighter">About <span class="gradient-text">Axiom</span></h1>
            <p class="text-2xl md:text-3xl max-w-3xl mx-auto font-light">Redefining the Future of Fashion</p>
            <div class="mt-10">
                <a href="#vision" class="animate-bounce inline-block">
                    <i class="fas fa-chevron-down text-3xl opacity-80"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Vision Section -->
    <section id="vision" class="py-24 bg-black">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto text-center scroll-reveal">
                <h2 class="text-4xl font-bold mb-8">Our Vision</h2>
                <p class="text-xl leading-relaxed text-gray-300">
                    In a world where digital and physical realities converge, fashion becomes the ultimate medium for self-transformation. 
                    We envision a future where every garment tells a story, where style becomes a superpower, 
                    and where the boundaries between fashion and technology dissolve completely.
                </p>
            </div>
        </div>
    </section>

    <!-- Philosophy Section -->
    <section class="grid-bg py-24 relative">
        <div class="philosophy-overlay absolute inset-0"></div>
        <div class="container mx-auto px-6 relative z-10">
            <h2 class="text-4xl font-bold mb-12 text-center scroll-reveal">The Axiom Philosophy</h2>
            
            <div class="grid md:grid-cols-3 gap-10">
                <div class="glass-card p-8 rounded-xl scroll-reveal" style="transition-delay: 0.1s">
                    <h3 class="text-2xl font-bold mb-4">Futuristic Craftsmanship</h3>
                    <p class="text-gray-300">
                        Every piece in our collection represents hours of meticulous design, combining traditional craftsmanship 
                        with revolutionary materials and techniques. We work with bio-responsive fabrics, LED-integrated textiles, 
                        and smart materials that adapt to your environment.
                    </p>
                </div>
                
                <div class="glass-card p-8 rounded-xl scroll-reveal" style="transition-delay: 0.3s">
                    <h3 class="text-2xl font-bold mb-4">Sustainable Innovation</h3>
                    <p class="text-gray-300">
                        The future demands responsibility. Our commitment to sustainability drives us to develop eco-friendly 
                        production methods, recyclable smart materials, and designs built to last generations, not seasons.
                    </p>
                </div>
                
                <div class="glass-card p-8 rounded-xl scroll-reveal" style="transition-delay: 0.5s">
                    <h3 class="text-2xl font-bold mb-4">Individual Expression</h3>
                    <p class="text-gray-300">
                        Fashion is personal revolution. We create pieces that empower individuals to express their unique identity 
                        while embracing the collective human journey toward tomorrow.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Story Section -->
    <section class="py-24 bg-black">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row">
                <div class="md:w-1/2 scroll-reveal">
                    <h2 class="text-4xl font-bold mb-8">Our Story</h2>
                    <p class="text-lg leading-relaxed text-gray-300 mb-6">
                        Founded in 2024 by a collective of fashion designers, technologists, and futurists, Axiom emerged from a simple question: 
                        "What would fashion look like if we designed it for the world we're building, not the world we inherited?"
                    </p>
                    <p class="text-lg leading-relaxed text-gray-300">
                        Our breakthrough came with the development of our signature <span class="font-bold text-white">NeuroWeave™</span> technology – 
                        fabrics that respond to biometric data, environmental changes, and personal preferences. 
                        This innovation marked the beginning of truly intelligent clothing.
                    </p>
                </div>
                <div class="md:w-1/2 md:pl-12 mt-8 md:mt-0 flex items-center justify-center scroll-reveal" style="transition-delay: 0.3s">
                    <div class="h-80 w-full glass-card rounded-xl overflow-hidden relative grid-bg">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="glass-card p-4 rounded-lg">
                                <i class="fas fa-lightbulb text-4xl text-purple-400"></i>
                                <p class="mt-2 text-xl font-space">Innovation is our core</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tech Section -->
    <section class="grid-bg py-24 relative">
        <div class="tech-overlay absolute inset-0"></div>
        <div class="container mx-auto px-6 relative z-10">
            <h2 class="text-4xl font-bold mb-12 scroll-reveal">What Sets Us Apart</h2>
            
            <div class="grid md:grid-cols-3 gap-10">
                <div class="glass-card p-8 rounded-xl scroll-reveal" style="transition-delay: 0.1s">
                    <h3 class="text-2xl font-bold mb-4">Technology Integration</h3>
                    <ul class="space-y-3 text-gray-200">
                        <li class="flex items-start">
                            <i class="fas fa-microchip mt-1 mr-3 text-purple-400"></i>
                            <span>Smart fabrics that adapt to temperature and mood</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb mt-1 mr-3 text-purple-400"></i>
                            <span>Integrated LED systems for dynamic visual effects</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-mobile-alt mt-1 mr-3 text-purple-400"></i>
                            <span>App-controlled customization features</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-heartbeat mt-1 mr-3 text-purple-400"></i>
                            <span>Biometric response capabilities</span>
                        </li>
                    </ul>
                </div>
                
                <div class="glass-card p-8 rounded-xl scroll-reveal" style="transition-delay: 0.3s">
                    <h3 class="text-2xl font-bold mb-4">Design Philosophy</h3>
                    <ul class="space-y-3 text-gray-200">
                        <li class="flex items-start">
                            <i class="fas fa-city mt-1 mr-3 text-purple-400"></i>
                            <span>Cyberpunk-inspired aesthetics</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-cubes mt-1 mr-3 text-purple-400"></i>
                            <span>Modular, interchangeable components</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-users mt-1 mr-3 text-purple-400"></i>
                            <span>Gender-neutral, inclusive sizing</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-gem mt-1 mr-3 text-purple-400"></i>
                            <span>Limited edition collections for exclusivity</span>
                        </li>
                    </ul>
                </div>
                
                <div class="glass-card p-8 rounded-xl scroll-reveal" style="transition-delay: 0.5s">
                    <h3 class="text-2xl font-bold mb-4">Community Focus</h3>
                    <ul class="space-y-3 text-gray-200">
                        <li class="flex items-start">
                            <i class="fas fa-hands-helping mt-1 mr-3 text-purple-400"></i>
                            <span>Direct collaboration with customers on custom pieces</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-users-cog mt-1 mr-3 text-purple-400"></i>
                            <span>Regular designer meetups and workshops</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-seedling mt-1 mr-3 text-purple-400"></i>
                            <span>Sustainability education programs</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-calendar-alt mt-1 mr-3 text-purple-400"></i>
                            <span>Digital fashion week participation</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="py-24 bg-black">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl font-bold mb-12 scroll-reveal">The Team</h2>
            <div class="max-w-4xl mx-auto scroll-reveal" style="transition-delay: 0.2s">
                <p class="text-xl leading-relaxed text-gray-300">
                    Our diverse team includes former engineers from leading tech companies, fashion designers from prestigious houses, 
                    sustainability experts, and digital artists. Together, we represent over 150 years of combined experience in 
                    pushing boundaries and challenging conventions.
                </p>
                <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-6">
                    <!-- Team placeholders with icons instead of images -->
                    <div class="glass-card h-40 rounded-lg flex flex-col items-center justify-center grid-bg">
                        <i class="fas fa-code text-2xl text-purple-400 mb-2"></i>
                        <p class="font-bold">Lead Engineer</p>
                        <p class="text-sm text-gray-400">Smart Textile Expert</p>
                    </div>
                    <div class="glass-card h-40 rounded-lg flex flex-col items-center justify-center grid-bg">
                        <i class="fas fa-palette text-2xl text-purple-400 mb-2"></i>
                        <p class="font-bold">Creative Director</p>
                        <p class="text-sm text-gray-400">Fashion Futurist</p>
                    </div>
                    <div class="glass-card h-40 rounded-lg flex flex-col items-center justify-center grid-bg">
                        <i class="fas fa-leaf text-2xl text-purple-400 mb-2"></i>
                        <p class="font-bold">Sustainability Lead</p>
                        <p class="text-sm text-gray-400">Material Innovator</p>
                    </div>
                    <div class="glass-card h-40 rounded-lg flex flex-col items-center justify-center grid-bg">
                        <i class="fas fa-users text-2xl text-purple-400 mb-2"></i>
                        <p class="font-bold">Community Manager</p>
                        <p class="text-sm text-gray-400">Digital Experience</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Awards Section -->
    <section class="py-24 grid-bg relative">
        <div class="absolute inset-0 bg-black bg-opacity-90"></div>
        <div class="container mx-auto px-6 relative z-10">
            <h2 class="text-4xl font-bold mb-12 text-center scroll-reveal">Awards & Recognition</h2>
            
            <div class="grid md:grid-cols-4 gap-8">
                <div class="text-center p-6 glass-card rounded-xl scroll-reveal" style="transition-delay: 0.1s">
                    <i class="fas fa-award text-5xl mb-4 award-icon"></i>
                    <h3 class="text-xl font-bold mb-2">2024 Innovation Award</h3>
                    <p class="text-gray-400">Future Fashion Institute</p>
                </div>
                
                <div class="text-center p-6 glass-card rounded-xl scroll-reveal" style="transition-delay: 0.2s">
                    <i class="fas fa-leaf text-5xl mb-4 award-icon"></i>
                    <h3 class="text-xl font-bold mb-2">Best Sustainable Design</h3>
                    <p class="text-gray-400">Green Fashion Awards</p>
                </div>
                
                <div class="text-center p-6 glass-card rounded-xl scroll-reveal" style="transition-delay: 0.3s">
                    <i class="fas fa-microchip text-5xl mb-4 award-icon"></i>
                    <h3 class="text-xl font-bold mb-2">Technology Integration</h3>
                    <p class="text-gray-400">Wearable Tech Summit</p>
                </div>
                
                <div class="text-center p-6 glass-card rounded-xl scroll-reveal" style="transition-delay: 0.4s">
                    <i class="fas fa-star text-5xl mb-4 award-icon"></i>
                    <h3 class="text-xl font-bold mb-2">Featured Brand</h3>
                    <p class="text-gray-400">Neo Tokyo Fashion Week</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Commitment Section -->
    <section class="py-24 bg-black">
        <div class="container mx-auto px-6 max-w-4xl">
            <h2 class="text-4xl font-bold mb-8 text-center scroll-reveal">Our Commitment</h2>
            <div class="glass-card p-8 rounded-xl grid-bg scroll-reveal" style="transition-delay: 0.2s">
                <p class="text-xl leading-relaxed text-gray-300 mb-6">
                    Every Axiom piece comes with our <span class="font-bold text-white">Future Guarantee</span> – if technology advances make your 
                    garment obsolete, we'll upgrade it or offer full credit toward a new piece. We believe in clothing that evolves with 
                    you and the world around us.
                </p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 gradient-purple">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl font-bold mb-6 scroll-reveal">Join the Revolution</h2>
            <div class="max-w-3xl mx-auto scroll-reveal" style="transition-delay: 0.2s">
                <p class="text-xl leading-relaxed mb-10">
                    Fashion is changing. The question isn't whether you'll be part of the future – it's whether you'll help create it.
                    Ready to find your future fashion? Explore our collections and discover what it means to wear tomorrow, today.
                </p>
                <a href="shop.php" class="inline-block bg-white text-purple-900 px-8 py-3 rounded-lg font-bold text-lg hover:bg-gray-200 transition">
                    Explore Collections
                </a>
            </div>
        </div>
    </section>

    <!-- Connect Section -->
    <section class="py-24 grid-bg relative">
        <div class="absolute inset-0 bg-black bg-opacity-80"></div>
        <div class="container mx-auto px-6 relative z-10">
            <h2 class="text-4xl font-bold mb-12 text-center scroll-reveal">Connect With Us</h2>
            
            <div class="grid md:grid-cols-4 gap-8 max-w-4xl mx-auto">
                <div class="text-center glass-card p-5 rounded-xl scroll-reveal" style="transition-delay: 0.1s">
                    <i class="fab fa-instagram text-4xl mb-4 text-purple-400"></i>
                    <p>Follow our design process on social media</p>
                </div>
                
                <div class="text-center glass-card p-5 rounded-xl scroll-reveal" style="transition-delay: 0.2s">
                    <i class="fas fa-flask text-4xl mb-4 text-purple-400"></i>
                    <p>Join our exclusive beta testing program</p>
                </div>
                
                <div class="text-center glass-card p-5 rounded-xl scroll-reveal" style="transition-delay: 0.3s">
                    <i class="fas fa-envelope text-4xl mb-4 text-purple-400"></i>
                    <p>Subscribe to our monthly innovation newsletter</p>
                </div>
                
                <div class="text-center glass-card p-5 rounded-xl scroll-reveal" style="transition-delay: 0.4s">
                    <i class="fas fa-comments text-4xl mb-4 text-purple-400"></i>
                    <p>Book a consultation for custom pieces</p>
                </div>
            </div>
            
            <div class="text-center mt-16 scroll-reveal" style="transition-delay: 0.5s">
                <p class="text-2xl font-space italic">Axiom - Where Fashion Meets Future</p>
            </div>
        </div>
    </section>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        // Scroll reveal animation
        document.addEventListener('DOMContentLoaded', function() {
            const revealElements = document.querySelectorAll('.scroll-reveal');
            
            function reveal() {
                revealElements.forEach(element => {
                    const elementTop = element.getBoundingClientRect().top;
                    const windowHeight = window.innerHeight;
                    
                    if (elementTop < windowHeight - 100) {
                        element.classList.add('active');
                    }
                });
            }
            
            window.addEventListener('scroll', reveal);
            window.addEventListener('load', reveal);
        });
    </script>
</body>
</html>