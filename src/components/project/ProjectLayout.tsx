import React from 'react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';
import BackButton from '../BackButton';

interface ProjectLayoutProps {
  children: React.ReactNode;
}

const ProjectLayout: React.FC<ProjectLayoutProps> = ({ children }) => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  // Scroll to top when component mounts
  React.useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  return (
    <main 
      aria-label="Project details" 
      className="min-h-screen py-20 transition-colors duration-300" 
      style={{ backgroundColor: isDarkMode ? themeColors.colors.dark[950] : themeColors.colors.pink[50] }}
    >
      <div className="container mx-auto px-6 max-w-4xl">
        <BackButton />
        {children}
      </div>
    </main>
  );
};

export default ProjectLayout;