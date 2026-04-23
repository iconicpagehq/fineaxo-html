// Initialize Lucide icons (safe)
if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
}

// Mobile Menu Toggle
const mobileBtn = document.querySelector('.mobile-menu-btn');
const navLinks = document.querySelector('.nav-links');

if (mobileBtn && navLinks) {
    mobileBtn.addEventListener('click', () => {
        navLinks.classList.toggle('active');

        if (navLinks.classList.contains('active')) {
            mobileBtn.innerHTML = '<i data-lucide="x"></i>';
        } else {
            mobileBtn.innerHTML = '<i data-lucide="menu"></i>';
        }

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    });
}

// Close mobile menu when a nav link is clicked
const navItems = document.querySelectorAll('.nav-links a');
navItems.forEach(item => {
    item.addEventListener('click', () => {
        if (navLinks && navLinks.classList.contains('active')) {
            navLinks.classList.remove('active');
            if (mobileBtn) {
                mobileBtn.innerHTML = '<i data-lucide="menu"></i>';
            }
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        }
    });
});

if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
}

// Navbar scroll effect
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    if (!navbar) return;
    if (window.scrollY > 50) navbar.classList.add('scrolled');
    else navbar.classList.remove('scrolled');
});

// Scroll Reveal Animation
const revealElements = document.querySelectorAll('.reveal');

const revealOnScroll = () => {
    const windowHeight = window.innerHeight;
    const elementVisible = 150;

    revealElements.forEach((element) => {
        const elementTop = element.getBoundingClientRect().top;

        if (elementTop < windowHeight - elementVisible) {
            element.classList.add('active');
        }
    });
};

// Trigger reveal on load and scroll
window.addEventListener('load', revealOnScroll);
window.addEventListener('scroll', revealOnScroll);

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        
        const targetId = this.getAttribute('href');
        const targetElement = document.querySelector(targetId);
        
        if (targetElement) {
            const navbarHeight = document.querySelector('.navbar').offsetHeight;
            const targetPosition = targetElement.getBoundingClientRect().top + window.scrollY - navbarHeight;
            
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
        }
    });
});

// Video cards play/pause (reels + testimonials)
const videoCards = document.querySelectorAll('.reel-card, .video-card');

const pauseAllReels = (exceptVideo) => {
    videoCards.forEach(card => {
        const video = card.querySelector('.reel-video, .video-embed');
        if (!video) return;
        if (exceptVideo && video === exceptVideo) return;
        video.pause();
        card.classList.remove('is-playing');
    });
};

videoCards.forEach(card => {
    const video = card.querySelector('.reel-video, .video-embed');
    const playBtn = card.querySelector('.play-btn');
    if (!video || !playBtn) return;

    // Avoid autoplay; ensure overlay visible initially
    card.classList.remove('is-playing');

    const togglePlay = async () => {
        if (video.paused) {
            pauseAllReels(video);
            try {
                await video.play();
                card.classList.add('is-playing');
            } catch (err) {
                // Autoplay restrictions or blocked play; keep overlay visible
                card.classList.remove('is-playing');
            }
        } else {
            video.pause();
            card.classList.remove('is-playing');
        }
    };

    playBtn.addEventListener('click', togglePlay);
    video.addEventListener('click', togglePlay);

    video.addEventListener('pause', () => {
        card.classList.remove('is-playing');
    });
    video.addEventListener('ended', () => {
        card.classList.remove('is-playing');
    });
});

// Founder Cards — scroll-triggered fade-in
(function () {
    const founderCards = document.querySelectorAll('.founder-card');
    if (!founderCards.length) return;

    const observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15 }
    );

    founderCards.forEach(function (card) { observer.observe(card); });
})();
