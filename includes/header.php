<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting & Finance Professional</title>
    <meta name="description"
        content="Portfolio of a results-driven Accounting and Finance professional specializing in bookkeeping, financial reporting, and business analysis.">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <!-- CSS -->
    <link rel="stylesheet" href="style.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400..700&display=swap" rel="stylesheet">

    <style>
        .caveat-uniquifier {
                font-family: "Caveat", cursive;
                font-optical-sizing: auto;
                font-weight: <weight>;
                font-style: normal;
            }
    </style>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'brand': '#0d9488',
                        'brand-dark': '#0f766e',
                        'brand-light': '#2dd4bf',
                    },
                    fontFamily: {
                        heading: ['Outfit', 'sans-serif'],
                        body: ['Inter', 'sans-serif'],
                    }
                }
            },
            corePlugins: { preflight: false }
        }
    </script>
</head>

<body>
    <!-- Background Elements -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="grid-overlay"></div>

    <nav class="navbar">
        <div class="container nav-container">
        <a href="#" class="logo" aria-label="Fineaxa Solution home">
            <img src="logo.png" alt="Fineaxa Solution" class="logo-img" />
        </a>
            <ul class="nav-links">
                <li><a href="#about">About</a></li>
                <li><a href="#expertise">Expertise</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#why-us">Why Us</a></li>
                 <li><a href="#pricing">Pricing</a></li>
            </ul>
            <a href="#contact" class="btn btn-primary nav-cta">Contact Us</a>
            <button class="mobile-menu-btn"><i data-lucide="menu"></i></button>
        </div>
    </nav>

