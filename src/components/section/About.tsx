import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import AsciiMorphText from '../AsciiMorphText';
import TypewriterCarousel from '../TypewriterCarousel';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors, withAlpha } from '../../hooks/useThemeColors';
import { profileHero, profileHeroWebp, stickers as stickerImages } from '../../assets';


const About = () => {
  const [showProfileModal, setShowProfileModal] = useState(false);
  const [isClosing, setIsClosing] = useState(false);
  const [currentImageIndex, setCurrentImageIndex] = useState(0);
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  const roles = [
    'Full-Stack Developer',
    'Graphic Designer',
    'UI/UX Designer',
    'Web Developer',
  ];

  const profileImages = [
    { src: profileHero, caption: "Nasteho Jelle Hassan" }
  ];

  const bio = [
    "I’m a Full Stack Developer and Computer Science graduate with a passion for building modern, user-friendly web applications that solve real-world problems.",
    "I enjoy turning ideas into functional digital products using technologies like JavaScript, React, Node.js, PHP, MySQL, and MongoDB. I’m always learning new tools and improving my skills to create fast, secure, and scalable solutions.",
    "I believe technology should make life easier, which is why I focus on writing clean code, designing intuitive user experiences, and building applications that have a meaningful impact.",
    "When I’m not coding, you’ll find me exploring new technologies, working on personal projects, improving my English, or learning more about software engineering and AI.",
  ];

  // Decorative stickers floating around the hero photo
  const heroStickers = [
    { image: stickerImages[0], top: '-6%', left: '-8%', size: 64, delay: '0s' },
    { image: stickerImages[3], top: '4%', right: '-10%', size: 56, delay: '1.2s' },
    { image: stickerImages[7], bottom: '-4%', left: '-6%', size: 60, delay: '0.6s' },
    { image: stickerImages[10], bottom: '6%', right: '-8%', size: 52, delay: '1.8s' },
  ];

  // Focus management for modal
  useEffect(() => {
    if (showProfileModal) {
      const timer = setTimeout(() => {
        const modal = document.querySelector('[role="region"][aria-label="Profile photo"]') as HTMLElement;
        if (modal) {
          modal.focus();
        }
      }, 100);
      return () => clearTimeout(timer);
    }
  }, [showProfileModal]);

  // Carousel navigation functions
  const goToPrevious = () => {
    setCurrentImageIndex((prevIndex) =>
      prevIndex === 0 ? profileImages.length - 1 : prevIndex - 1
    );
  };

  const goToNext = () => {
    setCurrentImageIndex((prevIndex) =>
      prevIndex === profileImages.length - 1 ? 0 : prevIndex + 1
    );
  };

  const goToSlide = (index: number) => {
    setCurrentImageIndex(index);
  };

  const closeModal = () => {
    setIsClosing(true);
    setTimeout(() => {
      setShowProfileModal(false);
      setIsClosing(false);
    }, 300);
  };

  // Keyboard navigation
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowLeft') {
      goToPrevious();
    } else if (e.key === 'ArrowRight') {
      goToNext();
    } else if (e.key === 'Escape') {
      closeModal();
    }
  };

  return (
    <section id="about" className="min-h-screen" style={{
      background: themeColors.background.sections?.about || themeColors.background.gradient,
      transition: 'background 0.3s ease-in-out',
      width: '100%',
      maxWidth: '100vw',
      contain: 'layout style'
    }}>
      {/* Hero Section */}
      <div className="py-14 md:py-24">
        <div className="container mx-auto px-4 md:px-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-center max-w-6xl mx-auto">
            {/* Hero Photo */}
            <div className="flex justify-center lg:justify-start order-1">
              <div className="relative w-full max-w-sm md:max-w-md">
                <div
                  className="absolute -inset-6 md:-inset-8 rounded-[2.5rem] blur-2xl hero-photo-ring"
                  style={{
                    background: isDarkMode
                      ? `conic-gradient(from 90deg, ${withAlpha(themeColors.colors.pink[300], 0.35)}, ${withAlpha(themeColors.colors.pink[500], 0.15)}, ${withAlpha(themeColors.colors.pink[300], 0.35)})`
                      : `conic-gradient(from 90deg, ${withAlpha(themeColors.colors.pink[300], 0.5)}, ${withAlpha(themeColors.colors.pink[200], 0.25)}, ${withAlpha(themeColors.colors.pink[300], 0.5)})`,
                    opacity: 0.8
                  }}
                  aria-hidden="true"
                />
                {heroStickers.map((sticker, index) => (
                  <img
                    key={index}
                    src={sticker.image}
                    alt=""
                    aria-hidden="true"
                    loading="lazy"
                    decoding="async"
                    className="absolute hero-float-sticker pointer-events-none select-none hidden sm:block"
                    style={{
                      top: sticker.top,
                      left: sticker.left,
                      right: sticker.right,
                      bottom: sticker.bottom,
                      width: sticker.size,
                      height: sticker.size,
                      animationDelay: sticker.delay,
                      filter: `drop-shadow(0 4px 10px ${themeColors.effects.dropShadow})`,
                      zIndex: 3
                    }}
                  />
                ))}
                <picture>
                  <source srcSet={profileHeroWebp} type="image/webp" />
                  <img
                    src={profileHero}
                    alt="Nasteho Jelle Hassan — Full Stack Developer and Graphic Designer"
                    onClick={() => setShowProfileModal(true)}
                    className="hero-photo-in relative w-full h-auto rounded-[2rem] object-cover cursor-pointer shadow-2xl"
                    style={{
                      zIndex: 2,
                      border: `4px solid ${isDarkMode ? withAlpha(themeColors.colors.pink[300], 0.25) : themeColors.colors.white}`,
                      aspectRatio: '700 / 854'
                    }}
                    width="700"
                    height="854"
                    fetchPriority="high"
                    loading="eager"
                  />
                </picture>
                {/* Availability badge */}
                <div
                  className="hero-float-sticker absolute -bottom-4 right-4 sm:right-8 flex items-center gap-2 rounded-full px-4 py-2 shadow-lg"
                  style={{
                    zIndex: 4,
                    animationDelay: '0.3s',
                    backgroundColor: isDarkMode ? withAlpha(themeColors.colors.dark[800], 0.95) : themeColors.colors.white,
                    border: `1px solid ${themeColors.colors.pink[200]}`
                  }}
                >
                  <span className="relative flex h-2.5 w-2.5">
                    <span
                      className="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"
                      style={{ backgroundColor: themeColors.colors.pink[400] }}
                    />
                    <span
                      className="relative inline-flex rounded-full h-2.5 w-2.5"
                      style={{ backgroundColor: themeColors.colors.pink[500] }}
                    />
                  </span>
                  <span
                    className="text-xs md:text-sm font-semibold"
                    style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[700] }}
                  >
                    Open to work
                  </span>
                </div>
              </div>
            </div>

            {/* Intro */}
            <div className="text-left order-2 animate-fadeIn">
              <div className="ascii-container justify-start text-3xl md:text-4xl lg:text-5xl">
                <AsciiMorphText text="Nasteho Jelle" />
              </div>
              <div className="hero-subtitle justify-start text-base md:text-lg lg:text-xl mt-2">
                <div className="flex flex-wrap items-center justify-start">
                  <span className={isDarkMode ? 'hero-subtitle-dark' : 'hero-subtitle-light'}>I am a&nbsp;</span>
                  <TypewriterCarousel roles={roles} className={isDarkMode ? 'hero-subtitle-dark' : 'hero-subtitle-light'} />
                </div>
              </div>
              <div className="mt-5 space-y-3 max-w-xl">
                <p
                  className="text-lg md:text-xl font-bold"
                  style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600] }}
                >
                  Hi!
                </p>
                {bio.map((paragraph, index) => (
                  <p
                    key={index}
                    className="text-sm md:text-base leading-relaxed"
                    style={{ color: isDarkMode ? themeColors.colors.dark[200] : themeColors.colors.dark[600] }}
                  >
                    {paragraph}
                  </p>
                ))}
                <p
                  className="text-sm md:text-base font-semibold leading-relaxed"
                  style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600] }}
                >
                  Let’s build something amazing together.
                </p>
              </div>
              <div className="hero-buttons flex flex-wrap justify-start gap-3 mt-5">
                <button
                  className="hero-action-btn text-sm md:text-base px-4 py-2 md:px-5 md:py-2.5"
                  onClick={() => {
                    window.open('/resume.pdf', '_blank');
                  }}
                >
                  Resume →
                </button>
                <Link
                  to="/contact"
                  className="hero-action-btn text-sm md:text-base px-4 py-2 md:px-5 md:py-2.5"
                >
                  Contact →
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Profile Modal */}
      {showProfileModal && (
        <div
          className={`fixed inset-0 z-50 flex items-center justify-center p-2 md:p-4 ${isClosing ? 'animate-fadeOut' : 'animate-fadeIn'}`}
          style={{ backgroundColor: themeColors.background.overlay }}
          onClick={closeModal}
          onKeyDown={handleKeyDown}
          tabIndex={-1}
        >
          <div className={`relative w-full max-w-sm md:max-w-md ${isClosing ? 'animate-scaleOut' : 'animate-scaleIn'}`} onClick={(e) => e.stopPropagation()}>
            {/* Carousel Container */}
            <div
              className="relative w-full bg-black rounded-lg shadow-2xl overflow-hidden focus:outline-none"
              style={{
                aspectRatio: '4/5',
                minHeight: '300px',
                maxHeight: '80vh'
              }}
              role="region"
              aria-label="Profile photo"
              aria-live="polite"
              tabIndex={0}
              onKeyDown={handleKeyDown}
            >
              {/* Image Display */}
              <div className="relative w-full h-full flex items-center justify-center">
                {profileImages.map((image, index) => (
                  <img
                    key={index}
                    src={image.src}
                    alt={`Profile photo ${index + 1}`}
                    className={`absolute w-full h-full object-contain transition-opacity duration-500 ${
                      index === currentImageIndex ? 'opacity-100' : 'opacity-0'
                    }`}
                    loading="eager"
                    onError={(e) => {
                      console.error('Image failed to load:', image.src);
                      e.currentTarget.style.display = 'block';
                      e.currentTarget.style.backgroundColor = '#f3f4f6';
                    }}
                  />
                ))}
              </div>

              {profileImages.length > 1 && (
                <>
                  {/* Navigation Arrows */}
                  <button
                    onClick={goToPrevious}
                    className="absolute left-4 top-1/2 -translate-y-1/2 p-3 rounded-full shadow-lg transition-all hover:scale-110 focus:outline-none focus:ring-2 focus:ring-pink-300"
                    style={{
                      backgroundColor: isDarkMode ? withAlpha(themeColors.colors.dark[700], 0.9) : withAlpha(themeColors.colors.white, 0.8),
                      color: isDarkMode ? themeColors.colors.white : themeColors.colors.dark[700],
                      border: isDarkMode ? '2px solid #374151' : 'none',
                      boxShadow: isDarkMode ? `0 4px 12px ${withAlpha(themeColors.colors.black, 0.6)}` : undefined
                    }}
                    aria-label="Previous image"
                  >
                    <ChevronLeft className="h-6 w-6" />
                  </button>

                  <button
                    onClick={goToNext}
                    className="absolute right-4 top-1/2 -translate-y-1/2 p-3 rounded-full shadow-lg transition-all hover:scale-110 focus:outline-none focus:ring-2 focus:ring-pink-300"
                    style={{
                      backgroundColor: isDarkMode ? withAlpha(themeColors.colors.dark[700], 0.9) : withAlpha(themeColors.colors.white, 0.8),
                      color: isDarkMode ? themeColors.colors.white : themeColors.colors.dark[700],
                      border: isDarkMode ? '2px solid #374151' : 'none',
                      boxShadow: isDarkMode ? `0 4px 12px ${withAlpha(themeColors.colors.black, 0.6)}` : undefined
                    }}
                    aria-label="Next image"
                  >
                    <ChevronRight className="h-6 w-6" />
                  </button>

                  {/* Image Counter */}
                  <div className="absolute bottom-4 left-4 bg-black/50 text-white px-3 py-1 rounded-full text-sm">
                    {currentImageIndex + 1} / {profileImages.length}
                  </div>
                </>
              )}

              {/* Caption */}
              <div className="absolute bottom-4 right-4 bg-black/70 text-white px-4 py-2 rounded-lg text-base font-medium max-w-[220px] text-center">
                {profileImages[currentImageIndex].caption}
              </div>
            </div>

            {/* Dots Indicator */}
            {profileImages.length > 1 && (
              <div className="flex justify-center gap-0 mt-4">
                {profileImages.map((_, index) => (
                  <button
                    key={index}
                    onClick={() => goToSlide(index)}
                    className="transition-all focus:outline-none focus:ring-2 focus:ring-pink-300 focus:ring-offset-2 flex items-center justify-center"
                    style={{
                      minWidth: '44px',
                      minHeight: '44px',
                      padding: '0',
                      backgroundColor: 'transparent',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center'
                    }}
                    aria-label={`Go to image ${index + 1}`}
                  >
                    <span
                      className="rounded-full transition-all"
                      style={{
                        width: index === currentImageIndex ? '32px' : '12px',
                        height: '12px',
                        backgroundColor: index === currentImageIndex ? themeColors.colors.pink[300] : (isDarkMode ? withAlpha(themeColors.colors.pink[300], 0.3) : themeColors.colors.dark[300])
                      }}
                    />
                  </button>
                ))}
              </div>
            )}

            {/* Close Button */}
            <button
              className="absolute top-4 right-4 text-white rounded-full w-11 h-11 flex items-center justify-center transition-all duration-300 hover:scale-110 hover:rotate-90"
              style={{
                backgroundColor: themeColors.colors.pink[500],
                transition: 'all 0.3s'
              }}
              onMouseEnter={(e) => e.currentTarget.style.backgroundColor = themeColors.colors.pink[600]}
              onMouseLeave={(e) => e.currentTarget.style.backgroundColor = themeColors.colors.pink[500]}
              aria-label="Close modal"
              onClick={(e) => {
                e.stopPropagation();
                closeModal();
              }}
            >
              ✕
            </button>
          </div>
        </div>
      )}
    </section>
  );
};

export default About;
