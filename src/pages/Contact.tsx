import React from 'react';
import { Mail, Github, Linkedin, MapPin, MessageCircle } from 'lucide-react';
import { useDarkMode } from '../contexts/DarkModeContext';
import { useThemeColors } from '../hooks/useThemeColors';
import Aurora from '../components/ui/aurora';
import BackButton from '../components/BackButton';
import { socialLinks } from '../config/socialLinks';

const Contact = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  // Scroll to top when component mounts
  React.useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  return (
    <main aria-label="Contact page" className="min-h-screen py-20 transition-colors duration-300 relative overflow-hidden" style={{ backgroundColor: themeColors.background.primary }}>
      {/* Aurora Background */}
      <div className="fixed inset-0 z-0" style={{ opacity: isDarkMode ? 1 : 0.3 }}>
        <Aurora
          colorStops={isDarkMode ? [themeColors.primary, themeColors.colors.special.aurora.dark, themeColors.secondary] : [themeColors.colors.special.aurora.light[1], themeColors.colors.special.aurora.light[2], themeColors.colors.special.aurora.light[3]]}
          blend={isDarkMode ? 0.3 : 0.25}
          amplitude={isDarkMode ? 0.8 : 0.6}
          speed={0.3}
        />
      </div>
      
      <div className="container mx-auto px-6 max-w-4xl relative z-10">
        {/* Back Button */}
        <BackButton 
          to="/" 
          scrollToId="" 
          label="Back to Home" 
          ariaLabel="Navigate back to homepage" 
        />

        {/* Contact Header */}
        <header className="text-center mb-12">
          <h1 className="text-4xl font-bold mb-4" style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600] }}>Let's Connect!</h1>
          <p className="text-lg" style={{ color: themeColors.text.secondary }}>
            Have a project in mind or just want to say hi? I'd love to hear from you.
          </p>
          <p className="flex items-center justify-center gap-2 text-sm mt-3" style={{ color: themeColors.text.tertiary }}>
            <MapPin className="h-4 w-4" aria-hidden="true" />
            {socialLinks.location}
          </p>
        </header>

        {/* Contact Cards */}
        <section className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto" aria-label="Contact methods">
          
          {/* Email Card */}
          <article className="rounded-lg shadow-lg p-6 text-center hover:scale-105 transition-transform duration-300" style={{ backgroundColor: themeColors.card.background }} aria-labelledby="email-heading">
            <div className="flex justify-center mb-4">
              <Mail className="h-12 w-12" style={{ color: themeColors.colors.pink[500] }} aria-hidden="true" />
            </div>
            <h3 id="email-heading" className="text-xl font-semibold mb-2" style={{ color: themeColors.text.primary }}>Email</h3>
            <a 
              href={`mailto:${socialLinks.email}`}
              aria-label={`Send email to ${socialLinks.email}`}
              className="inline-flex items-center gap-2 px-6 py-3 rounded-lg font-medium transition-colors hover:opacity-80"
              style={{
                backgroundColor: themeColors.interactive.primary,
                color: themeColors.text.pink
              }}
            >
              Send Email
            </a>
            <p className="text-xs mt-3" style={{ color: themeColors.text.tertiary }}>{socialLinks.display.email}</p>
          </article>

          {/* GitHub Card */}
          <article className="rounded-lg shadow-lg p-6 text-center hover:scale-105 transition-transform duration-300" style={{ backgroundColor: themeColors.card.background }} aria-labelledby="github-heading">
            <div className="flex justify-center mb-4">
              <Github className="h-12 w-12" style={{ color: themeColors.colors.pink[500] }} aria-hidden="true" />
            </div>
            <h3 id="github-heading" className="text-xl font-semibold mb-2" style={{ color: themeColors.text.primary }}>GitHub</h3>
            <a 
              href={socialLinks.github}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={`View GitHub profile at ${socialLinks.display.github} (opens in new tab)`}
              className="inline-flex items-center gap-2 px-6 py-3 rounded-lg font-medium transition-colors hover:opacity-80"
              style={{
                backgroundColor: themeColors.interactive.primary,
                color: themeColors.text.pink
              }}
            >
              View Profile
            </a>
            <p className="text-xs mt-3" style={{ color: themeColors.text.tertiary }}>{socialLinks.display.github}</p>
          </article>

          {/* LinkedIn Card */}
          <article className="rounded-lg shadow-lg p-6 text-center hover:scale-105 transition-transform duration-300" style={{ backgroundColor: themeColors.card.background }} aria-labelledby="linkedin-heading">
            <div className="flex justify-center mb-4">
              <Linkedin className="h-12 w-12" style={{ color: themeColors.colors.pink[500] }} aria-hidden="true" />
            </div>
            <h3 id="linkedin-heading" className="text-xl font-semibold mb-2" style={{ color: themeColors.text.primary }}>LinkedIn</h3>
            <a 
              href={socialLinks.linkedin}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={`Connect on LinkedIn at ${socialLinks.display.linkedin} (opens in new tab)`}
              className="inline-flex items-center gap-2 px-6 py-3 rounded-lg font-medium transition-colors hover:opacity-80"
              style={{
                backgroundColor: themeColors.interactive.primary,
                color: themeColors.text.pink
              }}
            >
              Connect
            </a>
            <p className="text-xs mt-3" style={{ color: themeColors.text.tertiary }}>{socialLinks.display.linkedin}</p>
          </article>

          {/* WhatsApp Card */}
          <article className="rounded-lg shadow-lg p-6 text-center hover:scale-105 transition-transform duration-300" style={{ backgroundColor: themeColors.card.background }} aria-labelledby="whatsapp-heading">
            <div className="flex justify-center mb-4">
              <MessageCircle className="h-12 w-12" style={{ color: themeColors.colors.pink[500] }} aria-hidden="true" />
            </div>
            <h3 id="whatsapp-heading" className="text-xl font-semibold mb-2" style={{ color: themeColors.text.primary }}>WhatsApp</h3>
            <a 
              href={socialLinks.whatsapp}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={`Message on WhatsApp at ${socialLinks.display.phone} (opens in new tab)`}
              className="inline-flex items-center gap-2 px-6 py-3 rounded-lg font-medium transition-colors hover:opacity-80"
              style={{
                backgroundColor: themeColors.interactive.primary,
                color: themeColors.text.pink
              }}
            >
              Message Me
            </a>
            <p className="text-xs mt-3" style={{ color: themeColors.text.tertiary }}>{socialLinks.display.phone}</p>
          </article>

        </section>
      </div>
    </main>
  );
};

export default Contact;