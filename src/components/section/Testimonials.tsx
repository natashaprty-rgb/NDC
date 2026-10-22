import { Quote, FileText } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';

const Testimonials = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <section
      id="testimonials"
      className="py-20 relative"
      style={{
        background: themeColors.background.sections?.testimonials || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2 className="text-4xl font-bold text-center mb-2" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
          Testimonials
        </h2>
        <p className="text-center mb-12 text-sm" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
          What people I've worked with have to say
        </p>
        <div
          className="relative max-w-2xl mx-auto rounded-2xl p-8 md:p-10 text-center"
          style={{
            backgroundColor: themeColors.card.background,
            border: `1px solid ${themeColors.card.border}`
          }}
        >
          <Quote className="h-10 w-10 mx-auto mb-4 opacity-70" style={{ color: themeColors.colors.pink[400] }} aria-hidden="true" />
          <p className="text-base md:text-lg leading-relaxed italic mb-6" style={{ color: isDarkMode ? themeColors.colors.dark[100] : themeColors.colors.dark[700] }}>
            “Nasteho exhibited not only exceptional academic prowess but also exemplary character and personal qualities. Her outstanding character, along with her demonstrated strong learning ability and impressive problem-solving skills, set her apart. She is a person of honesty, integrity, and a commendable work ethic. I would highly recommend her for a leading career.”
          </p>
          <div className="flex flex-col items-center gap-0.5">
            <span className="font-bold text-sm" style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600] }}>
              Assoc. Prof. Dr. Abdulkadir Hassan
            </span>
            <span className="text-xs" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
              Chief Registrar, Benadir University
            </span>
          </div>
          <a
            href="/documents/letter-of-recommendation.pdf"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 text-xs font-medium mt-5 hover:opacity-75 transition-opacity"
            style={{ color: themeColors.text.pink }}
          >
            <FileText className="h-3.5 w-3.5" />
            Read the full letter
          </a>
        </div>
      </div>
    </section>
  );
};

export default Testimonials;
