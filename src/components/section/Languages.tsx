import { useEffect, useRef, useState } from 'react';
import { FileText } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors, withAlpha } from '../../hooks/useThemeColors';
import { publicUrl } from '../../lib/publicUrl';

// CEFR level → bar width
const levelPercent: Record<string, number> = {
  A1: 20,
  A2: 35,
  B1: 50,
  B2: 65,
  C1: 80,
  C2: 95,
};

const englishSkills = [
  { name: "Listening", level: "C1" },
  { name: "Reading", level: "C1" },
  { name: "Writing", level: "C1" },
  { name: "Spoken Interaction", level: "B2" },
  { name: "Spoken Production", level: "B2" },
];

const Languages = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();
  const [barsVisible, setBarsVisible] = useState(false);
  const gridRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const node = gridRef.current;
    if (!node) return;
    const observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) {
        setBarsVisible(true);
        observer.disconnect();
      }
    }, { threshold: 0.2 });
    observer.observe(node);
    return () => observer.disconnect();
  }, []);

  const cardStyle = {
    backgroundColor: themeColors.card.background,
    border: `1px solid ${themeColors.card.border}`
  };
  const headingColor = isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600];
  const badgeStyle = { backgroundColor: themeColors.interactive.primary, color: themeColors.text.accent };
  const trackColor = isDarkMode ? withAlpha(themeColors.colors.pink[300], 0.15) : themeColors.colors.pink[100];
  const fillBackground = `linear-gradient(90deg, ${themeColors.colors.pink[300]}, ${themeColors.colors.pink[500]})`;
  const fillTransition = 'width 1s cubic-bezier(0.4, 0, 0.2, 1)';
  const linkClass = "inline-flex items-center gap-1.5 text-xs font-medium hover:opacity-75 transition-opacity";

  return (
    <section
      id="languages"
      className="py-20 relative"
      style={{
        background: themeColors.background.sections?.languages || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2 className="text-4xl font-bold text-center mb-2" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
          Languages
        </h2>
        <p className="text-center mb-12 text-sm" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
          Levels: A1/A2 Basic · B1/B2 Independent · C1/C2 Proficient user
        </p>
        <div ref={gridRef} className="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
          {/* Somali */}
          <div className="rounded-xl p-6" style={cardStyle}>
            <div className="flex items-baseline justify-between mb-4">
              <h3 className="text-lg font-bold" style={{ color: headingColor }}>Somali</h3>
              <span className="text-xs font-semibold px-2.5 py-1 rounded-full" style={badgeStyle}>Native / Mother tongue</span>
            </div>
            <div className="w-full rounded-full overflow-hidden" style={{ height: '8px', backgroundColor: trackColor }}>
              <div
                className="h-full rounded-full"
                style={{ width: barsVisible ? '100%' : '0%', background: fillBackground, transition: fillTransition }}
              />
            </div>
          </div>

          {/* English */}
          <div className="rounded-xl p-6" style={cardStyle}>
            <div className="flex items-baseline justify-between mb-4">
              <h3 className="text-lg font-bold" style={{ color: headingColor }}>English</h3>
              <span className="text-xs font-semibold px-2.5 py-1 rounded-full" style={badgeStyle}>Second language</span>
            </div>
            <div className="space-y-3">
              {englishSkills.map((skill) => (
                <div key={skill.name}>
                  <div className="flex justify-between items-baseline mb-1">
                    <span className="text-xs font-medium" style={{ color: isDarkMode ? themeColors.colors.dark[200] : themeColors.colors.dark[600] }}>
                      {skill.name}
                    </span>
                    <span className="text-[11px] font-semibold" style={{ color: themeColors.text.accent }}>
                      {skill.level}
                    </span>
                  </div>
                  <div className="w-full rounded-full overflow-hidden" style={{ height: '6px', backgroundColor: trackColor }}>
                    <div
                      className="h-full rounded-full"
                      style={{
                        width: barsVisible ? `${levelPercent[skill.level]}%` : '0%',
                        background: fillBackground,
                        transition: fillTransition
                      }}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
        <div className="flex flex-wrap justify-center gap-4 mt-8">
          <a
            href={publicUrl('/certificates/ielts-preparation-specialization.pdf')}
            target="_blank"
            rel="noopener noreferrer"
            className={linkClass}
            style={{ color: themeColors.text.pink }}
          >
            <FileText className="h-3.5 w-3.5" />
            IELTS Preparation Certificate
          </a>
          <a
            href={publicUrl('/certificates/medium-of-instruction-certificate.pdf')}
            target="_blank"
            rel="noopener noreferrer"
            className={linkClass}
            style={{ color: themeColors.text.pink }}
          >
            <FileText className="h-3.5 w-3.5" />
            Medium of Instruction Certificate
          </a>
        </div>
      </div>
    </section>
  );
};

export default Languages;
