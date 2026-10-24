import { GraduationCap, Briefcase, CodeXml, Award, Download, Eye } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';
import { publicUrl } from '../../lib/publicUrl';

const highlights = [
  { icon: GraduationCap, label: "B.Sc. Computer Science & IT", sub: "CGPA 4.5 (A) — Benadir University" },
  { icon: Briefcase, label: "4 Roles", sub: "Web dev, graphic design, teaching & admin coordination" },
  { icon: CodeXml, label: "Full-Stack + Design", sub: "React, Node.js, PHP, MySQL, MongoDB" },
  { icon: Award, label: "17+ Certificates", sub: "Academic, professional & online courses" }
];

const Resume = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();
  const buttonClass = "inline-flex items-center gap-2 px-6 py-3 rounded-lg font-semibold text-sm transition-opacity hover:opacity-85";

  return (
    <section
      id="resume"
      className="py-20 relative"
      style={{
        background: themeColors.background.sections?.resume || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2 className="text-4xl font-bold text-center mb-2" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
          Resume / CV
        </h2>
        <p className="text-center mb-12 text-sm" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
          A quick snapshot — or grab the full PDF
        </p>
        <div
          className="max-w-3xl mx-auto rounded-2xl p-8 md:p-10"
          style={{
            backgroundColor: themeColors.card.background,
            border: `1px solid ${themeColors.card.border}`
          }}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-8">
            {highlights.map((item) => {
              const Icon = item.icon;
              return (
                <div key={item.label} className="flex items-start gap-3">
                  <div
                    className="flex items-center justify-center rounded-lg flex-shrink-0"
                    style={{
                      width: '40px',
                      height: '40px',
                      backgroundColor: isDarkMode ? 'rgba(236, 72, 153, 0.15)' : themeColors.colors.pink[50]
                    }}
                  >
                    <Icon className="h-5 w-5" style={{ color: themeColors.colors.pink[500] }} />
                  </div>
                  <div>
                    <p className="text-sm font-semibold" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.dark[700] }}>
                      {item.label}
                    </p>
                    <p className="text-xs mt-0.5" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[500] }}>
                      {item.sub}
                    </p>
                  </div>
                </div>
              );
            })}
          </div>
          <div className="flex flex-wrap justify-center gap-3 pt-6 border-t" style={{ borderColor: themeColors.card.border }}>
            <a
              href={publicUrl('/resume.pdf')}
              download
              className={buttonClass}
              style={{ backgroundColor: themeColors.colors.pink[500], color: themeColors.colors.white }}
            >
              <Download className="h-4 w-4" />
              Download CV
            </a>
            <a
              href={publicUrl('/resume.pdf')}
              target="_blank"
              rel="noopener noreferrer"
              className={buttonClass}
              style={{ backgroundColor: themeColors.interactive.primary, color: themeColors.text.accent }}
            >
              <Eye className="h-4 w-4" />
              View Online
            </a>
          </div>
        </div>
      </div>
    </section>
  );
};

export default Resume;
