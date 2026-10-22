import React from 'react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';

interface ProjectOverviewProps {
  paragraphs: (string | React.ReactNode)[];
}

const ProjectOverview: React.FC<ProjectOverviewProps> = ({ paragraphs }) => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <>
      {paragraphs.map((paragraph, index) => (
        <p 
          key={index} 
          className={`leading-relaxed ${index < paragraphs.length - 1 ? 'mb-4' : ''}`} 
          style={{ color: isDarkMode ? themeColors.colors.white : themeColors.text.secondary }}
        >
          {paragraph}
        </p>
      ))}
    </>
  );
};

export default ProjectOverview;