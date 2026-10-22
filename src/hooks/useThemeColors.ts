import { useDarkMode } from '../contexts/DarkModeContext';
import { colors, getThemeColors } from '../styles/colors';
import type { ColorTheme } from '../styles/colors';

/**
 * Custom hook to get theme-aware colors throughout the application
 * 
 * @returns Object containing themed colors and utilities
 */
export const useThemeColors = () => {
  const { isDarkMode } = useDarkMode();
  const theme: ColorTheme = isDarkMode ? 'dark' : 'light';
  
  const themeColors = getThemeColors(theme);
  
  return {
    // Theme-specific colors
    ...themeColors,

    // Base colors (theme-independent)
    colors,

    // Utilities
    theme,
    isDarkMode,

    // Quick access to common colors
    primary: colors.pink[300], // Main dusty pink
    secondary: colors.pink[400], // Darker dusty pink
    accent: colors.pink[200], // Mimi pink

    // Semantic helpers
    textPrimary: themeColors.text.primary,
    textSecondary: themeColors.text.secondary,
    bgPrimary: themeColors.background.primary,
    bgSecondary: themeColors.background.secondary,

    // Background gradients with proper white midpoints
    background: {
      ...themeColors.background,
      // Override gradient to use proper color definitions
      gradient: colors.background[theme].gradient,
      gradientEnd: colors.background[theme].gradientEnd,
      sections: colors.background[theme].sections,
    },

    // Interactive state colors
    hover: themeColors.interactive.hover,
    active: themeColors.interactive.active,
    focus: themeColors.interactive.focus,

    // Effects (theme-independent)
    effects: colors.effects,
  };
};

/**
 * Helper function to get a specific color with alpha
 * 
 * @param color - The base color (hex)
 * @param alpha - Alpha value (0-1)
 * @returns RGBA color string
 */
export const withAlpha = (color: string, alpha: number): string => {
  // Convert hex to RGB
  const hex = color.replace('#', '');
  const r = parseInt(hex.substr(0, 2), 16);
  const g = parseInt(hex.substr(2, 2), 16);
  const b = parseInt(hex.substr(4, 2), 16);
  
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

/**
 * Get color variants for a specific use case
 */
export const getColorVariants = (baseColor: string) => ({
  base: baseColor,
  hover: withAlpha(baseColor, 0.8),
  active: withAlpha(baseColor, 0.9),
  disabled: withAlpha(baseColor, 0.5),
  subtle: withAlpha(baseColor, 0.1),
  muted: withAlpha(baseColor, 0.6),
});