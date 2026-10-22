import type { LucideIcon } from 'lucide-react';
import { GraduationCap, CodeXml, Award, Briefcase, Sparkles, Trophy } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors, withAlpha } from '../../hooks/useThemeColors';

interface Milestone {
  date: string;
  title: string;
  place: string;
  description: string;
  icon: LucideIcon;
}

const milestones: Milestone[] = [
  {
    date: "2021",
    title: "Completed Secondary Education",
    place: "Al-Anwar School, Merca, Somalia",
    description: "Finished secondary school and set my sights on studying Computer Science.",
    icon: GraduationCap
  },
  {
    date: "Sep 2021",
    title: "Began B.Sc. Computer Science & IT",
    place: "Benadir University",
    description: "Started my Bachelor’s degree, diving into programming, web design, and systems fundamentals.",
    icon: GraduationCap
  },
  {
    date: "Oct 2024",
    title: "MERN Stack & UI/UX Training",
    place: "Tabaarak ICT Solutions",
    description: "Completed an intensive professional training program in MERN Stack development, .NET, and UI/UX design.",
    icon: CodeXml
  },
  {
    date: "Dec 2024",
    title: "Recognized for ICT & Media Support",
    place: "Benadir University",
    description: "Received a Certificate of Appreciation for contributions to the ICT Office and the 18th Graduation Book.",
    icon: Award
  },
  {
    date: "Feb 2025",
    title: "Graphic Designer & Web Developer",
    place: "Somnuur Energy and Technology",
    description: "Designed social media content and built responsive WordPress websites with modern UI/UX standards.",
    icon: Briefcase
  },
  {
    date: "Jun 2025",
    title: "Mentorship Program & First Major Project",
    place: "Global Mentorship Initiative",
    description: "Completed the GMI Career Readiness Mentorship Program while building ELMS, a full-featured e-learning platform.",
    icon: Sparkles
  },
  {
    date: "Oct 2025",
    title: "Graduated — B.Sc. Computer Science & IT",
    place: "Benadir University · CGPA 4.5 (A)",
    description: "Earned my degree with a 4.5 CGPA, capped off by a final project in AI & information security.",
    icon: Trophy
  },
  {
    date: "Dec 2025",
    title: "SomNOG8 Workshop & Internship Completion",
    place: "Somali Network Operators Group · Benadir University",
    description: "Completed the Systems & Services track at SomNOG8 and wrapped up a six-month internship supporting university offices.",
    icon: Award
  },
  {
    date: "2026",
    title: "Admissions & Registration Coordinator",
    place: "Benadir University",
    description: "Took on front-desk, records, and event-coordination responsibilities for the university’s admissions office.",
    icon: Briefcase
  },
  {
    date: "2026",
    title: "Google AI Certificate & Freelancing",
    place: "Coursera · Self-Employed",
    description: "Earned the 7-course Google AI Professional Certificate and the IELTS Preparation Specialization, then began freelance web development.",
    icon: Sparkles
  }
];

const Journey = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <section
      id="journey"
      className="py-20 relative"
      style={{
        background: themeColors.background.sections?.journey || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2 className="text-4xl font-bold text-center mb-2" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
          My Journey
        </h2>
        <p className="text-center mb-16 text-sm" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
          A timeline of the milestones that shaped my path so far
        </p>
        <div className="max-w-4xl mx-auto relative">
          {/* Timeline line */}
          <div
            className="absolute top-0 bottom-0 w-0.5 left-4 md:left-1/2 md:-translate-x-1/2"
            style={{ backgroundColor: isDarkMode ? withAlpha(themeColors.colors.pink[300], 0.25) : themeColors.colors.pink[200] }}
            aria-hidden="true"
          />
          <div className="space-y-10">
            {milestones.map((milestone, index) => {
              const Icon = milestone.icon;
              const isLeft = index % 2 === 0;
              return (
                <div key={index} className="relative flex md:items-center">
                  <div
                    className="absolute left-4 md:left-1/2 -translate-x-1/2 flex items-center justify-center rounded-full shadow-md z-10"
                    style={{
                      width: '2.5rem',
                      height: '2.5rem',
                      backgroundColor: themeColors.colors.pink[500],
                      border: `3px solid ${isDarkMode ? themeColors.colors.dark[900] : themeColors.colors.white}`
                    }}
                  >
                    <Icon className="h-4 w-4" style={{ color: themeColors.colors.white }} />
                  </div>
                  <div className={`w-full pl-16 md:pl-0 md:w-1/2 ${isLeft ? 'md:pr-12 md:mr-auto md:text-right' : 'md:pl-12 md:ml-auto'}`}>
                    <div
                      className="inline-block w-full rounded-xl p-5 text-left transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
                      style={{
                        backgroundColor: themeColors.card.background,
                        border: `1px solid ${themeColors.card.border}`
                      }}
                    >
                      <span
                        className="inline-block text-xs font-semibold px-2.5 py-1 rounded-full mb-2"
                        style={{ backgroundColor: themeColors.interactive.primary, color: themeColors.text.accent }}
                      >
                        {milestone.date}
                      </span>
                      <h3 className="text-lg font-bold" style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600] }}>
                        {milestone.title}
                      </h3>
                      <p className="text-sm font-medium mt-0.5" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
                        {milestone.place}
                      </p>
                      <p className="text-sm mt-2 leading-relaxed" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[500] }}>
                        {milestone.description}
                      </p>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </section>
  );
};

export default Journey;
