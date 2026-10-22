import { lazy, Suspense } from 'react'
import { Routes, Route } from 'react-router-dom'
import { DarkModeProvider, useDarkMode } from './contexts/DarkModeContext'
import { useThemeColors } from './hooks/useThemeColors'
import { colors } from './styles/colors'
import Navigation from './components/section/Navigation'
import About from './components/section/About'
import { divider } from './assets'
import './App.css'

// Lazy load pages
const Contact = lazy(() => import('./pages/Contact'))
const ELMS = lazy(() => import('./pages/projects/ELMS'))
const SurveyManagementSystem = lazy(() => import('./pages/projects/SurveyManagementSystem'))

// Lazy load below-the-fold components for better initial load
const Journey = lazy(() => import('./components/section/Journey'))
const Education = lazy(() => import('./components/section/Education'))
const Experience = lazy(() => import('./components/section/Experience'))
const Services = lazy(() => import('./components/section/Services'))
const Projects = lazy(() => import('./components/section/Projects'))
const Skills = lazy(() => import('./components/section/Skills'))
const Certifications = lazy(() => import('./components/section/Certifications'))
const Achievements = lazy(() => import('./components/section/Achievements'))
const Languages = lazy(() => import('./components/section/Languages'))
const Testimonials = lazy(() => import('./components/section/Testimonials'))
const Resume = lazy(() => import('./components/section/Resume'))
const FAQ = lazy(() => import('./components/section/FAQ'))
const ContactCTA = lazy(() => import('./components/section/ContactCTA'))
const Footer = lazy(() => import('./components/Footer'))

function HomePage() {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <>
      <About />
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Journey />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Education />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Experience />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Services />
      </Suspense>
      <Suspense fallback={<div className="h-screen flex items-center justify-center">Loading...</div>}>
        <Projects />
      </Suspense>
      {/* Divider with gradient transitions */}
      <div className="w-full py-8 relative" style={{
        background: isDarkMode ? themeColors.background.gradientEnd : colors.white,
        transition: 'background 0.3s ease-in-out'
      }}>
        {/* Top gradient overlay to blend with Experience section */}
        <div
          className="absolute top-0 left-0 right-0 pointer-events-none"
          style={{
            height: '60px',
            background: isDarkMode
              ? `linear-gradient(180deg, ${themeColors.background.gradientEnd} 0%, transparent 100%)`
              : `linear-gradient(180deg, ${colors.white} 0%, transparent 100%)`,
            zIndex: 1
          }}
        />
        {/* Bottom gradient overlay to blend with Skills section */}
        <div 
          className="absolute bottom-0 left-0 right-0 pointer-events-none"
          style={{
            height: '200px',
            background: isDarkMode 
              ? `linear-gradient(180deg, transparent 0%, ${themeColors.background.gradientEnd} 100%)`
              : `linear-gradient(180deg, transparent 0%, ${themeColors.colors.pink[25]} 100%)`,
            zIndex: 1
          }}
        />
        <img
          src={divider}
          alt="Section divider"
          className="w-full h-auto relative"
          style={{
            zIndex: 2,
            filter: isDarkMode ? 'invert(1) hue-rotate(180deg)' : 'none',
            opacity: isDarkMode ? 0.7 : 1
          }}
          width="1200"
          height="100"
          loading="lazy"
        />
      </div>
      <Suspense fallback={<div className="h-screen flex items-center justify-center">Loading...</div>}>
        <Skills />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Certifications />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Achievements />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Languages />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Testimonials />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <Resume />
      </Suspense>
      <Suspense fallback={<div className="h-64 flex items-center justify-center">Loading...</div>}>
        <FAQ />
      </Suspense>
      <Suspense fallback={<div className="h-48 flex items-center justify-center">Loading...</div>}>
        <ContactCTA />
      </Suspense>
    </>
  )
}

function AppContent() {
  const { isDarkMode } = useDarkMode();

  return (
    <>
      <Navigation />
      <div className="app transition-colors duration-300" style={{ backgroundColor: isDarkMode ? '#101727' : undefined }}>
        <a href="#main-content" className="skip-link">Skip to main content</a>
        <main id="main-content" className="main-content">
          <Suspense fallback={<div className="flex items-center justify-center min-h-screen">Loading...</div>}>
            <Routes>
              <Route path="/" element={<HomePage />} />
              <Route path="/contact" element={<Contact />} />
              <Route path="/projects/elms" element={<ELMS />} />
              <Route path="/projects/survey-management-system" element={<SurveyManagementSystem />} />
            </Routes>
          </Suspense>
        </main>
        <Suspense fallback={<div className="h-32 flex items-center justify-center">Loading...</div>}>
          <Footer />
        </Suspense>
      </div>
    </>
  )
}

function App() {
  return (
    <DarkModeProvider>
      <AppContent />
    </DarkModeProvider>
  )
}

export default App