import { Link } from 'react-router-dom';
import { Mail } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';

const ContactCTA = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <section
      id="contact-cta"
      className="py-16 relative"
      style={{
        background: themeColors.background.sections?.contactCta || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 text-center relative" style={{ zIndex: 2 }}>
        <div
          className="max-w-3xl mx-auto rounded-2xl px-8 py-12 md:py-14"
          style={{
            backgroundColor: themeColors.card.background,
            border: `1px solid ${themeColors.card.border}`
          }}
        >
          <Mail className="h-9 w-9 mx-auto mb-4" style={{ color: themeColors.colors.pink[500] }} aria-hidden="true" />
          <h2 className="text-3xl md:text-4xl font-bold mb-3" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
            Let's Work Together
          </h2>
          <p className="text-sm md:text-base mb-8 max-w-xl mx-auto" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
            Have a project in mind, an opportunity to discuss, or just want to say hello? I'd love to hear from you.
          </p>
          <Link to="/contact" className="project-btn inline-flex items-center gap-2">
            Get In Touch
          </Link>
        </div>
      </div>
    </section>
  );
};

export default ContactCTA;
