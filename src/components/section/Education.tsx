import { GraduationCap, Calendar, MapPin, Award, FileText } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';

interface EducationEntry {
  degree: string;
  school: string;
  location: string;
  period: string;
  description: string[];
  highlight?: string;
  documents?: { label: string; href: string }[];
}

const Education = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  const education: EducationEntry[] = [
    {
      degree: "Bachelor of Science in Computer Science & IT",
      school: "Benadir University",
      location: "Mogadishu, Somalia",
      period: "Sep 2021 - Oct 2025",
      description: [
        "Field of study: Information and Communication Technologies (EQF level 6)",
        "Graduated with a CGPA of 4.5 / 5.0 (A) — coursework spanning software engineering, data structures & algorithms, OOP (C++ & Java), database systems, and AI fundamentals",
        "Final project focused on information security and applied AI"
      ],
      highlight: "CGPA 4.5 (A)",
      documents: [
        { label: "Degree Certificate", href: "/certificates/bachelor-degree-certificate.pdf" },
        { label: "Official Transcript", href: "/documents/official-transcript.pdf" }
      ]
    },
    {
      degree: "Secondary Education Certificate",
      school: "Al-Anwar School",
      location: "Merca, Somalia",
      period: "2020 - 2021",
      description: [
        "Completed secondary education under the Federal Republic of Somalia Ministry of Education curriculum",
        "Core subjects included Biology, Chemistry, Mathematics, Physics, and English"
      ],
      documents: [
        { label: "School Certificate", href: "/certificates/secondary-education-certificate.pdf" }
      ]
    }
  ];

  return (
    <section
      id="education"
      className="py-8 relative"
      style={{
        background: themeColors.background.sections?.education || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      {/* Top gradient overlay */}
      <div
        className="absolute top-0 left-0 right-0 pointer-events-none"
        style={{
          height: '60px',
          background: isDarkMode
            ? `linear-gradient(180deg, ${themeColors.background.gradientEnd} 0%, transparent 100%)`
            : `linear-gradient(180deg, ${themeColors.colors.pink[25]} 0%, transparent 100%)`,
          zIndex: 1
        }}
      />
      {/* Bottom gradient overlay */}
      <div
        className="absolute bottom-0 left-0 right-0 pointer-events-none"
        style={{
          height: '60px',
          background: isDarkMode
            ? `linear-gradient(180deg, transparent 0%, ${themeColors.background.gradientEnd} 100%)`
            : `linear-gradient(180deg, transparent 0%, ${themeColors.colors.white} 100%)`,
          zIndex: 1
        }}
      />
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2 className="text-4xl font-bold text-center mb-6" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
          Education
        </h2>
        <div className="max-w-4xl mx-auto space-y-4">
          {education.map((entry, index) => (
            <Card key={index} className="border-2 border-pink-100 dark:border-gray-700 hover:border-pink-200 dark:hover:border-gray-600 transition-all duration-300 hover:shadow-lg bg-white/95 dark:bg-gray-800/95">
              <CardHeader className="pb-2">
                <div className="flex flex-col sm:flex-row justify-between items-start gap-3">
                  <div className="flex items-start gap-3">
                    <GraduationCap className="h-7 w-7 mt-1 flex-shrink-0" style={{ color: themeColors.primary }} />
                    <div>
                      <CardTitle className="text-2xl" style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[400] }}>
                        {entry.degree}
                      </CardTitle>
                      <p className="text-lg font-semibold text-gray-700 dark:text-gray-400 mt-1">{entry.school}</p>
                    </div>
                  </div>
                  <div className="text-left sm:text-right flex-shrink-0">
                    <div className="flex items-center gap-2 text-gray-600 dark:text-gray-400 mb-1">
                      <Calendar className="h-4 w-4" />
                      <span className="text-sm">{entry.period}</span>
                    </div>
                    <div className="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                      <MapPin className="h-4 w-4" />
                      <span className="text-sm">{entry.location}</span>
                    </div>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="pt-2">
                {entry.highlight && (
                  <span
                    className="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1 rounded-full mb-3"
                    style={{ backgroundColor: themeColors.interactive.primary, color: themeColors.text.accent }}
                  >
                    <Award className="h-3.5 w-3.5" />
                    {entry.highlight}
                  </span>
                )}
                <ul className="space-y-1 mb-4">
                  {entry.description.map((item, itemIndex) => (
                    <li key={itemIndex} className="flex items-start">
                      <span className="mr-2" style={{ color: themeColors.primary }}>•</span>
                      <span className="text-sm" style={{ color: isDarkMode ? themeColors.colors.dark[200] : themeColors.colors.dark[600] }}>
                        {item}
                      </span>
                    </li>
                  ))}
                </ul>
                {entry.documents && (
                  <div className="flex flex-wrap gap-3 pt-2 border-t" style={{ borderColor: themeColors.card.border }}>
                    {entry.documents.map((doc) => (
                      <a
                        key={doc.label}
                        href={doc.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1.5 text-xs font-medium mt-3 hover:opacity-75 transition-opacity"
                        style={{ color: themeColors.text.pink }}
                        aria-label={`View ${doc.label} (opens in new tab)`}
                      >
                        <FileText className="h-3.5 w-3.5" />
                        {doc.label}
                      </a>
                    ))}
                  </div>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
};

export default Education;
