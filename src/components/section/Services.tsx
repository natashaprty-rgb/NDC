import { CodeXml, LayoutTemplate, Palette, Globe } from 'lucide-react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';

const services = [
  {
    icon: CodeXml,
    title: "Full-Stack Web Development",
    description: "End-to-end web applications — from database and API design to polished, responsive interfaces.",
    skills: ["React", "Node.js", "PHP", "MySQL", "MongoDB"]
  },
  {
    icon: LayoutTemplate,
    title: "UI/UX Design",
    description: "User-centered interfaces that are intuitive, accessible, and pleasant to use across every device.",
    skills: ["Wireframing", "Prototyping", "Figma", "Usability"]
  },
  {
    icon: Palette,
    title: "Graphic Design & Branding",
    description: "Visual identity, social media content, and marketing assets that keep a brand consistent and memorable.",
    skills: ["Branding", "Social Media Design", "Photoshop", "Illustrator"]
  },
  {
    icon: Globe,
    title: "WordPress Development",
    description: "Responsive, easy-to-manage WordPress websites built with modern design standards and clean structure.",
    skills: ["WordPress", "Custom Themes", "Site Maintenance"]
  }
];

const Services = () => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <section
      id="services"
      className="py-20 relative"
      style={{
        background: themeColors.background.sections?.services || themeColors.background.gradient,
        transition: 'background 0.3s ease-in-out'
      }}
    >
      <div className="container mx-auto px-6 relative" style={{ zIndex: 2 }}>
        <h2 className="text-4xl font-bold text-center mb-2" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.colors.pink[500] }}>
          Services
        </h2>
        <p className="text-center mb-12 text-sm" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
          What I can help you build
        </p>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
          {services.map((service) => {
            const Icon = service.icon;
            return (
              <div
                key={service.title}
                className="group rounded-xl p-6 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl"
                style={{
                  backgroundColor: themeColors.card.background,
                  border: `1px solid ${themeColors.card.border}`
                }}
              >
                <div
                  className="flex items-center justify-center rounded-xl mb-4 transition-transform duration-300 group-hover:scale-110"
                  style={{
                    width: '52px',
                    height: '52px',
                    backgroundColor: isDarkMode ? 'rgba(236, 72, 153, 0.15)' : themeColors.colors.pink[50]
                  }}
                >
                  <Icon className="h-6 w-6" style={{ color: themeColors.colors.pink[500] }} />
                </div>
                <h3 className="text-lg font-bold mb-2" style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.pink[600] }}>
                  {service.title}
                </h3>
                <p className="text-sm leading-relaxed mb-4" style={{ color: isDarkMode ? themeColors.colors.dark[300] : themeColors.colors.dark[600] }}>
                  {service.description}
                </p>
                <div className="flex flex-wrap gap-1.5">
                  {service.skills.map((skill) => (
                    <span
                      key={skill}
                      className="text-[11px] font-medium px-2 py-0.5 rounded-full"
                      style={{ backgroundColor: themeColors.interactive.primary, color: themeColors.text.accent }}
                    >
                      {skill}
                    </span>
                  ))}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
};

export default Services;
