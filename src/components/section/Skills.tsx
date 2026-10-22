import { useEffect, useRef, useState } from "react";
import DomeGallery from "../ui/domegallery";
import { useDarkMode } from "../../contexts/DarkModeContext";
import { useThemeColors, withAlpha } from "../../hooks/useThemeColors";

type Skill = {
  name: string;
  level: number;
};

type SkillCategory = {
  title: string;
  skills: Skill[];
};

const skillCategories: SkillCategory[] = [
  {
    title: "Frontend",
    skills: [
      { name: "HTML5", level: 92 },
      { name: "CSS3", level: 90 },
      { name: "JavaScript", level: 85 },
      { name: "React", level: 80 },
      { name: "Next.js", level: 60 },
    ],
  },
  {
    title: "Backend",
    skills: [
      { name: "PHP", level: 85 },
      { name: "Node.js", level: 78 },
      { name: "Express.js", level: 75 },
    ],
  },
  {
    title: "Database",
    skills: [
      { name: "MySQL", level: 82 },
      { name: "MongoDB", level: 75 },
    ],
  },
  {
    title: "Programming",
    skills: [
      { name: "C++", level: 78 },
      { name: "Java", level: 80 },
    ],
  },
  {
    title: "Graphic Design",
    skills: [
      { name: "Photoshop", level: 75 },
      { name: "Illustrator", level: 70 },
      { name: "Canva", level: 90 },
      { name: "Branding", level: 80 },
      { name: "Social Media Design", level: 85 },
    ],
  },
  {
    title: "Tools",
    skills: [
      { name: "Git", level: 78 },
      { name: "GitHub", level: 82 },
      { name: "VS Code", level: 92 },
      { name: "Figma", level: 70 },
      { name: "XAMPP", level: 85 },
    ],
  },
];

const Skills = () => {
  const [scale, setScale] = useState(0.5);
  const [barsVisible, setBarsVisible] = useState(false);
  const sectionRef = useRef<HTMLElement>(null);
  const domeContainerRef = useRef<HTMLDivElement>(null);
  const barsRef = useRef<HTMLDivElement>(null);
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  useEffect(() => {
    const handleScroll = () => {
      if (!sectionRef.current) return;

      const rect = sectionRef.current.getBoundingClientRect();
      const windowHeight = window.innerHeight;

      // Calculate visibility based on how centered the section is
      let visibilityRatio = 0;

      if (rect.top <= windowHeight && rect.bottom >= 0) {
        const sectionHeight = rect.height;
        const sectionCenter = rect.top + sectionHeight / 2;
        const windowCenter = windowHeight / 2;
        const distanceFromCenter = Math.abs(sectionCenter - windowCenter);
        const maxDistance = windowHeight / 2 + sectionHeight / 2;

        // Smooth curve that peaks when section is centered
        visibilityRatio = 1 - distanceFromCenter / maxDistance;
        visibilityRatio = Math.max(0, Math.min(1, visibilityRatio));

        // Apply easing curve for more natural growth
        visibilityRatio = visibilityRatio * visibilityRatio * (3 - 2 * visibilityRatio);
      }

      // Scale from 0.5 to 1 instead of 0 to 1 for better starting size
      const minScale = 0.5;
      const maxScale = 1;
      const finalScale = minScale + (maxScale - minScale) * visibilityRatio;
      setScale(finalScale);
    };

    window.addEventListener("scroll", handleScroll);
    handleScroll(); // Initial calculation

    return () => {
      window.removeEventListener("scroll", handleScroll);
    };
  }, []);

  // Animate the proficiency bars once the grid scrolls into view
  useEffect(() => {
    const el = barsRef.current;
    if (!el) return;

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setBarsVisible(true);
          observer.disconnect();
        }
      },
      { threshold: 0.15 }
    );

    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <section
      ref={sectionRef}
      id="skills"
      className="min-h-screen py-20 relative"
      style={{
        background: themeColors.background.sections?.skills || themeColors.background.gradient,
        transition: "background 0.3s ease-in-out",
      }}
    >
      {/* Gradient overlay for smooth transition from previous section */}
      <div
        className="absolute top-0 left-0 right-0 pointer-events-none"
        style={{
          height: "300px",
          background: isDarkMode
            ? `linear-gradient(180deg, ${themeColors.background.gradientEnd} 0%, transparent 100%)`
            : `linear-gradient(180deg, ${themeColors.colors.pink[25]} 0%, transparent 100%)`,
          zIndex: 1,
        }}
      />
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2
          className="text-4xl font-bold text-center mb-2"
          style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}
        >
          Skills
        </h2>
        <p
          className="text-center mb-10 text-sm"
          style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}
        >
          Tech Stack &amp; Technical / Design Proficiency
        </p>
        <div
          ref={domeContainerRef}
          className="relative w-full"
          style={{
            height: "600px",
            transform: `scale(${scale})`,
            transformOrigin: "center center",
            willChange: "transform",
          }}
        >
          <DomeGallery />
          {/* Faded edges overlay with performance-optimized blending */}
          <div
            className="absolute inset-0 pointer-events-none"
            style={{
              background: isDarkMode
                ? `radial-gradient(ellipse at center, transparent 40%, ${withAlpha(themeColors.colors.dark[900], 0.1)} 70%, ${withAlpha(themeColors.colors.dark[900], 0.6)} 90%, ${withAlpha(themeColors.colors.dark[900], 0.8)} 100%)`
                : `radial-gradient(ellipse at center, transparent 40%, ${withAlpha(themeColors.colors.pink[50], 0.1)} 70%, ${withAlpha(themeColors.colors.pink[50], 0.6)} 90%, ${withAlpha(themeColors.colors.pink[50], 0.8)} 100%)`,
              maskImage: "radial-gradient(ellipse at center, black 50%, transparent 85%)",
              WebkitMaskImage: "radial-gradient(ellipse at center, black 50%, transparent 85%)",
            }}
          />
        </div>

        {/* Proficiency bars */}
        <div
          ref={barsRef}
          className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto mt-8"
        >
          {skillCategories.map((category) => (
            <div
              key={category.title}
              className="rounded-xl p-6 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
              style={{
                backgroundColor: themeColors.card.background,
                border: `1px solid ${themeColors.card.border}`,
              }}
            >
              <h3
                className="text-lg font-semibold mb-5"
                style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[500] }}
              >
                {category.title}
              </h3>
              <div className="space-y-4">
                {category.skills.map((skill) => (
                  <div key={skill.name}>
                    <div className="flex justify-between items-baseline mb-1.5">
                      <span
                        className="text-sm font-medium"
                        style={{ color: isDarkMode ? themeColors.colors.dark[100] : themeColors.colors.dark[700] }}
                      >
                        {skill.name}
                      </span>
                      <span className="text-xs font-semibold" style={{ color: themeColors.text.accent }}>
                        {skill.level}%
                      </span>
                    </div>
                    <div
                      className="w-full rounded-full overflow-hidden"
                      style={{
                        height: "6px",
                        backgroundColor: isDarkMode
                          ? withAlpha(themeColors.colors.pink[300], 0.15)
                          : themeColors.colors.pink[100],
                      }}
                      role="progressbar"
                      aria-label={`${skill.name} proficiency`}
                      aria-valuenow={skill.level}
                      aria-valuemin={0}
                      aria-valuemax={100}
                    >
                      <div
                        className="h-full rounded-full"
                        style={{
                          width: barsVisible ? `${skill.level}%` : "0%",
                          background: `linear-gradient(90deg, ${themeColors.colors.pink[300]}, ${themeColors.colors.pink[500]})`,
                          transition: "width 1s cubic-bezier(0.4, 0, 0.2, 1)",
                        }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

export default Skills;
