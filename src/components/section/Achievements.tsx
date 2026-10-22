import type { LucideIcon } from 'lucide-react';
import { Trophy, Award, Star, Sparkles, Rocket, FileCheck } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';

interface Achievement {
  icon: LucideIcon;
  title: string;
  description: string;
  file?: string;
}

const achievements: Achievement[] = [
  {
    icon: Trophy,
    title: "4.5 CGPA (A) Graduate",
    description: "Graduated with a 4.5 / 5.0 CGPA in Computer Science & IT from Benadir University."
  },
  {
    icon: Award,
    title: "Convocation Recognition",
    description: "Awarded a Certificate of Appreciation for organizing Benadir University’s 19th Convocation Ceremony.",
    file: "/documents/certificate-of-appreciation-convocation.pdf"
  },
  {
    icon: Star,
    title: "ICT & Media Contribution",
    description: "Recognized for valuable contributions to the ICT Office and the 18th Graduation Book design.",
    file: "/documents/certificate-of-appreciation-ict-office.pdf"
  },
  {
    icon: Sparkles,
    title: "Google AI Professional Certificate",
    description: "Completed 7 Google AI courses and built a portfolio of 20+ AI-powered artifacts.",
    file: "/certificates/google-ai-professional-certificate.pdf"
  },
  {
    icon: Rocket,
    title: "3+ Real-World Projects",
    description: "Delivered an E-Learning Management System, a Survey Management System, and multiple freelance client sites."
  },
  {
    icon: FileCheck,
    title: "Faculty Recommendation",
    description: "Highly recommended by Benadir University’s Chief Registrar for academic excellence and integrity.",
    file: "/documents/letter-of-recommendation.pdf"
  }
];

const Achievements = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <section
      id="achievements"
      className="py-20 relative"
      style={{
        background: themeColors.background.sections?.achievements || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2 className="text-4xl font-bold text-center mb-2" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
          Achievements
        </h2>
        <p className="text-center mb-12 text-sm" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
          Recognition earned along the way
        </p>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
          {achievements.map((achievement) => {
            const Icon = achievement.icon;
            const cardStyle = {
              backgroundColor: themeColors.card.background,
              border: `1px solid ${themeColors.card.border}`,
              cursor: achievement.file ? 'pointer' : 'default'
            };
            const content = (
              <>
                <div
                  className="flex items-center justify-center rounded-full mb-4 transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6"
                  style={{
                    width: '52px',
                    height: '52px',
                    backgroundColor: isDarkMode ? 'rgba(236, 72, 153, 0.15)' : themeColors.colors.pink[50]
                  }}
                >
                  <Icon className="h-6 w-6" style={{ color: themeColors.colors.pink[500] }} />
                </div>
                <h3 className="text-base font-bold mb-1.5" style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600] }}>
                  {achievement.title}
                </h3>
                <p className="text-sm leading-relaxed" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
                  {achievement.description}
                </p>
                {achievement.file && (
                  <span className="text-xs font-semibold mt-3 opacity-70 group-hover:opacity-100 transition-opacity" style={{ color: themeColors.text.pink }}>
                    View document →
                  </span>
                )}
              </>
            );
            const className = "group flex flex-col items-start rounded-xl p-6 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl";

            return achievement.file ? (
              <a
                key={achievement.title}
                href={achievement.file}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={`View document for ${achievement.title} (opens in new tab)`}
                className={className}
                style={cardStyle}
              >
                {content}
              </a>
            ) : (
              <div key={achievement.title} className={className} style={cardStyle}>
                {content}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
};

export default Achievements;
