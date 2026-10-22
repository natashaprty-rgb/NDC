import React from 'react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';

interface ProjectSectionProps {
  title: string;
  children: React.ReactNode;
  className?: string;
}

const ProjectSection: React.FC<ProjectSectionProps> = ({ title, children, className = "" }) => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <div className={`rounded-lg shadow-lg p-8 mb-8 ${className}`} style={{ 
      backgroundColor: themeColors.card.background,
      border: `1px solid ${themeColors.card.border}`
    }}>
      <h2 className="text-2xl font-semibold mb-4" style={{ color: isDarkMode ? themeColors.colors.pink[300] : themeColors.colors.dark[700] }}>{title}</h2>
      {children}
    </div>
  );
};

export default ProjectSection;