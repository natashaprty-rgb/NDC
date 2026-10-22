import { useState } from 'react';
import type { ComponentType, CSSProperties } from 'react';
import { Award, BookOpen, Download, ExternalLink, GraduationCap } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors, withAlpha } from '../../hooks/useThemeColors';

type CertificateCategory = 'Academic' | 'Professional Training' | 'Online Courses';
type CategoryFilter = 'All' | CertificateCategory;

interface Certificate {
  id: string;
  title: string;
  issuer: string;
  date: string;
  category: CertificateCategory;
  file: string;
  featured?: string;
}

const categoryIcons: Record<CertificateCategory, ComponentType<{ className?: string; style?: CSSProperties }>> = {
  Academic: GraduationCap,
  'Professional Training': Award,
  'Online Courses': BookOpen,
};

const certificates: Certificate[] = [
  {
    id: 'degree',
    title: 'Bachelor of Computer Science & IT',
    issuer: 'Benadir University',
    date: 'Oct 2025',
    category: 'Academic',
    file: '/certificates/bachelor-degree-certificate.pdf',
  },
  {
    id: 'secondary',
    title: 'Secondary Education Certificate',
    issuer: 'Federal Republic of Somalia',
    date: '2021',
    category: 'Academic',
    file: '/certificates/secondary-education-certificate.pdf',
  },
  {
    id: 'moi',
    title: 'Medium of Instruction Certificate',
    issuer: 'Benadir University',
    date: 'Nov 2025',
    category: 'Academic',
    file: '/certificates/medium-of-instruction-certificate.pdf',
  },
  {
    id: 'internship',
    title: 'Certificate of Internship Completion',
    issuer: 'Benadir University',
    date: 'Dec 2025',
    category: 'Professional Training',
    file: '/certificates/internship-completion-certificate.pdf',
  },
  {
    id: 'mentorship',
    title: 'Certificate of Mentorship',
    issuer: 'Global Mentorship Initiative',
    date: 'Jun 2025',
    category: 'Professional Training',
    file: '/certificates/global-mentorship-initiative.pdf',
  },
  {
    id: 'somnog',
    title: 'Systems & Services — SomNOG8',
    issuer: 'Somali Network Operators Group',
    date: 'Dec 2025',
    category: 'Professional Training',
    file: '/certificates/somali-network-operators-group.pdf',
  },
  {
    id: 'tabaarak',
    title: 'MERN Stack Development',
    issuer: 'Tabaarak ICT Solutions',
    date: 'Nov 2024',
    category: 'Professional Training',
    file: '/certificates/tabaarak-ict-mern-stack.pdf',
  },
  {
    id: 'google-ai',
    title: 'Google AI Professional Certificate',
    issuer: 'Google · Coursera',
    date: 'Jun 2026',
    category: 'Online Courses',
    file: '/certificates/google-ai-professional-certificate.pdf',
    featured: '7 courses',
  },
  {
    id: 'ai-fund',
    title: 'AI Fundamentals',
    issuer: 'Google · Coursera',
    date: 'May 2026',
    category: 'Online Courses',
    file: '/certificates/ai-fundamentals.pdf',
  },
  {
    id: 'ai-brainstorm',
    title: 'AI for Brainstorming and Planning',
    issuer: 'Google · Coursera',
    date: 'May 2026',
    category: 'Online Courses',
    file: '/certificates/ai-for-brainstorming-and-planning.pdf',
  },
  {
    id: 'ai-content',
    title: 'AI for Content Creation',
    issuer: 'Google · Coursera',
    date: 'May 2026',
    category: 'Online Courses',
    file: '/certificates/ai-for-content-creation.pdf',
  },
  {
    id: 'ai-writing',
    title: 'AI for Writing and Communicating',
    issuer: 'Google · Coursera',
    date: 'May 2026',
    category: 'Online Courses',
    file: '/certificates/ai-for-writing-and-communicating.pdf',
  },
  {
    id: 'data-analytics',
    title: 'Introduction to Data Analytics',
    issuer: 'IBM · Coursera',
    date: 'Apr 2026',
    category: 'Online Courses',
    file: '/certificates/introduction-to-data-analytics.pdf',
  },
  {
    id: 'ielts-spec',
    title: 'IELTS Preparation',
    issuer: 'UC Irvine · Coursera',
    date: 'Aug 2026',
    category: 'Online Courses',
    file: '/certificates/ielts-preparation-specialization.pdf',
    featured: '3 courses',
  },
  {
    id: 'ielts-listen',
    title: 'IELTS Listening and Speaking Mastery',
    issuer: 'UC Irvine · Coursera',
    date: 'Jul 2026',
    category: 'Online Courses',
    file: '/certificates/ielts-listening-speaking.pdf',
  },
  {
    id: 'ielts-write',
    title: 'IELTS Writing Section Mastery',
    issuer: 'UC Irvine · Coursera',
    date: 'Jul 2026',
    category: 'Online Courses',
    file: '/certificates/ielts-writing.pdf',
  },
  {
    id: 'ielts-read',
    title: 'IELTS Reading Section Mastery',
    issuer: 'UC Irvine · Coursera',
    date: 'Aug 2026',
    category: 'Online Courses',
    file: '/certificates/ielts-reading.pdf',
  },
];

const filters: CategoryFilter[] = ['All', 'Academic', 'Professional Training', 'Online Courses'];

const Certifications = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();
  const [activeFilter, setActiveFilter] = useState<CategoryFilter>('All');

  const visibleCertificates =
    activeFilter === 'All' ? certificates : certificates.filter((cert) => cert.category === activeFilter);

  return (
    <section
      id="certifications"
      className="py-20 relative"
      style={{
        background: themeColors.background.sections?.certifications || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2
          className="text-4xl font-bold text-center mb-2"
          style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}
        >
          All Certificates
        </h2>
        <p
          className="text-center mb-8 text-sm"
          style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}
        >
          {certificates.length} certificates from academic, professional, and online learning
        </p>

        {/* Category filters */}
        <div className="flex flex-wrap justify-center gap-2 mb-10">
          {filters.map((filter) => (
            <button
              key={filter}
              onClick={() => setActiveFilter(filter)}
              className="text-sm font-medium px-4 py-2 rounded-full transition-all duration-200"
              style={{
                backgroundColor: activeFilter === filter ? themeColors.colors.pink[500] : themeColors.interactive.primary,
                color: activeFilter === filter ? themeColors.colors.white : themeColors.text.accent,
                border: `1px solid ${activeFilter === filter ? themeColors.colors.pink[500] : themeColors.card.border}`
              }}
            >
              {filter}
            </button>
          ))}
        </div>

        {/* Certificate cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
          {visibleCertificates.map((cert) => {
            const Icon = categoryIcons[cert.category];
            return (
              <div
                key={cert.id}
                className="group flex flex-col rounded-xl overflow-hidden transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl"
                style={{
                  backgroundColor: themeColors.card.background,
                  border: `1px solid ${themeColors.card.border}`
                }}
              >
                <div
                  className="relative flex items-center justify-center"
                  style={{
                    height: '110px',
                    background: isDarkMode
                      ? `linear-gradient(135deg, ${withAlpha(themeColors.colors.pink[500], 0.35)}, ${withAlpha(themeColors.colors.pink[300], 0.15)})`
                      : `linear-gradient(135deg, ${themeColors.colors.pink[200]}, ${themeColors.colors.pink[50]})`
                  }}
                >
                  <Icon
                    className="h-10 w-10 transition-transform duration-300 group-hover:scale-110"
                    style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[600] }}
                  />
                  {cert.featured && (
                    <span
                      className="absolute top-2 right-2 text-[10px] font-bold px-2 py-0.5 rounded-full"
                      style={{ backgroundColor: themeColors.colors.pink[600], color: themeColors.colors.white }}
                    >
                      {cert.featured}
                    </span>
                  )}
                  <span
                    className="absolute bottom-2 left-2 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                    style={{
                      backgroundColor: isDarkMode
                        ? withAlpha(themeColors.colors.dark[900], 0.5)
                        : withAlpha(themeColors.colors.white, 0.7),
                      color: isDarkMode ? themeColors.colors.pink[200] : themeColors.colors.pink[700]
                    }}
                  >
                    {cert.category}
                  </span>
                </div>

                <div className="p-5 flex flex-col flex-1">
                  <h3
                    className="text-base font-bold leading-snug mb-1"
                    style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600] }}
                  >
                    {cert.title}
                  </h3>
                  <p
                    className="text-sm mb-1"
                    style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}
                  >
                    {cert.issuer}
                  </p>
                  <p
                    className="text-xs mb-4"
                    style={{ color: isDarkMode ? themeColors.colors.dark[400] : themeColors.colors.dark[500] }}
                  >
                    {cert.date}
                  </p>

                  <div className="flex gap-2 mt-auto pt-2">
                    <a
                      href={cert.file}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg transition-opacity hover:opacity-80"
                      style={{ backgroundColor: themeColors.interactive.primary, color: themeColors.text.accent }}
                      aria-label={`View ${cert.title} certificate (opens in new tab)`}
                    >
                      <ExternalLink className="h-3.5 w-3.5" />
                      View
                    </a>
                    <a
                      href={cert.file}
                      download
                      className="flex-1 inline-flex items-center justify-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg transition-opacity hover:opacity-80"
                      style={{ backgroundColor: themeColors.colors.pink[500], color: themeColors.colors.white }}
                      aria-label={`Download ${cert.title} certificate`}
                    >
                      <Download className="h-3.5 w-3.5" />
                      Download
                    </a>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Bottom fade transition */}
      <div
        className="absolute bottom-0 left-0 right-0 pointer-events-none"
        style={{
          height: '60px',
          background: isDarkMode
            ? `linear-gradient(180deg, transparent 0%, ${themeColors.background.gradientEnd} 100%)`
            : `linear-gradient(180deg, transparent 0%, ${themeColors.colors.pink[25]} 100%)`,
          zIndex: 1
        }}
      />
    </section>
  );
};

export default Certifications;
