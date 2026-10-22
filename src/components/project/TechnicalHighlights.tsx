import React from 'react';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { useThemeColors } from '../../hooks/useThemeColors';

interface TechnicalHighlightsProps {
  highlights: string[];
}

const TechnicalHighlights: React.FC<TechnicalHighlightsProps> = ({ highlights }) => {
  const { isDarkMode } = useDarkMode();
  const themeColors = useThemeColors();

  return (
    <ul className="space-y-3" style={{ color: isDarkMode ? themeColors.colors.white : themeColors.text.secondary }}>
      {highlights.map((highlight, index) => (
        <li key={index} className="flex items-start gap-2">
          <span className="mt-1" style={{ color: themeColors.colors.pink[500] }}>•</span>
          <span>{highlight}</span>
        </li>
      ))}
    </ul>
  );
};

export default TechnicalHighlights;